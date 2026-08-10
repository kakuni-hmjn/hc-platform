<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/system-overview.php';
require_once dirname(__DIR__) . '/lib/operations.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'development.projects.view');

$data = staff_development_overview_load();
$phaseLabels = ['planned' => '計画中', 'developing' => '開発中', 'testing' => '検証中', 'active' => '提供中', 'released' => '公開済み'];
$statusLabels = ['draft' => '非公開', 'published' => '公開中', 'archived' => '終了'];
$components = [
    ['HC Account', '認証・顧客アカウント基盤', '/staff/customers/', 'active'],
    ['Staff Console', 'スタッフ業務と権限管理', '/staff/', 'active'],
    ['お問い合わせ', 'チャット・メール対応', '/staff/support/', 'active'],
    ['Game Server', '契約・決済・自動作成', '/staff/rental-server/game-server/', 'active'],
    ['物品管理', '在庫・棚・貸出管理', '/staff/property/', 'active'],
];

staff_layout_start([
    'title' => 'プロジェクト', 'heading' => 'プロジェクト', 'eyebrow' => 'DEVELOPMENT PROJECTS',
    'description' => 'HC Platform内の事業・主要機能・公開状態をまとめて確認します。',
]);
?>
<div class="ops-page">
    <?php foreach ($data['errors'] as $loadError): ?><div class="ops-alert"><?= staff_ui_escape($loadError) ?></div><?php endforeach; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">事業・サービス</span><strong><?= number_format($data['counts']['services']) ?></strong><small>登録プロジェクト</small></article><article class="ops-card"><span class="ops-card__label">公開中</span><strong><?= number_format($data['counts']['published']) ?></strong><small>サイトへ掲載</small></article><article class="ops-card"><span class="ops-card__label">計画中</span><strong><?= number_format($data['counts']['planned']) ?></strong><small>準備段階</small></article><article class="ops-card"><span class="ops-card__label">公開ニュース</span><strong><?= number_format($data['counts']['news']) ?></strong><small>開発・事業告知</small></article></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>サービスプロジェクト</h3><p>公開サイトのサービス情報と連動しています。</p></div><?php if (staff_can_access_admin($staffContext)): ?><a class="ops-button ops-button--secondary" href="/staff/admin/site/services/">サービス編集</a><?php endif; ?></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>サービス</th><th>概要</th><th>開発段階</th><th>公開状態</th><th>詳細ページ</th><th>更新日時</th></tr></thead><tbody><?php foreach ($data['services'] as $service): ?><tr><td><strong><?= staff_ui_escape($service['title']) ?></strong><br><span class="ops-muted"><?= staff_ui_escape($service['slug']) ?></span></td><td><?= staff_ui_escape($service['summary']) ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($service['service_phase']) ?>"><?= staff_ui_escape($phaseLabels[(string) $service['service_phase']] ?? $service['service_phase']) ?></span></td><td><span class="ops-status ops-status--<?= staff_ui_escape($service['status']) ?>"><?= staff_ui_escape($statusLabels[(string) $service['status']] ?? $service['status']) ?></span></td><td><?php if (!empty($service['has_detail_page']) && !empty($service['detail_url'])): ?><a href="<?= staff_ui_escape($service['detail_url']) ?>">開く</a><?php else: ?>なし<?php endif; ?></td><td><?= staff_ui_escape(staff_ops_datetime($service['updated_at'] ?? $service['created_at'] ?? null)) ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($data['services'] === []): ?><div class="ops-empty">サービスプロジェクトは未登録です。</div><?php endif; ?></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>主要コンポーネント</h3><p>スタッフ業務から利用できる機能の入口です。</p></div></header><div class="ops-panel__body"><div class="ops-component-grid"><?php foreach ($components as [$name, $description, $href, $status]): ?><a class="ops-component" href="<?= staff_ui_escape($href) ?>"><span class="material-icons">check_circle</span><span><strong><?= staff_ui_escape($name) ?></strong><small><?= staff_ui_escape($description) ?></small></span><span class="ops-status ops-status--<?= $status ?>">利用可能</span></a><?php endforeach; ?></div></div></section>
    <div class="ops-form-actions"><?php if (staff_has_permission($staffContext, 'development.deploy.staging') || staff_can_access_admin($staffContext)): ?><a class="ops-button" href="/staff/deployments/">デプロイ状態を確認</a><?php endif; ?><?php if (staff_can_access_admin($staffContext)): ?><a class="ops-button ops-button--secondary" href="/staff/admin/site/news/">ニュース編集</a><?php endif; ?></div>
</div>
<?php staff_layout_end();
