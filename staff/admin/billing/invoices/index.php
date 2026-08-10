<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$orders = [];
$selected = null;
$error = '';

function staff_invoice_price(int $amount, string $currency = 'jpy'): string
{
    return strtolower($currency) === 'jpy' ? '¥' . number_format($amount) : strtoupper($currency) . ' ' . number_format($amount);
}

function staff_invoice_number(array $order): string
{
    try { $date = (new DateTime((string) $order['created_at']))->format('Ymd'); }
    catch (Throwable $exception) { $date = date('Ymd'); }
    return 'HC-' . $date . '-' . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT);
}

try {
    $orders = $pdo->query(
        "SELECT gso.id, gso.user_id, gso.server_name, gso.status, gso.payment_status,
                gso.amount, gso.currency, gso.billing_type, gso.created_at, gso.paid_at,
                gsp.name AS plan_name, gsp.price_monthly, gsp.memory_mb, gsp.cpu_limit, gsp.disk_mb,
                u.username, u.email
         FROM game_server_orders gso
         JOIN game_server_plans gsp ON gsp.id = gso.plan_id
         LEFT JOIN users u ON u.id = gso.user_id
         ORDER BY gso.created_at DESC, gso.id DESC LIMIT 200"
    )->fetchAll() ?: [];
    if ($orderId <= 0 && $orders !== []) {
        $orderId = (int) $orders[0]['id'];
    }
    foreach ($orders as $order) {
        if ((int) $order['id'] === $orderId) { $selected = $order; break; }
    }
} catch (Throwable $exception) {
    $error = '請求情報を取得できませんでした。';
}

staff_layout_start([
    'title' => '請求書管理', 'heading' => '請求書管理', 'eyebrow' => 'BILLING / INVOICES',
    'description' => '全契約の請求情報を選択し、請求先・明細・支払い状態を確認します。',
]);
?>
<div class="ops-page admin-native-page">
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>
    <div class="ops-split">
        <section class="ops-list">
            <header class="ops-panel__header"><div><h3>請求一覧</h3><p><?= number_format(count($orders)) ?>件</p></div></header>
            <div class="ops-rows">
                <?php foreach ($orders as $order): ?><a class="ops-row <?= (int) $order['id'] === $orderId ? 'is-selected' : '' ?>" href="?order_id=<?= (int) $order['id'] ?>"><span><strong class="ops-row__title"><?= staff_ui_escape($order['server_name']) ?></strong><span class="ops-row__meta">#<?= (int) $order['id'] ?> / <?= staff_ui_escape($order['username'] ?: $order['email']) ?><br><?= staff_ui_escape($order['plan_name']) ?></span></span><span class="ops-row__end"><span class="ops-status ops-status--<?= staff_ui_escape($order['payment_status']) ?>"><?= staff_ui_escape($order['payment_status']) ?></span><span class="ops-row__meta"><?= staff_ui_escape(staff_invoice_price((int) ($order['amount'] ?: $order['price_monthly']), (string) $order['currency'])) ?></span></span></a><?php endforeach; ?>
                <?php if ($orders === []): ?><div class="ops-empty">請求対象の契約はありません。</div><?php endif; ?>
            </div>
        </section>
        <section class="ops-detail">
            <?php if ($selected): $amount = (int) ($selected['amount'] ?: $selected['price_monthly']); ?>
                <header class="ops-panel__header"><div><h3><?= staff_ui_escape(staff_invoice_number($selected)) ?></h3><p>契約 #<?= (int) $selected['id'] ?> の仮請求書</p></div><span class="ops-status ops-status--<?= staff_ui_escape($selected['payment_status']) ?>"><?= staff_ui_escape($selected['payment_status']) ?></span></header>
                <div class="ops-panel__body admin-native-invoice">
                    <dl class="ops-kv"><div><dt>請求先</dt><dd><?= staff_ui_escape($selected['username'] ?: '不明') ?><br><?= staff_ui_escape($selected['email'] ?: '-') ?></dd></div><div><dt>契約状態</dt><dd><?= staff_ui_escape($selected['status']) ?></dd></div><div><dt>発行日</dt><dd><?= staff_ui_escape(staff_administration_datetime($selected['created_at'])) ?></dd></div><div><dt>支払日</dt><dd><?= staff_ui_escape(staff_administration_datetime($selected['paid_at'] ?? null)) ?></dd></div></dl>
                    <div class="admin-native-invoice__item"><div><strong><?= staff_ui_escape($selected['plan_name']) ?></strong><span><?= staff_ui_escape($selected['server_name']) ?> / <?= number_format((int) $selected['memory_mb'] / 1024, 1) ?>GB / <?= number_format((int) $selected['disk_mb'] / 1024) ?>GB</span></div><strong><?= staff_ui_escape(staff_invoice_price($amount, (string) $selected['currency'])) ?></strong></div>
                    <dl class="admin-native-invoice__total"><dt>合計</dt><dd><?= staff_ui_escape(staff_invoice_price($amount, (string) $selected['currency'])) ?></dd></dl>
                    <div class="ops-form-actions"><a class="ops-button ops-button--secondary" href="/staff/rental-server/game-server/contracts/?id=<?= (int) $selected['id'] ?>">契約を確認</a><a class="ops-button" href="/staff/billing/?id=<?= (int) $selected['id'] ?>">決済履歴を確認</a></div>
                </div>
            <?php else: ?><div class="ops-empty">左の一覧から請求を選択してください。</div><?php endif; ?>
        </section>
    </div>
</div>
<?php staff_layout_end();
