<?php
session_start();

require_once __DIR__ . "/../lib/auth.php";

logout_user();

header("Location: /login/");
exit;