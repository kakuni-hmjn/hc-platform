<?php

function load_env(string $path): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    if (!file_exists($path)) {
        $loaded = true;
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        $loaded = true;
        return;
    }

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

        if (getenv($key) === false) {
            putenv($key . "=" . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    $loaded = true;
}