<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$adminUser = require_role("admin");
$pdo = db();

function ready_start_flash(string $type, string $message): void
{
    $_SESSION["ready_orders_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function ready_start_redirect(): void
{
    header("Location: /admin/server-orders/ready/");
    exit;
}

function ready_start_insert_event(PDO $pdo, int $orderId, int $adminUserId, string $oldStatus, string $newStatus): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO server_order_events
            (
                order_id,
                actor_user_id,
                event_type,
                title,
                message,
                old_status,
                new_status,
                created_at
            )
            VALUES
            (
                :order_id,
                :actor_user_id,
                'server_provision_started',
                'サーバー作成を開始しました',
                '管理者が支払い完了済み契約をサーバー作成中に進めました。',
                :old_status,
                :new_status,
                NOW()
            )
        ");

        $stmt->execute([
            "order_id" => $orderId,
            "actor_user_id" => $adminUserId,
            "old_status" => $oldStatus,
            "new_status" => $newStatus,
        ]);
    } catch (Throwable $e) {
        // 履歴記録の失敗で状態更新自体は止めない
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (empty($_SESSION["ready_orders_token"]) || !hash_equals((string)$_SESSION["ready_orders_token"], $token)) {
    ready_start_flash("error", "不正な操作です。もう一度やり直してください。");
    ready_start_redirect();
}

$orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

if (!$orderId) {
    ready_start_flash("error", "契約IDが不正です。");
    ready_start_redirect();
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            id,
            status,
            payment_status
        FROM game_server_orders
        WHERE id = :id
        FOR UPDATE
    ");

    $stmt->execute([
        "id" => $orderId,
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        throw new RuntimeException("契約が見つかりません。");
    }

    $oldStatus = (string)$order["status"];
    $paymentStatus = (string)$order["payment_status"];

    if ($paymentStatus !== "paid") {
        throw new RuntimeException("支払い完了済みの契約だけ作成開始できます。");
    }

    if ($oldStatus !== "paid") {
        throw new RuntimeException("契約状態がpaidの契約だけ作成開始できます。現在の状態: " . $oldStatus);
    }

    $newStatus = "creating";

    $update = $pdo->prepare("
        UPDATE game_server_orders
        SET status = :status
        WHERE id = :id
    ");

    $update->execute([
        "id" => $orderId,
        "status" => $newStatus,
    ]);

    ready_start_insert_event($pdo, $orderId, (int)$adminUser["id"], $oldStatus, $newStatus);

    $pdo->commit();

    ready_start_flash("success", "契約 #" . $orderId . " をサーバー作成中に変更しました。");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ready_start_flash("error", $e->getMessage());
}

ready_start_redirect();
