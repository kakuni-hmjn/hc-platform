<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/ptero_users.php";
require_once __DIR__ . "/../../lib/pterodactyl.php";

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
    header("Location: /admin/ptero-users/");
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
    hc_ptero_user_links_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT
            pul.user_id,
            pul.ptero_user_id,
            pul.ptero_external_id,
            pul.username,
            pul.email,
            u.username AS hc_username
        FROM ptero_user_links pul
        JOIN users u ON u.id = pul.user_id
        WHERE pul.user_id = :user_id
          AND pul.status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        "user_id" => $userId,
    ]);

    $link = $stmt->fetch();

    if (!$link) {
        throw new RuntimeException("ゲームサーバーパネル紐付けが見つかりません。");
    }

    $newPassword = hc_ptero_random_password();

    if (hc_ptero_enabled() && !hc_ptero_mock()) {
        hc_ptero_request("PATCH", "/api/application/users/" . rawurlencode((string)$link["ptero_user_id"]), [
            "external_id" => (string)$link["ptero_external_id"],
            "email" => (string)$link["email"],
            "username" => (string)$link["username"],
            "first_name" => (string)$link["hc_username"],
            "last_name" => "HC",
            "password" => $newPassword,
            "root_admin" => false,
            "language" => "en",
        ]);
    }

    $update = $pdo->prepare("
        UPDATE ptero_user_links
        SET
            initial_password = :initial_password,
            initial_password_created_at = NOW(),
            initial_password_viewed_at = NULL,
            password_setup_completed_at = NULL,
            updated_at = NOW()
        WHERE user_id = :user_id
    ");

    $update->execute([
        "user_id" => $userId,
        "initial_password" => $newPassword,
    ]);

    admin_ptero_users_flash("success", "初回パスワードを再発行しました。ユーザーのゲームサーバーパネルアカウントページに表示されます。");
} catch (Throwable $e) {
    admin_ptero_users_flash("error", "初回パスワード再発行に失敗しました: " . $e->getMessage());
}

admin_ptero_users_redirect();
