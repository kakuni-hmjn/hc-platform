<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/system-overview.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'infrastructure.servers.view');

$data = staff_infrastructure_load();
$memoryGb = (int) round($data['counts']['memory_mb'] / 1024);
$diskGb = (int) round($data['counts']['disk_mb'] / 1024);
$utilization = $data['counts']['nodes'] > 0 ? (int) round(($data['counts']['active_nodes'] / $data['counts']['nodes']) * 100) : 0;

staff_layout_start([
    'title' => 'システム状態', 'heading' => 'システム状態', 'eyebrow' => 'INFRASTRUCTURE STATUS',
    'description' => 'Node、仮想サーバー、登録リソースの稼働状況を確認します。',
]);
?>
<div class="ops-page">
    <?php foreach ($data['errors'] as $loadError): ?><div class="ops-alert"><?= staff_ui_escape($loadError) ?></div><?php endforeach; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">Node</span><strong><?= number_format($data['counts']['active_nodes']) ?>/<?= number_format($data['counts']['nodes']) ?></strong><small>稼働中 / 登録数</small></article><article class="ops-card"><span class="ops-card__label">仮想サーバー</span><strong><?= number_format($data['counts']['active_servers']) ?>/<?= number_format($data['counts']['servers']) ?></strong><small>稼働中 / 登録数</small></article><article class="ops-card"><span class="ops-card__label">登録メモリ</span><strong><?= number_format($memoryGb) ?> GB</strong><small>全Node合計</small></article><article class="ops-card"><span class="ops-card__label">登録ディスク</span><strong><?= number_format($diskGb) ?> GB</strong><small>全Node合計</small></article></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>Node稼働状況</h3><p>稼働率 <?= number_format($utilization) ?>% · Pterodactylへ登録されたホスト</p></div><a class="ops-button" href="/staff/infrastructure/servers/">サーバー一覧</a></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>Node</th><th>接続先</th><th>CPU</th><th>メモリ</th><th>ディスク</th><th>仮想サーバー</th><th>状態</th></tr></thead><tbody><?php foreach ($data['nodes'] as $node): ?><tr><td><strong><?= staff_ui_escape($node['label'] ?: $node['name']) ?></strong><br><span class="ops-muted">Ptero #<?= (int) $node['ptero_node_id'] ?></span></td><td><?= staff_ui_escape($node['fqdn'] ?: '-') ?></td><td><?= staff_ui_escape($node['cpu_type'] ?: '-') ?><?= !empty($node['is_high_performance']) ? '<br><span class="ops-muted">高性能</span>' : '' ?></td><td><?= number_format((int) $node['memory_mb']) ?> MB</td><td><?= number_format((int) $node['disk_mb']) ?> MB</td><td><?= number_format((int) $node['server_count']) ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($node['status']) ?>"><?= staff_ui_escape($node['status'] === 'active' ? '稼働中' : $node['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php if ($data['nodes'] === []): ?><div class="ops-empty">Nodeはまだ登録されていません。</div><?php endif; ?></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>運用導線</h3><p>日常の確認先をまとめています。</p></div></header><div class="ops-panel__body"><div class="ops-component-grid"><a class="ops-component" href="/staff/rental-server/game-server/provisioning/"><span class="material-icons">precision_manufacturing</span><span><strong>Provisioning</strong><small>作成ジョブと失敗内容</small></span></a><a class="ops-component" href="/staff/rental-server/game-server/nodes/"><span class="material-icons">hub</span><span><strong>Node管理</strong><small>プラン割当と容量</small></span></a><a class="ops-component" href="/staff/rental-server/game-server/servers/"><span class="material-icons">dns</span><span><strong>ゲームサーバー</strong><small>契約とPterodactyl情報</small></span></a></div></div></section>
</div>
<?php staff_layout_end();

