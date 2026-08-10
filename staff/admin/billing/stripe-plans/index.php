<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 4) . '/lib/stripe.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();
$pdo->exec("ALTER TABLE game_server_plans
    ADD COLUMN IF NOT EXISTS stripe_product_id VARCHAR(120),
    ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(120),
    ADD COLUMN IF NOT EXISTS stripe_sync_status VARCHAR(40) NOT NULL DEFAULT 'not_synced',
    ADD COLUMN IF NOT EXISTS stripe_synced_at TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS stripe_sync_error TEXT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = (int) ($_POST['plan_id'] ?? 0);
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) { throw new RuntimeException('操作の有効期限が切れました。'); }
        if ($planId <= 0) { throw new RuntimeException('プランが不正です。'); }
        $action = (string) ($_POST['action'] ?? 'sync_missing');
        if (!in_array($action, ['sync_missing', 'new_price'], true)) { throw new RuntimeException('同期方法が不正です。'); }
        $stmt = $pdo->prepare('SELECT id, name, slug, description, price_monthly, status, stripe_product_id, stripe_price_id FROM game_server_plans WHERE id = :id');
        $stmt->execute(['id' => $planId]); $plan = $stmt->fetch();
        if (!$plan || (int) $plan['price_monthly'] <= 0) { throw new RuntimeException('同期できるプランが見つかりません。'); }
        $productId = trim((string) ($plan['stripe_product_id'] ?? ''));
        $priceId = trim((string) ($plan['stripe_price_id'] ?? ''));
        if ($productId === '') {
            $product = hc_stripe_create_product([
                'name' => 'HC Game Server - ' . $plan['name'], 'description' => (string) ($plan['description'] ?: 'HC Platform game server plan'),
                'metadata' => ['hc_plan_id' => (string) $plan['id'], 'hc_plan_slug' => (string) $plan['slug'], 'hc_service' => 'game_server'],
            ], 'hc_plan_product_' . $plan['id']);
            $productId = (string) $product['id'];
        }
        if ($action === 'new_price' || $priceId === '') {
            $key = $action === 'new_price' ? 'hc_plan_price_' . $plan['id'] . '_' . $plan['price_monthly'] . '_monthly_' . bin2hex(random_bytes(8)) : 'hc_plan_price_' . $plan['id'] . '_' . $plan['price_monthly'] . '_monthly';
            $price = hc_stripe_create_price([
                'product' => $productId, 'unit_amount' => (int) $plan['price_monthly'], 'currency' => hc_stripe_currency(),
                'recurring' => ['interval' => 'month', 'interval_count' => 1], 'nickname' => 'HC ' . $plan['name'] . ' monthly',
                'metadata' => ['hc_plan_id' => (string) $plan['id'], 'hc_plan_slug' => (string) $plan['slug'], 'hc_service' => 'game_server', 'hc_billing_type' => 'monthly'],
            ], $key);
            $priceId = (string) $price['id'];
        }
        $update = $pdo->prepare("UPDATE game_server_plans SET stripe_product_id = :product, stripe_price_id = :price, stripe_sync_status = 'synced', stripe_synced_at = NOW(), stripe_sync_error = NULL, updated_at = NOW() WHERE id = :id");
        $update->execute(['id' => $planId, 'product' => $productId, 'price' => $priceId]);
        staff_administration_flash('success', 'Stripe連携を完了しました。Product: ' . $productId . ' / Price: ' . $priceId);
    } catch (Throwable $exception) {
        if ($planId > 0) {
            try { $failed = $pdo->prepare("UPDATE game_server_plans SET stripe_sync_status = 'failed', stripe_sync_error = :error, stripe_synced_at = NOW(), updated_at = NOW() WHERE id = :id"); $failed->execute(['id' => $planId, 'error' => mb_substr($exception->getMessage(), 0, 2000)]); } catch (Throwable $ignored) {}
        }
        staff_administration_flash('error', 'Stripe連携に失敗しました: ' . $exception->getMessage());
    }
    staff_administration_redirect('/staff/admin/billing/stripe-plans/');
}

$plans = $pdo->query('SELECT id, name, slug, description, price_monthly, memory_mb, cpu_limit, status, stripe_product_id, stripe_price_id, stripe_sync_status, stripe_synced_at, stripe_sync_error FROM game_server_plans ORDER BY sort_order, id')->fetchAll() ?: [];
$flash = staff_administration_take_flash();
$syncedCount = count(array_filter($plans, static fn(array $plan): bool => $plan['stripe_sync_status'] === 'synced' && $plan['stripe_product_id'] && $plan['stripe_price_id']));

staff_layout_start([
    'title' => 'Stripeプラン連携', 'heading' => 'Stripeプラン連携', 'eyebrow' => 'BILLING / STRIPE CATALOG',
    'description' => 'HCのゲームサーバープランとStripe Product・月額Priceの対応を管理します。',
]);
?>
<div class="ops-page admin-native-page">
    <?php if ($flash): ?><div class="ops-alert <?= $flash['type'] === 'success' ? 'ops-alert--success' : '' ?>"><?= staff_ui_escape($flash['message']) ?></div><?php endif; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">HCプラン</span><strong><?= count($plans) ?></strong><small>登録済み</small></article><article class="ops-card"><span class="ops-card__label">Stripe同期済み</span><strong><?= $syncedCount ?></strong><small>Product・Priceあり</small></article><article class="ops-card"><span class="ops-card__label">未同期・失敗</span><strong><?= count($plans) - $syncedCount ?></strong><small>確認が必要</small></article><article class="ops-card"><span class="ops-card__label">通貨</span><strong class="ops-card__text"><?= staff_ui_escape(strtoupper(hc_stripe_currency())) ?></strong><small>Stripe設定</small></article></section>
    <div class="admin-native-account-grid">
        <?php foreach ($plans as $plan): $synced = $plan['stripe_sync_status'] === 'synced' && $plan['stripe_product_id'] && $plan['stripe_price_id']; ?>
            <article class="ops-panel"><header class="ops-panel__header"><div><h3><?= staff_ui_escape($plan['name']) ?></h3><p>#<?= (int) $plan['id'] ?> / <?= staff_ui_escape($plan['slug']) ?> / ¥<?= number_format((int) $plan['price_monthly']) ?>月</p></div><span class="ops-status ops-status--<?= $synced ? 'completed' : staff_ui_escape($plan['stripe_sync_status'] ?: 'pending') ?>"><?= $synced ? '同期済み' : ($plan['stripe_sync_status'] === 'failed' ? '失敗' : '未同期') ?></span></header><div class="ops-panel__body"><dl class="ops-kv"><div><dt>Product</dt><dd><?= staff_ui_escape($plan['stripe_product_id'] ?: '-') ?></dd></div><div><dt>Price</dt><dd><?= staff_ui_escape($plan['stripe_price_id'] ?: '-') ?></dd></div><div><dt>最終同期</dt><dd><?= staff_ui_escape(staff_administration_datetime($plan['stripe_synced_at'] ?? null)) ?></dd></div><div><dt>プラン状態</dt><dd><?= staff_ui_escape($plan['status']) ?></dd></div></dl><?php if ($plan['stripe_sync_error']): ?><div class="ops-alert"><?= staff_ui_escape($plan['stripe_sync_error']) ?></div><?php endif; ?><div class="ops-form-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>"><input type="hidden" name="action" value="sync_missing"><button class="ops-button" type="submit"><?= $synced ? '再同期' : 'Stripeへ作成' ?></button></form><?php if ($plan['stripe_product_id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>"><input type="hidden" name="action" value="new_price"><button class="ops-button ops-button--secondary" type="submit">新しいPriceを作成</button></form><?php endif; ?></div></div></article>
        <?php endforeach; ?>
    </div>
</div>
<?php staff_layout_end();
