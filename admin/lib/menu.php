<?php

function admin_menu_config_file(): string
{
    return __DIR__ . '/../config/menu-config.json';
}

function admin_menu_default_categories(): array
{
    return [
        'operations' => [
            'name' => '運用管理',
            'description' => '注文からサービス提供までの運用機能',
            'order' => 10,
        ],
        'customers' => [
            'name' => 'ユーザー管理',
            'description' => 'ユーザー、権限、アカウント連携',
            'order' => 20,
        ],
        'services' => [
            'name' => '事業・サービス',
            'description' => '事業一覧、プラン、提供サービス',
            'order' => 30,
        ],
        'billing' => [
            'name' => '請求・決済',
            'description' => '請求書、支払い、Stripe関連機能',
            'order' => 40,
        ],
        'content' => [
            'name' => 'コンテンツ・通知',
            'description' => 'ニュース、通知、お問い合わせ',
            'order' => 50,
        ],
        'system' => [
            'name' => 'システム',
            'description' => 'Worker、ログ、設定、開発機能',
            'order' => 60,
        ],
        'uncategorized' => [
            'name' => '未分類',
            'description' => 'カテゴリが設定されていない管理機能',
            'order' => 999,
        ],
    ];
}


function admin_menu_default_descriptions(): array
{
    return [
        '/admin/server-orders/' =>
            'ゲームサーバーの注文内容、支払い状況、承認状態を確認・管理します。',

        '/admin/provisioning-jobs/' =>
            'ゲームサーバー作成処理の進行状況や失敗したジョブを確認します。',

        '/admin/servers/' =>
            '契約中のゲームサーバー、割り当て先ノード、稼働状態を管理します。',

        '/admin/plan-change-requests/' =>
            'ユーザーから申請されたプラン変更を確認し、承認または対応します。',

        '/admin/cancellation-requests/' =>
            'ユーザーから送信された解約申請と解約予定日を管理します。',

        '/admin/users/' =>
            'HCアカウント、ユーザー情報、権限、利用状態を管理します。',

        '/admin/ptero-users/' =>
            'HCアカウントとPterodactylアカウントの連携状態を管理します。',

        '/admin/roles/' =>
            'オーナー、管理者、スタッフ、一般ユーザーなどの権限を管理します。',

        '/admin/admin-users/' =>
            '管理画面へアクセスできるスタッフと管理者を管理します。',

        '/admin/services/' =>
            'HCが提供している事業やサービスの内容、公開状態を管理します。',

        '/admin/businesses/' =>
            '事業一覧に表示する事業情報、説明、リンク、表示状態を管理します。',

        '/admin/activities/' =>
            'クリエイター支援や活動支援サービスの内容を管理します。',

        '/admin/game-plans/' =>
            'ゲームサーバープランの料金、CPU、メモリ、容量、販売状態を管理します。',

        '/admin/order-settings/' =>
            '注文受付の有効・無効、受付条件、注文時の設定を管理します。',

        '/admin/nodes/' =>
            'ゲームサーバーを稼働させるノードと接続状態を管理します。',

        '/admin/eggs/' =>
            'ゲームやアプリケーションの起動テンプレートを管理します。',

        '/admin/allocations/' =>
            'ノードで使用するIPアドレスとポートの割り当てを管理します。',

        '/admin/billing/' =>
            '請求、支払い、未払い状況などの決済情報をまとめて確認します。',

        '/admin/invoices/' =>
            'ユーザーへ発行された請求書、支払期限、支払い状態を管理します。',

        '/admin/payments/' =>
            'Stripeなどを通じて行われた支払い履歴と決済結果を確認します。',

        '/admin/coupons/' =>
            '注文時に使用できる割引クーポンと有効期限を管理します。',

        '/admin/promotions/' =>
            '期間限定キャンペーン、割引、プロモーション設定を管理します。',

        '/admin/notifications/' =>
            'ユーザー個別通知、全体通知、重要なお知らせを作成・配信します。',

        '/admin/news/' =>
            'HC Platformやサービスに関するニュース記事を作成・管理します。',

        '/admin/inquiries/' =>
            'ユーザーから送信されたお問い合わせと対応状況を管理します。',

        '/admin/contact/' =>
            'お問い合わせフォームから受信した内容を確認・管理します。',

        '/admin/system/workers/' =>
            'バックグラウンド処理を行うWorkerの稼働状態を確認します。',

        '/admin/system/events/' =>
            '注文、決済、サーバー作成などのシステムイベントを確認します。',

        '/admin/settings/' =>
            'HC Platform全体で使用する共通設定を管理します。',

        '/admin/audit-logs/' =>
            '管理者やスタッフが行った操作の記録を確認します。',

        '/admin/logs/' =>
            'アプリケーションエラーやシステム処理のログを確認します。',

        '/admin/dev/' =>
            '開発中の機能、検証ツール、デバッグ情報を利用します。',

        '/admin/menu-settings/' =>
            '管理ページのカテゴリ、表示順、説明文を管理します。',
    ];
}

function admin_menu_default_assignments(): array
{
    return [
        '/admin/server-orders/' => 'operations',
        '/admin/provisioning-jobs/' => 'operations',
        '/admin/servers/' => 'operations',
        '/admin/plan-change-requests/' => 'operations',
        '/admin/cancellation-requests/' => 'operations',

        '/admin/users/' => 'customers',
        '/admin/ptero-users/' => 'customers',
        '/admin/roles/' => 'customers',
        '/admin/admin-users/' => 'customers',

        '/admin/services/' => 'services',
        '/admin/businesses/' => 'services',
        '/admin/activities/' => 'services',
        '/admin/game-plans/' => 'services',
        '/admin/order-settings/' => 'services',
        '/admin/nodes/' => 'services',
        '/admin/eggs/' => 'services',
        '/admin/allocations/' => 'services',

        '/admin/billing/' => 'billing',
        '/admin/invoices/' => 'billing',
        '/admin/payments/' => 'billing',
        '/admin/coupons/' => 'billing',
        '/admin/promotions/' => 'billing',

        '/admin/notifications/' => 'content',
        '/admin/news/' => 'content',
        '/admin/inquiries/' => 'content',
        '/admin/contact/' => 'content',

        '/admin/system/workers/' => 'system',
        '/admin/system/events/' => 'system',
        '/admin/settings/' => 'system',
        '/admin/audit-logs/' => 'system',
        '/admin/logs/' => 'system',
        '/admin/dev/' => 'system',
    ];
}

function admin_menu_page_definitions(): array
{
    return [
        '/admin/server-orders/' => [
            'title' => '注文管理',
            'description' => '注文、支払い状況、承認待ちを管理',
        ],
        '/admin/provisioning-jobs/' => [
            'title' => 'プロビジョニング',
            'description' => 'サーバー作成ジョブとエラーを管理',
        ],
        '/admin/servers/' => [
            'title' => 'サーバー管理',
            'description' => '契約中のゲームサーバーを管理',
        ],
        '/admin/plan-change-requests/' => [
            'title' => 'プラン変更',
            'description' => 'プラン変更申請を確認・処理',
        ],
        '/admin/cancellation-requests/' => [
            'title' => '解約申請',
            'description' => '解約申請を確認・処理',
        ],
        '/admin/users/' => [
            'title' => 'ユーザー管理',
            'description' => 'HCアカウントと権限を管理',
        ],
        '/admin/ptero-users/' => [
            'title' => 'Pterodactyl連携',
            'description' => 'Pterodactylアカウント連携を管理',
        ],
        '/admin/services/' => [
            'title' => '事業一覧',
            'description' => 'HCが提供する事業とサービスを管理',
        ],
        '/admin/businesses/' => [
            'title' => '事業一覧',
            'description' => '事業情報と公開状態を管理',
        ],
        '/admin/activities/' => [
            'title' => '活動支援',
            'description' => '活動支援サービスを管理',
        ],
        '/admin/game-plans/' => [
            'title' => 'ゲームプラン',
            'description' => '料金、CPU、メモリ、Stripe価格を管理',
        ],
        '/admin/order-settings/' => [
            'title' => '注文設定',
            'description' => '注文受付と販売設定を管理',
        ],
        '/admin/billing/' => [
            'title' => '請求・支払い',
            'description' => '請求書と支払い情報を管理',
        ],
        '/admin/invoices/' => [
            'title' => '請求書',
            'description' => '請求書の発行状況を管理',
        ],
        '/admin/payments/' => [
            'title' => '支払い履歴',
            'description' => 'Stripeを含む支払い履歴を確認',
        ],
        '/admin/notifications/' => [
            'title' => '通知管理',
            'description' => '個別通知と全体通知を管理',
        ],
        '/admin/news/' => [
            'title' => 'ニュース',
            'description' => 'サイト内ニュースを管理',
        ],
        '/admin/inquiries/' => [
            'title' => 'お問い合わせ',
            'description' => 'お問い合わせと対応状況を管理',
        ],
        '/admin/system/workers/' => [
            'title' => 'Worker',
            'description' => 'バックグラウンド処理の状態を確認',
        ],
        '/admin/system/events/' => [
            'title' => 'イベントログ',
            'description' => '注文やサーバー作成処理の履歴を確認',
        ],
        '/admin/settings/' => [
            'title' => 'システム設定',
            'description' => 'HC Platform全体の設定を管理',
        ],
        '/admin/dev/' => [
            'title' => '開発ツール',
            'description' => '開発・検証用の管理機能',
        ],
    ];
}

function admin_menu_load(): array
{
    $config = [
        'categories' => admin_menu_default_categories(),
        'assignments' => admin_menu_default_assignments(),
        'descriptions' => admin_menu_default_descriptions(),
    ];

    $file = admin_menu_config_file();

    if (!is_file($file) || filesize($file) === 0) {
        return $config;
    }

    $decoded = json_decode((string) file_get_contents($file), true);

    if (!is_array($decoded)) {
        return $config;
    }

    if (isset($decoded['categories']) && is_array($decoded['categories'])) {
        $config['categories'] = array_replace(
            $config['categories'],
            $decoded['categories']
        );
    }

    if (isset($decoded['assignments']) && is_array($decoded['assignments'])) {
        $config['assignments'] = array_replace(
            $config['assignments'],
            $decoded['assignments']
        );
    }

    uasort(
        $config['categories'],
        static fn(array $a, array $b): int =>
            ((int) ($a['order'] ?? 999))
            <=>
            ((int) ($b['order'] ?? 999))
    );

    return $config;
}

function admin_menu_save(array $config): bool
{
    $file = admin_menu_config_file();
    $directory = dirname($file);

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $json = json_encode(
        $config,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    if (!is_string($json)) {
        return false;
    }

    return file_put_contents($file, $json . PHP_EOL, LOCK_EX) !== false;
}

function admin_menu_slug(string $name): string
{
    $slug = mb_strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9ぁ-んァ-ヶ一-龠ー_-]+/u', '-', $slug);
    $slug = trim((string) $slug, '-_');

    return $slug !== ''
        ? $slug
        : 'category-' . bin2hex(random_bytes(4));
}

function admin_menu_detect_pages(array $config): array
{
    $definitions = admin_menu_page_definitions();

    $excludedParts = [
        'detail',
        'edit',
        'create',
        'new',
        'update',
        'delete',
        'remove',
        'approve',
        'reject',
        'cancel',
        'action',
        'actions',
        'config',
        'parts',
        'lib',
        'menu-settings',
    ];

    $pages = [];
    $root = realpath(__DIR__ . '/..');

    if (!is_string($root)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $fileInfo) {
        if (
            !$fileInfo instanceof SplFileInfo
            || !$fileInfo->isFile()
            || $fileInfo->getFilename() !== 'index.php'
        ) {
            continue;
        }

        $relative = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            substr($fileInfo->getPathname(), strlen($root))
        );

        $directory = trim(dirname($relative), '/.');

        if ($directory === '') {
            continue;
        }

        $parts = array_values(array_filter(explode('/', $directory)));

        if (array_intersect($parts, $excludedParts) !== []) {
            continue;
        }

        $route = '/admin/' . $directory . '/';
        $folderName = (string) end($parts);
        $definition = $definitions[$route] ?? null;

        $category = (string) (
            $config['assignments'][$route]
            ?? 'uncategorized'
        );

        if (!isset($config['categories'][$category])) {
            $category = 'uncategorized';
        }

                $title = is_array($definition)
            ? trim((string) ($definition['title'] ?? ''))
            : ucwords(str_replace(['-', '_'], ' ', $folderName));

        if ($title === '') {
            $title = ucwords(
                str_replace(['-', '_'], ' ', $folderName)
            );
        }

        $defaultDescription = is_array($definition)
            ? trim((string) ($definition['description'] ?? ''))
            : '';

        if ($defaultDescription === '') {
            $defaultDescription
                = $title . 'に関する設定や情報を管理します。';
        }

        $description = trim(
            (string) (
                $config['descriptions'][$route]
                ?? $defaultDescription
            )
        );

        if ($description === '') {
            $description
                = $title . 'に関する設定や情報を管理します。';
        }

        $pages[$route] = [
            'route' => $route,
            'title' => $title,
            'description' => $description,
            'category' => $category,
        ];
    }

    uasort(
        $pages,
        static fn(array $a, array $b): int =>
            strnatcasecmp($a['title'], $b['title'])
    );

    return $pages;
}

function admin_menu_group_pages(array $pages, array $categories): array
{
    $grouped = [];

    foreach ($categories as $key => $category) {
        $grouped[$key] = [];
    }

    foreach ($pages as $page) {
        $category = $page['category'] ?? 'uncategorized';

        if (!isset($grouped[$category])) {
            $category = 'uncategorized';
        }

        $grouped[$category][] = $page;
    }

    return $grouped;
}

function admin_menu_normalize_category_order(array $categories): array
{
    uasort(
        $categories,
        static fn(array $left, array $right): int =>
            ((int) ($left['order'] ?? 999))
            <=>
            ((int) ($right['order'] ?? 999))
    );

    $order = 10;

    foreach ($categories as $key => $category) {
        $categories[$key]['order'] = $order;
        $order += 10;
    }

    return $categories;
}

function admin_menu_move_category(
    array $categories,
    string $categoryKey,
    string $direction
): array {
    $categories = admin_menu_normalize_category_order($categories);
    $keys = array_keys($categories);
    $currentIndex = array_search($categoryKey, $keys, true);

    if ($currentIndex === false) {
        return $categories;
    }

    $targetIndex = $direction === 'up'
        ? $currentIndex - 1
        : $currentIndex + 1;

    if ($targetIndex < 0 || $targetIndex >= count($keys)) {
        return $categories;
    }

    $currentKey = $keys[$currentIndex];
    $targetKey = $keys[$targetIndex];

    $keys[$currentIndex] = $targetKey;
    $keys[$targetIndex] = $currentKey;

    $sorted = [];
    $order = 10;

    foreach ($keys as $key) {
        $sorted[$key] = $categories[$key];
        $sorted[$key]['order'] = $order;
        $order += 10;
    }

    return $sorted;
}
