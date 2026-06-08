<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/ptero_users.php";

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

$pteroUserId = filter_input(INPUT_POST, "ptero_user_id", FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1],
]);

$pteroUsername = trim((string)($_POST["ptero_username"] ?? ""));
$pteroEmail = trim((string)($_POST["ptero_email"] ?? ""));
$pteroExternalId = trim((string)($_POST["ptero_external_id"] ?? ""));
$pteroUuid = trim((string)($_POST["ptero_uuid"] ?? ""));

if (!$userId || !$pteroUserId || $pteroUsername === "" || $pteroEmail === "" || $pteroExternalId === "") {
    admin_ptero_users_flash("error", "必要項目が不足しています。");
    admin_ptero_users_redirect();
}

try {
    hc_ptero_user_links_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO ptero_user_links
        (
            user_id,
            ptero_user_id,
            ptero_external_id,
            ptero_uuid,
            username,
            email,
            status,
            created_at,
            updated_at,
            last_synced_at
        )
        VALUES
        (
            :user_id,
            :ptero_user_id,
            :ptero_external_id,
            :ptero_uuid,
            :username,
            :email,
            'active',
            NOW(),
            NOW(),
            NOW()
        )
        ON CONFLICT (user_id) DO UPDATE SET
            ptero_user_id = EXCLUDED.ptero_user_id,
            ptero_external_id = EXCLUDED.ptero_external_id,
            ptero_uuid = EXCLUDED.ptero_uuid,
            username = EXCLUDED.username,
            email = EXCLUDED.email,
            status = 'active',
            updated_at = NOW(),
            last_synced_at = NOW()
    ");

    $stmt->execute([
        "user_id" => $userId,
        "ptero_user_id" => $pteroUserId,
        "ptero_external_id" => $pteroExternalId,
        "ptero_uuid" => $pteroUuid !== "" ? $pteroUuid : null,
        "username" => $pteroUsername,
        "email" => $pteroEmail,
    ]);

    admin_ptero_users_flash("success", "HCユーザー #" . $userId . " にゲームサーバーパネルユーザー #" . $pteroUserId . " を紐付けました。");
} catch (Throwable $e) {
    admin_ptero_users_flash("error", "紐付け保存に失敗しました: " . $e->getMessage());
}

admin_ptero_users_redirect();
