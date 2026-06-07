<?php

require_once __DIR__ . "/../lib/env.php";

return [
    "enabled" => (bool)env_value("STRIPE_ENABLED", false),
    "mock" => (bool)env_value("STRIPE_MOCK", true),

    "secret_key" => (string)env_value("STRIPE_SECRET_KEY", ""),
    "webhook_secret" => (string)env_value("STRIPE_WEBHOOK_SECRET", ""),

    "success_url" => (string)env_value("STRIPE_SUCCESS_URL", "http://localhost:8080/order/game-server/success/"),
    "cancel_url" => (string)env_value("STRIPE_CANCEL_URL", "http://localhost:8080/order/game-server/cancel/"),

    "currency" => (string)env_value("STRIPE_CURRENCY", "jpy"),
];
