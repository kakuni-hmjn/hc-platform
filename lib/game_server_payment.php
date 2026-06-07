<?php

require_once __DIR__ . "/game_server_provisioning.php";

function mark_game_server_order_paid_by_session(PDO $pdo, string $checkoutSessionId, array $stripeData = []): bool
{
    if ($checkoutSessionId === "") {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM game_server_orders
        WHERE stripe_checkout_session_id = :stripe_checkout_session_id
        LIMIT 1
    ");
    $stmt->execute([
        "stripe_checkout_session_id" => $checkoutSessionId,
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        return false;
    }

    if (($order["payment_status"] ?? "") === "paid") {
        return true;
    }

    $billingType = $order["billing_type"] ?? "auto_subscription";

    $expiresSql = "NULL";
    $nextPaymentSql = "NULL";

    if ($billingType === "manual_renewal") {
        $expiresSql = "NOW() + INTERVAL '30 days'";
        $nextPaymentSql = "NOW() + INTERVAL '30 days'";
    }

    if ($billingType === "auto_subscription") {
        $nextPaymentSql = "NOW() + INTERVAL '30 days'";
    }

    $update = $pdo->prepare("
        UPDATE game_server_orders
        SET
            payment_status = 'paid',
            status = 'paid',
            stripe_customer_id = :stripe_customer_id,
            stripe_subscription_id = :stripe_subscription_id,
            stripe_payment_intent_id = :stripe_payment_intent_id,
            paid_at = NOW(),
            expires_at = {$expiresSql},
            next_payment_due_at = {$nextPaymentSql},
            updated_at = NOW()
        WHERE id = :id
    ");

    $update->execute([
        "id" => (int)$order["id"],
        "stripe_customer_id" => $stripeData["customer"] ?? $order["stripe_customer_id"] ?? null,
        "stripe_subscription_id" => $stripeData["subscription"] ?? $order["stripe_subscription_id"] ?? null,
        "stripe_payment_intent_id" => $stripeData["payment_intent"] ?? $order["stripe_payment_intent_id"] ?? null,
    ]);

    provision_game_server_order($pdo, (int)$order["id"]);

    return true;
}

function mark_game_server_order_cancelled_by_id(PDO $pdo, int $orderId, int $userId = 0): bool
{
    if ($orderId <= 0) {
        return false;
    }

    if ($userId > 0) {
        $stmt = $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'cancelled',
                payment_status = 'cancelled',
                cancelled_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
            AND user_id = :user_id
            AND payment_status != 'paid'
        ");

        $stmt->execute([
            "id" => $orderId,
            "user_id" => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    $stmt = $pdo->prepare("
        UPDATE game_server_orders
        SET
            status = 'cancelled',
            payment_status = 'cancelled',
            cancelled_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
        AND payment_status != 'paid'
    ");

    $stmt->execute([
        "id" => $orderId,
    ]);

    return $stmt->rowCount() > 0;
}

function get_game_server_order_by_id(PDO $pdo, int $orderId): ?array
{
    if ($orderId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT
            gso.*,
            gsp.name AS plan_name,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        WHERE gso.id = :id
        LIMIT 1
    ");
    $stmt->execute([
        "id" => $orderId,
    ]);

    $order = $stmt->fetch();

    return $order ?: null;
}
