<?php

$envPath = '/etc/hc-platform/app.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, null);

        if ($key !== null && $value !== null) {
            $_ENV[trim($key)] = trim($value);
        }
    }
}

return [
    'db_host' => $_ENV['DB_HOST'] ?? '',
    'db_port' => $_ENV['DB_PORT'] ?? '5432',
    'db_name' => $_ENV['DB_NAME'] ?? '',
    'db_user' => $_ENV['DB_USER'] ?? '',
    'db_password' => $_ENV['DB_PASSWORD'] ?? '',

    'turnstile_site_key' => $_ENV['TURNSTILE_SITE_KEY'] ?? '',
    'turnstile_secret_key' => $_ENV['TURNSTILE_SECRET_KEY'] ?? '',
];
