<?php

require_once __DIR__ . "/../lib/env.php";
load_env("/var/www/.env");

return [
    "app_url" => rtrim(getenv("APP_URL") ?: "https://hc-jp.net", "/"),
];