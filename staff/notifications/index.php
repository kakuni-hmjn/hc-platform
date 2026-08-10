<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/operations.php';
require_once dirname(__DIR__, 2) . '/lib/csrf.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'staff.dashboard.view');

$staffUserId = (int) ($staffUser['id'] ?? 0);
$category = trim((string) ($_GET['category'] ?? 'all'));
$readState = trim((string) ($_GET['read'] ?? 'all'));
$message = '';
$error = '';
$allowedCategories = ['all', 'system', 'order', 'user', 'discord', 'github', 'development', 'general'];

if (!in_array($category, $allowedCategories, true)) {
    $category = 'all';
}
if (!in_array($readState, ['all', 'unread', 'read'], true)) {
    $readState = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = '確認情報の有効期限が切れました。再読み込みしてお試しください。';
    } else {
        try {
            if ($action === 'read_one') {
                $notificationId = max(0, (int) ($_POST['notification_id'] ?? 0));
                $statement = staff_db()->prepare(
                    'UPDATE staff_notifications
                     SET is_read = TRUE, read_at = COALESCE(read_at, CURRENT_TIMESTAMP),
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id AND user_id = :user_id'
                );
                $statement->execute(['id' => $notificationId, 'user_id' => $staffUserId]);
                $message = $statement->rowCount() > 0 ? '通知を既読にしました。' : '対象の通知は既に更新済みです。';
            } elseif ($action === 'read_all') {
                $statement = staff_db()->prepare(
                    'UPDATE staff_notifications
                     SET is_read = TRUE, read_at = COALESCE(read_at, CURRENT_TIMESTAMP),
                         updated_at = CURRENT_TIMESTAMP
                     WHERE user_id = :user_id AND is_read = FALSE'
                );
                $statement->execute(['user_id' => $staffUserId]);
                $message = number_format($statement->rowCount()) . '件の通知を既読にしました。';
            } else {
                $error = '操作内容が正しくありません。';
            }
        } catch (Throwable $exception) {
            $error = '通知を更新できませんでした。';
        }
    }
}

$counts = ['total' => 0, 'unread' => 0, 'important' => 0, 'today' => 0];
$notifications = [];
try {
    $statement = staff_db()->prepare(
        "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE is_read = FALSE) AS unread,
                COUNT(*) FILTER (WHERE level IN ('warning', 'error', 'critical')) AS important,
                COUNT(*) FILTER (WHERE created_at >= CURRENT_DATE) AS today
         FROM staff_notifications WHERE user_id = :user_id"
    );
    $statement->execute(['user_id' => $staffUserId]);
    $row = $statement->fetch();
    if (is_array($row)) {
        foreach ($counts as $key => $value) {
            $counts[$key] = (int) ($row[$key] ?? 0);
        }
    }

    $where = ['user_id = :user_id'];
    $params = ['user_id' => $staffUserId];
    if ($category !== 'all') {
        $where[] = 'COALESCE(category, type, \'general\') = :category';
        $params['category'] = $category;
    }
    if ($readState === 'unread') {
        $where[] = 'is_read = FALSE';
    } elseif ($readState === 'read') {
        $where[] = 'is_read = TRUE';
    }
    $statement = staff_db()->prepare(
        'SELECT id, title, COALESCE(body, message) AS content,
                COALESCE(category, type, \'general\') AS category,
                COALESCE(level, \'info\') AS level,
                COALESCE(action_url, link_url) AS action_url,
                is_read, read_at, created_at
         FROM staff_notifications WHERE ' . implode(' AND ', $where) . '
         ORDER BY created_at DESC, id DESC LIMIT 150'
    );
    $statement->execute($params);
    $notifications = $statement->fetchAll() ?: [];
} catch (Throwable $exception) {
    $error = $error !== '' ? $error : '通知一覧を取得できませんでした。';
}

$categoryLabels = [
    'all' => 'すべて', 'general' => '一般', 'system' => 'システム', 'order' => '注文',
    'user' => '顧客', 'discord' => 'Discord', 'github' => 'GitHub', 'development' => '開発',
];

staff_layout_start([
    'title' => '通知',
    'heading' => '通知',
    'eyebrow' => 'STAFF NOTIFICATIONS',
    'description' => '自分宛ての業務通知を確認し、対応済みのものを既読にできます。',
]);
?>
<div class="ops-page">
    <?php if ($message !== ''): ?><div class="ops-alert ops-alert--success"><?= staff_ui_escape($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>

    <section class="ops-summary" aria-label="通知集計">
        <article class="ops-card"><span class="ops-card__label">未読</span><strong><?= number_format($counts['unread']) ?></strong><small>確認が必要な通知</small></article>
        <article class="ops-card"><span class="ops-card__label">今日</span><strong><?= number_format($counts['today']) ?></strong><small>本日届いた通知</small></article>
        <article class="ops-card"><span class="ops-card__label">重要</span><strong><?= number_format($counts['important']) ?></strong><small>警告・エラー通知</small></article>
        <article class="ops-card"><span class="ops-card__label">合計</span><strong><?= number_format($counts['total']) ?></strong><small>保存されている通知</small></article>
    </section>

    <form class="ops-toolbar" method="get">
        <label class="ops-toolbar__field"><span class="ops-label">カテゴリ</span><select class="ops-select" name="category"><?php foreach ($categoryLabels as $value => $label): ?><option value="<?= staff_ui_escape($value) ?>" <?= $category === $value ? 'selected' : '' ?>><?= staff_ui_escape($label) ?></option><?php endforeach; ?></select></label>
        <label class="ops-toolbar__field"><span class="ops-label">既読状態</span><select class="ops-select" name="read"><option value="all" <?= $readState === 'all' ? 'selected' : '' ?>>すべて</option><option value="unread" <?= $readState === 'unread' ? 'selected' : '' ?>>未読</option><option value="read" <?= $readState === 'read' ? 'selected' : '' ?>>既読</option></select></label>
        <button class="ops-button" type="submit"><span class="material-icons">filter_alt</span>絞り込む</button>
        <a class="ops-button ops-button--secondary" href="/staff/notifications/">クリア</a>
    </form>

    <section class="ops-panel">
        <header class="ops-panel__header"><div><h3>通知一覧</h3><p><?= number_format(count($notifications)) ?>件を表示しています。</p></div><?php if ($counts['unread'] > 0): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="read_all"><button class="ops-button ops-button--secondary" type="submit">すべて既読</button></form><?php endif; ?></header>
        <div class="ops-rows">
            <?php foreach ($notifications as $notification): ?>
                <article class="ops-row <?= empty($notification['is_read']) ? 'is-selected' : '' ?>">
                    <span><strong class="ops-row__title"><?= staff_ui_escape($notification['title'] ?: 'お知らせ') ?></strong><span class="ops-row__meta"><?= nl2br(staff_ui_escape($notification['content'] ?? '')) ?><br><?= staff_ui_escape(staff_ops_datetime($notification['created_at'] ?? null)) ?> · <?= staff_ui_escape($categoryLabels[(string) ($notification['category'] ?? '')] ?? (string) ($notification['category'] ?? '一般')) ?></span><?php if (!empty($notification['action_url'])): ?><a class="ops-inline-link" href="<?= staff_ui_escape($notification['action_url']) ?>">関連画面を開く</a><?php endif; ?></span>
                    <span class="ops-row__end"><?php if (empty($notification['is_read'])): ?><span class="ops-status ops-status--pending">未読</span><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="read_one"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><button class="ops-button ops-button--secondary ops-button--compact" type="submit">既読</button></form><?php else: ?><span class="ops-status ops-status--completed">既読</span><?php endif; ?></span>
                </article>
            <?php endforeach; ?>
            <?php if ($notifications === []): ?><div class="ops-empty">条件に一致する通知はありません。</div><?php endif; ?>
        </div>
    </section>
</div>
<?php staff_layout_end();

