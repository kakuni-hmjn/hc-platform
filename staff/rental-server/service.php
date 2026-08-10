<?php

declare(strict_types=1);

$rentalServiceType = isset($rentalServiceType) ? (string) $rentalServiceType : 'vps';

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/system-overview.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'orders.view');

$config = [
    'vps' => ['VPS', 'VIRTUAL PRIVATE SERVER', 'memory', '仮想マシン型サーバーの提供準備状況を確認します。'],
    'hosting' => ['ホスティング', 'WEB HOSTING', 'language', 'Webサイト・アプリケーション向けホスティングの提供準備状況を確認します。'],
    'dedicated' => ['専用サーバー', 'DEDICATED SERVER', 'dns', '物理サーバー専有サービスの提供準備状況を確認します。'],
    'colocation' => ['コロケーション', 'COLOCATION', 'domain', '機器預かり・設置サービスの提供準備状況を確認します。'],
];
$current = $config[$rentalServiceType] ?? $config['vps'];
$infrastructure = staff_infrastructure_load();
$catalog = null;
try {
    $statement = staff_db()->prepare("SELECT * FROM services WHERE slug = 'infrastructure' LIMIT 1");
    $statement->execute();
    $row = $statement->fetch();
    $catalog = is_array($row) ? $row : null;
} catch (Throwable $exception) {
    $catalog = null;
}
$phase = (string) ($catalog['service_phase'] ?? 'planned');
$phaseLabels = ['planned' => '提供準備中', 'developing' => '開発中', 'testing' => '検証中', 'active' => '提供中', 'released' => '提供中'];
$requirements = match ($rentalServiceType) {
    'hosting' => [['基盤ホスト', 'Node設計・Web実行環境'], ['ドメイン', 'SSL・DNS運用'], ['バックアップ', '世代管理と復元手順']],
    'dedicated' => [['物理在庫', '機種・構成・保管場所'], ['ネットワーク', 'IP・帯域・監視'], ['保守', '交換部品・障害対応']],
    'colocation' => [['ラック', '設置位置・空き状況'], ['電源', '容量・冗長化'], ['入退室', '作業申請・記録']],
    default => [['仮想化基盤', 'ホスト・テンプレート'], ['ネットワーク', 'IP・Firewall'], ['バックアップ', 'スナップショット・復元']],
};

staff_layout_start(['title' => $current[0], 'heading' => $current[0], 'eyebrow' => $current[1], 'description' => $current[3]]);
?>
<div class="ops-page">
    <nav class="ops-tabs" aria-label="レンタルサーバー事業"><a href="/staff/rental-server/">事業概要</a><a href="/staff/rental-server/game-server/">ゲームサーバー</a><?php foreach ($config as $key => $item): ?><a class="<?= $key === $rentalServiceType ? 'is-active' : '' ?>" href="/staff/rental-server/<?= staff_ui_escape($key) ?>/"><?= staff_ui_escape($item[0]) ?></a><?php endforeach; ?></nav>
    <?php foreach ($infrastructure['errors'] as $loadError): ?><div class="ops-alert"><?= staff_ui_escape($loadError) ?></div><?php endforeach; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">事業段階</span><strong class="ops-card__text"><?= staff_ui_escape($phaseLabels[$phase] ?? $phase) ?></strong><small>公開サービス情報と連動</small></article><article class="ops-card"><span class="ops-card__label">利用可能Node</span><strong><?= number_format($infrastructure['counts']['active_nodes']) ?></strong><small>共通インフラ候補</small></article><article class="ops-card"><span class="ops-card__label">登録メモリ</span><strong><?= number_format((int) round($infrastructure['counts']['memory_mb'] / 1024)) ?> GB</strong><small>全Node合計</small></article><article class="ops-card"><span class="ops-card__label">登録ディスク</span><strong><?= number_format((int) round($infrastructure['counts']['disk_mb'] / 1024)) ?> GB</strong><small>全Node合計</small></article></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3><?= staff_ui_escape($current[0]) ?> 管理概要</h3><p><?= staff_ui_escape($catalog['summary'] ?? '提供に必要な設備・運用項目を確認します。') ?></p></div><span class="ops-status ops-status--<?= staff_ui_escape($phase) ?>"><?= staff_ui_escape($phaseLabels[$phase] ?? $phase) ?></span></header><div class="ops-panel__body"><div class="ops-component-grid"><?php foreach ($requirements as [$title, $description]): ?><article class="ops-component"><span class="material-icons"><?= staff_ui_escape($current[2]) ?></span><span><strong><?= staff_ui_escape($title) ?></strong><small><?= staff_ui_escape($description) ?></small></span><span class="ops-status ops-status--pending">要準備</span></article><?php endforeach; ?></div></div></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>管理導線</h3><p>提供開始に必要な共通管理機能です。</p></div></header><div class="ops-panel__body"><div class="ops-component-grid"><a class="ops-component" href="/staff/infrastructure/servers/"><span class="material-icons">storage</span><span><strong>物理・仮想サーバー</strong><small>Nodeと稼働サーバー</small></span></a><a class="ops-component" href="/staff/property/"><span class="material-icons">inventory_2</span><span><strong>物品管理センター</strong><small>機器・ラック・所在</small></span></a><a class="ops-component" href="/staff/development/"><span class="material-icons">code</span><span><strong>プロジェクト</strong><small>サービス公開状態</small></span></a></div></div></section>
    <div class="ops-alert ops-alert--info">管理画面は利用できますが、このサービス自体はまだ受注・契約テーブルへ接続されていません。提供開始時にゲームサーバーと同じ契約・請求フローを接続します。</div>
</div>
<?php staff_layout_end();

