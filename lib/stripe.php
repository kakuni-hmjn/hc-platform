<?php

function hc_stripe_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value !== false && $value !== "") {
        return $value;
    }

    $paths = [
        __DIR__ . "/../.env",
        __DIR__ . "/../docker/.env",
    ];

    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === "" || str_starts_with($line, "#") || !str_contains($line, "=")) {
                continue;
            }

            [$envKey, $envValue] = explode("=", $line, 2);

            if (trim($envKey) === $key) {
                return trim($envValue, " \t\n\r\0\x0B\"'");
            }
        }
    }

    return $default;
}

function hc_stripe_secret_key(): string
{
    $secretKey = hc_stripe_env("STRIPE_SECRET_KEY");

    if (!$secretKey) {
        throw new RuntimeException("STRIPE_SECRET_KEY が設定されていません。");
    }

    if (!str_starts_with($secretKey, "sk_test_") && !str_starts_with($secretKey, "sk_live_")) {
        throw new RuntimeException("STRIPE_SECRET_KEY の形式が不正です。");
    }

    return $secretKey;
}

function hc_stripe_currency(): string
{
    return strtolower((string)hc_stripe_env("STRIPE_CURRENCY", "jpy"));
}

function hc_stripe_webhook_secret(): string
{
    $secret = hc_stripe_env("STRIPE_WEBHOOK_SECRET");

    if (!$secret) {
        throw new RuntimeException("STRIPE_WEBHOOK_SECRET が設定されていません。");
    }

    if (!str_starts_with($secret, "whsec_")) {
        throw new RuntimeException("STRIPE_WEBHOOK_SECRET の形式が不正です。");
    }

    return $secret;
}

function hc_stripe_request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
{
    $secretKey = hc_stripe_secret_key();
    $method = strtoupper($method);
    $url = "https://api.stripe.com" . $path;
    $body = http_build_query($params, "", "&");

    $headers = [
        "Authorization: Basic " . base64_encode($secretKey . ":"),
        "Content-Type: application/x-www-form-urlencoded",
    ];

    if ($idempotencyKey) {
        $headers[] = "Idempotency-Key: " . $idempotencyKey;
    }

    if (function_exists("curl_init")) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method !== "GET") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException("Stripe API通信に失敗しました: " . $curlError);
        }
    } else {
        $context = stream_context_create([
            "http" => [
                "method" => $method,
                "header" => implode("\r\n", $headers),
                "content" => $method === "GET" ? "" : $body,
                "timeout" => 30,
                "ignore_errors" => true,
            ],
        ]);

        $responseBody = file_get_contents($url, false, $context);
        $statusCode = 0;

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int)$matches[1];
        }

        if ($responseBody === false) {
            throw new RuntimeException("Stripe API通信に失敗しました。");
        }
    }

    $decoded = json_decode($responseBody, true);

    if (!is_array($decoded)) {
        throw new RuntimeException("Stripe APIレスポンスを解析できませんでした。");
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $message = $decoded["error"]["message"] ?? "Stripe APIエラーが発生しました。";
        throw new RuntimeException($message);
    }

    return $decoded;
}

function hc_stripe_create_product(array $params, string $idempotencyKey): array
{
    return hc_stripe_request("POST", "/v1/products", $params, $idempotencyKey);
}

function hc_stripe_create_price(array $params, string $idempotencyKey): array
{
    return hc_stripe_request("POST", "/v1/prices", $params, $idempotencyKey);
}

function hc_stripe_create_checkout_session(array $params, string $idempotencyKey): array
{
    return hc_stripe_request("POST", "/v1/checkout/sessions", $params, $idempotencyKey);
}

function hc_stripe_verify_webhook_signature(string $payload, string $signatureHeader, string $secret, int $tolerance = 300): bool
{
    if ($signatureHeader === "") {
        return false;
    }

    $timestamp = null;
    $signatures = [];

    foreach (explode(",", $signatureHeader) as $part) {
        $pieces = explode("=", trim($part), 2);

        if (count($pieces) !== 2) {
            continue;
        }

        [$key, $value] = $pieces;

        if ($key === "t") {
            $timestamp = (int)$value;
        }

        if ($key === "v1") {
            $signatures[] = $value;
        }
    }

    if (!$timestamp || !$signatures) {
        return false;
    }

    if (abs(time() - $timestamp) > $tolerance) {
        return false;
    }

    $signedPayload = $timestamp . "." . $payload;
    $expected = hash_hmac("sha256", $signedPayload, $secret);

    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function hc_stripe_public_url(): string
{
    $url = hc_stripe_env("HC_PUBLIC_URL");

    if ($url) {
        return rtrim($url, "/");
    }

    $appUrl = hc_stripe_env("APP_URL");

    if ($appUrl) {
        return rtrim($appUrl, "/");
    }

    return "";
}

function hc_stripe_retrieve_checkout_session(string $sessionId): array
{
    if ($sessionId === "" || !str_starts_with($sessionId, "cs_")) {
        throw new RuntimeException("Checkout Session IDが不正です。");
    }

    return hc_stripe_request("GET", "/v1/checkout/sessions/" . rawurlencode($sessionId));
}
