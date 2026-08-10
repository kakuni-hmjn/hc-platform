<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();
$statuses = ['published' => '公開', 'draft' => '下書き', 'hidden' => '非表示'];
$pdo->exec("CREATE TABLE IF NOT EXISTS user_direct_notifications (
    id SERIAL PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL, title VARCHAR(180) NOT NULL,
    body TEXT, link_url VARCHAR(255), status VARCHAR(40) NOT NULL DEFAULT 'published', priority INTEGER NOT NULL DEFAULT 0,
    published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) { throw new RuntimeException('操作の有効期限が切れました。'); }
        $action = (string) ($_POST['action'] ?? 'add');
        if ($action === 'add') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $url = staff_administration_internal_url($_POST['link_url'] ?? null);
            $status = (string) ($_POST['status'] ?? 'published');
            $priority = (int) ($_POST['priority'] ?? 0);
            $userCheck = $pdo->prepare('SELECT id FROM users WHERE id = :id');
            $userCheck->execute(['id' => $userId]);
            if ($userId <= 0 || $userCheck->fetchColumn() === false) { throw new RuntimeException('送信先を選択してください。'); }
            if ($title === '') { throw new RuntimeException('タイトルを入力してください。'); }
            if (!isset($statuses[$status])) { throw new RuntimeException('公開状態が不正です。'); }
            $stmt = $pdo->prepare('INSERT INTO user_direct_notifications (user_id, created_by, title, body, link_url, status, priority, published_at, created_at, updated_at) VALUES (:user_id, :created_by, :title, :body, :url, :status, :priority, NOW(), NOW(), NOW())');
            $stmt->execute(['user_id' => $userId, 'created_by' => $staffAccountId, 'title' => $title, 'body' => $body !== '' ? $body : null, 'url' => $url, 'status' => $status, 'priority' => $priority]);
            staff_administration_flash('success', '個別通知を作成しました。');
        } elseif ($action === 'status') {
            $id = (int) ($_POST['id'] ?? 0); $status = (string) ($_POST['status'] ?? 'hidden');
            if ($id <= 0 || !isset($statuses[$status])) { throw new RuntimeException('更新内容が不正です。'); }
            $stmt = $pdo->prepare('UPDATE user_direct_notifications SET status = :status, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $id, 'status' => $status]);
            staff_administration_flash('success', '通知の状態を更新しました。');
        } else { throw new RuntimeException('不明な操作です。'); }
    } catch (Throwable $exception) { staff_administration_flash('error', $exception->getMessage()); }
    staff_administration_redirect('/staff/admin/site/user-notifications/');
}

$users = $pdo->query('SELECT id, username, email FROM users ORDER BY username, id')->fetchAll() ?: [];
$items = $pdo->query('SELECT n.*, u.username, u.email FROM user_direct_notifications n JOIN users u ON u.id = n.user_id ORDER BY n.created_at DESC, n.id DESC LIMIT 100')->fetchAll() ?: [];
$flash = staff_administration_take_flash();

staff_layout_start([
    'title' => 'ユーザー通知', 'heading' => 'ユーザー通知', 'eyebrow' => 'WEBSITE / DIRECT NOTIFICATIONS',
    'description' => '特定のHCアカウントへ通知を作成し、送信履歴と公開状態を管理します。',
]);
?>
<div class="ops-page admin-native-page">
    <?php if ($flash): ?><div class="ops-alert <?= $flash['type'] === 'success' ? 'ops-alert--success' : '' ?>"><?= staff_ui_escape($flash['message']) ?></div><?php endif; ?>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>個別通知を作成</h3><p>通知は対象ユーザーの「あなた宛」に表示されます。</p></div><a class="ops-button ops-button--secondary" href="/dashboard/notifications/#personal">表示確認</a></header><div class="ops-panel__body"><form method="post" class="admin-native-form"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="add"><div class="ops-form-grid"><label><span class="ops-label">送信先</span><select class="ops-select" name="user_id" required><option value="">選択してください</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>">#<?= (int) $user['id'] ?> <?= staff_ui_escape($user['username']) ?> / <?= staff_ui_escape($user['email']) ?></option><?php endforeach; ?></select></label><label><span class="ops-label">タイトル</span><input class="ops-input" name="title" maxlength="180" required></label><label><span class="ops-label">内部リンク</span><input class="ops-input" name="link_url" placeholder="/dashboard/servers/"></label><label><span class="ops-label">状態</span><select class="ops-select" name="status"><?php foreach ($statuses as $value => $label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach; ?></select></label><label><span class="ops-label">優先度</span><input class="ops-input" type="number" name="priority" value="0"></label></div><label class="admin-native-field"><span class="ops-label">本文</span><textarea class="ops-textarea" name="body" rows="4"></textarea></label><div class="ops-form-actions"><button class="ops-button" type="submit">通知を作成</button></div></form></div></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>送信履歴</h3><p>直近<?= number_format(count($items)) ?>件</p></div></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>送信先</th><th>通知</th><th>作成日時</th><th>状態</th><th>変更</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><strong><?= staff_ui_escape($item['username']) ?></strong><br><span class="ops-muted"><?= staff_ui_escape($item['email']) ?></span></td><td><?= staff_ui_escape($item['title']) ?><br><span class="ops-muted"><?= staff_ui_escape($item['body'] ?? '') ?></span></td><td><?= staff_ui_escape(staff_administration_datetime($item['created_at'])) ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($item['status']) ?>"><?= staff_ui_escape($statuses[$item['status']] ?? $item['status']) ?></span></td><td><form method="post" class="admin-native-inline-form"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><select class="ops-select" name="status"><?php foreach ($statuses as $value => $label): ?><option value="<?= $value ?>" <?= $item['status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><button class="ops-button ops-button--compact" type="submit">保存</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php if ($items === []): ?><div class="ops-empty">個別通知はまだありません。</div><?php endif; ?></section>
</div>
<?php staff_layout_end();
