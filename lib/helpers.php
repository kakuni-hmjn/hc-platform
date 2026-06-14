<?php

function h(?string $value): string
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}

function client_ip(): string
{
    if (!empty($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        return $_SERVER["HTTP_CF_CONNECTING_IP"];
    }

    if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        return trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"])[0]);
    }

    return client_ip();
}

function redirect(string $path): never
{
    header("Location: " . $path);
    exit;
}