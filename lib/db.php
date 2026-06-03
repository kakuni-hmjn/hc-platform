<?php

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . "/../config/database.php";

    $dsn = sprintf(
        "pgsql:host=%s;port=%s;dbname=%s",
        $config["host"],
        $config["port"],
        $config["dbname"]
    );

    $pdo = new PDO($dsn, $config["user"], $config["password"], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}