<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/operations.php';
require_once dirname(__DIR__) . '/lib/rental-server.php';
require_once dirname(__DIR__) . '/lib/approval-actions.php';
require_once dirname(__DIR__, 2) . '/lib/csrf.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'orders.approve');

$actionFlash = $_SESSION['staff_approval_flash'] ?? null;
unset($_SESSION['staff_approval_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) throw new RuntimeException('操作の有効期限が切れました。もう一度お試しください。');
        $resultMessage = staff_approval_execute(
            staff_db(), $staffAccountId, (int) ($staffUser['id'] ?? 0), $staffDisplayName,
            trim((string) ($_POST['action'] ?? '')), $_POST
        );
        $_SESSION['staff_approval_flash'] = ['type' => 'success', 'message' => $resultMessage];
    } catch (Throwable $exception) {
        $_SESSION['staff_approval_flash'] = ['type' => 'error', 'message' => $exception->getMessage()];
    }
    header('Location: /staff/approvals/');
    exit;
}

$orders = [];
$planChanges = [];
$counts = ['orders' => 0, 'failed' => 0, 'plan_changes' => 0, 'total' => 0];
$error = '';
try {
    $statement = staff_db()->query(
        "SELECT gso.id, gso.server_name, gso.status, gso.payment_status,
                gso.amount, gso.currency, gso.approval_requested_at, gso.approval_error,
                gso.created_at, u.username, u.email, gsp.name AS plan_name
         FROM game_server_orders gso
         LEFT JOIN users u ON u.id = gso.user_id
         LEFT JOIN game_server_plans gsp ON gsp.id = gso.plan_id
         WHERE gso.status IN ('pending_approval', 'approval_failed')
         ORDER BY CASE WHEN gso.status = 'approval_failed' THEN 1 ELSE 2 END,
                  gso.approval_requested_at ASC NULLS LAST, gso.id ASC LIMIT 100"
    );
    $orders = $statement->fetchAll() ?: [];
    foreach ($orders as $order) {
        $counts['orders']++;
        if (($order['status'] ?? '') === 'approval_failed') {
            $counts['failed']++;
        }
    }
} catch (Throwable $exception) {
    $error = 'サーバー承認待ちを取得できませんでした。';
}

try {
    $statement = staff_db()->query(
        "SELECT r.id, r.order_id, r.status, r.change_type, r.user_note, r.created_at,
                u.username, u.email, current_plan.name AS current_plan_name,
                requested_plan.name AS requested_plan_name
         FROM server_order_plan_change_requests r
         LEFT JOIN users u ON u.id = r.user_id
         LEFT JOIN game_server_plans current_plan ON current_plan.id = r.current_plan_id
         LEFT JOIN game_server_plans requested_plan ON requested_plan.id = r.requested_plan_id
         WHERE r.status IN ('pending', 'reviewing', 'approved')
         ORDER BY CASE r.status WHEN 'pending' THEN 1 WHEN 'reviewing' THEN 2 ELSE 3 END,
                  r.created_at ASC, r.id ASC LIMIT 100"
    );
    $planChanges = $statement->fetchAll() ?: [];
    $counts['plan_changes'] = count(array_filter($planChanges, static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), ['pending', 'reviewing'], true)));
} catch (Throwable $exception) {
    $error = $error !== '' ? $error : 'プラン変更承認待ちを取得できませんでした。';
}
$counts['total'] = $counts['orders'] + $counts['plan_changes'];
$planStatusLabels = ['pending' => '確認待ち', 'reviewing' => '確認中', 'approved' => '承認済み'];

staff_layout_start([
    'title' => '承認センター', 'heading' => '承認センター', 'eyebrow' => 'APPROVAL CENTER',
    'description' => 'サーバー作成とプラン変更の承認待ちを一か所で確認し、既存の安全な承認処理へ進みます。',
]);
?>
<div class="ops-page">
    <?php if (is_array($actionFlash)): ?><div class="ops-alert <?= ($actionFlash['type'] ?? '') === 'success' ? 'ops-alert--success' : '' ?>"><?= staff_ui_escape($actionFlash['message'] ?? '') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">承認待ち合計</span><strong><?= number_format($counts['total']) ?></strong><small>対応が必要</small></article><article class="ops-card"><span class="ops-card__label">サーバー作成</span><strong><?= number_format($counts['orders']) ?></strong><small>作成承認・再試行</small></article><article class="ops-card"><span class="ops-card__label">承認失敗</span><strong><?= number_format($counts['failed']) ?></strong><small>優先確認</small></article><article class="ops-card"><span class="ops-card__label">プラン変更</span><strong><?= number_format($counts['plan_changes']) ?></strong><small>確認待ち・確認中</small></article></section>
    <section class="ops-panel" id="server-approvals"><header class="ops-panel__header"><div><h3>サーバー作成承認</h3><p>決済済み注文の構成を確認して承認します。</p></div><a class="ops-button ops-button--secondary" href="/staff/rental-server/game-server/approvals/">作成・承認一覧</a></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>注文</th><th>顧客</th><th>サーバー</th><th>プラン</th><th>金額</th><th>状態</th><th>依頼日時</th><th>操作</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td>#<?= (int) $order['id'] ?></td><td><?= staff_ui_escape($order['username'] ?: $order['email']) ?></td><td><?= staff_ui_escape($order['server_name']) ?></td><td><?= staff_ui_escape($order['plan_name'] ?: '-') ?></td><td><?= staff_ui_escape(staff_rental_price($order['amount'], $order['currency'])) ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($order['status']) ?>"><?= staff_ui_escape(staff_rental_order_status_label((string) $order['status'])) ?></span><?php if (!empty($order['approval_error'])): ?><br><span class="ops-muted"><?= staff_ui_escape($order['approval_error']) ?></span><?php endif; ?></td><td><?= staff_ui_escape(staff_ops_datetime($order['approval_requested_at'] ?? $order['created_at'] ?? null)) ?></td><td><div class="admin-approval-actions"><a href="/staff/rental-server/game-server/contracts/?id=<?= (int) $order['id'] ?>">詳細</a><form method="post" onsubmit="return confirm('このサーバーを承認して利用開始にしますか？')"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="approve_server"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><button class="ops-button ops-button--compact" type="submit"><?= staff_icon('approval', '', 15) ?>利用開始を承認</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php if ($orders === []): ?><div class="ops-empty">サーバー作成の承認待ちはありません。</div><?php endif; ?></section>
    <section class="ops-panel" id="plan-change-approvals"><header class="ops-panel__header"><div><h3>プラン変更承認</h3><p>契約中サーバーの変更申請を確認します。</p></div><a class="ops-button ops-button--secondary" href="/staff/admin/services/game-plans/">プラン設定</a></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>申請</th><th>注文</th><th>顧客</th><th>変更内容</th><th>反映方法</th><th>状態</th><th>申請日時</th><th>操作</th></tr></thead><tbody><?php foreach ($planChanges as $request): ?><tr><td>#<?= (int) $request['id'] ?></td><td><a href="/staff/rental-server/game-server/contracts/?id=<?= (int) $request['order_id'] ?>">#<?= (int) $request['order_id'] ?></a></td><td><?= staff_ui_escape($request['username'] ?: $request['email']) ?></td><td><?= staff_ui_escape(($request['current_plan_name'] ?: '-') . ' → ' . ($request['requested_plan_name'] ?: '-')) ?></td><td><?= staff_ui_escape($request['change_type'] === 'immediate' ? '即時' : '次回更新時') ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($request['status']) ?>"><?= staff_ui_escape($planStatusLabels[(string) $request['status']] ?? $request['status']) ?></span></td><td><?= staff_ui_escape(staff_ops_datetime($request['created_at'] ?? null)) ?></td><td><details class="admin-approval-menu"><summary>処理する</summary><div><a href="/staff/rental-server/game-server/contracts/?id=<?= (int) $request['order_id'] ?>">契約詳細</a><?php if ((string) $request['status'] === 'pending'): ?><form method="post" onsubmit="return confirm('このプラン変更を承認して反映しますか？')"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="action" value="process_plan_change"><textarea class="ops-textarea" name="admin_note" rows="2" placeholder="承認メモ（任意）"></textarea><button class="ops-button ops-button--compact" type="submit">承認・反映</button></form><form method="post" onsubmit="return confirm('このプラン変更申請を却下しますか？')"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="action" value="reject_plan_change"><textarea class="ops-textarea" name="admin_note" rows="2" required placeholder="却下理由"></textarea><button class="ops-button ops-button--danger ops-button--compact" type="submit">却下</button></form><?php elseif ((string) $request['status'] === 'approved'): ?><form method="post" onsubmit="return confirm('承認済みの変更を契約へ反映しますか？')"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="action" value="apply_plan_change"><button class="ops-button ops-button--compact" type="submit">契約へ反映</button></form><?php endif; ?></div></details></td></tr><?php endforeach; ?></tbody></table></div><?php if ($planChanges === []): ?><div class="ops-empty">プラン変更の承認待ちはありません。</div><?php endif; ?></section>
</div>
<?php staff_layout_end();
