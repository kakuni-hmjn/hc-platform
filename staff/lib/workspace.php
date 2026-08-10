<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function staff_workspace_widget_catalog(): array
{
    return [
        'summary' => ['label' => '業務サマリー', 'description' => 'タスク・期限超過・未読通知の件数', 'icon' => 'monitoring'],
        'systems' => ['label' => '業務システム', 'description' => '物品管理などの業務センター', 'icon' => 'apps'],
        'tasks' => ['label' => '自分のタスク', 'description' => '優先度と期限順の担当業務', 'icon' => 'task_alt'],
        'announcements' => ['label' => '社内連絡', 'description' => '最新のお知らせと確認事項', 'icon' => 'campaign'],
        'categories' => ['label' => '担当業務', 'description' => '担当カテゴリと全体管理への入口', 'icon' => 'workspaces'],
        'context' => ['label' => '所属・権限', 'description' => 'ロール・部署・権限の集計', 'icon' => 'badge'],
        'custom_links' => ['label' => 'カスタムリンク', 'description' => '自分で追加したリンクウィジェット', 'icon' => 'add_link'],
    ];
}

function staff_workspace_icon_catalog(): array
{
    return [
        'link' => 'リンク',
        'language' => 'Webサイト',
        'home' => 'ホーム',
        'dashboard' => 'ダッシュボード',
        'apps' => 'アプリ',
        'support_agent' => 'お問い合わせ',
        'chat' => 'チャット',
        'mail' => 'メール',
        'group' => '顧客・チーム',
        'person' => 'アカウント',
        'task_alt' => 'タスク',
        'campaign' => 'お知らせ',
        'calendar_month' => 'カレンダー',
        'schedule' => 'スケジュール',
        'description' => 'ドキュメント',
        'folder' => 'フォルダー',
        'inventory_2' => '物品・在庫',
        'dns' => 'サーバー',
        'cloud' => 'クラウド',
        'storage' => 'ストレージ',
        'terminal' => 'ターミナル',
        'code' => '開発',
        'hub' => '連携',
        'monitoring' => 'モニタリング',
        'analytics' => '分析',
        'payments' => '決済',
        'receipt_long' => '請求',
        'security' => 'セキュリティ',
        'settings' => '設定',
        'public' => '公開ページ',
        'bolt' => 'クイック操作',
        'favorite' => 'お気に入り',
        'open_in_new' => '外部リンク',
    ];
}

function staff_workspace_background_presets(): array
{
    return [
        'aurora' => [
            'label' => 'オーロラ',
            'css' => 'radial-gradient(circle at 12% 18%, #bfdbfe 0, transparent 38%), radial-gradient(circle at 88% 8%, #a7f3d0 0, transparent 34%), linear-gradient(135deg, #f8fafc, #eef2ff)',
        ],
        'sunset' => [
            'label' => 'サンセット',
            'css' => 'radial-gradient(circle at 80% 15%, #fed7aa 0, transparent 38%), radial-gradient(circle at 15% 85%, #fbcfe8 0, transparent 34%), linear-gradient(135deg, #fff7ed, #fdf2f8)',
        ],
        'ocean' => [
            'label' => 'オーシャン',
            'css' => 'radial-gradient(circle at 82% 18%, #67e8f9 0, transparent 34%), radial-gradient(circle at 18% 75%, #93c5fd 0, transparent 38%), linear-gradient(145deg, #ecfeff, #eff6ff)',
        ],
        'forest' => [
            'label' => 'フォレスト',
            'css' => 'radial-gradient(circle at 18% 12%, #86efac 0, transparent 34%), radial-gradient(circle at 82% 78%, #a7f3d0 0, transparent 38%), linear-gradient(145deg, #f0fdf4, #ecfdf5)',
        ],
        'night' => [
            'label' => 'ナイト',
            'css' => 'radial-gradient(circle at 18% 16%, #4338ca 0, transparent 34%), radial-gradient(circle at 82% 72%, #0f766e 0, transparent 40%), linear-gradient(145deg, #0f172a, #172554)',
        ],
        'mono' => [
            'label' => 'モノクローム',
            'css' => 'radial-gradient(circle at 85% 12%, #d1d5db 0, transparent 36%), linear-gradient(145deg, #f8fafc, #e5e7eb)',
        ],
    ];
}

function staff_workspace_defaults(): array
{
    return [
        'accent_color' => '#2563eb',
        'background_mode' => 'plain',
        'background_preset' => 'aurora',
        'background_image_path' => null,
        'background_position' => 'center',
        'background_scale' => 100,
        'background_overlay' => 72,
        'panel_style' => 'solid',
        'dashboard_layout' => 'balanced',
        'compact_mode' => false,
        'custom_greeting' => '',
        'widgets' => array_keys(staff_workspace_widget_catalog()),
        'custom_links' => [],
        'profile_bio' => '',
        'avatar_image_path' => null,
    ];
}

function staff_workspace_custom_link_url(mixed $value): ?string
{
    $url = mb_substr(trim((string) ($value ?? '')), 0, 500, 'UTF-8');
    if ($url === '') {
        return null;
    }
    if (preg_match('#^/(?!/)#', $url) === 1) {
        return $url;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : null;
}

function staff_workspace_normalize_custom_links(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }

    $icons = staff_workspace_icon_catalog();
    $links = [];
    $usedIds = [];
    foreach (array_slice((array) $value, 0, 12) as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = mb_substr(trim((string) ($item['title'] ?? '')), 0, 60, 'UTF-8');
        $url = staff_workspace_custom_link_url($item['url'] ?? null);
        if ($title === '' || $url === null) {
            continue;
        }
        $id = strtolower(trim((string) ($item['id'] ?? '')));
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $id) !== 1 || isset($usedIds[$id])) {
            $id = 'link-' . ($index + 1);
            while (isset($usedIds[$id])) {
                $id .= '-x';
            }
        }
        $usedIds[$id] = true;
        $icon = trim((string) ($item['icon'] ?? 'link'));
        $links[] = [
            'id' => $id,
            'title' => $title,
            'description' => mb_substr(trim((string) ($item['description'] ?? '')), 0, 140, 'UTF-8'),
            'url' => $url,
            'icon' => isset($icons[$icon]) ? $icon : 'link',
            'open_new_tab' => filter_var($item['open_new_tab'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }
    return $links;
}

function staff_workspace_custom_links_from_input(mixed $value): array
{
    foreach (array_slice((array) $value, 0, 12) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string) ($item['title'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));
        if ($title === '' && $url === '') {
            continue;
        }
        if ($title === '' || staff_workspace_custom_link_url($url) === null) {
            throw new RuntimeException('カスタムリンクのタイトルとURLを確認してください。URLは / から始まるサイト内パス、または http・https が使えます。');
        }
    }
    return staff_workspace_normalize_custom_links($value);
}

function staff_workspace_asset_url(mixed $value): ?string
{
    $path = trim((string) ($value ?? ''));
    if ($path === '') {
        return null;
    }
    if (preg_match('#^/storage/staff-workspace-backgrounds/[a-zA-Z0-9._-]+\.(?:jpe?g|png|webp)$#i', $path) !== 1) {
        return null;
    }
    return $path;
}

function staff_workspace_normalize(array $row): array
{
    $preferences = array_replace(staff_workspace_defaults(), $row);
    $preferences['accent_color'] = preg_match('/^#[0-9a-f]{6}$/i', (string) $preferences['accent_color']) === 1
        ? strtolower((string) $preferences['accent_color']) : '#2563eb';
    $preferences['background_mode'] = in_array($preferences['background_mode'], ['plain', 'preset', 'image'], true)
        ? $preferences['background_mode'] : 'plain';
    $preferences['background_preset'] = array_key_exists((string) $preferences['background_preset'], staff_workspace_background_presets())
        ? (string) $preferences['background_preset'] : 'aurora';
    $preferences['background_image_path'] = staff_workspace_asset_url($preferences['background_image_path']);
    $preferences['avatar_image_path'] = staff_workspace_asset_url($preferences['avatar_image_path']);
    $preferences['background_position'] = in_array($preferences['background_position'], ['center', 'top', 'bottom', 'left', 'right'], true)
        ? $preferences['background_position'] : 'center';
    $preferences['background_scale'] = max(100, min(200, (int) $preferences['background_scale']));
    $preferences['background_overlay'] = max(0, min(90, (int) $preferences['background_overlay']));
    $preferences['panel_style'] = in_array($preferences['panel_style'], ['solid', 'glass'], true)
        ? $preferences['panel_style'] : 'solid';
    $preferences['dashboard_layout'] = in_array($preferences['dashboard_layout'], ['balanced', 'wide', 'stacked'], true)
        ? $preferences['dashboard_layout'] : 'balanced';
    $preferences['compact_mode'] = filter_var($preferences['compact_mode'], FILTER_VALIDATE_BOOLEAN);
    $preferences['custom_greeting'] = mb_substr(trim((string) $preferences['custom_greeting']), 0, 160, 'UTF-8');
    $preferences['profile_bio'] = mb_substr(trim((string) $preferences['profile_bio']), 0, 500, 'UTF-8');
    $preferences['custom_links'] = staff_workspace_normalize_custom_links($preferences['custom_links']);

    $widgets = $preferences['widgets'];
    if (is_string($widgets)) {
        $decoded = json_decode($widgets, true);
        $widgets = is_array($decoded) ? $decoded : [];
    }
    $catalog = staff_workspace_widget_catalog();
    $normalizedWidgets = [];
    foreach ((array) $widgets as $widget) {
        $widget = (string) $widget;
        if (isset($catalog[$widget]) && !in_array($widget, $normalizedWidgets, true)) {
            $normalizedWidgets[] = $widget;
        }
    }
    $preferences['widgets'] = $normalizedWidgets;
    return $preferences;
}

function staff_workspace_preferences_load(int $staffUserId): array
{
    if ($staffUserId <= 0) {
        return staff_workspace_defaults();
    }
    try {
        $statement = staff_db()->prepare('SELECT * FROM staff_workspace_preferences WHERE staff_user_id = :staff_user_id LIMIT 1');
        $statement->execute(['staff_user_id' => $staffUserId]);
        $row = $statement->fetch();
        return staff_workspace_normalize(is_array($row) ? $row : []);
    } catch (Throwable $exception) {
        return staff_workspace_defaults();
    }
}

function staff_workspace_ensure_schema(): void
{
    staff_db()->exec(
        <<<'SQL'
        CREATE TABLE IF NOT EXISTS staff_workspace_preferences (
            staff_user_id BIGINT PRIMARY KEY REFERENCES staff_users(id) ON DELETE CASCADE,
            accent_color VARCHAR(7) NOT NULL DEFAULT '#2563eb',
            background_mode VARCHAR(20) NOT NULL DEFAULT 'plain',
            background_preset VARCHAR(40) NOT NULL DEFAULT 'aurora',
            background_image_path TEXT,
            background_position VARCHAR(20) NOT NULL DEFAULT 'center',
            background_scale SMALLINT NOT NULL DEFAULT 100,
            background_overlay SMALLINT NOT NULL DEFAULT 72,
            panel_style VARCHAR(20) NOT NULL DEFAULT 'solid',
            dashboard_layout VARCHAR(20) NOT NULL DEFAULT 'balanced',
            compact_mode BOOLEAN NOT NULL DEFAULT FALSE,
            custom_greeting VARCHAR(160),
            widgets JSONB NOT NULL DEFAULT
                '["summary", "systems", "tasks", "announcements", "categories", "context", "custom_links"]'::JSONB,
            custom_links JSONB NOT NULL DEFAULT '[]'::JSONB,
            profile_bio VARCHAR(500),
            avatar_image_path TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT staff_workspace_accent_check CHECK (accent_color ~ '^#[0-9A-Fa-f]{6}$'),
            CONSTRAINT staff_workspace_background_mode_check CHECK (background_mode IN ('plain', 'preset', 'image')),
            CONSTRAINT staff_workspace_background_position_check CHECK (background_position IN ('center', 'top', 'bottom', 'left', 'right')),
            CONSTRAINT staff_workspace_background_scale_check CHECK (background_scale BETWEEN 100 AND 200),
            CONSTRAINT staff_workspace_overlay_check CHECK (background_overlay BETWEEN 0 AND 90),
            CONSTRAINT staff_workspace_panel_style_check CHECK (panel_style IN ('solid', 'glass')),
            CONSTRAINT staff_workspace_layout_check CHECK (dashboard_layout IN ('balanced', 'wide', 'stacked'))
        );
        ALTER TABLE staff_workspace_preferences
            ADD COLUMN IF NOT EXISTS background_scale SMALLINT NOT NULL DEFAULT 100;
        ALTER TABLE staff_workspace_preferences
            ADD COLUMN IF NOT EXISTS custom_links JSONB NOT NULL DEFAULT '[]'::JSONB;
        CREATE INDEX IF NOT EXISTS idx_staff_workspace_preferences_updated
            ON staff_workspace_preferences(updated_at DESC);
        SQL
    );
}

function staff_workspace_preferences_from_input(array $input, array $current): array
{
    $preferences = $current;
    $preferences['accent_color'] = trim((string) ($input['accent_color'] ?? '#2563eb'));
    $preferences['background_mode'] = trim((string) ($input['background_mode'] ?? 'plain'));
    $preferences['background_preset'] = trim((string) ($input['background_preset'] ?? 'aurora'));
    $preferences['background_position'] = trim((string) ($input['background_position'] ?? 'center'));
    $preferences['background_scale'] = (int) ($input['background_scale'] ?? 100);
    $preferences['background_overlay'] = (int) ($input['background_overlay'] ?? 72);
    $preferences['panel_style'] = trim((string) ($input['panel_style'] ?? 'solid'));
    $preferences['dashboard_layout'] = trim((string) ($input['dashboard_layout'] ?? 'balanced'));
    $preferences['compact_mode'] = isset($input['compact_mode']);
    $preferences['custom_greeting'] = trim((string) ($input['custom_greeting'] ?? ''));
    $preferences['widgets'] = (array) ($input['widgets'] ?? []);
    $preferences['custom_links'] = staff_workspace_custom_links_from_input($input['custom_links'] ?? []);
    return staff_workspace_normalize($preferences);
}

function staff_workspace_preferences_save(int $staffUserId, array $preferences): void
{
    staff_workspace_ensure_schema();
    $preferences = staff_workspace_normalize($preferences);
    $statement = staff_db()->prepare(
        'INSERT INTO staff_workspace_preferences (
            staff_user_id, accent_color, background_mode, background_preset,
            background_image_path, background_position, background_scale, background_overlay,
            panel_style, dashboard_layout, compact_mode, custom_greeting,
            widgets, custom_links, profile_bio, avatar_image_path, created_at, updated_at
         ) VALUES (
            :staff_user_id, :accent_color, :background_mode, :background_preset,
            :background_image_path, :background_position, :background_scale, :background_overlay,
            :panel_style, :dashboard_layout, :compact_mode, :custom_greeting,
            CAST(:widgets AS JSONB), CAST(:custom_links AS JSONB), :profile_bio, :avatar_image_path,
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
         )
         ON CONFLICT (staff_user_id) DO UPDATE SET
            accent_color = EXCLUDED.accent_color,
            background_mode = EXCLUDED.background_mode,
            background_preset = EXCLUDED.background_preset,
            background_image_path = EXCLUDED.background_image_path,
            background_position = EXCLUDED.background_position,
            background_scale = EXCLUDED.background_scale,
            background_overlay = EXCLUDED.background_overlay,
            panel_style = EXCLUDED.panel_style,
            dashboard_layout = EXCLUDED.dashboard_layout,
            compact_mode = EXCLUDED.compact_mode,
            custom_greeting = EXCLUDED.custom_greeting,
            widgets = EXCLUDED.widgets,
            custom_links = EXCLUDED.custom_links,
            profile_bio = EXCLUDED.profile_bio,
            avatar_image_path = EXCLUDED.avatar_image_path,
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'staff_user_id' => $staffUserId,
        'accent_color' => $preferences['accent_color'],
        'background_mode' => $preferences['background_mode'],
        'background_preset' => $preferences['background_preset'],
        'background_image_path' => $preferences['background_image_path'],
        'background_position' => $preferences['background_position'],
        'background_scale' => $preferences['background_scale'],
        'background_overlay' => $preferences['background_overlay'],
        'panel_style' => $preferences['panel_style'],
        'dashboard_layout' => $preferences['dashboard_layout'],
        // PDO PostgreSQL converts an untyped PHP false value to an empty
        // string when parameters are supplied through execute(). PostgreSQL
        // cannot parse that value as BOOLEAN, so always send a valid boolean
        // literal for both checked and unchecked states.
        'compact_mode' => $preferences['compact_mode'] ? 'true' : 'false',
        'custom_greeting' => $preferences['custom_greeting'] !== '' ? $preferences['custom_greeting'] : null,
        'widgets' => json_encode($preferences['widgets'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'custom_links' => json_encode($preferences['custom_links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'profile_bio' => $preferences['profile_bio'] !== '' ? $preferences['profile_bio'] : null,
        'avatar_image_path' => $preferences['avatar_image_path'],
    ]);
}

function staff_workspace_store_image(array $file, int $staffUserId, string $kind): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || $staffUserId <= 0) {
        throw new RuntimeException('画像を受け取れませんでした。');
    }
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $maxBytes = $kind === 'avatar' ? 3 * 1024 * 1024 : 6 * 1024 * 1024;
    if ($size <= 0 || $size > $maxBytes || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException($kind === 'avatar' ? 'プロフィール画像は3MB以内にしてください。' : '背景画像は6MB以内にしてください。');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new RuntimeException('JPEG・PNG・WebP画像を選択してください。');
    }
    $imageSize = @getimagesize($temporaryPath);
    if (!is_array($imageSize) || ($imageSize[0] ?? 0) < 1 || ($imageSize[1] ?? 0) < 1) {
        throw new RuntimeException('画像ファイルを確認できませんでした。');
    }
    if ((int) $imageSize[0] > 8000 || (int) $imageSize[1] > 8000 || ((int) $imageSize[0] * (int) $imageSize[1]) > 40000000) {
        throw new RuntimeException('画像サイズが大きすぎます。8000px以内の画像を選択してください。');
    }
    $root = dirname(__DIR__, 2);
    $directory = $root . '/storage/staff-workspace-backgrounds';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('画像の保存先を準備できませんでした。');
    }
    $safeKind = $kind === 'avatar' ? 'avatar' : 'background';
    $filename = sprintf('staff-%d-%s-%s.%s', $staffUserId, $safeKind, bin2hex(random_bytes(12)), $extensions[$mime]);
    $destination = $directory . '/' . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('画像を保存できませんでした。');
    }
    @chmod($destination, 0644);
    return '/storage/staff-workspace-backgrounds/' . $filename;
}

function staff_workspace_inline_style(array $preferences): string
{
    $preferences = staff_workspace_normalize($preferences);
    $presets = staff_workspace_background_presets();
    $background = 'linear-gradient(145deg, #f8fafc, #eef2f7)';
    if ($preferences['background_mode'] === 'preset') {
        $background = $presets[$preferences['background_preset']]['css'];
    } elseif ($preferences['background_mode'] === 'image' && $preferences['background_image_path'] !== null) {
        $background = 'url("' . $preferences['background_image_path'] . '")';
    }
    return sprintf(
        '--workspace-accent:%s;--workspace-background:%s;--workspace-background-position:%s;--workspace-background-scale:%.2f;--workspace-overlay:%.2f;',
        $preferences['accent_color'],
        $background,
        $preferences['background_position'],
        $preferences['background_scale'] / 100,
        $preferences['background_overlay'] / 100
    );
}
