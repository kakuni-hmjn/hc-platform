<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/operations.php';
require_once dirname(__DIR__, 2) . '/lib/csrf.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'announcements.view');

$staffUserId = (int) ($staffUser['id'] ?? 0);
$canManage = staff_has_permission($staffContext, 'announcements.manage') || staff_can_access_admin($staffContext);
$message = '';
$error = '';
$filter = trim((string) ($_GET['filter'] ?? 'active'));
if (!in_array($filter, ['active', 'unread', 'all'], true)) {
    $filter = 'active';
}

$targets = ['all' => '全スタッフ'];
foreach ((array) ($staffContext['roles'] ?? []) as $role) {
    $targets['role:' . (int) ($role['id'] ?? 0)] = 'ロール: ' . (string) ($role['name'] ?? '');
}
foreach ((array) ($staffContext['departments'] ?? []) as $department) {
    $targets['department:' . (int) ($department['id'] ?? 0)] = '部署: ' . (string) ($department['name'] ?? '');
}
foreach ((array) ($staffContext['categories'] ?? []) as $categoryItem) {
    $targets['category:' . (int) ($categoryItem['id'] ?? 0)] = 'カテゴリ: ' . (string) ($categoryItem['name'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = '確認情報の有効期限が切れました。再読み込みしてお試しください。';
    } else {
        try {
            if (in_array($action, ['read', 'confirm'], true)) {
                $announcementId = max(0, (int) ($_POST['announcement_id'] ?? 0));
                $statement = staff_db()->prepare(
                    'INSERT INTO staff_announcement_reads (announcement_id, user_id, read_at, confirmed_at)
                     VALUES (:announcement_id, :user_id, CURRENT_TIMESTAMP, :confirmed_at)
                     ON CONFLICT (announcement_id, user_id) DO UPDATE SET
                        read_at = COALESCE(staff_announcement_reads.read_at, CURRENT_TIMESTAMP),
                        confirmed_at = COALESCE(EXCLUDED.confirmed_at, staff_announcement_reads.confirmed_at)'
                );
                $statement->execute([
                    'announcement_id' => $announcementId,
                    'user_id' => $staffUserId,
                    'confirmed_at' => $action === 'confirm' ? date('c') : null,
                ]);
                $message = $action === 'confirm' ? '社内連絡を確認済みにしました。' : '社内連絡を既読にしました。';
            } elseif ($action === 'create' && $canManage) {
                $title = trim((string) ($_POST['title'] ?? ''));
                $body = trim((string) ($_POST['body'] ?? ''));
                $priority = trim((string) ($_POST['priority'] ?? 'normal'));
                $scope = trim((string) ($_POST['target_scope'] ?? 'all'));
                $requiresConfirmation = isset($_POST['requires_confirmation']);
                if ($title === '' || $body === '' || !in_array($priority, ['normal', 'important', 'urgent'], true)) {
                    throw new InvalidArgumentException('invalid content');
                }
                $targetType = 'all';
                $targetId = null;
                if ($scope !== 'all' && preg_match('/^(role|department|category):(\d+)$/', $scope, $matches) === 1 && isset($targets[$scope])) {
                    $targetType = $matches[1];
                    $targetId = (int) $matches[2];
                }
                $statement = staff_db()->prepare(
                    'INSERT INTO staff_announcements
                        (title, body, priority, target_type, target_id, requires_confirmation, published_at, created_by)
                     VALUES (:title, :body, :priority, :target_type, :target_id, :requires_confirmation, CURRENT_TIMESTAMP, :created_by)
                     RETURNING id'
                );
                $statement->execute([
                    'title' => $title, 'body' => $body, 'priority' => $priority,
                    'target_type' => $targetType, 'target_id' => $targetId,
                    'requires_confirmation' => $requiresConfirmation,
                    'created_by' => $staffUserId,
                ]);
                $newId = (int) $statement->fetchColumn();
                staff_ops_audit($staffUserId, 'announcement.create', 'announcement', $newId, '社内連絡を公開しました。', null, ['title' => $title, 'target_type' => $targetType, 'target_id' => $targetId]);
                $message = '社内連絡を公開しました。';
            } else {
                $error = 'この操作を行う権限がありません。';
            }
        } catch (InvalidArgumentException $exception) {
            $error = 'タイトルと本文を入力してください。';
        } catch (Throwable $exception) {
            $error = '社内連絡を更新できませんでした。';
        }
    }
}

$roleIds = array_map('intval', array_column((array) ($staffContext['roles'] ?? []), 'id'));
$departmentIds = array_map('intval', array_column((array) ($staffContext['departments'] ?? []), 'id'));
$categoryIds = array_map('intval', array_column((array) ($staffContext['categories'] ?? []), 'id'));
$announcements = [];
$counts = ['active' => 0, 'unread' => 0, 'confirmation' => 0];
try {
    $statement = staff_db()->prepare(
        'SELECT a.*, r.read_at, r.confirmed_at, COALESCE(su.display_name, u.username, \'システム\') AS author_name
         FROM staff_announcements a
         LEFT JOIN staff_announcement_reads r ON r.announcement_id = a.id AND r.user_id = :user_id
         LEFT JOIN staff_users su ON su.id = a.created_by
         LEFT JOIN users u ON u.id = su.account_id
         WHERE a.published_at IS NOT NULL
         ORDER BY CASE a.priority WHEN \'urgent\' THEN 1 WHEN \'important\' THEN 2 ELSE 3 END,
                  a.published_at DESC, a.id DESC LIMIT 150'
    );
    $statement->execute(['user_id' => $staffUserId]);
    foreach ($statement->fetchAll() ?: [] as $row) {
        $targetType = (string) ($row['target_type'] ?? 'all');
        $targetId = (int) ($row['target_id'] ?? 0);
        $visible = $targetType === 'all'
            || ($targetType === 'user' && $targetId === $staffUserId)
            || ($targetType === 'role' && in_array($targetId, $roleIds, true))
            || ($targetType === 'department' && in_array($targetId, $departmentIds, true))
            || ($targetType === 'category' && in_array($targetId, $categoryIds, true));
        if (!$visible) {
            continue;
        }
        $isActive = empty($row['expires_at']) || strtotime((string) $row['expires_at']) >= time();
        if ($isActive) {
            $counts['active']++;
            if (empty($row['read_at'])) {
                $counts['unread']++;
            }
            if (!empty($row['requires_confirmation']) && empty($row['confirmed_at'])) {
                $counts['confirmation']++;
            }
        }
        if ($filter === 'active' && !$isActive) {
            continue;
        }
        if ($filter === 'unread' && (!$isActive || !empty($row['read_at']))) {
            continue;
        }
        $announcements[] = $row;
    }
} catch (Throwable $exception) {
    $error = $error !== '' ? $error : '社内連絡を取得できませんでした。';
}

$priorityLabels = ['normal' => '通常', 'important' => '重要', 'urgent' => '緊急'];
staff_layout_start([
    'title' => '社内連絡', 'heading' => '社内連絡', 'eyebrow' => 'STAFF ANNOUNCEMENTS',
    'description' => '全社・ロール・部署・担当カテゴリごとの連絡を確認します。',
]);
?>
<div class="ops-page">
    <?php if ($message !== ''): ?><div class="ops-alert ops-alert--success"><?= staff_ui_escape($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">公開中</span><strong><?= number_format($counts['active']) ?></strong><small>現在確認できる連絡</small></article><article class="ops-card"><span class="ops-card__label">未読</span><strong><?= number_format($counts['unread']) ?></strong><small>まだ開いていない連絡</small></article><article class="ops-card"><span class="ops-card__label">確認待ち</span><strong><?= number_format($counts['confirmation']) ?></strong><small>確認操作が必要</small></article><article class="ops-card"><span class="ops-card__label">配信先</span><strong><?= number_format(count($targets)) ?></strong><small>自分が選べる対象</small></article></section>

    <?php if ($canManage): ?><section class="ops-panel"><header class="ops-panel__header"><div><h3>新しい社内連絡</h3><p>対象を選び、すぐに公開します。</p></div></header><div class="ops-panel__body"><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="create"><div class="ops-form-grid"><label><span class="ops-label">タイトル</span><input class="ops-input" name="title" maxlength="255" required></label><label><span class="ops-label">配信先</span><select class="ops-select" name="target_scope"><?php foreach ($targets as $value => $label): ?><option value="<?= staff_ui_escape($value) ?>"><?= staff_ui_escape($label) ?></option><?php endforeach; ?></select></label><label><span class="ops-label">優先度</span><select class="ops-select" name="priority"><option value="normal">通常</option><option value="important">重要</option><option value="urgent">緊急</option></select></label><label class="ops-check"><input type="checkbox" name="requires_confirmation" value="1"><span>受信者に「確認済み」操作を求める</span></label></div><label><span class="ops-label">本文</span><textarea class="ops-textarea" name="body" rows="5" required></textarea></label><div class="ops-form-actions"><button class="ops-button" type="submit"><span class="material-icons">campaign</span>公開する</button></div></form></div></section><?php endif; ?>

    <nav class="ops-tabs" aria-label="表示条件"><a class="<?= $filter === 'active' ? 'is-active' : '' ?>" href="?filter=active">公開中</a><a class="<?= $filter === 'unread' ? 'is-active' : '' ?>" href="?filter=unread">未読</a><a class="<?= $filter === 'all' ? 'is-active' : '' ?>" href="?filter=all">すべて</a></nav>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>連絡一覧</h3><p><?= number_format(count($announcements)) ?>件を表示しています。</p></div></header><div class="ops-rows">
        <?php foreach ($announcements as $announcement): ?><article class="ops-row <?= empty($announcement['read_at']) ? 'is-selected' : '' ?>"><span><strong class="ops-row__title"><?= staff_ui_escape($announcement['title']) ?></strong><span class="ops-row__meta"><?= nl2br(staff_ui_escape($announcement['body'])) ?><br><?= staff_ui_escape(staff_ops_datetime($announcement['published_at'] ?? null)) ?> · <?= staff_ui_escape($announcement['author_name']) ?></span></span><span class="ops-row__end"><span class="ops-status ops-status--<?= staff_ui_escape($announcement['priority']) ?>"><?= staff_ui_escape($priorityLabels[(string) $announcement['priority']] ?? '通常') ?></span><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>"><?php if (!empty($announcement['requires_confirmation']) && empty($announcement['confirmed_at'])): ?><input type="hidden" name="action" value="confirm"><button class="ops-button ops-button--compact" type="submit">確認済みにする</button><?php elseif (empty($announcement['read_at'])): ?><input type="hidden" name="action" value="read"><button class="ops-button ops-button--secondary ops-button--compact" type="submit">既読</button><?php else: ?><span class="ops-status ops-status--completed"><?= !empty($announcement['confirmed_at']) ? '確認済み' : '既読' ?></span><?php endif; ?></form></span></article><?php endforeach; ?>
        <?php if ($announcements === []): ?><div class="ops-empty">表示できる社内連絡はありません。</div><?php endif; ?>
    </div></section>
</div>
<?php staff_layout_end();
