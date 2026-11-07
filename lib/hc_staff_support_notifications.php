<?php
/**
 * HC Phase 3b: best-effort staff notices for ordinary support intake and replies.
 * Never includes requester name, email, or message content.
 * Notification failure must not interrupt the customer support workflow.
 */
declare(strict_types=1);

function hc_support_staff_notice(
    PDO $pdo,
    int $contactId,
    string $kind,
    int $messageId = 0,
    int $assignedAccountId = 0
): int {
    if ($contactId <= 0 || !in_array($kind, ['new_contact', 'customer_reply'], true)) {
        return 0;
    }
    if ($kind === 'customer_reply' && $messageId <= 0) {
        return 0;
    }

    // SAVEPOINT prevents a notification SQL error from poisoning an active PostgreSQL
    // transaction that has already stored a customer message.
    $inTransaction = $pdo->inTransaction();
    $savepointCreated = false;
    try {
        if ($inTransaction) {
            $pdo->exec('SAVEPOINT hc_support_notice_sp');
            $savepointCreated = true;
        }

        $statement = $pdo->query(<<<'SQL'
SELECT su.id, su.account_id
FROM staff_users su
INNER JOIN users u ON u.id = su.account_id
WHERE su.status = 'active'
  AND u.deleted_at IS NULL
  AND (
    SELECT COUNT(DISTINCT p.permission_key)
    FROM user_roles ur
    INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
    INNER JOIN permissions p ON p.id = rp.permission_id
    WHERE ur.user_id = su.account_id
      AND p.permission_key IN (
        'staff.access', 'staff.dashboard.view', 'support.tickets.view'
      )
  ) = 3
ORDER BY su.id
SQL);
        $allRecipients = $statement->fetchAll(PDO::FETCH_ASSOC);
        $recipients = $allRecipients;
        if ($kind === 'customer_reply' && $assignedAccountId > 0) {
            $assigned = array_values(array_filter(
                $allRecipients,
                static fn(array $row): bool => (int)$row['account_id'] === $assignedAccountId
            ));
            if ($assigned !== []) {
                $recipients = $assigned;
            }
        }

        $insert = $pdo->prepare(<<<'SQL'
INSERT INTO staff_notifications (
  user_id, type, title, body, action_url, category, level, source, dedupe_key
) VALUES (
  :user_id, :type, :title, :body, :action_url,
  'general', :level, 'hc_support', :dedupe_key
)
ON CONFLICT (dedupe_key) WHERE dedupe_key IS NOT NULL DO NOTHING
SQL);

        $count = 0;
        foreach ($recipients as $recipient) {
            $staffId = (int)$recipient['id'];
            if ($staffId <= 0) {
                continue;
            }
            $new = $kind === 'new_contact';
            $insert->execute([
                'user_id' => $staffId,
                'type' => $new ? 'support_new_contact' : 'support_customer_reply',
                'title' => $new ? '新しいお問い合わせ' : 'お問い合わせに返信が届きました',
                'body' => $new
                    ? '新しいお問い合わせを受け付けました。詳細画面で内容を確認してください。'
                    : 'お客さまから追加のメッセージが届きました。詳細画面で確認してください。',
                'action_url' => '/staff/support/?view=overview&id=' . $contactId,
                'level' => 'info',
                'dedupe_key' => $new
                    ? 'hc-support-v1:new:' . $contactId . ':staff:' . $staffId
                    : 'hc-support-v1:reply:' . $messageId . ':staff:' . $staffId,
            ]);
            $count += $insert->rowCount();
        }
        if ($savepointCreated) {
            $pdo->exec('RELEASE SAVEPOINT hc_support_notice_sp');
        }
        return $count;
    } catch (Throwable) {
        if ($savepointCreated) {
            try {
                $pdo->exec('ROLLBACK TO SAVEPOINT hc_support_notice_sp');
                $pdo->exec('RELEASE SAVEPOINT hc_support_notice_sp');
            } catch (Throwable) {
                // Caller transaction may be unrecoverable; no customer data is logged.
            }
        }
        error_log('HC_PHASE3B_SUPPORT_STAFF_NOTICE_FAILED');
        return 0;
    }
}
