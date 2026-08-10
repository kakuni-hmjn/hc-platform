<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * スタッフコンソールのページ表示権限一覧。
 * path は完全一致、prefix は配下すべてを対象にする。
 */
function staff_page_access_registry(): array
{
    return [
        'page.staff.dashboard' => ['group' => 'ワークスペース', 'label' => 'ダッシュボード', 'path' => '/staff/'],
        'page.staff.tasks' => ['group' => 'ワークスペース', 'label' => 'マイタスク', 'prefix' => '/staff/tasks/'],
        'page.staff.notifications' => ['group' => 'ワークスペース', 'label' => '通知', 'prefix' => '/staff/notifications/'],
        'page.staff.announcements' => ['group' => 'ワークスペース', 'label' => '社内連絡', 'prefix' => '/staff/announcements/'],
        'page.staff.account' => ['group' => 'ワークスペース', 'label' => 'スタッフアカウント', 'prefix' => '/staff/account/'],

        'page.staff.rental' => ['group' => 'レンタルサーバー', 'label' => 'サービス概要', 'path' => '/staff/rental-server/'],
        'page.staff.rental.game.overview' => ['group' => 'レンタルサーバー', 'label' => 'ゲームサーバー概要', 'path' => '/staff/rental-server/game-server/'],
        'page.staff.rental.game.contracts' => ['group' => 'レンタルサーバー', 'label' => '申込・契約', 'prefix' => '/staff/rental-server/game-server/contracts/'],
        'page.staff.rental.game.approvals' => ['group' => 'レンタルサーバー', 'label' => '作成・承認', 'prefix' => '/staff/rental-server/game-server/approvals/'],
        'page.staff.rental.game.provisioning' => ['group' => 'レンタルサーバー', 'label' => 'Provisioning', 'prefix' => '/staff/rental-server/game-server/provisioning/'],
        'page.staff.rental.game.servers' => ['group' => 'レンタルサーバー', 'label' => 'サーバー一覧', 'prefix' => '/staff/rental-server/game-server/servers/'],
        'page.staff.rental.game.plans' => ['group' => 'レンタルサーバー', 'label' => 'プラン一覧', 'prefix' => '/staff/rental-server/game-server/plans/'],
        'page.staff.rental.game.nodes' => ['group' => 'レンタルサーバー', 'label' => 'Node管理', 'prefix' => '/staff/rental-server/game-server/nodes/'],
        'page.staff.rental.game.settings' => ['group' => 'レンタルサーバー', 'label' => 'ゲームサーバー設定', 'prefix' => '/staff/rental-server/game-server/settings/'],
        'page.staff.rental.game.pterodactyl' => ['group' => 'レンタルサーバー', 'label' => 'Pterodactyl連携', 'prefix' => '/staff/rental-server/game-server/pterodactyl/'],
        'page.staff.rental.vps' => ['group' => 'レンタルサーバー', 'label' => 'VPS', 'prefix' => '/staff/rental-server/vps/'],
        'page.staff.rental.hosting' => ['group' => 'レンタルサーバー', 'label' => 'ホスティング', 'prefix' => '/staff/rental-server/hosting/'],
        'page.staff.rental.dedicated' => ['group' => 'レンタルサーバー', 'label' => '専用サーバー', 'prefix' => '/staff/rental-server/dedicated/'],
        'page.staff.rental.colocation' => ['group' => 'レンタルサーバー', 'label' => 'コロケーション', 'prefix' => '/staff/rental-server/colocation/'],

        'page.staff.customers' => ['group' => '共通業務', 'label' => '顧客管理', 'prefix' => '/staff/customers/'],
        'page.staff.support.overview' => ['group' => '共通業務', 'label' => 'お問い合わせ概要', 'path' => '/staff/support/'],
        'page.staff.support.chat' => ['group' => '共通業務', 'label' => 'サポートチャット', 'prefix' => '/staff/support/chat/'],
        'page.staff.support.email' => ['group' => '共通業務', 'label' => 'サポートメール', 'prefix' => '/staff/support/email/'],
        'page.staff.billing' => ['group' => '共通業務', 'label' => '決済・請求', 'prefix' => '/staff/billing/'],
        'page.staff.audit' => ['group' => '共通業務', 'label' => '操作ログ', 'prefix' => '/staff/audit/'],
        'page.staff.approvals' => ['group' => '共通業務', 'label' => '承認センター', 'prefix' => '/staff/approvals/'],

        'page.staff.development' => ['group' => '開発', 'label' => 'プロジェクト', 'prefix' => '/staff/development/'],
        'page.staff.deployments' => ['group' => '開発', 'label' => 'デプロイ', 'prefix' => '/staff/deployments/'],
        'page.staff.infrastructure' => ['group' => 'インフラ', 'label' => 'システム状態', 'path' => '/staff/infrastructure/'],
        'page.staff.infrastructure.servers' => ['group' => 'インフラ', 'label' => '物理・仮想サーバー', 'prefix' => '/staff/infrastructure/servers/'],

        'page.staff.property.dashboard' => ['group' => '物品管理', 'label' => '物品管理ダッシュボード', 'path' => '/staff/property/'],
        'page.staff.property.inventory' => ['group' => '物品管理', 'label' => '在庫一覧', 'prefix' => '/staff/property/inventory/'],
        'page.staff.property.items' => ['group' => '物品管理', 'label' => '物品一覧', 'prefix' => '/staff/property/items/'],
        'page.staff.property.locations' => ['group' => '物品管理', 'label' => '設置場所', 'prefix' => '/staff/property/locations/'],
        'page.staff.property.racks' => ['group' => '物品管理', 'label' => 'ラック管理', 'prefix' => '/staff/property/racks/'],
        'page.staff.property.register' => ['group' => '物品管理', 'label' => '物品登録', 'prefix' => '/staff/property/register/'],
        'page.staff.property.scan' => ['group' => '物品管理', 'label' => 'QRスキャン', 'prefix' => '/staff/property/scan/'],
        'page.staff.property.qr' => ['group' => '物品管理', 'label' => 'QR発行', 'prefix' => '/staff/property/qr-issue/'],
        'page.staff.property.settings' => ['group' => '物品管理', 'label' => '物品管理設定', 'prefix' => '/staff/property/settings/'],

        'page.staff.admin.hub' => ['group' => '上位管理', 'label' => '上位管理センター', 'path' => '/staff/admin/'],
        'page.staff.admin.users' => ['group' => '上位管理', 'label' => 'スタッフ管理', 'prefix' => '/staff/admin/users/'],
        'page.staff.admin.roles' => ['group' => '上位管理', 'label' => 'ロール・権限管理', 'prefix' => '/staff/admin/roles/'],
        'page.staff.settings' => ['group' => '上位管理', 'label' => 'システム設定', 'prefix' => '/staff/settings/'],
        'page.staff.admin.site.services' => ['group' => '上位管理', 'label' => '事業・サービス掲載', 'prefix' => '/staff/admin/site/services/'],
        'page.staff.admin.site.news' => ['group' => '上位管理', 'label' => 'ニュース管理', 'prefix' => '/staff/admin/site/news/'],
        'page.staff.admin.site.header' => ['group' => '上位管理', 'label' => 'ヘッダー設定', 'prefix' => '/staff/admin/site/header/'],
        'page.staff.admin.site.notifications' => ['group' => '上位管理', 'label' => 'サイト通知', 'prefix' => '/staff/admin/site/notifications/'],
        'page.staff.admin.site.user_notifications' => ['group' => '上位管理', 'label' => 'ユーザー通知', 'prefix' => '/staff/admin/site/user-notifications/'],
        'page.staff.admin.site.menu' => ['group' => '上位管理', 'label' => '管理メニュー設定', 'prefix' => '/staff/admin/site/menu/'],
        'page.staff.admin.game_plans' => ['group' => '上位管理', 'label' => 'ゲームサーバープラン編集', 'prefix' => '/staff/admin/services/game-plans/'],
        'page.staff.admin.order_settings' => ['group' => '上位管理', 'label' => '申込受付設定', 'prefix' => '/staff/admin/services/order-settings/'],
        'page.staff.admin.ptero_users' => ['group' => '上位管理', 'label' => 'Pterodactylアカウント連携', 'prefix' => '/staff/admin/customers/ptero-users/'],
        'page.staff.admin.invoices' => ['group' => '上位管理', 'label' => '請求書発行', 'prefix' => '/staff/admin/billing/invoices/'],
        'page.staff.admin.stripe' => ['group' => '上位管理', 'label' => 'Stripeプラン同期', 'prefix' => '/staff/admin/billing/stripe-plans/'],
        'page.staff.admin.logs' => ['group' => '上位管理', 'label' => 'システムログ', 'prefix' => '/staff/admin/system/logs/'],
    ];
}

function staff_page_access_role_definitions(): array
{
    return [
        'owner' => ['name' => 'オーナー', 'type' => 'system', 'priority' => 1000, 'system' => true, 'color' => '#111827', 'icon' => 'verified_user'],
        'administrator' => ['name' => '管理者', 'type' => 'system', 'priority' => 900, 'system' => true, 'color' => '#7c3aed', 'icon' => 'admin_panel_settings'],
        'manager' => ['name' => 'マネージャー', 'type' => 'staff', 'priority' => 800, 'system' => false, 'color' => '#2563eb', 'icon' => 'manage_accounts'],
        'staff' => ['name' => '一般スタッフ', 'type' => 'staff', 'priority' => 700, 'system' => false, 'color' => '#475569', 'icon' => 'badge'],
        'temporary_staff' => ['name' => '臨時スタッフ', 'type' => 'staff', 'priority' => 600, 'system' => false, 'color' => '#64748b', 'icon' => 'schedule'],
        'web_developer' => ['name' => 'Web開発者', 'type' => 'job', 'priority' => 550, 'system' => false, 'color' => '#0891b2', 'icon' => 'code'],
        'infra_operator' => ['name' => 'インフラ担当', 'type' => 'job', 'priority' => 540, 'system' => false, 'color' => '#ea580c', 'icon' => 'dns'],
        'support_agent' => ['name' => 'サポート担当', 'type' => 'job', 'priority' => 530, 'system' => false, 'color' => '#16a34a', 'icon' => 'support_agent'],
        'order_operator' => ['name' => '注文担当', 'type' => 'job', 'priority' => 520, 'system' => false, 'color' => '#ca8a04', 'icon' => 'receipt_long'],
        'order_approver' => ['name' => '注文承認者', 'type' => 'job', 'priority' => 510, 'system' => false, 'color' => '#dc2626', 'icon' => 'approval'],
        'viewer' => ['name' => '閲覧専用', 'type' => 'staff', 'priority' => 400, 'system' => false, 'color' => '#6b7280', 'icon' => 'visibility'],
    ];
}

function staff_page_access_default_assignments(): array
{
    $allPages = array_keys(staff_page_access_registry());
    $common = ['staff.access', 'staff.dashboard.view', 'tasks.view.own', 'announcements.view'];
    return [
        'owner' => ['*'],
        'administrator' => ['*'],
        'manager' => array_merge($common, [
            'staff.users.view', 'staff.users.edit', 'staff.roles.assign', 'tasks.view.department',
            'tasks.create', 'tasks.assign', 'tasks.complete', 'orders.view', 'orders.process',
            'orders.approve', 'support.tickets.view', 'support.tickets.reply', 'audit.logs.view',
            'infrastructure.servers.view', 'announcements.manage',
        ], array_values(array_filter($allPages, static fn(string $key): bool => !str_starts_with($key, 'page.staff.admin.')))),
        'staff' => array_merge($common, ['tasks.complete', 'page.staff.dashboard', 'page.staff.tasks', 'page.staff.notifications', 'page.staff.announcements', 'page.staff.account']),
        'temporary_staff' => ['staff.access', 'staff.dashboard.view', 'tasks.view.own', 'page.staff.dashboard', 'page.staff.tasks', 'page.staff.notifications', 'page.staff.account'],
        'web_developer' => array_merge($common, ['development.projects.view', 'development.deploy.staging', 'audit.logs.view', 'page.staff.dashboard', 'page.staff.tasks', 'page.staff.notifications', 'page.staff.announcements', 'page.staff.account', 'page.staff.development', 'page.staff.deployments', 'page.staff.audit']),
        'infra_operator' => array_merge($common, ['orders.view', 'infrastructure.servers.view', 'infrastructure.servers.restart', 'audit.logs.view', 'page.staff.dashboard', 'page.staff.tasks', 'page.staff.notifications', 'page.staff.account', 'page.staff.rental', 'page.staff.rental.game.overview', 'page.staff.rental.game.contracts', 'page.staff.rental.game.servers', 'page.staff.rental.game.nodes', 'page.staff.infrastructure', 'page.staff.infrastructure.servers', 'page.staff.audit']),
        'support_agent' => array_merge($common, ['support.tickets.view', 'support.tickets.reply', 'page.staff.dashboard', 'page.staff.tasks', 'page.staff.notifications', 'page.staff.announcements', 'page.staff.account', 'page.staff.customers', 'page.staff.support.overview', 'page.staff.support.chat', 'page.staff.support.email']),
        'order_operator' => array_merge($common, ['orders.view', 'orders.process', 'page.staff.dashboard', 'page.staff.tasks', 'page.staff.notifications', 'page.staff.account', 'page.staff.rental', 'page.staff.rental.game.overview', 'page.staff.rental.game.contracts', 'page.staff.rental.game.provisioning', 'page.staff.rental.game.servers', 'page.staff.rental.game.plans', 'page.staff.billing']),
        'order_approver' => array_merge($common, ['orders.view', 'orders.approve', 'audit.logs.view', 'page.staff.dashboard', 'page.staff.tasks', 'page.staff.notifications', 'page.staff.account', 'page.staff.rental', 'page.staff.rental.game.overview', 'page.staff.rental.game.contracts', 'page.staff.rental.game.approvals', 'page.staff.approvals', 'page.staff.audit']),
        'viewer' => ['staff.access', 'staff.dashboard.view', 'page.staff.dashboard', 'page.staff.notifications', 'page.staff.account'],
    ];
}

function staff_page_access_ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;

    $pdo->exec("ALTER TABLE account_roles ADD COLUMN IF NOT EXISTS color VARCHAR(7) NOT NULL DEFAULT '#475569'");
    $pdo->exec("ALTER TABLE account_roles ADD COLUMN IF NOT EXISTS icon VARCHAR(80) NOT NULL DEFAULT 'badge'");
    $pdo->exec('CREATE TABLE IF NOT EXISTS staff_access_seed_state (seed_key VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW())');

    $seedKey = 'staff_page_roles_v1';
    $check = $pdo->prepare('SELECT 1 FROM staff_access_seed_state WHERE seed_key = :seed_key');
    $check->execute(['seed_key' => $seedKey]);
    $seeded = $check->fetchColumn() !== false;

    if (!$seeded) {
        $roleStatement = $pdo->prepare(
            'INSERT INTO account_roles (slug, name, role_type, description, priority, is_system, is_staff_role, color, icon)
             VALUES (:slug, :name, :role_type, :description, :priority, :is_system, TRUE, :color, :icon)
             ON CONFLICT (slug) DO UPDATE SET is_staff_role = TRUE,
                name = CASE WHEN account_roles.name IN (
                    \'Owner\', \'Administrator\', \'Manager\', \'Staff\', \'Temporary Staff\',
                    \'Web Developer\', \'Infrastructure Operator\', \'Support Agent\',
                    \'Order Operator\', \'Order Approver\'
                ) THEN EXCLUDED.name ELSE account_roles.name END,
                color = CASE WHEN account_roles.color = \'#475569\' THEN EXCLUDED.color ELSE account_roles.color END,
                icon = CASE WHEN account_roles.icon = \'badge\' THEN EXCLUDED.icon ELSE account_roles.icon END'
        );
        foreach (staff_page_access_role_definitions() as $slug => $role) {
            $roleStatement->execute([
                'slug' => $slug, 'name' => $role['name'], 'role_type' => $role['type'],
                'description' => $role['name'] . '用の初期ロール', 'priority' => $role['priority'],
                'is_system' => $role['system'] ? 'true' : 'false', 'color' => $role['color'], 'icon' => $role['icon'],
            ]);
        }
    }

    $permissionStatement = $pdo->prepare(
        'INSERT INTO permissions (permission_key, name, description)
         VALUES (:permission_key, :name, :description)
         ON CONFLICT (permission_key) DO UPDATE SET name = EXCLUDED.name, description = EXCLUDED.description'
    );
    foreach (staff_page_access_registry() as $key => $page) {
        $permissionStatement->execute([
            'permission_key' => $key,
            'name' => $page['label'] . 'を表示',
            'description' => $page['group'] . ' / ' . $page['label'] . 'ページの表示権限',
        ]);
    }

    if (!$seeded) {
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT INTO role_permissions (role_id, permission_id)
                 SELECT r.id, p.id FROM account_roles r CROSS JOIN permissions p
                 WHERE r.slug = :slug AND (:all_permissions = TRUE OR p.permission_key = ANY(CAST(:keys AS TEXT[])))
                 ON CONFLICT DO NOTHING'
            );
            foreach (staff_page_access_default_assignments() as $slug => $keys) {
                $all = $keys === ['*'];
                $pgArray = '{' . implode(',', array_map(static fn(string $key): string => '"' . str_replace('"', '\\"', $key) . '"', $all ? [] : array_values(array_unique($keys)))) . '}';
                $insert->execute(['slug' => $slug, 'all_permissions' => $all ? 'true' : 'false', 'keys' => $pgArray]);
            }
            $statement = $pdo->prepare('INSERT INTO staff_access_seed_state (seed_key) VALUES (:seed_key) ON CONFLICT DO NOTHING');
            $statement->execute(['seed_key' => $seedKey]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
    $ready = true;
}

function staff_page_access_permission_for_path(string $path): ?string
{
    $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
    $best = null;
    $bestLength = -1;
    foreach (staff_page_access_registry() as $permission => $page) {
        if (isset($page['path']) && $path === $page['path'] && strlen($page['path']) > $bestLength) {
            $best = $permission;
            $bestLength = strlen($page['path']);
        }
        if (isset($page['prefix']) && str_starts_with($path, $page['prefix']) && strlen($page['prefix']) > $bestLength) {
            $best = $permission;
            $bestLength = strlen($page['prefix']);
        }
    }
    return $best;
}

function staff_page_access_allowed(array $context, string $path): bool
{
    // ページ権限の全解除はシステム上の最上位ロールだけに限定する。
    // ロール編集権限を持つカスタムロールが、未許可ページまで見えることを防ぐ。
    if (staff_has_role($context, 'owner') || staff_has_role($context, 'administrator')) return true;
    $permission = staff_page_access_permission_for_path($path);
    return $permission === null || staff_has_permission($context, $permission);
}

function staff_page_access_require(array $context, string $path): void
{
    if (staff_page_access_allowed($context, $path)) return;
    http_response_code(403);
    exit('このロールには、このスタッフページを表示する権限がありません。');
}
