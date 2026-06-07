<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/stripe.php";

$currentUser = require_login();
$pdo = db();

function server_detail_flash(string $type, string $message): void
{
    $_SESSION["server_detail_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function redirect_server_detail(int $orderId): void
{
    header("Location: /dashboard/servers/detail/?id=" . $orderId);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$csrfToken = (string)($_POST["csrf_token"] ?? "");

if (!$orderId) {
    server_detail_flash("error", "契約IDが不正です。");
    header("Location: /dashboard/servers/");
    exit;
}

if (
    empty($_SESSION["server_detail_action_token"]) ||
    !hash_equals((string)$_SESSION["server_detail_action_token"], $csrfToken)
) {
    server_detail_flash("error", "不正な操作です。もう一度やり直してください。");
    redirect_server_detail($orderId);
}

try {
    $stmt = $pdo->prepare("
        SELECT id, user_id, stripe_customer_id, billing_type, status
        FROM game_server_orders
        WHERE id = :id
          AND user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        "id" => $orderId,
        "user_id" => (int)$currentUser["id"],
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        throw new RuntimeException("契約が見つかりません。");
    }

    $config = stripe_config();

    if (!empty($config["mock"]) || empty($config["enabled"])) {
        server_detail_flash("success", "開発環境のため、支払い方法変更はMock処理として完了しました。");
        redirect_server_detail($orderId);
    }

    if (empty($order["stripe_customer_id"])) {
        throw new RuntimeException("Stripe Customer IDが未登録のため、支払い方法を変更できません。");
    }

    $init = stripe_init();

    if (empty($init["ok"])) {
        throw new RuntimeException($init["error"] ?? "Stripe初期化に失敗しました。");
    }

    $appUrl = rtrim((string)(getenv("APP_URL") ?: "http://localhost:8080"), "/");
    $returnUrl = $appUrl . "/dashboard/servers/detail/?id=" . urlencode((string)$orderId);

    $session = \Stripe\BillingPortal\Session::create([
        "customer" => (string)$order["stripe_customer_id"],
        "return_url" => $returnUrl,
    ]);

    header("Location: " . $session->url);
    exit;
} catch (Throwable $e) {
    server_detail_flash("error", $e->getMessage());
    redirect_server_detail($orderId);
}
