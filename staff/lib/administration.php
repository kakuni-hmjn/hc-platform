<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once dirname(__DIR__, 2) . '/admin/lib/menu.php';
require_once dirname(__DIR__, 2) . '/lib/csrf.php';

function staff_administration_require_admin(array $context): void
{
    if (staff_can_access_admin($context)) {
        return;
    }

    http_response_code(403);
    exit('このページは上位管理者のみ利用できます。');
}

function staff_administration_flash(string $type, string $message): void
{
    $_SESSION['staff_administration_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function staff_administration_take_flash(): ?array
{
    $flash = $_SESSION['staff_administration_flash'] ?? null;
    unset($_SESSION['staff_administration_flash']);
    return is_array($flash) ? $flash : null;
}

function staff_administration_redirect(string $path): never
{
    if (!str_starts_with($path, '/staff/admin/')) {
        $path = '/staff/admin/';
    }
    header('Location: ' . $path);
    exit;
}

function staff_administration_datetime(?string $value, string $fallback = '-'): string
{
    if (!$value) {
        return $fallback;
    }
    try {
        return (new DateTime($value))->format('Y/m/d H:i');
    } catch (Throwable $exception) {
        return $value;
    }
}

function staff_administration_internal_url(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (!str_starts_with($value, '/') || str_contains($value, '://')) {
        throw new RuntimeException('URLは / から始まる内部URLで入力してください。');
    }
    return $value;
}

/**
 * スタッフコンソール上位管理の分類。
 *
 * 旧 /admin/ の分類とは分け、日々の業務で探しやすい単位に再編する。
 */
function staff_administration_sections(): array
{
    return [
        'site' => [
            'label' => 'Webサイト全体',
            'description' => '公開サイト、掲載内容、お知らせ、通知と共通メニューを管理します。',
            'icon' => 'language',
            'order' => 10,
        ],
        'services' => [
            'label' => 'サービス管理',
            'description' => '各サービスのプラン、設備、提供状態と外部連携を管理します。',
            'icon' => 'category',
            'order' => 20,
        ],
        'operations' => [
            'label' => '契約・運用',
            'description' => '申込、契約、作成処理、承認と変更申請を管理します。',
            'icon' => 'assignment',
            'order' => 30,
        ],
        'customers' => [
            'label' => '顧客・アカウント',
            'description' => 'HCアカウント、お問い合わせ、連携アカウントを管理します。',
            'icon' => 'group',
            'order' => 40,
        ],
        'billing' => [
            'label' => '決済・請求',
            'description' => '支払い、請求書、Stripeプランと決済履歴を管理します。',
            'icon' => 'payments',
            'order' => 50,
        ],
        'organization' => [
            'label' => 'スタッフ・組織',
            'description' => 'スタッフ、ロール、権限、社内業務と監査情報を管理します。',
            'icon' => 'admin_panel_settings',
            'order' => 60,
        ],
        'system' => [
            'label' => 'システム・開発',
            'description' => '環境設定、ログ、開発状況、デプロイと基盤状態を管理します。',
            'icon' => 'settings_suggest',
            'order' => 70,
        ],
    ];
}

/**
 * 旧管理画面からスタッフ版への移行先と、日本語表示を定義する。
 */
function staff_administration_legacy_overrides(): array
{
    return [
        '/admin/services/' => [
            'section' => 'site', 'title' => '事業・サービス掲載', 'icon' => 'view_list',
            'description' => '公開サイトに掲載する事業とサービスを編集します。',
            'destination' => '/staff/admin/site/services/',
        ],
        '/admin/news/' => [
            'section' => 'site', 'title' => 'ニュース管理', 'icon' => 'newspaper',
            'description' => 'サイト内ニュースの作成、編集、公開状態を管理します。',
            'destination' => '/staff/admin/site/news/',
        ],
        '/admin/header-settings/' => [
            'section' => 'site', 'title' => 'ヘッダー設定', 'icon' => 'web_asset',
            'description' => '公開サイトのヘッダー表示と操作リンクを管理します。',
            'destination' => '/staff/admin/site/header/',
        ],
        '/admin/site-notifications/' => [
            'section' => 'site', 'title' => 'サイト通知', 'icon' => 'notification_important',
            'description' => 'サイト全体に表示する重要通知を作成・管理します。',
            'destination' => '/staff/admin/site/notifications/',
        ],
        '/admin/user-notifications/' => [
            'section' => 'site', 'title' => 'ユーザー通知', 'icon' => 'notifications_active',
            'description' => 'HCアカウントへの個別通知を作成・配信します。',
            'destination' => '/staff/admin/site/user-notifications/',
        ],
        '/admin/game-plans/' => [
            'section' => 'services', 'title' => 'ゲームサーバープラン', 'icon' => 'sell',
            'description' => '料金、CPU、メモリ、容量と販売状態を管理します。',
            'destination' => '/staff/admin/services/game-plans/',
        ],
        '/admin/order-settings/' => [
            'section' => 'services', 'title' => 'ゲームサーバー注文設定', 'icon' => 'tune',
            'description' => '注文受付の有効・無効と販売条件を管理します。',
            'destination' => '/staff/admin/services/order-settings/',
        ],
        '/admin/ptero/' => [
            'section' => 'services', 'title' => 'Pterodactyl管理', 'icon' => 'dns',
            'description' => 'Pterodactylとの接続状態とサーバー情報を確認します。',
            'destination' => '/staff/rental-server/game-server/pterodactyl/',
        ],
        '/admin/ptero/allocations/' => [
            'section' => 'services', 'title' => 'Node・Allocation', 'icon' => 'hub',
            'description' => 'NodeとIP・ポート割り当てを確認・管理します。',
            'destination' => '/staff/rental-server/game-server/nodes/',
        ],
        '/admin/server-orders/' => [
            'section' => 'operations', 'title' => 'ゲームサーバー契約', 'icon' => 'receipt_long',
            'description' => '申込内容、支払い状況、契約状態をまとめて管理します。',
            'destination' => '/staff/rental-server/game-server/contracts/',
        ],
        '/admin/server-orders/ready/' => [
            'section' => 'operations', 'title' => '支払い済み・作成待ち', 'icon' => 'play_circle',
            'description' => '支払いが完了し、サーバー作成を開始できる契約を確認します。',
            'destination' => '/staff/rental-server/game-server/provisioning/',
        ],
        '/admin/server-orders/provision/' => [
            'section' => 'operations', 'title' => 'サーバー作成', 'icon' => 'precision_manufacturing',
            'description' => '作成中の契約とプロビジョニング処理を管理します。',
            'destination' => '/staff/rental-server/game-server/provisioning/',
        ],
        '/admin/server-orders/provision/failed/' => [
            'section' => 'operations', 'title' => '作成失敗・再試行', 'icon' => 'error',
            'description' => '失敗したサーバー作成処理の内容確認と再試行を行います。',
            'destination' => '/staff/rental-server/game-server/provisioning/',
        ],
        '/admin/server-orders/pending/' => [
            'section' => 'operations', 'title' => 'サーバー作成承認', 'icon' => 'approval',
            'description' => '作成済みサーバーの最終確認と利用開始承認を行います。',
            'destination' => '/staff/rental-server/game-server/approvals/',
        ],
        '/admin/plan-change-requests/' => [
            'section' => 'operations', 'title' => 'プラン変更申請', 'icon' => 'swap_horiz',
            'description' => '顧客から申請されたプラン変更を確認・処理します。',
            'destination' => '/staff/approvals/',
        ],
        '/admin/users/' => [
            'section' => 'customers', 'title' => 'HCアカウント管理', 'icon' => 'manage_accounts',
            'description' => '顧客アカウント、利用状態と契約情報を確認します。',
            'destination' => '/staff/customers/',
        ],
        '/admin/ptero-users/' => [
            'section' => 'customers', 'title' => 'Pterodactylアカウント連携', 'icon' => 'link',
            'description' => 'HCアカウントとPterodactylアカウントの連携を管理します。',
            'destination' => '/staff/admin/customers/ptero-users/',
        ],
        '/admin/billing/' => [
            'section' => 'billing', 'title' => '決済・請求管理', 'icon' => 'payments',
            'description' => '請求、支払い、未払いと決済イベントを確認します。',
            'destination' => '/staff/billing/',
        ],
        '/admin/billing/invoice/' => [
            'section' => 'billing', 'title' => '請求書発行', 'icon' => 'request_quote',
            'description' => '契約に対する請求書の内容を作成・確認します。',
            'destination' => '/staff/admin/billing/invoices/',
        ],
        '/admin/stripe-plans/' => [
            'section' => 'billing', 'title' => 'Stripeプラン同期', 'icon' => 'sync',
            'description' => '販売プランとStripe Priceの対応関係を同期します。',
            'destination' => '/staff/admin/billing/stripe-plans/',
        ],
        '/admin/staff/' => [
            'section' => 'organization', 'title' => 'スタッフ管理', 'icon' => 'groups',
            'description' => 'スタッフプロフィール、勤務状態、ロールと権限を管理します。',
            'destination' => '/staff/admin/users/',
        ],
        '/admin/dev/' => [
            'section' => 'system', 'title' => '開発管理', 'icon' => 'code',
            'description' => 'サービス開発状況と検証用情報を確認します。',
            'destination' => '/staff/development/',
        ],
        '/admin/dev/logs/' => [
            'section' => 'system', 'title' => '開発ログ', 'icon' => 'terminal',
            'description' => 'アプリケーションのログとエラー情報を確認します。',
            'destination' => '/staff/admin/system/logs/',
        ],
    ];
}

function staff_administration_extra_modules(): array
{
    return [
        [
            'id' => 'site-menu-settings', 'section' => 'site', 'title' => '管理メニュー設定',
            'description' => '管理機能の分類、表示順と説明文を管理します。',
            'icon' => 'menu_open', 'destination' => '/staff/admin/site/menu/', 'legacy_route' => '/admin/menu-settings/',
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'support', 'section' => 'customers', 'title' => 'お問い合わせ管理',
            'description' => '問い合わせ概要、チャット、メール履歴と返信を管理します。',
            'icon' => 'support_agent', 'destination' => '/staff/support/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'property', 'section' => 'services', 'title' => '物品管理センター',
            'description' => '機材、在庫、設置場所、ラック、QRコードを管理します。',
            'icon' => 'inventory_2', 'destination' => '/staff/property/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'rental-server', 'section' => 'services', 'title' => 'レンタルサーバー全体',
            'description' => 'ゲームサーバー、VPS、ホスティングなど各サービスを管理します。',
            'icon' => 'dns', 'destination' => '/staff/rental-server/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'infrastructure', 'section' => 'system', 'title' => 'インフラ状態',
            'description' => 'データベース、外部連携、Nodeとサーバーの稼働状態を確認します。',
            'icon' => 'monitor_heart', 'destination' => '/staff/infrastructure/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'staff-settings', 'section' => 'system', 'title' => 'システム設定',
            'description' => 'HC Platform全体の設定入口と接続状態を確認します。',
            'icon' => 'settings', 'destination' => '/staff/settings/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'staff-audit', 'section' => 'organization', 'title' => '操作ログ・監査',
            'description' => 'スタッフ操作と重要なシステム処理の履歴を追跡します。',
            'icon' => 'history', 'destination' => '/staff/audit/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'staff-permissions', 'section' => 'organization', 'title' => 'ロール・有効権限',
            'description' => '自分に付与されたロール、部署、カテゴリと操作権限を確認します。',
            'icon' => 'key', 'destination' => '/staff/account/permissions/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'staff-role-manager', 'section' => 'organization', 'title' => 'ロール・権限管理',
            'description' => 'Discordのように複数ロールとページ・操作権限を編集します。',
            'icon' => 'admin_panel_settings', 'destination' => '/staff/admin/roles/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'staff-announcements', 'section' => 'organization', 'title' => '社内連絡',
            'description' => 'スタッフ向けのお知らせと重要な業務連絡を管理します。',
            'icon' => 'campaign', 'destination' => '/staff/announcements/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
        [
            'id' => 'deployments', 'section' => 'system', 'title' => 'デプロイ管理',
            'description' => 'ステージング・本番環境へのデプロイ状況を確認します。',
            'icon' => 'rocket_launch', 'destination' => '/staff/deployments/', 'legacy_route' => null,
            'surface' => 'staff', 'auto_detected' => false,
        ],
    ];
}

function staff_administration_default_section(string $legacyCategory): string
{
    return match ($legacyCategory) {
        'content' => 'site',
        'services', 'レンタルサーバー' => 'services',
        'operations' => 'operations',
        'customers' => 'customers',
        'billing' => 'billing',
        default => 'system',
    };
}

function staff_administration_catalog(): array
{
    $config = admin_menu_load();
    $legacyPages = admin_menu_detect_pages($config);
    $overrides = staff_administration_legacy_overrides();
    $modules = [];

    foreach ($legacyPages as $legacyRoute => $page) {
        $override = $overrides[$legacyRoute] ?? [];
        $destination = (string) ($override['destination'] ?? $legacyRoute);
        $surface = str_starts_with($destination, '/staff/') ? 'staff' : 'legacy';

        $modules[] = [
            'id' => trim($legacyRoute, '/'),
            'section' => (string) ($override['section'] ?? staff_administration_default_section((string) ($page['category'] ?? ''))),
            'title' => (string) ($override['title'] ?? $page['title']),
            'description' => (string) ($override['description'] ?? $page['description']),
            'icon' => (string) ($override['icon'] ?? 'settings'),
            'destination' => $destination,
            'legacy_route' => $legacyRoute,
            'surface' => $surface,
            'auto_detected' => $override === [],
        ];
    }

    foreach (staff_administration_extra_modules() as $module) {
        $modules[] = $module;
    }

    usort(
        $modules,
        static function (array $left, array $right): int {
            $sections = staff_administration_sections();
            $leftOrder = (int) ($sections[$left['section']]['order'] ?? 999);
            $rightOrder = (int) ($sections[$right['section']]['order'] ?? 999);
            return $leftOrder <=> $rightOrder ?: strnatcasecmp((string) $left['title'], (string) $right['title']);
        }
    );

    return $modules;
}

function staff_administration_filter_modules(array $modules, string $section, string $query): array
{
    $query = mb_strtolower(trim($query), 'UTF-8');

    return array_values(array_filter(
        $modules,
        static function (array $module) use ($section, $query): bool {
            if ($section !== 'all' && (string) $module['section'] !== $section) {
                return false;
            }
            if ($query === '') {
                return true;
            }
            $haystack = mb_strtolower(implode(' ', [
                (string) ($module['title'] ?? ''),
                (string) ($module['description'] ?? ''),
                (string) ($module['destination'] ?? ''),
                (string) ($module['legacy_route'] ?? ''),
            ]), 'UTF-8');
            return mb_strpos($haystack, $query, 0, 'UTF-8') !== false;
        }
    ));
}

function staff_administration_group_modules(array $modules): array
{
    $grouped = [];
    foreach (staff_administration_sections() as $key => $_section) {
        $grouped[$key] = [];
    }
    foreach ($modules as $module) {
        $key = (string) ($module['section'] ?? 'system');
        if (!isset($grouped[$key])) {
            $key = 'system';
        }
        $grouped[$key][] = $module;
    }
    return $grouped;
}

function staff_administration_query_count(string $sql): int
{
    try {
        return (int) (staff_db()->query($sql)->fetchColumn() ?: 0);
    } catch (Throwable $exception) {
        return 0;
    }
}

function staff_administration_overview(): array
{
    return [
        'customers' => staff_administration_query_count('SELECT COUNT(*) FROM users'),
        'services' => staff_administration_query_count("SELECT COUNT(*) FROM services WHERE status = 'published'"),
        'active_orders' => staff_administration_query_count("SELECT COUNT(*) FROM game_server_orders WHERE status = 'active'"),
        'attention' => staff_administration_query_count(
            "SELECT COUNT(*) FROM game_server_orders WHERE status IN ('paid', 'creating', 'provision_failed', 'pending_approval', 'approval_failed')"
        ) + staff_administration_query_count("SELECT COUNT(*) FROM contacts WHERE status IN ('open', 'pending')"),
    ];
}
