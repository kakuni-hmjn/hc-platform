<?php

require_once __DIR__ . "/../lib/env.php";
load_env("/var/www/.env");

return [
    "mode" => getenv("MAIL_MODE") ?: "log",

    "smtp_host" => getenv("SMTP_HOST") ?: "smtp-relay.brevo.com",
    "smtp_port" => (int)(getenv("SMTP_PORT") ?: 587),
    "smtp_user" => getenv("SMTP_USER") ?: "",
    "smtp_password" => getenv("SMTP_PASSWORD") ?: "",

    "from_email" => getenv("MAIL_FROM_ADDRESS") ?: "no-reply@hc-jp.net",
    "from_name" => getenv("MAIL_FROM_NAME") ?: "HCPlatform",
];