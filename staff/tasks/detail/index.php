<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/operations.php';
require_once dirname(__DIR__, 3) . '/lib/csrf.php';
require_once dirname(__DIR__, 2) . '/components/layout.php';
require_once dirname(__DIR__, 2) . '/components/ui.php';

staff_require_permission($staffContext, 'tasks.view.own');

$taskId = max(0, (int) ($_GET['id'] ?? $_POST['task_id'] ?? 0));
$staffUserId = (int) ($staffUser['id'] ?? 0);
$departmentIds = array_map('intval', array_column((array) ($staffContext['departments'] ?? []), 'id'));
$message = '';
$error = '';

function staff_task_detail_load(int $taskId): ?array
{
    $statement = staff_db()->prepare(
        'SELECT t.*, c.name AS category_name, d.name AS department_name,
                COALESCE(assignee.display_name, assignee_account.username) AS assignee_name,
                COALESCE(requester.display_name, requester_account.username) AS requester_name
         FROM staff_tasks t
         LEFT JOIN staff_categories c ON c.id = t.category_id
         LEFT JOIN staff_departments d ON d.id = t.department_id
         LEFT JOIN staff_users assignee ON assignee.id = t.assigned_user_id
         LEFT JOIN users assignee_account ON assignee_account.id = assignee.account_id
         LEFT JOIN staff_users requester ON requester.id = t.requested_by
         LEFT JOIN users requester_account ON requester_account.id = requester.account_id
         WHERE t.id = :id LIMIT 1'
    );
    $statement->execute(['id' => $taskId]);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function staff_task_detail_can_view(array $task, int $staffUserId, array $departmentIds, array $staffContext): bool
{
    if ((int) ($task['assigned_user_id'] ?? 0) === $staffUserId || staff_can_access_admin($staffContext)) {
        return true;
    }
    return staff_has_permission($staffContext, 'tasks.view.department')
        && in_array((int) ($task['department_id'] ?? 0), $departmentIds, true);
}

$task = $taskId > 0 ? staff_task_detail_load($taskId) : null;
if (!is_array($task) || !staff_task_detail_can_view($task, $staffUserId, $departmentIds, $staffContext)) {
    http_response_code(404);
    $task = null;
}

$canUpdate = is_array($task)
    && ((int) ($task['assigned_user_id'] ?? 0) === $staffUserId || staff_can_access_admin($staffContext))
    && (staff_has_permission($staffContext, 'tasks.complete') || staff_can_access_admin($staffContext));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($task)) {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = '確認情報の有効期限が切れました。再読み込みしてお試しください。';
    } elseif (!$canUpdate) {
        $error = 'このタスクを更新する権限がありません。';
    } else {
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));
        $validStatuses = ['todo', 'in_progress', 'review', 'waiting', 'completed', 'cancelled'];
        if (!in_array($newStatus, $validStatuses, true)) {
            $error = '進行状況が正しくありません。';
        } else {
            try {
                $pdo = staff_db();
                $pdo->beginTransaction();
                $oldStatus = (string) ($task['status'] ?? 'todo');
                $statement = $pdo->prepare(
                    'UPDATE staff_tasks SET status = :status,
                        started_at = CASE WHEN :status = \'in_progress\' THEN COALESCE(started_at, CURRENT_TIMESTAMP) ELSE started_at END,
                        completed_at = CASE WHEN :status = \'completed\' THEN CURRENT_TIMESTAMP ELSE NULL END,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );
                $statement->execute(['status' => $newStatus, 'id' => $taskId]);
                $statement = $pdo->prepare(
                    'INSERT INTO staff_task_logs (task_id, user_id, action, message, old_data, new_data)
                     VALUES (:task_id, :user_id, \'status.update\', :message,
                        CAST(:old_data AS JSONB), CAST(:new_data AS JSONB))'
                );
                $statement->execute([
                    'task_id' => $taskId, 'user_id' => $staffUserId,
                    'message' => $note !== '' ? $note : '進行状況を更新しました。',
                    'old_data' => json_encode(['status' => $oldStatus], JSON_UNESCAPED_UNICODE),
                    'new_data' => json_encode(['status' => $newStatus], JSON_UNESCAPED_UNICODE),
                ]);
                $pdo->commit();
                staff_ops_audit($staffUserId, 'task.status.update', 'staff_task', $taskId, 'タスクの進行状況を更新しました。', ['status' => $oldStatus], ['status' => $newStatus]);
                $message = 'タスクを更新しました。';
                $task = staff_task_detail_load($taskId);
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'タスクを更新できませんでした。';
            }
        }
    }
}

$logs = [];
if (is_array($task)) {
    try {
        $statement = staff_db()->prepare(
            'SELECT l.*, COALESCE(su.display_name, u.username, \'システム\') AS actor_name
             FROM staff_task_logs l
             LEFT JOIN staff_users su ON su.id = l.user_id
             LEFT JOIN users u ON u.id = su.account_id
             WHERE l.task_id = :task_id ORDER BY l.created_at DESC, l.id DESC LIMIT 100'
        );
        $statement->execute(['task_id' => $taskId]);
        $logs = $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        $error = $error !== '' ? $error : '更新履歴を取得できませんでした。';
    }
}

$statusLabels = ['todo' => '未着手', 'in_progress' => '対応中', 'review' => 'レビュー', 'waiting' => '待機中', 'completed' => '完了', 'cancelled' => 'キャンセル'];
$priorityLabels = ['low' => '低', 'normal' => '通常', 'high' => '高', 'urgent' => '緊急'];
$relatedHref = '';
if (is_array($task) && (int) ($task['related_id'] ?? 0) > 0) {
    $relatedHref = match ((string) ($task['related_type'] ?? '')) {
        'contact', 'support' => '/staff/support/?view=overview&id=' . (int) $task['related_id'],
        'order', 'game_server_order' => '/staff/rental-server/game-server/contracts/?id=' . (int) $task['related_id'],
        'user', 'customer' => '/staff/customers/?id=' . (int) $task['related_id'],
        default => '',
    };
}

staff_layout_start([
    'title' => is_array($task) ? (string) ($task['title'] ?? 'タスク詳細') : 'タスクが見つかりません',
    'heading' => 'タスク詳細', 'eyebrow' => 'TASK DETAIL',
    'description' => '担当業務の内容、進行状況、更新履歴を確認します。',
]);
?>
<div class="ops-page">
    <?php if ($message !== ''): ?><div class="ops-alert ops-alert--success"><?= staff_ui_escape($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>
    <?php if (!is_array($task)): ?>
        <section class="ops-panel"><div class="ops-empty">指定されたタスクは見つからないか、閲覧する権限がありません。<br><a class="ops-inline-link" href="/staff/tasks/">タスク一覧へ戻る</a></div></section>
    <?php else: ?>
        <div class="ops-split">
            <section class="ops-detail"><header class="ops-panel__header"><div><h3><?= staff_ui_escape($task['title']) ?></h3><p><?= staff_ui_escape($task['task_number']) ?></p></div><span class="ops-status ops-status--<?= staff_ui_escape($task['status']) ?>"><?= staff_ui_escape($statusLabels[(string) $task['status']] ?? $task['status']) ?></span></header><div class="ops-panel__body">
                <dl class="ops-kv"><div><dt>担当者</dt><dd><?= staff_ui_escape($task['assignee_name'] ?: '未割当') ?></dd></div><div><dt>依頼者</dt><dd><?= staff_ui_escape($task['requester_name'] ?: '未設定') ?></dd></div><div><dt>優先度</dt><dd><?= staff_ui_escape($priorityLabels[(string) $task['priority']] ?? $task['priority']) ?></dd></div><div><dt>期限</dt><dd><?= staff_ui_escape(staff_ops_datetime($task['due_at'] ?? null)) ?></dd></div><div><dt>部署</dt><dd><?= staff_ui_escape($task['department_name'] ?: '未設定') ?></dd></div><div><dt>カテゴリ</dt><dd><?= staff_ui_escape($task['category_name'] ?: '未設定') ?></dd></div></dl>
                <div class="ops-section"><h4>業務内容</h4><div class="ops-prose"><?= nl2br(staff_ui_escape($task['description'] ?: '詳細は登録されていません。')) ?></div></div>
                <?php if ($relatedHref !== ''): ?><div class="ops-form-actions"><a class="ops-button ops-button--secondary" href="<?= staff_ui_escape($relatedHref) ?>">関連画面を開く</a></div><?php endif; ?>
                <?php if ($canUpdate): ?><div class="ops-section"><h4>進行状況を更新</h4><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="task_id" value="<?= $taskId ?>"><div class="ops-form-grid"><label><span class="ops-label">進行状況</span><select class="ops-select" name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= staff_ui_escape($value) ?>" <?= $task['status'] === $value ? 'selected' : '' ?>><?= staff_ui_escape($label) ?></option><?php endforeach; ?></select></label><label><span class="ops-label">更新メモ</span><input class="ops-input" name="note" maxlength="500" placeholder="変更理由や引き継ぎ事項"></label></div><div class="ops-form-actions"><button class="ops-button" type="submit">更新する</button></div></form></div><?php endif; ?>
            </div></section>
            <section class="ops-list"><header class="ops-panel__header"><div><h3>更新履歴</h3><p><?= number_format(count($logs)) ?>件</p></div></header><div class="ops-rows"><?php foreach ($logs as $log): ?><article class="ops-row"><span><strong class="ops-row__title"><?= staff_ui_escape($log['message'] ?: $log['action']) ?></strong><span class="ops-row__meta"><?= staff_ui_escape($log['actor_name']) ?> · <?= staff_ui_escape(staff_ops_datetime($log['created_at'] ?? null)) ?></span></span></article><?php endforeach; ?><?php if ($logs === []): ?><div class="ops-empty">更新履歴はまだありません。</div><?php endif; ?></div></section>
        </div>
        <div class="ops-form-actions"><a class="ops-button ops-button--secondary" href="/staff/tasks/">タスク一覧へ戻る</a></div>
    <?php endif; ?>
</div>
<?php staff_layout_end();
