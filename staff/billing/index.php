<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/operations.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'orders.view');

$search = trim((string) ($_GET['q'] ?? ''));
$paymentStatus = trim((string) ($_GET['payment_status'] ?? ''));
$selectedId = max(0, (int) ($_GET['id'] ?? 0));
$data = staff_billing_load($search, $paymentStatus, $selectedId);
$selected = $data['selected'];
$baseParams = array_filter(['q' => $search, 'payment_status' => $paymentStatus], static fn ($value): bool => $value !== '');

staff_layout_start([
    'title' => '決済・請求',
    'heading' => '決済・請求',
    'eyebrow' => 'BILLING AND REVENUE',
    'description' => '請求状態、Stripe決済、返金履歴と売上を確認します。',
]);
?>
<div class="ops-page">
    <?php foreach ($data['errors'] as $loadError): ?><div class="ops-alert"><?= staff_ui_escape($loadError) ?></div><?php endforeach; ?>
    <section class="ops-summary" aria-label="決済集計">
        <article class="ops-card"><span class="ops-card__label">決済済み売上</span><strong>¥<?= number_format($data['counts']['paid_total']) ?></strong><small><?= number_format($data['counts']['paid_orders']) ?>件の支払い</small></article>
        <article class="ops-card"><span class="ops-card__label">未払い・処理中</span><strong><?= number_format($data['counts']['unpaid_orders']) ?></strong><small>決済待ちの請求</small></article>
        <article class="ops-card"><span class="ops-card__label">決済失敗</span><strong><?= number_format($data['counts']['failed_orders']) ?></strong><small>再確認が必要</small></article>
        <article class="ops-card"><span class="ops-card__label">返金記録</span><strong>¥<?= number_format($data['counts']['refunded_total']) ?></strong><small>決済イベント集計</small></article>
    </section>

    <form class="ops-toolbar" method="get">
        <label class="ops-toolbar__field"><span class="ops-label">請求を検索</span><input class="ops-input" type="search" name="q" value="<?= staff_ui_escape($search) ?>" placeholder="顧客・メール・サーバー・注文ID"></label>
        <label class="ops-toolbar__field"><span class="ops-label">決済状態</span><select class="ops-select" name="payment_status"><option value="">すべて</option><?php foreach (['unpaid' => '未払い', 'checkout_created' => 'Checkout作成済み', 'paid' => '支払い済み', 'failed' => '失敗', 'refunded' => '返金済み', 'cancelled' => 'キャンセル'] as $value => $label): ?><option value="<?= $value ?>" <?= $paymentStatus === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <button class="ops-button" type="submit"><span class="material-icons">search</span>検索</button><a class="ops-button ops-button--secondary" href="/staff/billing/">クリア</a>
    </form>

    <div class="ops-split">
        <section class="ops-list"><header class="ops-panel__header"><div><h3>請求一覧</h3><p><?= number_format(count($data['orders'])) ?>件を表示</p></div></header><div class="ops-rows">
            <?php foreach ($data['orders'] as $order): $params = $baseParams + ['id' => (int) $order['id']]; $isSelected = (int) ($selected['id'] ?? 0) === (int) $order['id']; ?>
                <a class="ops-row <?= $isSelected ? 'is-selected' : '' ?>" href="?<?= staff_ui_escape(http_build_query($params)) ?>"><span><strong class="ops-row__title">#<?= (int) $order['id'] ?> <?= staff_ui_escape($order['server_name']) ?></strong><span class="ops-row__meta"><?= staff_ui_escape($order['username'] ?? '不明') ?>・<?= staff_ui_escape($order['plan_name'] ?? '-') ?><br><?= staff_ui_escape(staff_ops_datetime($order['created_at'] ?? null)) ?></span></span><span class="ops-row__end"><strong class="ops-row__title"><?= staff_ui_escape(staff_rental_price($order['amount'], $order['currency'])) ?></strong><span class="ops-status ops-status--<?= staff_ui_escape($order['payment_status']) ?>"><?= staff_ui_escape(staff_rental_payment_status_label((string) $order['payment_status'])) ?></span></span></a>
            <?php endforeach; ?>
            <?php if ($data['orders'] === []): ?><div class="ops-empty">条件に一致する請求はありません。</div><?php endif; ?>
        </div></section>

        <section class="ops-detail">
            <?php if (is_array($selected)): ?>
                <header class="ops-panel__header"><div><h3>請求 #<?= (int) $selected['id'] ?></h3><p><?= staff_ui_escape($selected['server_name']) ?> / <?= staff_ui_escape($selected['plan_name'] ?? '-') ?></p></div><span class="ops-status ops-status--<?= staff_ui_escape($selected['payment_status']) ?>"><?= staff_ui_escape(staff_rental_payment_status_label((string) $selected['payment_status'])) ?></span></header>
                <div class="ops-panel__body">
                    <dl class="ops-kv"><div><dt>顧客</dt><dd><a href="/staff/customers/?id=<?= (int) $selected['user_id'] ?>"><?= staff_ui_escape($selected['username'] ?? '不明') ?></a></dd></div><div><dt>メール</dt><dd><?= staff_ui_escape($selected['email'] ?? '-') ?></dd></div><div><dt>請求金額</dt><dd><?= staff_ui_escape(staff_rental_price($selected['amount'], $selected['currency'])) ?></dd></div><div><dt>請求周期</dt><dd><?= staff_ui_escape($selected['billing_period'] ?? '-') ?></dd></div><div><dt>支払日時</dt><dd><?= staff_ui_escape(staff_ops_datetime($selected['paid_at'] ?? null)) ?></dd></div><div><dt>次回支払予定</dt><dd><?= staff_ui_escape(staff_ops_datetime($selected['next_payment_due_at'] ?? null)) ?></dd></div><div><dt>Stripe Customer</dt><dd><?= staff_ui_escape($selected['stripe_customer_id'] ?? '-') ?></dd></div><div><dt>Subscription</dt><dd><?= staff_ui_escape($selected['stripe_subscription_id'] ?? '-') ?></dd></div></dl>
                    <div class="ops-section"><div class="ops-form-actions"><a class="ops-button" href="/staff/rental-server/game-server/contracts/?id=<?= (int) $selected['id'] ?>">契約・注文の詳細を確認</a></div></div>
                    <div class="ops-section"><h4>決済イベント履歴</h4><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>日時</th><th>イベント</th><th>状態</th><th>金額</th><th>内容</th></tr></thead><tbody><?php foreach ($data['events'] as $event): ?><tr><td><?= staff_ui_escape(staff_ops_datetime($event['created_at'] ?? null)) ?></td><td><?= staff_ui_escape($event['event_type']) ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($event['payment_status'] ?? '') ?>"><?= staff_ui_escape(staff_rental_payment_status_label((string) ($event['payment_status'] ?? '-'))) ?></span></td><td><?= $event['amount'] !== null ? staff_ui_escape(staff_rental_price($event['amount'], $event['currency'])) : '-' ?></td><td><?= staff_ui_escape($event['message'] ?? '-') ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($data['events'] === []): ?><div class="ops-empty">決済イベントはまだありません。</div><?php endif; ?></div>
                </div>
            <?php else: ?><div class="ops-empty">左の一覧から請求を選択してください。</div><?php endif; ?>
        </section>
    </div>
</div>
<?php staff_layout_end();
