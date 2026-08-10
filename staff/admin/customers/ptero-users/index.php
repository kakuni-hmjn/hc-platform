<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 4) . '/lib/ptero_users.php';
require_once dirname(__DIR__, 4) . '/lib/pterodactyl.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();
hc_ptero_user_links_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) { throw new RuntimeException('操作の有効期限が切れました。'); }
        $action = (string) ($_POST['action'] ?? 'link');
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) { throw new RuntimeException('HCアカウントが不正です。'); }
        if ($action === 'link') {
            $pteroUserId = (int) ($_POST['ptero_user_id'] ?? 0);
            $username = trim((string) ($_POST['ptero_username'] ?? ''));
            $email = trim((string) ($_POST['ptero_email'] ?? ''));
            $externalId = trim((string) ($_POST['ptero_external_id'] ?? ''));
            $uuid = trim((string) ($_POST['ptero_uuid'] ?? ''));
            if ($pteroUserId <= 0 || $username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $externalId === '') { throw new RuntimeException('連携情報を正しく入力してください。'); }
            $stmt = $pdo->prepare("INSERT INTO ptero_user_links (user_id, ptero_user_id, ptero_external_id, ptero_uuid, username, email, status, created_at, updated_at, last_synced_at) VALUES (:user_id, :ptero_user_id, :external_id, :uuid, :username, :email, 'active', NOW(), NOW(), NOW()) ON CONFLICT (user_id) DO UPDATE SET ptero_user_id = EXCLUDED.ptero_user_id, ptero_external_id = EXCLUDED.ptero_external_id, ptero_uuid = EXCLUDED.ptero_uuid, username = EXCLUDED.username, email = EXCLUDED.email, status = 'active', updated_at = NOW(), last_synced_at = NOW()");
            $stmt->execute(['user_id' => $userId, 'ptero_user_id' => $pteroUserId, 'external_id' => $externalId, 'uuid' => $uuid !== '' ? $uuid : null, 'username' => $username, 'email' => $email]);
            staff_administration_flash('success', 'Pterodactylアカウント連携を保存しました。');
        } elseif ($action === 'unlink') {
            $stmt = $pdo->prepare("UPDATE ptero_user_links SET status = 'unlinked', initial_password = NULL, updated_at = NOW() WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);
            staff_administration_flash('success', 'アカウント連携を解除しました。');
        } elseif ($action === 'reset_password') {
            $stmt = $pdo->prepare("SELECT pul.*, u.username AS hc_username FROM ptero_user_links pul JOIN users u ON u.id = pul.user_id WHERE pul.user_id = :user_id AND pul.status = 'active' LIMIT 1");
            $stmt->execute(['user_id' => $userId]); $link = $stmt->fetch();
            if (!$link) { throw new RuntimeException('有効なPterodactyl連携が見つかりません。'); }
            $password = hc_ptero_random_password();
            if (hc_ptero_enabled() && !hc_ptero_mock()) {
                hc_ptero_request('PATCH', '/api/application/users/' . rawurlencode((string) $link['ptero_user_id']), [
                    'external_id' => (string) $link['ptero_external_id'], 'email' => (string) $link['email'], 'username' => (string) $link['username'],
                    'first_name' => (string) $link['hc_username'], 'last_name' => 'HC', 'password' => $password, 'root_admin' => false, 'language' => 'en',
                ]);
            }
            $update = $pdo->prepare('UPDATE ptero_user_links SET initial_password = :password, initial_password_created_at = NOW(), initial_password_viewed_at = NULL, password_setup_completed_at = NULL, updated_at = NOW() WHERE user_id = :user_id');
            $update->execute(['user_id' => $userId, 'password' => $password]);
            staff_administration_flash('success', '初回パスワードを再発行しました。ユーザー側のアカウント画面に表示されます。');
        } else { throw new RuntimeException('不明な操作です。'); }
    } catch (Throwable $exception) { staff_administration_flash('error', $exception->getMessage()); }
    staff_administration_redirect('/staff/admin/customers/ptero-users/');
}

$query = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT u.id AS user_id, u.username AS hc_username, u.email AS hc_email, u.status AS hc_status, u.created_at AS user_created_at,
               pul.ptero_user_id, pul.ptero_external_id, pul.ptero_uuid, pul.username AS ptero_username, pul.email AS ptero_email,
               pul.status AS link_status, pul.initial_password, pul.initial_password_created_at, pul.password_setup_completed_at, pul.last_synced_at,
               COUNT(ps.id) AS server_count
        FROM users u LEFT JOIN ptero_user_links pul ON pul.user_id = u.id
        LEFT JOIN game_server_orders gso ON gso.user_id = u.id LEFT JOIN ptero_servers ps ON ps.order_id = gso.id AND ps.deleted_at IS NULL";
$params = [];
if ($query !== '') { $sql .= ' WHERE u.username ILIKE :q OR u.email ILIKE :q OR pul.username ILIKE :q OR pul.email ILIKE :q'; $params['q'] = '%' . $query . '%'; }
$sql .= ' GROUP BY u.id, pul.id ORDER BY u.id';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $users = $stmt->fetchAll() ?: [];
$flash = staff_administration_take_flash();

staff_layout_start([
    'title' => 'Pterodactylアカウント連携', 'heading' => 'Pterodactylアカウント連携', 'eyebrow' => 'CUSTOMERS / PTERODACTYL',
    'description' => 'HCアカウントとPterodactylユーザーの紐付け、解除、初回パスワード再発行を管理します。',
]);
?>
<div class="ops-page admin-native-page">
    <?php if ($flash): ?><div class="ops-alert <?= $flash['type'] === 'success' ? 'ops-alert--success' : '' ?>"><?= staff_ui_escape($flash['message']) ?></div><?php endif; ?>
    <form class="ops-toolbar" method="get"><label class="ops-toolbar__field"><span class="ops-label">アカウント検索</span><input class="ops-input" type="search" name="q" value="<?= staff_ui_escape($query) ?>" placeholder="ユーザー名・メール"></label><button class="ops-button" type="submit">検索</button><?php if ($query !== ''): ?><a class="ops-button ops-button--secondary" href="/staff/admin/customers/ptero-users/">クリア</a><?php endif; ?></form>
    <div class="admin-native-account-grid">
        <?php foreach ($users as $user): $linked = !empty($user['ptero_user_id']) && $user['link_status'] === 'active'; $external = (string) ($user['ptero_external_id'] ?: 'hc_user_' . $user['user_id']); $defaultName = 'hc' . $user['user_id'] . '_' . preg_replace('/[^a-z0-9_]/', '_', strtolower((string) $user['hc_username'])); ?>
            <article class="ops-panel admin-native-account-card"><header class="ops-panel__header"><div><h3><?= staff_ui_escape($user['hc_username']) ?></h3><p>#<?= (int) $user['user_id'] ?> / <?= staff_ui_escape($user['hc_email']) ?></p></div><span class="ops-status ops-status--<?= $linked ? 'active' : 'inactive' ?>"><?= $linked ? '連携済み' : '未連携' ?></span></header><div class="ops-panel__body"><dl class="ops-kv"><div><dt>Pterodactylユーザー</dt><dd><?= $linked ? '#' . (int) $user['ptero_user_id'] . ' / ' . staff_ui_escape($user['ptero_username']) : '-' ?></dd></div><div><dt>サーバー数</dt><dd><?= (int) $user['server_count'] ?>件</dd></div><div><dt>External ID</dt><dd><?= staff_ui_escape($external) ?></dd></div><div><dt>最終同期</dt><dd><?= staff_ui_escape(staff_administration_datetime($user['last_synced_at'] ?? null)) ?></dd></div></dl>
                <form method="post" class="admin-native-form"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>"><input type="hidden" name="action" value="link"><div class="ops-form-grid"><label><span class="ops-label">Pterodactyl User ID</span><input class="ops-input" type="number" min="1" name="ptero_user_id" value="<?= staff_ui_escape($user['ptero_user_id'] ?? '') ?>" required></label><label><span class="ops-label">ユーザー名</span><input class="ops-input" name="ptero_username" value="<?= staff_ui_escape($user['ptero_username'] ?: $defaultName) ?>" required></label><label><span class="ops-label">メール</span><input class="ops-input" type="email" name="ptero_email" value="<?= staff_ui_escape($user['ptero_email'] ?: $user['hc_email']) ?>" required></label><label><span class="ops-label">External ID</span><input class="ops-input" name="ptero_external_id" value="<?= staff_ui_escape($external) ?>" required></label><label><span class="ops-label">UUID（任意）</span><input class="ops-input" name="ptero_uuid" value="<?= staff_ui_escape($user['ptero_uuid'] ?? '') ?>"></label></div><div class="ops-form-actions"><button class="ops-button" type="submit">連携を保存</button><?php if ($linked): ?><button class="ops-button ops-button--secondary" type="submit" name="action" value="reset_password">初回PW再発行</button><button class="ops-button ops-button--danger" type="submit" name="action" value="unlink">連携解除</button><?php endif; ?></div></form>
            </div></article>
        <?php endforeach; ?>
    </div>
    <?php if ($users === []): ?><div class="ops-empty">条件に一致するHCアカウントはありません。</div><?php endif; ?>
</div>
<?php staff_layout_end();
