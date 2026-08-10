<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'orders.view');
$data = staff_rental_server_dashboard_load();

staff_layout_start([
    'title' => 'レンタルサーバー',
    'heading' => 'レンタルサーバー',
    'eyebrow' => 'RENTAL SERVER BUSINESS',
    'description' => '契約、稼働状況、売上、対応が必要な申込をまとめて確認します。',
]);
?>
<div class="ops-page">
    <?php foreach ($data['errors'] as $loadError): ?><div class="ops-alert"><?= staff_ui_escape($loadError) ?></div><?php endforeach; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">全申込・契約</span><strong><?= number_format($data['counts']['total']) ?></strong><small>ゲームサーバー事業</small></article><article class="ops-card"><span class="ops-card__label">稼働中</span><strong><?= number_format($data['counts']['active']) ?></strong><small>提供中の契約</small></article><article class="ops-card"><span class="ops-card__label">対応待ち</span><strong><?= number_format($data['counts']['paid'] + $data['counts']['creating'] + $data['counts']['pending_approval']) ?></strong><small>作成・承認が必要</small></article><article class="ops-card"><span class="ops-card__label">月額売上見込み</span><strong><?= staff_ui_escape(staff_rental_price($data['revenue']['monthly_estimate'])) ?></strong><small>稼働中契約の合計</small></article></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>ゲームサーバー管理</h3><p>申込受付から承認、Provisioning、稼働確認まで管理します。</p></div><a class="ops-button" href="/staff/rental-server/game-server/">管理画面を開く</a></header><div class="ops-panel__body"><div class="ops-summary"><a class="ops-card" href="/staff/rental-server/game-server/contracts/"><span class="ops-card__label">申込・契約</span><strong><?= number_format($data['counts']['total']) ?></strong><small>一覧と顧客詳細</small></a><a class="ops-card" href="/staff/rental-server/game-server/approvals/"><span class="ops-card__label">承認待ち</span><strong><?= number_format($data['counts']['pending_approval']) ?></strong><small>審査が必要</small></a><a class="ops-card" href="/staff/rental-server/game-server/servers/"><span class="ops-card__label">提供中</span><strong><?= number_format($data['counts']['active']) ?></strong><small>Pterodactylサーバー</small></a><a class="ops-card" href="/staff/rental-server/game-server/plans/"><span class="ops-card__label">公開プラン</span><strong><?= number_format($data['counts']['plans']) ?></strong><small><?= number_format($data['counts']['nodes']) ?> Node</small></a></div></div></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>その他のレンタルサービス</h3><p>提供準備状況と必要な基盤を確認します。</p></div></header><div class="ops-panel__body"><div class="ops-component-grid"><a class="ops-component" href="/staff/rental-server/vps/"><span class="material-icons">memory</span><span><strong>VPS</strong><small>仮想マシン型サーバー</small></span><span class="ops-status ops-status--planned">準備中</span></a><a class="ops-component" href="/staff/rental-server/hosting/"><span class="material-icons">language</span><span><strong>ホスティング</strong><small>Webサイト・アプリ基盤</small></span><span class="ops-status ops-status--planned">準備中</span></a><a class="ops-component" href="/staff/rental-server/dedicated/"><span class="material-icons">dns</span><span><strong>専用サーバー</strong><small>物理サーバー専有</small></span><span class="ops-status ops-status--planned">準備中</span></a><a class="ops-component" href="/staff/rental-server/colocation/"><span class="material-icons">domain</span><span><strong>コロケーション</strong><small>機器預かり・設置</small></span><span class="ops-status ops-status--planned">準備中</span></a></div></div></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>最新の申込・契約</h3><p>新しい順に8件を表示</p></div><a class="ops-button ops-button--secondary" href="/staff/rental-server/game-server/contracts/">すべて表示</a></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>ID</th><th>顧客</th><th>サーバー</th><th>プラン</th><th>契約状態</th><th>決済</th><th>金額</th><th>受付日時</th></tr></thead><tbody><?php foreach ($data['latest_orders'] as $order): ?><tr><td><a href="/staff/rental-server/game-server/contracts/?id=<?= (int) $order['id'] ?>">#<?= (int) $order['id'] ?></a></td><td><?= staff_ui_escape($order['username'] ?? $order['email'] ?? '-') ?></td><td><?= staff_ui_escape($order['server_name']) ?></td><td><?= staff_ui_escape($order['plan_name'] ?? '-') ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($order['status']) ?>"><?= staff_ui_escape(staff_rental_order_status_label((string) $order['status'])) ?></span></td><td><?= staff_ui_escape(staff_rental_payment_status_label((string) $order['payment_status'])) ?></td><td><?= staff_ui_escape(staff_rental_price($order['amount'], $order['currency'])) ?></td><td><?= staff_ui_escape(staff_rental_datetime($order['created_at'] ?? null)) ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($data['latest_orders'] === []): ?><div class="ops-empty">申込はまだありません。</div><?php endif; ?></section>
</div>
<?php staff_layout_end();
