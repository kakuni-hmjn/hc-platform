<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/system-overview.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'staff.users.view');

$checks = staff_system_health();
$environment = staff_system_environment();
$revision = staff_system_revision();
$tableCount = 0;
$staffTableCount = 0;
try {
    $statement = staff_db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'");
    $tableCount = (int) ($statement->fetchColumn() ?: 0);
    $statement = staff_db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE 'staff_%'");
    $staffTableCount = (int) ($statement->fetchColumn() ?: 0);
} catch (Throwable $exception) {
    // ヘルスチェック側にDB状態が表示されるため、設定画面自体は継続する。
}
$canSystemSettings = staff_has_permission($staffContext, 'system.settings') || staff_can_access_admin($staffContext);
$settingGroups = [
    ['サイト表示', 'ヘッダー・メニュー・公開サービスを管理します。', 'web', [
        ['メニュー設定', '/staff/admin/site/menu/'], ['ヘッダー設定', '/staff/admin/site/header/'], ['サービス設定', '/staff/admin/site/services/'],
    ]],
    ['注文・決済', 'ゲームサーバー注文、プラン、請求の設定先です。', 'payments', [
        ['注文設定', '/staff/admin/services/order-settings/'], ['ゲームプラン', '/staff/rental-server/game-server/plans/'], ['Stripeプラン', '/staff/admin/billing/stripe-plans/'],
    ]],
    ['通知・連絡', '顧客向け通知とスタッフ向け連絡を管理します。', 'notifications', [
        ['サイト通知', '/staff/admin/site/notifications/'], ['ユーザー通知', '/staff/admin/site/user-notifications/'], ['社内連絡', '/staff/announcements/'],
    ]],
    ['スタッフ・業務', 'スタッフ権限と物品管理の設定先です。', 'groups', [
        ['スタッフ管理', '/staff/admin/users/'], ['有効権限', '/staff/account/permissions/'], ['物品管理設定', '/staff/property/settings/'],
    ]],
];

staff_layout_start([
    'title' => 'システム設定', 'heading' => 'システム設定', 'eyebrow' => 'SYSTEM SETTINGS',
    'description' => 'HC Platform全体の設定入口と、現在の実行環境・接続状態を確認します。',
]);
?>
<div class="ops-page">
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">実行環境</span><strong class="ops-card__text"><?= staff_ui_escape($environment) ?></strong><small>APP_ENV</small></article><article class="ops-card"><span class="ops-card__label">コード版</span><strong class="ops-card__text"><?= staff_ui_escape($revision) ?></strong><small>現在のリビジョン</small></article><article class="ops-card"><span class="ops-card__label">DBテーブル</span><strong><?= number_format($tableCount) ?></strong><small>うちStaff <?= number_format($staffTableCount) ?></small></article><article class="ops-card"><span class="ops-card__label">設定権限</span><strong class="ops-card__text"><?= $canSystemSettings ? '変更可能' : '閲覧のみ' ?></strong><small>システム設定権限</small></article></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>接続・保存状態</h3><p>秘密情報の値は表示しません。</p></div><a class="ops-button ops-button--secondary" href="/staff/settings/">再チェック</a></header><div class="ops-panel__body"><div class="ops-health-grid"><?php foreach ($checks as $check): ?><article class="ops-health <?= !empty($check['ok']) ? 'is-ok' : 'is-warning' ?>"><span class="material-icons"><?= !empty($check['ok']) ? 'check_circle' : 'warning' ?></span><span><strong><?= staff_ui_escape($check['label']) ?></strong><small><?= staff_ui_escape($check['detail']) ?></small></span></article><?php endforeach; ?></div></div></section>
    <section class="ops-panel"><header class="ops-panel__header"><div><h3>設定メニュー</h3><p>既存の設定機能を用途別にまとめています。</p></div></header><div class="ops-panel__body"><div class="ops-settings-grid"><?php foreach ($settingGroups as [$title, $description, $icon, $links]): ?><article class="ops-setting-card"><span class="material-icons"><?= staff_ui_escape($icon) ?></span><div><h4><?= staff_ui_escape($title) ?></h4><p><?= staff_ui_escape($description) ?></p><div class="ops-chip-list"><?php foreach ($links as [$label, $href]): ?><a class="ops-chip" href="<?= staff_ui_escape($href) ?>"><?= staff_ui_escape($label) ?></a><?php endforeach; ?></div></div></article><?php endforeach; ?></div></div></section>
    <?php if (!$canSystemSettings): ?><div class="ops-alert ops-alert--info">設定値の変更にはシステム設定権限が必要です。この画面では現在の状態と管理先だけを確認できます。</div><?php endif; ?>
</div>
<?php staff_layout_end();
