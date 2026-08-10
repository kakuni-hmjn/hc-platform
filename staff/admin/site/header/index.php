<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();
$roles = ['staff' => 'スタッフ以上', 'developer' => '開発者以上', 'admin' => '管理者以上', 'owner' => 'オーナーのみ'];

function staff_header_default_links(): array
{
    return [
        ['staff', 'スタッフページ', '/staff/', 'staff', true, 10],
        ['admin', '上位管理センター', '/staff/admin/', 'admin', true, 20],
        ['server_orders', 'ゲームサーバー契約', '/staff/rental-server/game-server/contracts/', 'admin', true, 30],
        ['plan_change_requests', '承認センター', '/staff/approvals/', 'admin', true, 40],
        ['game_plans', 'ゲームサーバープラン', '/staff/rental-server/game-server/plans/', 'admin', true, 50],
        ['services', '事業・サービス掲載', '/staff/admin/site/services/', 'admin', true, 60],
        ['news', 'ニュース管理', '/staff/admin/site/news/', 'admin', true, 70],
        ['ptero', 'Pterodactyl管理', '/staff/rental-server/game-server/pterodactyl/', 'admin', true, 80],
        ['dev', '開発管理', '/staff/development/', 'developer', true, 90],
        ['header_settings', 'ヘッダー表示設定', '/staff/admin/site/header/', 'admin', true, 100],
    ];
}

function staff_header_ensure(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS header_operation_links (
        id SERIAL PRIMARY KEY, item_key VARCHAR(80) NOT NULL UNIQUE, label VARCHAR(120) NOT NULL,
        url VARCHAR(255) NOT NULL, required_role VARCHAR(40) NOT NULL DEFAULT 'staff', is_visible BOOLEAN NOT NULL DEFAULT true,
        sort_order INTEGER NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
    )");
    $insert = $pdo->prepare('INSERT INTO header_operation_links (item_key, label, url, required_role, is_visible, sort_order) VALUES (:key, :label, :url, :role, :visible, :sort) ON CONFLICT (item_key) DO NOTHING');
    foreach (staff_header_default_links() as [$key, $label, $url, $role, $visible, $sort]) {
        $insert->execute(['key' => $key, 'label' => $label, 'url' => $url, 'role' => $role, 'visible' => $visible ? 'true' : 'false', 'sort' => $sort]);
    }
    $routeUpdates = [
        'admin' => '/staff/admin/', 'server_orders' => '/staff/rental-server/game-server/contracts/',
        'plan_change_requests' => '/staff/approvals/', 'game_plans' => '/staff/rental-server/game-server/plans/',
        'services' => '/staff/admin/site/services/', 'news' => '/staff/admin/site/news/',
        'ptero' => '/staff/rental-server/game-server/pterodactyl/', 'dev' => '/staff/development/',
        'header_settings' => '/staff/admin/site/header/',
    ];
    $update = $pdo->prepare('UPDATE header_operation_links SET url = :url, updated_at = NOW() WHERE item_key = :key AND url LIKE :legacy');
    foreach ($routeUpdates as $key => $url) {
        $update->execute(['key' => $key, 'url' => $url, 'legacy' => '/admin/%']);
    }
}

function staff_header_reset(PDO $pdo): void
{
    $pdo->exec('DELETE FROM header_operation_links');
    $insert = $pdo->prepare('INSERT INTO header_operation_links (item_key, label, url, required_role, is_visible, sort_order, created_at, updated_at) VALUES (:key, :label, :url, :role, :visible, :sort, NOW(), NOW())');
    foreach (staff_header_default_links() as [$key, $label, $url, $role, $visible, $sort]) {
        $insert->execute(['key' => $key, 'label' => $label, 'url' => $url, 'role' => $role, 'visible' => $visible ? 'true' : 'false', 'sort' => $sort]);
    }
}

staff_header_ensure($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) { throw new RuntimeException('操作の有効期限が切れました。'); }
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'save') {
            $links = $_POST['links'] ?? [];
            if (!is_array($links)) { throw new RuntimeException('送信内容が不正です。'); }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE header_operation_links SET label = :label, url = :url, required_role = :role, is_visible = :visible, sort_order = :sort, updated_at = NOW() WHERE id = :id');
            foreach ($links as $id => $link) {
                $id = (int) $id; $label = trim((string) ($link['label'] ?? '')); $url = staff_administration_internal_url($link['url'] ?? null); $role = (string) ($link['required_role'] ?? 'staff');
                if ($id <= 0 || $label === '' || $url === null || !isset($roles[$role])) { throw new RuntimeException('入力されていない項目があります。'); }
                $keyStmt = $pdo->prepare('SELECT item_key FROM header_operation_links WHERE id = :id'); $keyStmt->execute(['id' => $id]); $key = (string) ($keyStmt->fetchColumn() ?: '');
                $visible = isset($link['is_visible']); if ($key === 'admin') { $visible = true; $role = 'admin'; }
                $stmt->execute(['id' => $id, 'label' => $label, 'url' => $url, 'role' => $role, 'visible' => $visible ? 'true' : 'false', 'sort' => (int) ($link['sort_order'] ?? 0)]);
            }
            $pdo->commit(); staff_administration_flash('success', 'ヘッダー設定を保存しました。');
        } elseif ($action === 'add') {
            $label = trim((string) ($_POST['label'] ?? '')); $url = staff_administration_internal_url($_POST['url'] ?? null); $role = (string) ($_POST['required_role'] ?? 'admin');
            if ($label === '' || $url === null || !isset($roles[$role])) { throw new RuntimeException('追加項目を正しく入力してください。'); }
            $stmt = $pdo->prepare("INSERT INTO header_operation_links (item_key, label, url, required_role, is_visible, sort_order, created_at, updated_at) VALUES (:key, :label, :url, :role, :visible, :sort, NOW(), NOW())");
            $stmt->execute(['key' => 'custom_' . bin2hex(random_bytes(5)), 'label' => $label, 'url' => $url, 'role' => $role, 'visible' => isset($_POST['is_visible']) ? 'true' : 'false', 'sort' => (int) ($_POST['sort_order'] ?? 999)]);
            staff_administration_flash('success', 'ヘッダー項目を追加しました。');
        } elseif ($action === 'reset') {
            $pdo->beginTransaction(); staff_header_reset($pdo); $pdo->commit(); staff_administration_flash('success', 'スタッフ版の初期設定へ戻しました。');
        } else { throw new RuntimeException('不明な操作です。'); }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        staff_administration_flash('error', $exception->getMessage());
    }
    staff_administration_redirect('/staff/admin/site/header/');
}

$links = $pdo->query('SELECT * FROM header_operation_links ORDER BY sort_order, id')->fetchAll() ?: [];
$flash = staff_administration_take_flash();

staff_layout_start([
    'title' => 'ヘッダー設定', 'heading' => 'ヘッダー設定', 'eyebrow' => 'WEBSITE / HEADER',
    'description' => '公開サイトのOperationメニューに表示する項目、権限と並び順を管理します。',
]);
?>
<div class="ops-page admin-native-page">
    <?php if ($flash): ?><div class="ops-alert <?= $flash['type'] === 'success' ? 'ops-alert--success' : '' ?>"><?= staff_ui_escape($flash['message']) ?></div><?php endif; ?>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>現在のヘッダーメニュー</h3><p>上位管理センターは安全のため常に表示されます。</p></div></header><div class="ops-panel__body"><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="save"><div class="admin-native-link-list">
        <?php foreach ($links as $link): $required = $link['item_key'] === 'admin'; ?><article class="admin-native-link-row"><div class="admin-native-link-row__top"><strong><?= staff_ui_escape($link['item_key']) ?></strong><label class="ops-check"><input type="checkbox" name="links[<?= (int) $link['id'] ?>][is_visible]" value="1" <?= !empty($link['is_visible']) ? 'checked' : '' ?> <?= $required ? 'disabled' : '' ?>>表示</label></div><div class="ops-form-grid"><label><span class="ops-label">表示名</span><input class="ops-input" name="links[<?= (int) $link['id'] ?>][label]" value="<?= staff_ui_escape($link['label']) ?>" required></label><label><span class="ops-label">内部URL</span><input class="ops-input" name="links[<?= (int) $link['id'] ?>][url]" value="<?= staff_ui_escape($link['url']) ?>" required></label><label><span class="ops-label">必要権限</span><select class="ops-select" name="links[<?= (int) $link['id'] ?>][required_role]" <?= $required ? 'disabled' : '' ?>><?php foreach ($roles as $value => $label): ?><option value="<?= $value ?>" <?= $link['required_role'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><?php if ($required): ?><input type="hidden" name="links[<?= (int) $link['id'] ?>][required_role]" value="admin"><?php endif; ?></label><label><span class="ops-label">表示順</span><input class="ops-input" type="number" name="links[<?= (int) $link['id'] ?>][sort_order]" value="<?= (int) $link['sort_order'] ?>"></label></div></article><?php endforeach; ?>
    </div><div class="ops-form-actions"><button class="ops-button" type="submit">すべて保存</button></div></form></div></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>項目を追加</h3><p>サイト内のURLだけを登録できます。</p></div></header><div class="ops-panel__body"><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="add"><div class="ops-form-grid"><label><span class="ops-label">表示名</span><input class="ops-input" name="label" required></label><label><span class="ops-label">内部URL</span><input class="ops-input" name="url" placeholder="/staff/example/" required></label><label><span class="ops-label">必要権限</span><select class="ops-select" name="required_role"><?php foreach ($roles as $value => $label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach; ?></select></label><label><span class="ops-label">表示順</span><input class="ops-input" type="number" name="sort_order" value="999"></label></div><label class="ops-check"><input type="checkbox" name="is_visible" value="1" checked>追加後すぐ表示</label><div class="ops-form-actions"><button class="ops-button" type="submit">項目を追加</button></div></form></div></section>
    <form method="post" class="ops-form-actions"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="reset"><button class="ops-button ops-button--secondary" type="submit">スタッフ版の初期設定へ戻す</button></form>
</div>
<?php staff_layout_end();
