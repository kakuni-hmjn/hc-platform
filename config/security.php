<?php

require_once __DIR__ . "/../lib/env.php";
load_env("/var/www/.env");

return [
    "turnstile_site_key" => getenv("TURNSTILE_SITE_KEY") ?: "",
    "turnstile_secret_key" => getenv("TURNSTILE_SECRET_KEY") ?: "",

    "verification_code_minutes" => 10,
    "max_verify_attempts" => 5,

    "verification_resend_wait_seconds" => 60,
    "max_verification_resends" => 5,

    "password_min_length" => 8,

    "register_ip_limit_minutes" => 10,
    "register_ip_limit_count" => 5,

    "login_failed_limit" => 5,
    "login_lock_minutes" => 15,

    "password_reset_minutes" => 30,
];