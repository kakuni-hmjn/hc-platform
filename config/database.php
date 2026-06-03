<?php

require_once __DIR__ . "/../lib/env.php";
load_env("/var/www/.env");

return [
    "host" => getenv("DB_HOST") ?: "127.0.0.1",
    "port" => getenv("DB_PORT") ?: "5432",
    "dbname" => getenv("DB_NAME") ?: "hc_account",
    "user" => getenv("DB_USER") ?: "hc_user",
    "password" => getenv("DB_PASSWORD") ?: "",
];