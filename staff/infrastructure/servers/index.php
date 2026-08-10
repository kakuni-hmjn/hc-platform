<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/system-overview.php';
require_once dirname(__DIR__, 2) . '/lib/operations.php';
require_once dirname(__DIR__, 2) . '/components/layout.php';
require_once dirname(__DIR__, 2) . '/components/ui.php';

staff_require_permission($staffContext, 'infrastructure.servers.view');

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
if (in_array($status, ['all', 'any', '*'], true)) {
    $status = '';
}
$selectedId = max(0, (int) ($_GET['id'] ?? 0));
$data = staff_infrastructure_load($search, $status, $selectedId);
$selected = $data['selected'];
$baseParams = array_filter(['q' => $search, 'status' => $status], static fn ($value): bool => $value !== '');

staff_layout_start([
    'title' => '物理・仮想サーバー', 'heading' => '物理・仮想サーバー', 'eyebrow' => 'SERVER INVENTORY',
    'description' => 'Pterodactyl Nodeを物理ホスト、作成済みサーバーを仮想サーバーとして横断確認します。',
]);
?>
<div class="ops-page">
    <?php foreach ($data['errors'] as $loadError): ?><div class="ops-alert"><?= staff_ui_escape($loadError) ?></div><?php endforeach; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">物理Node</span><strong><?= number_format($data['counts']['nodes']) ?></strong><small>Pterodactylホスト</small></article><article class="ops-card"><span class="ops-card__label">稼働Node</span><strong><?= number_format($data['counts']['active_nodes']) ?></strong><small>active状態</small></article><article class="ops-card"><span class="ops-card__label">仮想サーバー</span><strong><?= number_format($data['counts']['servers']) ?></strong><small>削除済みを除く</small></article><article class="ops-card"><span class="ops-card__label">稼働サーバー</span><strong><?= number_format($data['counts']['active_servers']) ?></strong><small>提供中</small></article></section>
    <form class="ops-toolbar" method="get"><label class="ops-toolbar__field"><span class="ops-label">検索</span><input class="ops-input" type="search" name="q" value="<?= staff_ui_escape($search) ?>" placeholder="サーバー名・顧客・Node"></label><label class="ops-toolbar__field"><span class="ops-label">状態</span><select class="ops-select" name="status"><option value="">すべて</option><?php foreach (['active' => '稼働中', 'suspended' => '停止中', 'provisioning' => '作成中', 'failed' => 'エラー'] as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="ops-button" type="submit">検索</button><a class="ops-button ops-button--secondary" href="/staff/infrastructure/servers/">クリア</a></form>
    <div class="ops-split">
        <section class="ops-list"><header class="ops-panel__header"><div><h3>仮想サーバー</h3><p><?= number_format(count($data['servers'])) ?>件を表示</p></div></header><div class="ops-rows"><?php foreach ($data['servers'] as $server): $params = $baseParams + ['id' => (int) $server['id']]; ?><a class="ops-row <?= (int) ($selected['id'] ?? 0) === (int) $server['id'] ? 'is-selected' : '' ?>" href="?<?= staff_ui_escape(http_build_query($params)) ?>"><span><strong class="ops-row__title"><?= staff_ui_escape($server['name']) ?></strong><span class="ops-row__meta"><?= staff_ui_escape($server['username'] ?: $server['email'] ?: '顧客不明') ?><br><?= staff_ui_escape($server['node_label'] ?: 'Node未割当') ?> · <?= staff_ui_escape($server['ptero_identifier'] ?: 'ID未発行') ?></span></span><span class="ops-row__end"><span class="ops-status ops-status--<?= staff_ui_escape($server['status']) ?>"><?= staff_ui_escape($server['status'] === 'active' ? '稼働中' : $server['status']) ?></span><span class="ops-row__meta">#<?= (int) $server['id'] ?></span></span></a><?php endforeach; ?><?php if ($data['servers'] === []): ?><div class="ops-empty">条件に一致するサーバーはありません。</div><?php endif; ?></div></section>
        <section class="ops-detail"><?php if (is_array($selected)): ?><header class="ops-panel__header"><div><h3><?= staff_ui_escape($selected['name']) ?></h3><p>仮想サーバー #<?= (int) $selected['id'] ?></p></div><span class="ops-status ops-status--<?= staff_ui_escape($selected['status']) ?>"><?= staff_ui_escape($selected['status'] === 'active' ? '稼働中' : $selected['status']) ?></span></header><div class="ops-panel__body"><dl class="ops-kv"><div><dt>顧客</dt><dd><a href="/staff/customers/?id=<?= (int) $selected['user_id'] ?>"><?= staff_ui_escape($selected['username'] ?: $selected['email'] ?: '-') ?></a></dd></div><div><dt>契約</dt><dd><a href="/staff/rental-server/game-server/contracts/?id=<?= (int) $selected['order_id'] ?>">#<?= (int) $selected['order_id'] ?></a></dd></div><div><dt>Node</dt><dd><?= staff_ui_escape($selected['node_label'] ?: '-') ?></dd></div><div><dt>接続先</dt><dd><?= staff_ui_escape($selected['fqdn'] ?: '-') ?></dd></div><div><dt>プラン</dt><dd><?= staff_ui_escape($selected['plan_name'] ?: '-') ?></dd></div><div><dt>Identifier</dt><dd><?= staff_ui_escape($selected['ptero_identifier'] ?: '-') ?></dd></div><div><dt>メモリ</dt><dd><?= number_format((int) ($selected['memory_mb'] ?? 0)) ?> MB</dd></div><div><dt>CPU / ディスク</dt><dd><?= number_format((int) ($selected['cpu_limit'] ?? 0)) ?>% / <?= number_format((int) ($selected['disk_mb'] ?? 0)) ?> MB</dd></div><div><dt>作成日時</dt><dd><?= staff_ui_escape(staff_ops_datetime($selected['created_at'] ?? null)) ?></dd></div><div><dt>Pterodactyl ID</dt><dd><?= staff_ui_escape($selected['ptero_server_id'] ?: '-') ?></dd></div></dl><div class="ops-form-actions"><a class="ops-button" href="/staff/rental-server/game-server/contracts/?id=<?= (int) $selected['order_id'] ?>">契約・サーバー詳細を開く</a></div></div><?php else: ?><div class="ops-empty">左の一覧からサーバーを選択してください。</div><?php endif; ?></section>
    </div>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>物理Node</h3><p>登録ホストと容量</p></div></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>Node</th><th>FQDN</th><th>CPU</th><th>メモリ</th><th>ディスク</th><th>サーバー数</th><th>状態</th></tr></thead><tbody><?php foreach ($data['nodes'] as $node): ?><tr><td><?= staff_ui_escape($node['label'] ?: $node['name']) ?></td><td><?= staff_ui_escape($node['fqdn'] ?: '-') ?></td><td><?= staff_ui_escape($node['cpu_type'] ?: '-') ?></td><td><?= number_format((int) $node['memory_mb']) ?> MB</td><td><?= number_format((int) $node['disk_mb']) ?> MB</td><td><?= number_format((int) $node['server_count']) ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($node['status']) ?>"><?= staff_ui_escape($node['status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>
</div>
<?php staff_layout_end();
