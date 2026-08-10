<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/system-overview.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'development.deploy.staging');

$checks = staff_system_health();
$healthyCount = count(array_filter($checks, static fn (array $check): bool => !empty($check['ok'])));
$revision = staff_system_revision();
$environment = staff_system_environment();
$canProduction = staff_has_permission($staffContext, 'development.deploy.production') || staff_can_access_admin($staffContext);

staff_layout_start([
    'title' => 'デプロイ', 'heading' => 'デプロイ', 'eyebrow' => 'DEPLOYMENTS',
    'description' => '現在稼働中の環境、コード版、外部連携の準備状態を確認します。',
]);
?>
<div class="ops-page">
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">現在の環境</span><strong class="ops-card__text"><?= staff_ui_escape($environment) ?></strong><small>アプリ実行環境</small></article><article class="ops-card"><span class="ops-card__label">コード版</span><strong class="ops-card__text"><?= staff_ui_escape($revision) ?></strong><small>現在のリビジョン</small></article><article class="ops-card"><span class="ops-card__label">正常チェック</span><strong><?= number_format($healthyCount) ?>/<?= number_format(count($checks)) ?></strong><small>接続・保存先</small></article><article class="ops-card"><span class="ops-card__label">本番権限</span><strong class="ops-card__text"><?= $canProduction ? 'あり' : 'なし' ?></strong><small>本番反映の実行権限</small></article></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>リリース前チェック</h3><p>秘密情報の値は表示せず、設定の有無だけを確認します。</p></div><a class="ops-button ops-button--secondary" href="/staff/deployments/">再チェック</a></header><div class="ops-panel__body"><div class="ops-health-grid"><?php foreach ($checks as $check): ?><article class="ops-health <?= !empty($check['ok']) ? 'is-ok' : 'is-warning' ?>"><span class="material-icons"><?= !empty($check['ok']) ? 'check_circle' : 'warning' ?></span><span><strong><?= staff_ui_escape($check['label']) ?></strong><small><?= staff_ui_escape($check['detail']) ?></small></span></article><?php endforeach; ?></div></div></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>反映先</h3><p>デプロイ実行は接続済みのCI/CDで行います。</p></div></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>環境</th><th>用途</th><th>実行権限</th><th>状態</th><th>実行方法</th></tr></thead><tbody><tr><td>開発</td><td>ローカル検証</td><td>開発権限</td><td><span class="ops-status ops-status--active">現在の環境</span></td><td>ワークスペースへ反映</td></tr><tr><td>ステージング</td><td>公開前検証</td><td>ステージングデプロイ</td><td><span class="ops-status ops-status--pending">CI連携待ち</span></td><td>外部パイプライン</td></tr><tr><td>本番</td><td>顧客向け環境</td><td><?= $canProduction ? '本番デプロイ' : '権限なし' ?></td><td><span class="ops-status ops-status--draft">画面からは実行不可</span></td><td>承認済みパイプライン</td></tr></tbody></table></div></section>
    <div class="ops-alert ops-alert--info">この画面は状態確認専用です。CI/CD接続先が未登録のため、誤操作を防ぐ目的でデプロイ実行ボタンは表示していません。</div>
</div>
<?php staff_layout_end();
