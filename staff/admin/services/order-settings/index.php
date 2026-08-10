<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 4) . '/lib/order_access.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();
hc_order_settings_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('操作の有効期限が切れました。もう一度お試しください。');
        }
        $serviceKey = trim((string) ($_POST['service_key'] ?? ''));
        if ($serviceKey === '') {
            throw new RuntimeException('サービスキーが不正です。');
        }
        $isEnabled = (string) ($_POST['is_enabled'] ?? '0') === '1';
        $disabledMessage = trim((string) ($_POST['disabled_message'] ?? ''));
        $adminMemo = trim((string) ($_POST['admin_memo'] ?? ''));
        if (!$isEnabled && $disabledMessage === '') {
            $disabledMessage = '現在、新規申込受付を一時停止しています。メンテナンス完了後に再度お試しください。';
        }
        hc_order_update_setting($pdo, $serviceKey, $isEnabled, $disabledMessage, $adminMemo, $staffAccountId);
        staff_administration_flash('success', '申込受付設定を保存しました。');
    } catch (Throwable $exception) {
        staff_administration_flash('error', $exception->getMessage());
    }
    staff_administration_redirect('/staff/admin/services/order-settings/');
}

$settings = [hc_order_get_setting($pdo, 'game_server')];
$flash = staff_administration_take_flash();

staff_layout_start([
    'title' => '申込受付設定', 'heading' => '申込受付設定', 'eyebrow' => 'SERVICES / ORDER CONTROL',
    'description' => 'サービスごとの新規申込受付と、停止中に表示する案内を管理します。',
]);
?>
<div class="ops-page admin-native-page">
    <?php if ($flash): ?><div class="ops-alert <?= $flash['type'] === 'success' ? 'ops-alert--success' : '' ?>"><?= staff_ui_escape($flash['message']) ?></div><?php endif; ?>
    <div class="ops-form-actions"><a class="ops-button ops-button--secondary" href="/order/game-server/">申込ページを確認</a></div>
    <?php foreach ($settings as $setting): $enabled = hc_order_bool_value($setting['is_enabled'] ?? true); ?>
        <section class="ops-panel">
            <header class="ops-panel__header"><div><h3><?= staff_ui_escape($setting['service_name']) ?></h3><p>サービスキー: <?= staff_ui_escape($setting['service_key']) ?> / 更新: <?= staff_ui_escape(staff_administration_datetime($setting['updated_at'] ?? null)) ?></p></div><span class="ops-status ops-status--<?= $enabled ? 'active' : 'suspended' ?>"><?= $enabled ? '受付中' : '受付停止中' ?></span></header>
            <div class="ops-panel__body">
                <form method="post" class="admin-native-form">
                    <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                    <input type="hidden" name="service_key" value="<?= staff_ui_escape($setting['service_key']) ?>">
                    <div class="ops-form-grid">
                        <label><span class="ops-label">申込受付</span><select class="ops-select" name="is_enabled"><option value="1" <?= $enabled ? 'selected' : '' ?>>受付する</option><option value="0" <?= !$enabled ? 'selected' : '' ?>>受付停止</option></select></label>
                        <label><span class="ops-label">管理メモ</span><textarea class="ops-textarea" name="admin_memo" rows="3"><?= staff_ui_escape($setting['admin_memo'] ?? '') ?></textarea></label>
                    </div>
                    <label class="admin-native-field"><span class="ops-label">受付停止中メッセージ</span><textarea class="ops-textarea" name="disabled_message" rows="4"><?= staff_ui_escape($setting['disabled_message'] ?? '') ?></textarea></label>
                    <div class="ops-form-actions"><button class="ops-button" type="submit">設定を保存</button></div>
                </form>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php staff_layout_end();
