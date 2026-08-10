<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || !in_array('--apply', $argv ?? [], true)) {
    fwrite(STDERR, "Usage: php scripts/seed-staff-role-test-accounts.php --apply\n");
    exit(2);
}

require_once dirname(__DIR__) . '/staff/lib/db.php';
require_once dirname(__DIR__) . '/staff/lib/page-access.php';

$pdo = staff_db();
staff_page_access_ensure_schema($pdo);
$password = 'HC-Test-2026!';
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$accounts = [
    ['username' => 'test-manager', 'email' => 'staff-test-manager@hc.local', 'name' => 'テスト マネージャー', 'code' => 'TEST-MGR', 'roles' => ['manager']],
    ['username' => 'test-support', 'email' => 'staff-test-support@hc.local', 'name' => 'テスト サポート', 'code' => 'TEST-SUP', 'roles' => ['support_agent']],
    ['username' => 'test-orders', 'email' => 'staff-test-orders@hc.local', 'name' => 'テスト 注文担当', 'code' => 'TEST-ORD', 'roles' => ['order_operator', 'order_approver']],
    ['username' => 'test-developer', 'email' => 'staff-test-developer@hc.local', 'name' => 'テスト 開発者', 'code' => 'TEST-DEV', 'roles' => ['web_developer']],
    ['username' => 'test-viewer', 'email' => 'staff-test-viewer@hc.local', 'name' => 'テスト 閲覧専用', 'code' => 'TEST-VIEW', 'roles' => ['viewer']],
];

$pdo->beginTransaction();
try {
    $userStatement = $pdo->prepare(
        "INSERT INTO users (username, email, password, role, status, email_verified, email_verified_at,
            terms_accepted, terms_accepted_at, created_at)
         VALUES (:username, :email, :password, 'staff', 'active', TRUE, NOW(), TRUE, NOW(), NOW())
         ON CONFLICT (email) DO UPDATE SET password=EXCLUDED.password, status='active', deleted_at=NULL
         RETURNING id"
    );
    $staffStatement = $pdo->prepare(
        "INSERT INTO staff_users (account_id, employee_code, display_name, status, work_status, created_at, updated_at)
         VALUES (:account_id, :employee_code, :display_name, 'active', 'offline', NOW(), NOW())
         ON CONFLICT (account_id) DO UPDATE SET employee_code=EXCLUDED.employee_code,
            display_name=EXCLUDED.display_name, status='active', updated_at=NOW() RETURNING id"
    );
    $clearStatement = $pdo->prepare(
        'DELETE FROM user_roles ur USING account_roles ar
         WHERE ur.role_id=ar.id AND ar.is_staff_role=TRUE AND ur.user_id=:user_id'
    );
    $assignStatement = $pdo->prepare(
        'INSERT INTO user_roles (user_id, role_id, assigned_by)
         SELECT :user_id, id, NULL FROM account_roles WHERE slug=:slug AND is_staff_role=TRUE
         ON CONFLICT DO NOTHING'
    );
    foreach ($accounts as $account) {
        $userStatement->execute(['username' => $account['username'], 'email' => $account['email'], 'password' => $passwordHash]);
        $userId = (int) $userStatement->fetchColumn();
        $staffStatement->execute(['account_id' => $userId, 'employee_code' => $account['code'], 'display_name' => $account['name']]);
        $clearStatement->execute(['user_id' => $userId]);
        foreach ($account['roles'] as $roleSlug) $assignStatement->execute(['user_id' => $userId, 'slug' => $roleSlug]);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}

echo "Created or refreshed " . count($accounts) . " local staff test accounts.\n";
echo "Shared password: {$password}\n";
foreach ($accounts as $account) echo $account['email'] . ' [' . implode(' + ', $account['roles']) . "]\n";

