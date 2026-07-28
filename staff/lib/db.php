<?php

declare(strict_types=1);

function staff_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '5432';

    $database = getenv('DB_NAME')
        ?: getenv('POSTGRES_DB')
        ?: 'hc_account';

    $username = getenv('DB_USER')
        ?: getenv('POSTGRES_USER')
        ?: 'hc_user';

    $password = getenv('DB_PASSWORD')
        ?: getenv('POSTGRES_PASSWORD')
        ?: 'hc_password_dev';

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $host,
        $port,
        $database
    );

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}
