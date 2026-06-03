<?php

function verify_turnstile(string $token, string $ip): bool
{
    $security = require __DIR__ . "/../config/security.php";

    if ($token === "") {
        return false;
    }

    $ch = curl_init("https://challenges.cloudflare.com/turnstile/v0/siteverify");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            "secret" => $security["turnstile_secret_key"],
            "response" => $token,
            "remoteip" => $ip,
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    $result = json_decode($response, true);

    return isset($result["success"]) && $result["success"] === true;
}