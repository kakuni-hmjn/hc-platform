<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/stripe.php";

$currentUser = require_login();
$pdo = db();

function checkout_create_redirect_to_checkout(int $orderId): void
{
    header("Location: /billing/checkout/?order_id=" . rawurlencode((string)$orderId));
    exit;
}

function checkout_create_flash(string $type, string $message): void
{
    $_SESSION["checkout_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function checkout_create_base_url(): string
{
    $publicUrl = rtrim(trim(hc_stripe_public_url()), "/");

    /*
     * STRIPE_PUBLIC_URLが本番URLならその値を使用する。
     * localhostや127.0.0.1が設定されている場合は、本番URLへ固定する。
     */
    if (
        $publicUrl !== "" &&
        !preg_match(
            '#^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?(?:/|$)#i',
            $publicUrl
        )
    ) {
        return $publicUrl;
    }

    return "https://www.hc-jp.net";
}

function checkout_create_ensure_columns(PDO $pdo): void
{
    $pdo->exec("
        ALTER TABLE game_server_orders
        ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(160),
        ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(160),
        ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(160)
    ");

    $pdo->exec("
        ALTER TABLE game_server_plans
        ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(160)
    ");
}

function checkout_create_record_event(PDO $pdo, int $orderId, int $userId, ?string $oldPaymentStatus, string $newPaymentStatus, string $message): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO server_order_events
            (
                order_id,
                actor_user_id,
                event_type,
                title,
                message,
                old_payment_status,
                new_payment_status,
                created_at
            )
            VALUES
            (
                :order_id,
                :actor_user_id,
                'payment_checkout_created',
                'Stripe Checkoutを作成しました',
                :message,
                :old_payment_status,
                :new_payment_status,
                NOW()
            )
        ");

        $stmt->execute([
            "order_id" => $orderId,
            "actor_user_id" => $userId,
            "message" => $message,
            "old_payment_status" => $oldPaymentStatus,
            "new_payment_status" => $newPaymentStatus,
        ]);
    } catch (Throwable $e) {
        // イベント記録の失敗でCheckout作成自体は止めない
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (empty($_SESSION["checkout_token"]) || !hash_equals((string)$_SESSION["checkout_token"], $token)) {
    checkout_create_flash("error", "不正な操作です。もう一度やり直してください。");
    header("Location: /billing/");
    exit;
}

$orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

if (!$orderId) {
    checkout_create_flash("error", "契約IDが不正です。");
    header("Location: /billing/");
    exit;
}

try {
    checkout_create_ensure_columns($pdo);

    $stmt = $pdo->prepare("
        SELECT
            gso.id,
            gso.user_id,
            gso.server_name,
            gso.status,
            gso.payment_status,
            gso.amount,
            gso.currency,
            gso.stripe_checkout_session_id,

            gsp.name AS plan_name,
            gsp.price_monthly,
            gsp.stripe_price_id,

            u.email,
            u.username
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        JOIN users u ON u.id = gso.user_id
        WHERE gso.id = :order_id
          AND gso.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        "order_id" => $orderId,
        "user_id" => (int)$currentUser["id"],
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        throw new RuntimeException("契約が見つからないか、表示権限がありません。");
    }

    $paymentStatus = (string)$order["payment_status"];
    $orderStatus = (string)$order["status"];
    $stripePriceId = trim((string)($order["stripe_price_id"] ?? ""));

    if ($paymentStatus === "paid") {
        throw new RuntimeException("この契約はすでに支払い済みです。");
    }

    if (in_array($orderStatus, ["cancelled", "expired", "suspended"], true)) {
        throw new RuntimeException("この契約は現在支払いできません。");
    }

    if ($stripePriceId === "") {
        throw new RuntimeException("このプランはStripe Priceと未連携です。管理者に連絡してください。");
    }

    $baseUrl = checkout_create_base_url();
    $successUrl = $baseUrl . "/billing/success/?order_id=" . rawurlencode((string)$orderId) . "&session_id={CHECKOUT_SESSION_ID}";
    $cancelUrl = $baseUrl . "/billing/cancel/?order_id=" . rawurlencode((string)$orderId);

    $session = hc_stripe_create_checkout_session([
        "mode" => "subscription",
        "allow_promotion_codes" => "true",
        "success_url" => $successUrl,
        "cancel_url" => $cancelUrl,
        "customer_email" => (string)$order["email"],
        "client_reference_id" => (string)$orderId,
        "line_items" => [
            [
                "price" => $stripePriceId,
                "quantity" => 1,
            ],
        ],
        "metadata" => [
            "hc_order_id" => (string)$orderId,
            "hc_user_id" => (string)$currentUser["id"],
            "hc_service" => "game_server",
        ],
        "subscription_data" => [
            "metadata" => [
                "hc_order_id" => (string)$orderId,
                "hc_user_id" => (string)$currentUser["id"],
                "hc_service" => "game_server",
            ],
        ],
    ], "hc_checkout_order_" . (string)$orderId . "_" . bin2hex(random_bytes(8)));

    if (empty($session["id"]) || empty($session["url"])) {
        throw new RuntimeException("Stripe Checkout Sessionの作成に失敗しました。");
    }

    $oldPaymentStatus = $paymentStatus;
    $newPaymentStatus = "checkout_created";

    $update = $pdo->prepare("
        UPDATE game_server_orders
        SET
            payment_status = :payment_status,
            stripe_checkout_session_id = :stripe_checkout_session_id
        WHERE id = :id
          AND user_id = :user_id
    ");

    $update->execute([
        "id" => $orderId,
        "user_id" => (int)$currentUser["id"],
        "payment_status" => $newPaymentStatus,
        "stripe_checkout_session_id" => (string)$session["id"],
    ]);

    checkout_create_record_event(
        $pdo,
        $orderId,
        (int)$currentUser["id"],
        $oldPaymentStatus,
        $newPaymentStatus,
        "Stripe Checkout Session: " . (string)$session["id"]
    );

    header("Location: " . (string)$session["url"], true, 303);
    exit;
} catch (Throwable $e) {
    checkout_create_flash("error", $e->getMessage());
    checkout_create_redirect_to_checkout((int)$orderId);
}
