<?php

require_once __DIR__ . "/../config/stripe.php";

function stripe_config(): array
{
    return require __DIR__ . "/../config/stripe.php";
}

function stripe_is_enabled(): bool
{
    $config = stripe_config();

    return !empty($config["enabled"]);
}

function stripe_is_mock(): bool
{
    $config = stripe_config();

    return !empty($config["mock"]);
}

function stripe_mask_secret_key(string $key): string
{
    if ($key === "") {
        return "未設定";
    }

    if (strlen($key) <= 12) {
        return "設定済み";
    }

    return substr($key, 0, 8) . "..." . substr($key, -4);
}

function stripe_load_sdk(): bool
{
    $autoloadPath = __DIR__ . "/../vendor/autoload.php";

    if (!file_exists($autoloadPath)) {
        return false;
    }

    require_once $autoloadPath;

    return class_exists("\\Stripe\\Stripe");
}

function stripe_init(): array
{
    $config = stripe_config();

    if (!empty($config["mock"])) {
        return [
            "ok" => true,
            "mock" => true,
            "error" => null,
        ];
    }

    if (empty($config["enabled"])) {
        return [
            "ok" => false,
            "mock" => false,
            "error" => "Stripe連携が無効です。",
        ];
    }

    if (($config["secret_key"] ?? "") === "") {
        return [
            "ok" => false,
            "mock" => false,
            "error" => "Stripe Secret Key が設定されていません。",
        ];
    }

    if (!stripe_load_sdk()) {
        return [
            "ok" => false,
            "mock" => false,
            "error" => "Stripe PHP SDK が読み込めません。vendor/autoload.php を確認してください。",
        ];
    }

    \Stripe\Stripe::setApiKey($config["secret_key"]);

    return [
        "ok" => true,
        "mock" => false,
        "error" => null,
    ];
}

function stripe_create_checkout_session(array $order, array $plan, string $billingType): array
{
    $config = stripe_config();

    if (!empty($config["mock"])) {
        return [
            "ok" => true,
            "mock" => true,
            "checkout_session_id" => "cs_test_mock_" . bin2hex(random_bytes(6)),
            "url" => "/order/game-server/success/?mock=1&order_id=" . urlencode((string)$order["id"]),
            "error" => null,
        ];
    }

    $init = stripe_init();

    if (empty($init["ok"])) {
        return [
            "ok" => false,
            "mock" => false,
            "checkout_session_id" => null,
            "url" => null,
            "error" => $init["error"] ?? "Stripe初期化に失敗しました。",
        ];
    }

    $mode = $billingType === "auto_subscription" ? "subscription" : "payment";

    $lineItem = [
        "price_data" => [
            "currency" => $config["currency"],
            "product_data" => [
                "name" => "ゲームサーバーレンタル - " . $plan["name"],
                "description" => $plan["description"],
            ],
            "unit_amount" => (int)$plan["price_monthly"],
        ],
        "quantity" => 1,
    ];

    if ($mode === "subscription") {
        $lineItem["price_data"]["recurring"] = [
            "interval" => "month",
        ];
    }

    try {
        $session = \Stripe\Checkout\Session::create([
            "mode" => $mode,
            "payment_method_types" => ["card"],
            "line_items" => [$lineItem],
            "success_url" => $config["success_url"] . "?session_id={CHECKOUT_SESSION_ID}",
            "cancel_url" => $config["cancel_url"] . "?order_id=" . urlencode((string)$order["id"]),
            "metadata" => [
                "order_id" => (string)$order["id"],
                "user_id" => (string)$order["user_id"],
                "plan_id" => (string)$order["plan_id"],
                "billing_type" => $billingType,
            ],
        ]);

        return [
            "ok" => true,
            "mock" => false,
            "checkout_session_id" => $session->id,
            "url" => $session->url,
            "error" => null,
        ];
    } catch (Throwable $e) {
        return [
            "ok" => false,
            "mock" => false,
            "checkout_session_id" => null,
            "url" => null,
            "error" => $e->getMessage(),
        ];
    }
}
