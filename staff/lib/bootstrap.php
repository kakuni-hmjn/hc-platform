<?php

declare(strict_types=1);

require_once __DIR__ . '/icon.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/page-access.php';
require_once __DIR__ . '/dashboard.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/rental-server.php';
require_once __DIR__ . '/workspace.php';

/*
|--------------------------------------------------------------------------
| HCアカウント認証
|--------------------------------------------------------------------------
*/

$staffAccount = staff_require_account();

$staffAccountId = (int) (
    $staffAccount['id']
    ?? 0
);

staff_page_access_ensure_schema(staff_db());

/*
|--------------------------------------------------------------------------
| Staff Consoleアクセス権限
|--------------------------------------------------------------------------
*/

if (
    !staff_account_has_permission(
        $staffAccountId,
        'staff.access'
    )
) {
    http_response_code(403);

    exit(
        'このHCアカウントにはスタッフコンソールを利用する権限がありません。'
    );
}

/*
|--------------------------------------------------------------------------
| スタッフ固有プロフィール
|--------------------------------------------------------------------------
*/

$staffUser = staff_find_or_create_user(
    $staffAccount
);

$staffWorkspacePreferences = staff_workspace_preferences_load(
    (int) ($staffUser['id'] ?? 0)
);

/*
|--------------------------------------------------------------------------
| ロール・権限・部署・カテゴリ
|--------------------------------------------------------------------------
*/

$staffContext = staff_load_context(
    $staffAccount,
    $staffUser
);

staff_page_access_require(
    $staffContext,
    (string) ($_SERVER['REQUEST_URI'] ?? '/staff/')
);

staff_require_permission(
    $staffContext,
    'staff.dashboard.view'
);

$staffNavigation = staff_navigation_build(
    $staffContext
);

/*
|--------------------------------------------------------------------------
| 共通表示情報
|--------------------------------------------------------------------------
*/

$staffPrimaryRole = $staffContext['primary_role'];

$staffDisplayName = trim(
    (string) (
        $staffUser['display_name']
        ?? $staffAccount['display_name']
        ?? $staffAccount['username']
        ?? 'Staff'
    )
);

if ($staffDisplayName === '') {
    $staffDisplayName = 'Staff';
}

$staffRoleName = trim(
    (string) (
        $staffPrimaryRole['name']
        ?? 'Staff'
    )
);

if ($staffRoleName === '') {
    $staffRoleName = 'Staff';
}

$staffRoleSlug = trim(
    (string) (
        $staffPrimaryRole['slug']
        ?? 'staff'
    )
);

if ($staffRoleSlug === '') {
    $staffRoleSlug = 'staff';
}
