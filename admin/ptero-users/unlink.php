<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$adminUser = require_role("admin");
$pdo = db();

function admin_ptero_users_flash(string $type, string $message): void
{
    $_SESSION["admin_ptero_users_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function admin_ptero_users_redirect(): void
{
    header("Location: /staff/admin/customers/ptero-users/");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (empty($_SESSION["admin_ptero_users_token"]) || !hash_equals((string)$_SESSION["admin_ptero_users_token"], $token)) {
    admin_ptero_users_flash("error", "不正な操作です。もう一度やり直してください。");
    admin_ptero_users_redirect();
}

$userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1],
]);

if (!$userId) {
    admin_ptero_users_flash("error", "ユーザーIDが不正です。");
    admin_ptero_users_redirect();
}

try {
    $stmt = $pdo->prepare("
        UPDATE ptero_user_links
        SET
            status = 'unlinked',
            initial_password = NULL,
            updated_at = NOW()
        WHERE user_id = :user_id
    ");

    $stmt->execute([
        "user_id" => $userId,
    ]);

    admin_ptero_users_flash("success", "ゲームサーバーパネルユーザー紐付けを解除しました。");
} catch (Throwable $e) {
    admin_ptero_users_flash("error", "紐付け解除に失敗しました: " . $e->getMessage());
}

admin_ptero_users_redirect();
