<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = require_login();
$pdo = db();

function ptero_password_redirect(): void
{
    header("Location: /dashboard/ptero-account/");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (empty($_SESSION["ptero_account_token"]) || !hash_equals((string)$_SESSION["ptero_account_token"], $token)) {
    $_SESSION["ptero_account_flash"] = [
        "type" => "error",
        "message" => "不正な操作です。もう一度やり直してください。",
    ];
    ptero_password_redirect();
}

try {
    $stmt = $pdo->prepare("
        UPDATE ptero_user_links
        SET
            initial_password = NULL,
            initial_password_viewed_at = COALESCE(initial_password_viewed_at, NOW()),
            password_setup_completed_at = NOW(),
            updated_at = NOW()
        WHERE user_id = :user_id
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $_SESSION["ptero_account_flash"] = [
        "type" => "success",
        "message" => "初回パスワードを設定済みにしました。HC側に保存していた初回パスワードは削除されました。",
    ];
} catch (Throwable $e) {
    $_SESSION["ptero_account_flash"] = [
        "type" => "error",
        "message" => "初回パスワード設定状態の更新に失敗しました。",
    ];
}

ptero_password_redirect();
