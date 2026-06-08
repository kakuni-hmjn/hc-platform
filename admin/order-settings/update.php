<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/order_access.php";

$adminUser = require_role("admin");
$pdo = db();

function order_settings_flash(string $type, string $message): void
{
    $_SESSION["order_settings_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function order_settings_redirect(): void
{
    header("Location: /admin/order-settings/");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (empty($_SESSION["order_settings_token"]) || !hash_equals((string)$_SESSION["order_settings_token"], $token)) {
    order_settings_flash("error", "不正な操作です。もう一度やり直してください。");
    order_settings_redirect();
}

$serviceKey = trim((string)($_POST["service_key"] ?? ""));
$isEnabled = (string)($_POST["is_enabled"] ?? "0") === "1";
$disabledMessage = trim((string)($_POST["disabled_message"] ?? ""));
$adminMemo = trim((string)($_POST["admin_memo"] ?? ""));

if ($serviceKey === "") {
    order_settings_flash("error", "サービスキーが不正です。");
    order_settings_redirect();
}

if (!$isEnabled && $disabledMessage === "") {
    $disabledMessage = "現在、新規申込受付を一時停止しています。メンテナンス完了後に再度お試しください。";
}

try {
    hc_order_update_setting(
        $pdo,
        $serviceKey,
        $isEnabled,
        $disabledMessage,
        $adminMemo,
        (int)$adminUser["id"]
    );

    order_settings_flash("success", "申込受付設定を保存しました。");
} catch (Throwable $e) {
    order_settings_flash("error", "申込受付設定の保存に失敗しました: " . $e->getMessage());
}

order_settings_redirect();
