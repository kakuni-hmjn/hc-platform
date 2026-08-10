<?php

declare(strict_types=1);

require_once __DIR__ . '/notifications.php';

function contact_chat_schema_ready(PDO $pdo): bool
{
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

function contact_chat_ticket_number(int $contactId): string
{
    return 'HC-' . str_pad(
        (string) $contactId,
        6,
        '0',
        STR_PAD_LEFT
    );
}

function contact_chat_status_label(string $status): string
{
    return match ($status) {
        'open' => '受付済み',
        'in_progress' => 'スタッフ対応中',
        'waiting' => 'あなたの返信待ち',
        'resolved', 'closed' => '解決済み',
        default => '受付済み',
    };
}

function contact_chat_list_for_user(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $hasMessages = contact_chat_schema_ready($pdo);
    $latestMessageSql = $hasMessages
        ? "COALESCE(
            (
                SELECT message.body
                FROM contact_messages message
                WHERE message.contact_id = contact.id
                  AND message.visibility = 'public'
                  AND message.delivery_channel IN ('chat', 'imported')
                ORDER BY message.created_at DESC, message.id DESC
                LIMIT 1
            ),
            contact.message
        )"
        : 'contact.message';
    $latestAtSql = $hasMessages
        ? "COALESCE(
            (
                SELECT message.created_at
                FROM contact_messages message
                WHERE message.contact_id = contact.id
                  AND message.visibility = 'public'
                  AND message.delivery_channel IN ('chat', 'imported')
                ORDER BY message.created_at DESC, message.id DESC
                LIMIT 1
            ),
            contact.updated_at,
            contact.created_at
        )"
        : 'COALESCE(contact.updated_at, contact.created_at)';

    $statement = $pdo->prepare(
        'SELECT
            contact.id,
            contact.subject,
            contact.category,
            contact.status,
            contact.created_at,
            contact.updated_at,
            ' . $latestMessageSql . ' AS latest_message,
            ' . $latestAtSql . ' AS latest_at
        FROM contacts contact
        WHERE contact.user_id = :user_id
        ORDER BY latest_at DESC, contact.id DESC
        LIMIT 100'
    );
    $statement->execute(['user_id' => $userId]);
    $contacts = $statement->fetchAll();

    return is_array($contacts) ? $contacts : [];
}

function contact_chat_find_for_user(
    PDO $pdo,
    int $contactId,
    int $userId
): ?array {
    if ($contactId <= 0 || $userId <= 0) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT contact.*
        FROM contacts contact
        WHERE contact.id = :contact_id
          AND contact.user_id = :user_id
        LIMIT 1'
    );
    $statement->execute([
        'contact_id' => $contactId,
        'user_id' => $userId,
    ]);
    $contact = $statement->fetch();

    return is_array($contact) ? $contact : null;
}

function contact_chat_messages(PDO $pdo, array $contact): array
{
    $messages = [[
        'id' => 0,
        'author_name' => (string) ($contact['name'] ?? 'お客さま'),
        'author_type' => 'customer',
        'delivery_channel' => 'chat',
        'body' => (string) ($contact['message'] ?? ''),
        'delivery_status' => 'received',
        'created_at' => (string) ($contact['created_at'] ?? ''),
    ]];

    if (!contact_chat_schema_ready($pdo)) {
        return $messages;
    }

    $statement = $pdo->prepare(
        "SELECT
            message.id,
            CASE
                WHEN message.author_type = 'customer'
                    THEN COALESCE(account.username, :customer_name)
                ELSE COALESCE(
                    staff_user.display_name,
                    account.username,
                    'HCサポート'
                )
            END AS author_name,
            message.author_type,
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
          AND message.visibility = 'public'
          AND message.delivery_channel IN ('chat', 'imported')
        ORDER BY message.created_at ASC, message.id ASC"
    );
    $statement->execute([
        'customer_name' => (string) ($contact['name'] ?? 'お客さま'),
        'contact_id' => (int) ($contact['id'] ?? 0),
    ]);
    $storedMessages = $statement->fetchAll();

    if (is_array($storedMessages)) {
        array_push($messages, ...$storedMessages);
    }

    return $messages;
}

function contact_chat_add_customer_message(
    PDO $pdo,
    array $contact,
    int $userId,
    string $body
): int {
    $body = trim($body);

    if ($body === '') {
        throw new InvalidArgumentException('メッセージを入力してください。');
    }

    if (mb_strlen($body) > 5000) {
        throw new InvalidArgumentException(
            'メッセージは5000文字以内で入力してください。'
        );
    }

    if ((int) ($contact['user_id'] ?? 0) !== $userId) {
        throw new RuntimeException('このチャットは表示できません。');
    }

    if (!contact_chat_schema_ready($pdo)) {
        throw new RuntimeException(
            'サポートチャットのDB更新がまだ適用されていません。'
        );
    }

    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            "INSERT INTO contact_messages (
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
                'customer',
                'public',
                'chat',
                :body,
                'received',
                CURRENT_TIMESTAMP
            )
            RETURNING id"
        );
        $statement->execute([
            'contact_id' => (int) $contact['id'],
            'author_account_id' => $userId,
            'body' => $body,
        ]);
        $messageId = (int) $statement->fetchColumn();

        $update = $pdo->prepare(
            "UPDATE contacts
            SET
                status = 'in_progress',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :contact_id
              AND user_id = :user_id"
        );
        $update->execute([
            'contact_id' => (int) $contact['id'],
            'user_id' => $userId,
        ]);

        contact_chat_notify_assignee(
            $pdo,
            $contact,
            $body,
            $messageId
        );

        $pdo->commit();

        return $messageId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}

function contact_chat_notify_assignee(
    PDO $pdo,
    array $contact,
    string $body,
    int $messageId
): void {
    $assignedAccountId = (int) ($contact['assigned_to'] ?? 0);

    if ($assignedAccountId <= 0) {
        return;
    }

    try {
        $statement = $pdo->prepare(
            "INSERT INTO staff_notifications (
                user_id,
                type,
                title,
                body,
                action_url,
                is_read,
                created_at
            )
            SELECT
                staff_user.id,
                'support_reply',
                :title,
                :body,
                :action_url,
                FALSE,
                CURRENT_TIMESTAMP
            FROM staff_users staff_user
            WHERE staff_user.account_id = :account_id
              AND staff_user.status = 'active'
            LIMIT 1"
        );
        $statement->execute([
            'title' => 'サポートチャットに返信が届きました',
            'body' => mb_substr($body, 0, 240),
            'action_url' => '/staff/support/?id=' . (int) $contact['id'],
            'account_id' => $assignedAccountId,
        ]);
    } catch (Throwable) {
        // 通知失敗でチャット送信を止めない。
    }
}

function contact_chat_notify_customer(
    PDO $pdo,
    array $contact,
    string $body,
    int $messageId
): bool {
    $userId = (int) ($contact['user_id'] ?? 0);

    if ($userId <= 0) {
        return false;
    }

    return hc_notify_user(
        $pdo,
        $userId,
        'HCサポートから返信が届きました',
        mb_substr(trim($body), 0, 240),
        '/dashboard/support/?id=' . (int) $contact['id'],
        'info',
        'support-contact-' . (int) $contact['id']
            . '-message-' . $messageId
    );
}

function contact_chat_mark_notifications_read(
    PDO $pdo,
    int $userId,
    int $contactId
): void {
    if ($userId <= 0 || $contactId <= 0) {
        return;
    }

    try {
        hc_notifications_ensure_schema($pdo);
        $statement = $pdo->prepare(
            "INSERT INTO user_notification_reads (
                user_id,
                notification_type,
                notification_id,
                read_at,
                created_at
            )
            SELECT
                notice.user_id,
                'direct_notice',
                notice.id,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            FROM user_direct_notifications notice
            WHERE notice.user_id = :user_id
              AND notice.link_url = :link_url
            ON CONFLICT (
                user_id,
                notification_type,
                notification_id
            ) DO UPDATE SET read_at = CURRENT_TIMESTAMP"
        );
        $statement->execute([
            'user_id' => $userId,
            'link_url' => '/dashboard/support/?id=' . $contactId,
        ]);
    } catch (Throwable) {
        // 既読記録の失敗でチャット表示を止めない。
    }
}
