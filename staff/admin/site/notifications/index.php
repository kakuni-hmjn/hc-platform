<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();
$statuses = ['published' => '公開', 'draft' => '下書き', 'hidden' => '非表示'];
$pdo->exec("CREATE TABLE IF NOT EXISTS site_notifications (
    id SERIAL PRIMARY KEY, title VARCHAR(180) NOT NULL, body TEXT, link_url VARCHAR(255),
    target_scope VARCHAR(40) NOT NULL DEFAULT 'all', status VARCHAR(40) NOT NULL DEFAULT 'published',
    priority INTEGER NOT NULL DEFAULT 0, published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('操作の有効期限が切れました。');
        }
        $action = (string) ($_POST['action'] ?? 'add');
        if ($action === 'add') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $url = staff_administration_internal_url($_POST['link_url'] ?? null);
            $status = (string) ($_POST['status'] ?? 'published');
            $priority = (int) ($_POST['priority'] ?? 0);
            $published = trim((string) ($_POST['published_at'] ?? ''));
            if ($title === '') { throw new RuntimeException('タイトルを入力してください。'); }
            if (!isset($statuses[$status])) { throw new RuntimeException('公開状態が不正です。'); }
            try { $publishedAt = $published === '' ? date('Y-m-d H:i:s') : (new DateTime($published))->format('Y-m-d H:i:s'); }
            catch (Throwable $exception) { throw new RuntimeException('公開日時が不正です。'); }
            $stmt = $pdo->prepare("INSERT INTO site_notifications (title, body, link_url, target_scope, status, priority, published_at, created_at, updated_at) VALUES (:title, :body, :url, 'all', :status, :priority, :published_at, NOW(), NOW())");
            $stmt->execute(['title' => $title, 'body' => $body !== '' ? $body : null, 'url' => $url, 'status' => $status, 'priority' => $priority, 'published_at' => $publishedAt]);
            staff_administration_flash('success', 'サイト全体通知を作成しました。');
        } elseif ($action === 'status') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'hidden');
            if ($id <= 0 || !isset($statuses[$status])) { throw new RuntimeException('更新内容が不正です。'); }
            $stmt = $pdo->prepare('UPDATE site_notifications SET status = :status, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $id, 'status' => $status]);
            staff_administration_flash('success', '通知の公開状態を更新しました。');
        } else { throw new RuntimeException('不明な操作です。'); }
    } catch (Throwable $exception) {
        staff_administration_flash('error', $exception->getMessage());
    }
    staff_administration_redirect('/staff/admin/site/notifications/');
}

$items = $pdo->query('SELECT * FROM site_notifications ORDER BY priority DESC, published_at DESC, id DESC LIMIT 100')->fetchAll() ?: [];
$flash = staff_administration_take_flash();

staff_layout_start([
    'title' => 'サイト通知', 'heading' => 'サイト通知', 'eyebrow' => 'WEBSITE / GLOBAL NOTIFICATIONS',
    'description' => 'ログイン中の全ユーザーへ表示する通知を作成し、公開状態を管理します。',
]);
?>
<div class="ops-page admin-native-page">
    <?php if ($flash): ?><div class="ops-alert <?= $flash['type'] === 'success' ? 'ops-alert--success' : '' ?>"><?= staff_ui_escape($flash['message']) ?></div><?php endif; ?>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>新しい全体通知</h3><p>下書き保存や公開日時の予約にも対応します。</p></div><a class="ops-button ops-button--secondary" href="/dashboard/notifications/#global">表示確認</a></header><div class="ops-panel__body">
        <form method="post" class="admin-native-form"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="add">
            <div class="ops-form-grid"><label><span class="ops-label">タイトル</span><input class="ops-input" name="title" maxlength="180" required></label><label><span class="ops-label">内部リンク</span><input class="ops-input" name="link_url" placeholder="/news/"></label><label><span class="ops-label">公開状態</span><select class="ops-select" name="status"><?php foreach ($statuses as $value => $label): ?><option value="<?= $value ?>"><?= $label ?></option><?php endforeach; ?></select></label><label><span class="ops-label">公開日時</span><input class="ops-input" type="datetime-local" name="published_at" value="<?= date('Y-m-d\TH:i') ?>"></label><label><span class="ops-label">優先度</span><input class="ops-input" type="number" name="priority" value="0"></label></div>
            <label class="admin-native-field"><span class="ops-label">本文</span><textarea class="ops-textarea" name="body" rows="4"></textarea></label><div class="ops-form-actions"><button class="ops-button" type="submit">通知を作成</button></div>
        </form>
    </div></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>通知履歴</h3><p>直近<?= number_format(count($items)) ?>件</p></div></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>通知</th><th>公開日時</th><th>優先度</th><th>状態</th><th>変更</th></tr></thead><tbody><?php foreach ($items as $item): ?><tr><td><strong><?= staff_ui_escape($item['title']) ?></strong><br><span class="ops-muted"><?= staff_ui_escape($item['body'] ?? '') ?><?= $item['link_url'] ? ' / ' . staff_ui_escape($item['link_url']) : '' ?></span></td><td><?= staff_ui_escape(staff_administration_datetime($item['published_at'])) ?></td><td><?= (int) $item['priority'] ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($item['status']) ?>"><?= staff_ui_escape($statuses[$item['status']] ?? $item['status']) ?></span></td><td><form method="post" class="admin-native-inline-form"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><select class="ops-select" name="status"><?php foreach ($statuses as $value => $label): ?><option value="<?= $value ?>" <?= $item['status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><button class="ops-button ops-button--compact" type="submit">保存</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php if ($items === []): ?><div class="ops-empty">通知はまだありません。</div><?php endif; ?></section>
</div>
<?php staff_layout_end();
