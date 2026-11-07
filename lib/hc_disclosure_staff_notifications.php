<?php
/**
 * HC_PHASE2AA_DISCLOSURE_NOTICE_20261107
 * Server-side notification for dedicated commerce disclosure requests.
 * Run only inside the contact INSERT transaction, and throw on failure.
 * Does not send email, mutate an existing ticket, or create/update DB schema.
 */
declare(strict_types=1);

function hc_disclosure_staff_notice(PDO $pdo, int $contactId): int
{
    if ($contactId <= 0 || !$pdo->inTransaction()) {
        throw new RuntimeException('Disclosure notification requires a saved contact and active transaction');
    }

    // Eligible staff user ids, NOT HC account ids. Use the same permissions as Staff Console.
    $recipients = $pdo->query(<<<'SQL'
SELECT su.id
FROM staff_users su
INNER JOIN users u ON u.id = su.account_id
WHERE su.status = 'active'
  AND u.deleted_at IS NULL
  AND (
      SELECT COUNT(DISTINCT permission.permission_key)
      FROM user_roles user_role
      INNER JOIN account_roles role ON role.id = user_role.role_id
      INNER JOIN role_permissions role_permission ON role_permission.role_id = role.id
      INNER JOIN permissions permission ON permission.id = role_permission.permission_id
      WHERE user_role.user_id = su.account_id
        AND permission.permission_key IN (
            'staff.access',
            'staff.dashboard.view',
            'support.tickets.view'
        )
  ) = 3
ORDER BY su.id
SQL);

    if ($recipients === false) {
        throw new RuntimeException('Unable to resolve disclosure notification recipients');
    }
    $ids = $recipients->fetchAll(PDO::FETCH_COLUMN);
    if ($ids === []) {
        throw new RuntimeException('No eligible staff for disclosure notification');
    }

    // Partial UNIQUE index uq_staff_notifications_dedupe_key protects concurrent duplicates.
    $insert = $pdo->prepare(<<<'SQL'
INSERT INTO staff_notifications (
    user_id,
    type,
    title,
    body,
    action_url,
    category,
    level,
    source,
    dedupe_key
) VALUES (
    :user_id,
    'commerce_disclosure',
    '販売者情報の開示請求',
    '購入前の開示請求を受け付けました。問い合わせを優先確認してください。',
    :action_url,
    'general',
    'warning',
    'hc_contact',
    :dedupe_key
)
ON CONFLICT (dedupe_key) WHERE dedupe_key IS NOT NULL DO NOTHING
SQL);

    if ($insert === false) {
        throw new RuntimeException('Unable to prepare disclosure notification');
    }

    $count = 0;
    foreach ($ids as $id) {
        $staffUserId = (int) $id;
        if ($staffUserId <= 0) {
            throw new RuntimeException('Invalid staff notification recipient');
        }
        $insert->execute([
            'user_id' => $staffUserId,
            'action_url' => '/staff/support/?view=overview&id=' . $contactId,
            'dedupe_key' => 'hc-disclosure-v1:' . $contactId . ':staff:' . $staffUserId,
        ]);
        $count++;
    }

    return $count;
}
