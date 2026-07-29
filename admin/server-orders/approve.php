<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/game_server_approval.php";

$adminUser = require_role("admin");
$pdo = db();

function server_approval_flash(string $type, string $message): void
{
    $_SESSION["server_approval_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function server_approval_redirect(): void
{
    header("Location: /admin/server-orders/pending/");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (
    empty($_SESSION["server_approval_token"])
    || !hash_equals(
        (string)$_SESSION["server_approval_token"],
        $token
    )
) {
    server_approval_flash(
        "error",
        "不正な操作です。もう一度やり直してください。"
    );

    server_approval_redirect();
}

$orderId = filter_input(
    INPUT_POST,
    "order_id",
    FILTER_VALIDATE_INT,
    [
        "options" => [
            "min_range" => 1,
        ],
    ]
);

if (!$orderId) {
    server_approval_flash(
        "error",
        "注文IDが不正です。"
    );

    server_approval_redirect();
}

$result = hc_approve_game_server_order(
    $pdo,
    (int)$orderId,
    (int)$adminUser["id"],
    "web"
);

if (!empty($result["ok"])) {
    if (!empty($result["already"])) {
        server_approval_flash(
            "success",
            "このサーバーはすでに承認済みです。"
        );
    } else {
        server_approval_flash(
            "success",
            "ゲームサーバーを承認し、利用可能にしました。"
        );
    }
} else {
    server_approval_flash(
        "error",
        "承認処理に失敗しました: "
        . (string)($result["error"] ?? "不明なエラー")
    );
}

server_approval_redirect();
