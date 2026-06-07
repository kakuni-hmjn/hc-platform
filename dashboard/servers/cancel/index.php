<?php
session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";

$currentUser = current_user();

if (!$currentUser) {
    header("Location: /login/?redirect=/dashboard/servers/");
    exit;
}

$pdo = db();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /dashboard/servers/");
    exit;
}

$serverId = (int)($_POST["server_id"] ?? 0);
$agreeRefundPolicy = isset($_POST["agree_refund_policy"]) && $_POST["agree_refund_policy"] === "1";
$cancelReason = trim($_POST["cancel_reason"] ?? "");

if ($serverId <= 0 || !$agreeRefundPolicy) {
    header("Location: /dashboard/servers/?cancel_error=1");
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            ps.id AS server_id,
            ps.order_id,
            ps.user_id,
            ps.status AS server_status,
            gso.id AS order_id,
            gso.billing_type,
            gso.status AS order_status,
            gso.payment_status,
            gso.expires_at,
            gso.next_payment_due_at
        FROM ptero_servers ps
        JOIN game_server_orders gso ON gso.id = ps.order_id
        WHERE ps.id = :server_id
        AND ps.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        "server_id" => $serverId,
        "user_id" => (int)$currentUser["id"],
    ]);

    $server = $stmt->fetch();

    if (!$server) {
        throw new RuntimeException("対象サーバーが見つかりません。");
    }

    if (!in_array($server["server_status"], ["active", "suspended"], true)) {
        throw new RuntimeException("このサーバーはキャンセルできません。");
    }

    $effectiveSql = "NOW() + INTERVAL '30 days'";

    if (!empty($server["expires_at"])) {
        $effectiveSql = "expires_at";
    } elseif (!empty($server["next_payment_due_at"])) {
        $effectiveSql = "next_payment_due_at";
    }

    $updateOrder = $pdo->prepare("
        UPDATE game_server_orders
        SET
            auto_renew_cancelled = true,
            refund_policy_agreed = true,
            cancel_requested_at = NOW(),
            cancel_effective_at = {$effectiveSql},
            cancel_reason = :cancel_reason,
            updated_at = NOW()
        WHERE id = :order_id
        AND user_id = :user_id
    ");

    $updateOrder->execute([
        "order_id" => (int)$server["order_id"],
        "user_id" => (int)$currentUser["id"],
        "cancel_reason" => $cancelReason !== "" ? $cancelReason : null,
    ]);

    /*
      初期実装では、サーバー自体は即停止しません。
      現在の利用期間までは利用可能にして、次回更新だけ止める扱いです。

      本番Stripe連携後はここで、
      - auto_subscription: Stripe Subscriptionを cancel_at_period_end=true にする
      - manual_renewal: 更新ボタンを出さない/期限で停止
      を追加します。
    */

    $pdo->commit();

    header("Location: /dashboard/servers/?cancelled=1");
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: /dashboard/servers/?cancel_error=1");
    exit;
}
