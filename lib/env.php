<?php

function load_env(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }

        if (!str_contains($line, "=")) {
            continue;
        }

        [$key, $value] = explode("=", $line, 2);

        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key === "") {
            continue;
        }

        $_ENV[$key] = $value;
        putenv($key . "=" . $value);
    }
}

function env_value(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === "") {
        return $default;
    }

    $lower = strtolower((string)$value);

    if ($lower === "true") {
        return true;
    }

    if ($lower === "false") {
        return false;
    }

    if ($lower === "null") {
        return null;
    }

    return $value;
}

load_env(__DIR__ . "/../.env");