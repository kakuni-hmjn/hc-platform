<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$adminUser = require_role("admin");

header('Location: /staff/admin/billing/stripe-plans/', true, 302);
exit;

$pageTitle = "Stripeプラン連携 | HC Platform";
$pageDescription = "HCプランとStripe Product / Priceを連携します。";
$pageCss = "/admin/stripe-plans/stripe-plans.css";

$pdo = db();
$plans = [];
$errors = [];

$flash = $_SESSION["stripe_plans_flash"] ?? null;
unset($_SESSION["stripe_plans_flash"]);

if (empty($_SESSION["stripe_plans_token"])) {
    $_SESSION["stripe_plans_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["stripe_plans_token"];

function stripe_plans_ensure_columns(PDO $pdo): void
{
    $pdo->exec("
        ALTER TABLE game_server_plans
        ADD COLUMN IF NOT EXISTS stripe_product_id VARCHAR(120),
        ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(120),
        ADD COLUMN IF NOT EXISTS stripe_sync_status VARCHAR(40) NOT NULL DEFAULT 'not_synced',
        ADD COLUMN IF NOT EXISTS stripe_synced_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS stripe_sync_error TEXT NULL
    ");
}

function stripe_sync_status_label(?string $status): string
{
    return match ((string)$status) {
        "synced" => "同期済み",
        "failed" => "失敗",
        "not_synced", "" => "未連携",
        default => (string)$status,
    };
}

function stripe_plan_price(int $price): string
{
    return "¥" . number_format($price) . " / 月";
}

function stripe_plan_datetime(?string $value): string
{
    if (!$value) {
        return "-";
    }

    try {
        return (new DateTime($value))->format("Y/m/d H:i");
    } catch (Throwable $e) {
        return $value;
    }
}

try {
    stripe_plans_ensure_columns($pdo);

    $stmt = $pdo->query("
        SELECT
            id,
            name,
            slug,
            description,
            price_monthly,
            memory_mb,
            cpu_limit,
            status,
            sort_order,
            stripe_product_id,
            stripe_price_id,
            stripe_sync_status,
            stripe_synced_at,
            stripe_sync_error
        FROM game_server_plans
        ORDER BY sort_order ASC, id ASC
    ");

    $plans = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "Stripe連携用プラン情報の取得に失敗しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="stripe-plans-page">
    <section class="stripe-plans-hero">
        <div class="container stripe-plans-hero-grid">
            <div class="stripe-plans-copy reveal">
                <p class="eyebrow">Admin / Stripe Plans</p>
                <h1>Stripeプラン連携</h1>
                <p>
                    HC側のゲームサーバープランから、Stripeの商品と月額Priceを作成します。
                    まずはProduct / Price同期だけを行い、Checkout連携は次の段階で追加します。
                </p>
            </div>

            <aside class="stripe-plans-status-card reveal">
                <span>Stripe Sync</span>
                <h2><?php echo h((string)count($plans)); ?> 件</h2>
                <p>HCプランをStripe Product / Priceへ同期します。</p>
            </aside>
        </div>
    </section>

    <section class="section stripe-plans-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                <a href="/admin/game-plans/" class="sub-button">プラン管理へ</a>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="stripe-plans-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Plans</p>
                        <h2>HCプラン一覧</h2>
                    </div>
                </div>

                <?php if (!$plans): ?>
                    <div class="empty-box">
                        <h3>プランがありません。</h3>
                        <p>先にゲームサーバープランを作成してください。</p>
                    </div>
                <?php else: ?>
                    <div class="stripe-plan-list">
                        <?php foreach ($plans as $plan): ?>
                            <?php
                            $syncStatus = (string)($plan["stripe_sync_status"] ?: "not_synced");
                            $isSynced = $syncStatus === "synced" && !empty($plan["stripe_product_id"]) && !empty($plan["stripe_price_id"]);
                            ?>
                            <article class="stripe-plan-card status-<?php echo h($syncStatus); ?>">
                                <div class="stripe-plan-head">
                                    <div>
                                        <span class="plan-id">Plan #<?php echo h((string)$plan["id"]); ?></span>
                                        <strong class="sync-badge sync-<?php echo h($syncStatus); ?>">
                                            <?php echo h(stripe_sync_status_label($syncStatus)); ?>
                                        </strong>
                                    </div>

                                    <small><?php echo h(stripe_plan_price((int)$plan["price_monthly"])); ?></small>
                                </div>

                                <div class="stripe-plan-main">
                                    <div class="plan-info">
                                        <h3><?php echo h((string)$plan["name"]); ?></h3>
                                        <p><?php echo h((string)($plan["description"] ?: "説明なし")); ?></p>
                                    </div>

                                    <div class="stripe-info">
                                        <span>Stripe Product</span>
                                        <strong><?php echo h((string)($plan["stripe_product_id"] ?: "-")); ?></strong>

                                        <span>Stripe Price</span>
                                        <strong><?php echo h((string)($plan["stripe_price_id"] ?: "-")); ?></strong>

                                        <p>最終同期: <?php echo h(stripe_plan_datetime((string)($plan["stripe_synced_at"] ?? ""))); ?></p>
                                    </div>

                                    <div class="stripe-actions">
                                        <form method="post" action="/admin/stripe-plans/sync.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <input type="hidden" name="plan_id" value="<?php echo h((string)$plan["id"]); ?>">
                                            <input type="hidden" name="action" value="sync_missing">
                                            <button type="submit" class="primary-action">
                                                <?php echo $isSynced ? "再同期" : "Stripeへ作成"; ?>
                                            </button>
                                        </form>

                                        <?php if (!empty($plan["stripe_product_id"])): ?>
                                            <form method="post" action="/admin/stripe-plans/sync.php">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <input type="hidden" name="plan_id" value="<?php echo h((string)$plan["id"]); ?>">
                                                <input type="hidden" name="action" value="new_price">
                                                <button type="submit" class="secondary-action">
                                                    新しいPrice作成
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($plan["stripe_sync_error"])): ?>
                                    <div class="sync-error">
                                        <?php echo h((string)$plan["stripe_sync_error"]); ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
