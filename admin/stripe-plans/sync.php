<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/stripe.php";

$adminUser = require_role("admin");
$pdo = db();

function stripe_sync_redirect(): void
{
    header("Location: /staff/admin/billing/stripe-plans/");
    exit;
}

function stripe_sync_flash(string $type, string $message): void
{
    $_SESSION["stripe_plans_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function stripe_sync_ensure_columns(PDO $pdo): void
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

function stripe_sync_mark_failed(PDO $pdo, int $planId, string $message): void
{
    $stmt = $pdo->prepare("
        UPDATE game_server_plans
        SET
            stripe_sync_status = 'failed',
            stripe_sync_error = :error,
            stripe_synced_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        "id" => $planId,
        "error" => mb_substr($message, 0, 2000),
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (empty($_SESSION["stripe_plans_token"]) || !hash_equals((string)$_SESSION["stripe_plans_token"], $token)) {
    stripe_sync_flash("error", "不正な操作です。");
    stripe_sync_redirect();
}

$planId = filter_input(INPUT_POST, "plan_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$action = trim((string)($_POST["action"] ?? "sync_missing"));

if (!$planId) {
    stripe_sync_flash("error", "プランIDが不正です。");
    stripe_sync_redirect();
}

try {
    stripe_sync_ensure_columns($pdo);

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            slug,
            description,
            price_monthly,
            status,
            stripe_product_id,
            stripe_price_id
        FROM game_server_plans
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "id" => $planId,
    ]);

    $plan = $stmt->fetch();

    if (!$plan) {
        throw new RuntimeException("プランが見つかりません。");
    }

    $priceMonthly = (int)$plan["price_monthly"];

    if ($priceMonthly <= 0) {
        throw new RuntimeException("Stripeへ同期するには月額料金が1円以上必要です。");
    }

    $currency = hc_stripe_currency();
    $stripeProductId = trim((string)($plan["stripe_product_id"] ?? ""));
    $stripePriceId = trim((string)($plan["stripe_price_id"] ?? ""));

    if ($stripeProductId === "") {
        $product = hc_stripe_create_product([
            "name" => "HC Game Server - " . (string)$plan["name"],
            "description" => (string)($plan["description"] ?: "HC Platform game server plan"),
            "metadata" => [
                "hc_plan_id" => (string)$plan["id"],
                "hc_plan_slug" => (string)$plan["slug"],
                "hc_service" => "game_server",
            ],
        ], "hc_plan_product_" . (string)$plan["id"]);

        $stripeProductId = (string)$product["id"];
    }

    $shouldCreatePrice = $action === "new_price" || $stripePriceId === "";

    if ($shouldCreatePrice) {
        $priceIdempotency = $action === "new_price"
            ? "hc_plan_price_" . (string)$plan["id"] . "_" . (string)$priceMonthly . "_monthly_" . bin2hex(random_bytes(8))
            : "hc_plan_price_" . (string)$plan["id"] . "_" . (string)$priceMonthly . "_monthly";

        $price = hc_stripe_create_price([
            "product" => $stripeProductId,
            "unit_amount" => $priceMonthly,
            "currency" => $currency,
            "recurring" => [
                "interval" => "month",
                "interval_count" => 1,
            ],
            "nickname" => "HC " . (string)$plan["name"] . " monthly",
            "metadata" => [
                "hc_plan_id" => (string)$plan["id"],
                "hc_plan_slug" => (string)$plan["slug"],
                "hc_service" => "game_server",
                "hc_billing_type" => "monthly",
            ],
        ], $priceIdempotency);

        $stripePriceId = (string)$price["id"];
    }

    $update = $pdo->prepare("
        UPDATE game_server_plans
        SET
            stripe_product_id = :stripe_product_id,
            stripe_price_id = :stripe_price_id,
            stripe_sync_status = 'synced',
            stripe_synced_at = NOW(),
            stripe_sync_error = NULL,
            updated_at = NOW()
        WHERE id = :id
    ");

    $update->execute([
        "id" => $planId,
        "stripe_product_id" => $stripeProductId,
        "stripe_price_id" => $stripePriceId,
    ]);

    stripe_sync_flash("success", "Stripe連携を完了しました。Product: {$stripeProductId} / Price: {$stripePriceId}");
} catch (Throwable $e) {
    try {
        stripe_sync_mark_failed($pdo, (int)$planId, $e->getMessage());
    } catch (Throwable $inner) {
    }

    stripe_sync_flash("error", "Stripe連携に失敗しました: " . $e->getMessage());
}

stripe_sync_redirect();
