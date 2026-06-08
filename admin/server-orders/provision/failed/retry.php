<?php

session_start();

require_once __DIR__ . "/../../../../lib/helpers.php";
require_once __DIR__ . "/../../../../lib/auth.php";
require_once __DIR__ . "/../../../../lib/db.php";
require_once __DIR__ . "/../../../../lib/permissions.php";

$adminUser = require_role("admin");
$pdo = db();

function failed_retry_flash(string $type, string $message): void
{
    $_SESSION["provision_failed_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function failed_retry_redirect(): void
{
    header("Location: /admin/server-orders/provision/failed/");
    exit;
}

function failed_retry_insert_event(
    PDO $pdo,
    int $orderId,
    int $adminUserId,
    string $oldStatus,
    string $newStatus
): void {
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
                'server_provision_retry_queued',
                'ゲームサーバー作成を再実行待ちに戻しました',
                '管理者が作成失敗した契約を再作成待ちに戻しました。',
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
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (empty($_SESSION["provision_failed_token"]) || !hash_equals((string)$_SESSION["provision_failed_token"], $token)) {
    failed_retry_flash("error", "不正な操作です。もう一度やり直してください。");
    failed_retry_redirect();
}

$orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

if (!$orderId) {
    failed_retry_flash("error", "契約IDが不正です。");
    failed_retry_redirect();
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
        throw new RuntimeException("支払い完了済みの契約だけ再実行できます。");
    }

    if ($oldStatus !== "provision_failed") {
        throw new RuntimeException("作成失敗状態の契約だけ再実行できます。現在の状態: " . $oldStatus);
    }

    $newStatus = "creating";

    $update = $pdo->prepare("
        UPDATE game_server_orders
        SET
            status = :status,
            failed_at = NULL,
            provision_error = NULL,
            updated_at = NOW()
        WHERE id = :id
    ");

    $update->execute([
        "id" => $orderId,
        "status" => $newStatus,
    ]);

    failed_retry_insert_event($pdo, $orderId, (int)$adminUser["id"], $oldStatus, $newStatus);

    $pdo->commit();

    failed_retry_flash("success", "契約 #" . $orderId . " を再作成待ちに戻しました。ゲームサーバーパネル作成ページから再実行してください。");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    failed_retry_flash("error", $e->getMessage());
}

failed_retry_redirect();
