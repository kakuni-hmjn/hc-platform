<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../lib/mailer.php';
require_once __DIR__ . '/../../lib/contact-chat.php';

function staff_support_statuses(): array
{
    return [
        'open' => '未対応',
        'in_progress' => '対応中',
        'waiting' => '返信待ち',
        'resolved' => '解決済み',
    ];
}

function staff_support_categories(): array
{
    return [
        'general' => '一般',
        'account' => 'アカウント',
        'service' => 'サービス',
        'billing' => '契約・支払い',
        'bug' => '不具合',
        'other' => 'その他',
    ];
}

function staff_support_normalize_status(string $status): string
{
    if ($status === 'closed') {
        return 'resolved';
    }

    return array_key_exists(
        $status,
        staff_support_statuses()
    ) ? $status : 'open';
}

function staff_support_status_label(string $status): string
{
    $normalized = staff_support_normalize_status($status);

    return staff_support_statuses()[$normalized];
}

function staff_support_category_label(string $category): string
{
    return staff_support_categories()[$category]
        ?? 'その他';
}

function staff_support_ticket_number(int $id): string
{
    return 'HC-' . str_pad(
        (string) $id,
        6,
        '0',
        STR_PAD_LEFT
    );
}

function staff_support_schema_ready(?PDO $pdo = null): bool
{
    $pdo ??= staff_db();

    try {
        $value = $pdo->query(
            "SELECT
                to_regclass('public.contact_messages') IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = 'public'
                      AND table_name = 'contact_messages'
                      AND column_name = 'delivery_channel'
                )"
        )->fetchColumn();

        return in_array($value, [true, 1, '1', 't'], true);
    } catch (Throwable) {
        return false;
    }
}

function staff_support_counts(
    PDO $pdo,
    string $channel = 'all'
): array
{
    $where = $channel === 'chat'
        ? ' WHERE user_id IS NOT NULL'
        : '';
    $row = $pdo->query(
        "SELECT
            COUNT(*) AS all_count,
            COUNT(*) FILTER (
                WHERE status = 'open'
            ) AS open_count,
            COUNT(*) FILTER (
                WHERE status = 'in_progress'
            ) AS in_progress_count,
            COUNT(*) FILTER (
                WHERE status = 'waiting'
            ) AS waiting_count,
            COUNT(*) FILTER (
                WHERE status IN ('resolved', 'closed')
            ) AS resolved_count,
            COUNT(*) FILTER (
                WHERE status IN ('resolved', 'closed')
                  AND COALESCE(updated_at, handled_at, created_at)
                      >= CURRENT_DATE
            ) AS resolved_today_count
        FROM contacts" . $where
    )->fetch();

    if (!is_array($row)) {
        $row = [];
    }

    return [
        'all' => (int) ($row['all_count'] ?? 0),
        'open' => (int) ($row['open_count'] ?? 0),
        'in_progress' => (int) (
            $row['in_progress_count'] ?? 0
        ),
        'waiting' => (int) (
            $row['waiting_count'] ?? 0
        ),
        'resolved' => (int) (
            $row['resolved_count'] ?? 0
        ),
        'resolved_today' => (int) (
            $row['resolved_today_count'] ?? 0
        ),
    ];
}

function staff_support_list(
    PDO $pdo,
    string $status,
    string $query,
    bool $schemaReady,
    string $channel = 'all'
): array {
    $where = [];
    $params = [];
    $messageChannelCondition = match ($channel) {
        'chat' => "AND search_message.delivery_channel IN ('chat', 'imported')",
        'email' => "AND search_message.delivery_channel IN ('email', 'imported')",
        default => '',
    };
    $latestMessageChannelCondition = match ($channel) {
        'chat' => "AND message.delivery_channel IN ('chat', 'imported')",
        'email' => "AND message.delivery_channel IN ('email', 'imported')",
        default => '',
    };

    if ($channel === 'chat') {
        $where[] = 'contact.user_id IS NOT NULL';
    }

    if ($status !== 'all') {
        if ($status === 'resolved') {
            $where[] = "contact.status IN ('resolved', 'closed')";
        } elseif (array_key_exists(
            $status,
            staff_support_statuses()
        )) {
            $where[] = 'contact.status = :status';
            $params['status'] = $status;
        }
    }

    if ($query !== '') {
        $where[] = '(
            contact.name ILIKE :query
            OR contact.email ILIKE :query
            OR contact.subject ILIKE :query
            OR contact.message ILIKE :query
            OR CAST(contact.id AS TEXT) ILIKE :query
            OR EXISTS (
                SELECT 1
                FROM users contact_account
                WHERE contact_account.id = contact.user_id
                  AND contact_account.username ILIKE :query
            )
            ' . ($schemaReady ? 'OR EXISTS (
                SELECT 1
                FROM contact_messages search_message
                WHERE search_message.contact_id = contact.id
                  AND search_message.body ILIKE :query
                  ' . $messageChannelCondition . '
            )' : '') . '
        )';
        $params['query'] = '%' . $query . '%';
    }

    $latestMessageSql = $schemaReady
        ? "COALESCE(
            (
                SELECT message.body
                FROM contact_messages message
                WHERE message.contact_id = contact.id
                " . $latestMessageChannelCondition . "
                ORDER BY message.created_at DESC, message.id DESC
                LIMIT 1
            ),
            contact.message
        )"
        : 'contact.message';

    $sql = 'SELECT
        contact.id,
        contact.user_id,
        contact.name,
        contact.email,
        contact.category,
        contact.subject,
        contact.status,
        contact.assigned_to,
        contact.handled_at,
        contact.created_at,
        contact.updated_at,
        ' . $latestMessageSql . ' AS latest_message
    FROM contacts contact';

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY
        CASE contact.status
            WHEN 'open' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'waiting' THEN 3
            ELSE 4
        END,
        COALESCE(contact.updated_at, contact.created_at) DESC,
        contact.id DESC
    LIMIT 200";

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    $tickets = $statement->fetchAll();

    if (!is_array($tickets)) {
        return [];
    }

    return array_map(
        static function (array $ticket): array {
            $ticket['status'] = staff_support_normalize_status(
                (string) ($ticket['status'] ?? '')
            );
            $ticket['priority'] = staff_support_priority($ticket);

            return $ticket;
        },
        $tickets
    );
}

function staff_support_find(
    PDO $pdo,
    int $contactId
): ?array {
    if ($contactId <= 0) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT
            contact.*,
            account.username AS account_name
        FROM contacts contact
        LEFT JOIN users account
            ON account.id = contact.user_id
        WHERE contact.id = :contact_id
        LIMIT 1'
    );

    $statement->execute([
        'contact_id' => $contactId,
    ]);

    $ticket = $statement->fetch();

    if (!is_array($ticket)) {
        return null;
    }

    $ticket['status'] = staff_support_normalize_status(
        (string) ($ticket['status'] ?? '')
    );
    $ticket['priority'] = staff_support_priority($ticket);

    return $ticket;
}

function staff_support_messages(
    PDO $pdo,
    array $ticket,
    bool $schemaReady,
    string $channel = 'all'
): array {
    $messages = [[
        'id' => 0,
        'author_name' => (string) ($ticket['name'] ?? 'お客さま'),
        'author_type' => 'customer',
        'visibility' => 'public',
        'delivery_channel' => $channel === 'chat'
            ? 'chat'
            : 'imported',
        'body' => (string) ($ticket['message'] ?? ''),
        'delivery_status' => 'received',
        'created_at' => (string) ($ticket['created_at'] ?? ''),
    ]];

    if (!$schemaReady) {
        return $messages;
    }

    $statement = $pdo->prepare(
        "SELECT
            message.id,
            COALESCE(
                staff_user.display_name,
                account.username,
                'Staff'
            ) AS author_name,
            message.author_type,
            message.visibility,
            message.delivery_channel,
            message.body,
            message.delivery_status,
            message.created_at
        FROM contact_messages message
        LEFT JOIN users account
            ON account.id = message.author_account_id
        LEFT JOIN staff_users staff_user
            ON staff_user.account_id = account.id
        WHERE message.contact_id = :contact_id
        " . ($channel === 'chat'
            ? "AND (
                message.visibility = 'internal'
                OR message.delivery_channel IN ('chat', 'imported')
            )"
            : ($channel === 'email'
                ? "AND (
                    message.visibility = 'internal'
                    OR message.delivery_channel IN ('email', 'imported')
                )"
                : '')) . "
        ORDER BY message.created_at ASC, message.id ASC"
    );

    $statement->execute([
        'contact_id' => (int) ($ticket['id'] ?? 0),
    ]);

    $storedMessages = $statement->fetchAll();

    if (is_array($storedMessages)) {
        array_push($messages, ...$storedMessages);
    }

    return $messages;
}

function staff_support_staff_options(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            account.id,
            COALESCE(
                staff_user.display_name,
                account.username
            ) AS name
        FROM staff_users staff_user
        INNER JOIN users account
            ON account.id = staff_user.account_id
        WHERE staff_user.status = 'active'
          AND account.deleted_at IS NULL
        ORDER BY name ASC, account.id ASC"
    );

    $staff = $statement->fetchAll();

    return is_array($staff) ? $staff : [];
}

function staff_support_priority(array $ticket): string
{
    $status = staff_support_normalize_status(
        (string) ($ticket['status'] ?? '')
    );

    if ($status === 'resolved') {
        return 'low';
    }

    $category = (string) ($ticket['category'] ?? '');

    if (in_array($category, ['bug', 'billing'], true)) {
        return 'high';
    }

    try {
        $createdAt = new DateTimeImmutable(
            (string) ($ticket['created_at'] ?? '')
        );

        if ($createdAt < new DateTimeImmutable('-2 days')) {
            return 'high';
        }
    } catch (Throwable) {
        // 日付を解釈できない場合は通常優先度として扱う。
    }

    return 'normal';
}

function staff_support_time_label(mixed $value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '';
    }

    try {
        $date = new DateTimeImmutable((string) $value);
        $now = new DateTimeImmutable();
        $seconds = max(0, $now->getTimestamp() - $date->getTimestamp());

        if ($seconds < 60) {
            return 'たった今';
        }

        if ($seconds < 3600) {
            return (string) floor($seconds / 60) . '分前';
        }

        if ($seconds < 86400) {
            return (string) floor($seconds / 3600) . '時間前';
        }

        if ($seconds < 604800) {
            return (string) floor($seconds / 86400) . '日前';
        }

        return $date->format('Y/m/d H:i');
    } catch (Throwable) {
        return (string) $value;
    }
}

function staff_support_date_label(mixed $value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable((string) $value))
            ->format('Y/m/d H:i');
    } catch (Throwable) {
        return (string) $value;
    }
}

function staff_support_update_status(
    PDO $pdo,
    int $contactId,
    string $status
): void {
    if (!array_key_exists($status, staff_support_statuses())) {
        throw new InvalidArgumentException(
            '対応状況の値が正しくありません。'
        );
    }

    $statement = $pdo->prepare(
        "UPDATE contacts
        SET
            status = :status_value,
            handled_at = CASE
                WHEN :status_check = 'resolved'
                    THEN COALESCE(handled_at, CURRENT_TIMESTAMP)
                ELSE handled_at
            END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :contact_id"
    );

    $statement->execute([
        'status_value' => $status,
        'status_check' => $status,
        'contact_id' => $contactId,
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'お問い合わせが見つかりません。'
        );
    }
}

function staff_support_assign(
    PDO $pdo,
    int $contactId,
    ?int $accountId
): void {
    if ($accountId !== null) {
        $staffStatement = $pdo->prepare(
            "SELECT 1
            FROM staff_users
            WHERE account_id = :account_id
              AND status = 'active'
            LIMIT 1"
        );
        $staffStatement->execute([
            'account_id' => $accountId,
        ]);

        if ($staffStatement->fetchColumn() === false) {
            throw new InvalidArgumentException(
                '担当スタッフが見つかりません。'
            );
        }
    }

    $statement = $pdo->prepare(
        "UPDATE contacts
        SET
            assigned_to = :assigned_account_id,
            status = CASE
                WHEN status = 'open'
                 AND CAST(:assignment_check AS INTEGER) IS NOT NULL
                    THEN 'in_progress'
                ELSE status
            END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :contact_id"
    );

    $statement->bindValue(
        'assigned_account_id',
        $accountId,
        $accountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );
    $statement->bindValue(
        'assignment_check',
        $accountId,
        $accountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );
    $statement->bindValue('contact_id', $contactId, PDO::PARAM_INT);
    $statement->execute();

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException(
            'お問い合わせが見つかりません。'
        );
    }
}

function staff_support_add_message(
    PDO $pdo,
    array $ticket,
    int $authorAccountId,
    string $body,
    string $mode,
    string $deliveryChannel = 'chat'
): array {
    $body = trim($body);

    if ($body === '') {
        throw new InvalidArgumentException(
            '内容を入力してください。'
        );
    }

    if (mb_strlen($body) > 5000) {
        throw new InvalidArgumentException(
            '内容は5000文字以内で入力してください。'
        );
    }

    if (!in_array($mode, ['reply', 'note'], true)) {
        throw new InvalidArgumentException(
            '送信方法が正しくありません。'
        );
    }

    if ($mode === 'note') {
        $deliveryChannel = 'internal';
    } elseif (!in_array($deliveryChannel, ['chat', 'email'], true)) {
        throw new InvalidArgumentException(
            '返信方法が正しくありません。'
        );
    }

    if (
        $deliveryChannel === 'chat'
        && (int) ($ticket['user_id'] ?? 0) <= 0
    ) {
        throw new InvalidArgumentException(
            'HCアカウントがないため、チャットは利用できません。'
        );
    }

    if (!staff_support_schema_ready($pdo)) {
        throw new RuntimeException(
            '会話履歴用のDB更新がまだ適用されていません。'
        );
    }

    $visibility = $mode === 'note' ? 'internal' : 'public';
    $deliveryStatus = $mode === 'note' ? 'saved' : 'pending';

    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            'INSERT INTO contact_messages (
                contact_id,
                author_account_id,
                author_type,
                visibility,
                delivery_channel,
                body,
                delivery_status,
                created_at
            ) VALUES (
                :contact_id,
                :author_account_id,
                :author_type,
                :visibility,
                :delivery_channel,
                :body,
                :delivery_status,
                CURRENT_TIMESTAMP
            )
            RETURNING id'
        );

        $statement->execute([
            'contact_id' => (int) ($ticket['id'] ?? 0),
            'author_account_id' => $authorAccountId,
            'author_type' => 'staff',
            'visibility' => $visibility,
            'delivery_channel' => $deliveryChannel,
            'body' => $body,
            'delivery_status' => $deliveryStatus,
        ]);

        $messageId = (int) $statement->fetchColumn();

        $contactStatement = $pdo->prepare(
            "UPDATE contacts
            SET
                status = CASE
                    WHEN :mode = 'reply' THEN 'waiting'
                    WHEN status = 'open' THEN 'in_progress'
                    ELSE status
                END,
                assigned_to = COALESCE(
                    assigned_to,
                    :author_account_id
                ),
                handled_at = COALESCE(
                    handled_at,
                    CURRENT_TIMESTAMP
                ),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :contact_id"
        );

        $contactStatement->execute([
            'mode' => $mode,
            'author_account_id' => $authorAccountId,
            'contact_id' => (int) ($ticket['id'] ?? 0),
        ]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }

    if ($mode === 'note') {
        return [
            'message_id' => $messageId,
            'delivery_status' => 'saved',
            'delivery_channel' => 'internal',
        ];
    }

    if ($deliveryChannel === 'chat') {
        try {
            $notified = contact_chat_notify_customer(
                $pdo,
                $ticket,
                $body,
                $messageId
            );
            $deliveryStatus = $notified ? 'sent' : 'failed';
        } catch (Throwable) {
            $deliveryStatus = 'failed';
        }
    } else {
        $deliveryStatus = staff_support_deliver_reply(
            $ticket,
            $body
        );
    }

    $deliveryStatement = $pdo->prepare(
        'UPDATE contact_messages
        SET delivery_status = :delivery_status
        WHERE id = :message_id'
    );
    $deliveryStatement->execute([
        'delivery_status' => $deliveryStatus,
        'message_id' => $messageId,
    ]);

    if ($deliveryStatus === 'failed') {
        staff_support_update_status(
            $pdo,
            (int) ($ticket['id'] ?? 0),
            'in_progress'
        );
    }

    return [
        'message_id' => $messageId,
        'delivery_status' => $deliveryStatus,
        'delivery_channel' => $deliveryChannel,
    ];
}

function staff_support_deliver_reply(
    array $ticket,
    string $body
): string {
    $email = trim((string) ($ticket['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'failed';
    }

    $config = require __DIR__ . '/../../config/mail.php';

    $name = trim((string) ($ticket['name'] ?? 'お客さま'));
    $subjectValue = preg_replace(
        '/[\r\n]+/',
        ' ',
        (string) ($ticket['subject'] ?? 'お問い合わせ')
    );
    $ticketNumber = staff_support_ticket_number(
        (int) ($ticket['id'] ?? 0)
    );
    $subject = 'Re: [' . $ticketNumber . '] ' . $subjectValue;
    $message = $name . " 様\n\n"
        . $body
        . "\n\n---\n"
        . "HC Platform サポート\n"
        . 'お問い合わせ番号: ' . $ticketNumber;

    if (($config['mode'] ?? 'log') !== 'smtp') {
        $logDirectory = __DIR__ . '/../../storage/logs';

        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0775, true);
        }

        $written = file_put_contents(
            $logDirectory . '/mail.log',
            '[' . date('Y-m-d H:i:s') . '] '
                . $email . ' ' . $subject . "\n"
                . $message . "\n\n",
            FILE_APPEND | LOCK_EX
        );

        return $written === false ? 'failed' : 'logged';
    }

    $sent = send_smtp_mail(
        (string) ($config['smtp_host'] ?? ''),
        (int) ($config['smtp_port'] ?? 587),
        (string) ($config['smtp_user'] ?? ''),
        (string) ($config['smtp_password'] ?? ''),
        (string) ($config['from_email'] ?? ''),
        (string) ($config['from_name'] ?? 'HC Platform'),
        $email,
        $subject,
        $message
    );

    return $sent ? 'sent' : 'failed';
}

function staff_support_audit(
    PDO $pdo,
    int $staffUserId,
    string $action,
    int $contactId,
    string $description,
    array $data = []
): void {
    try {
        $statement = $pdo->prepare(
            'INSERT INTO staff_audit_logs (
                actor_staff_id,
                action,
                target_type,
                target_id,
                description,
                new_data,
                source,
                result,
                created_at
            ) VALUES (
                :actor_staff_id,
                :action,
                :target_type,
                :target_id,
                :description,
                CAST(:new_data AS JSONB),
                :source,
                :result,
                CURRENT_TIMESTAMP
            )'
        );

        $statement->execute([
            'actor_staff_id' => $staffUserId,
            'action' => $action,
            'target_type' => 'contact',
            'target_id' => (string) $contactId,
            'description' => $description,
            'new_data' => json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
            ) ?: '{}',
            'source' => 'web',
            'result' => 'success',
        ]);
    } catch (Throwable) {
        // 監査記録の失敗で主処理を巻き戻さない。
    }
}
