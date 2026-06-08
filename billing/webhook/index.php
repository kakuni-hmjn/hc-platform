<?php

require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/stripe.php";

$pdo = db();

function webhook_response(int $statusCode, array $body): void
{
    http_response_code($statusCode);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function webhook_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_events (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NULL REFERENCES game_server_orders(id) ON DELETE SET NULL,
            user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            event_type VARCHAR(120) NOT NULL,
            payment_status VARCHAR(60),
            amount INTEGER,
            currency VARCHAR(12) NOT NULL DEFAULT 'jpy',
            provider VARCHAR(40) NOT NULL DEFAULT 'stripe',
            provider_event_id VARCHAR(180) UNIQUE,
            provider_object_id VARCHAR(180),
            message TEXT,
            raw_payload JSONB,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        ALTER TABLE game_server_orders
        ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(160),
        ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(160),
        ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(160)
    ");
}

function webhook_extract_order_id_from_session(array $session): ?int
{
    $metadataOrderId = $session["metadata"]["hc_order_id"] ?? null;
    $clientReferenceId = $session["client_reference_id"] ?? null;

    $value = $metadataOrderId ?: $clientReferenceId;

    if (!$value || !ctype_digit((string)$value)) {
        return null;
    }

    return (int)$value;
}

function webhook_insert_payment_event(
    PDO $pdo,
    ?int $orderId,
    ?int $userId,
    string $eventType,
    ?string $paymentStatus,
    ?int $amount,
    string $currency,
    string $providerEventId,
    ?string $providerObjectId,
    string $message,
    array $rawPayload
): bool {
    $stmt = $pdo->prepare("
        INSERT INTO payment_events
        (
            order_id,
            user_id,
            event_type,
            payment_status,
            amount,
            currency,
            provider,
            provider_event_id,
            provider_object_id,
            message,
            raw_payload,
            created_at
        )
        VALUES
        (
            :order_id,
            :user_id,
            :event_type,
            :payment_status,
            :amount,
            :currency,
            'stripe',
            :provider_event_id,
            :provider_object_id,
            :message,
            :raw_payload,
            NOW()
        )
        ON CONFLICT (provider_event_id) DO NOTHING
    ");

    $stmt->execute([
        "order_id" => $orderId,
        "user_id" => $userId,
        "event_type" => $eventType,
        "payment_status" => $paymentStatus,
        "amount" => $amount,
        "currency" => $currency,
        "provider_event_id" => $providerEventId,
        "provider_object_id" => $providerObjectId,
        "message" => $message,
        "raw_payload" => json_encode($rawPayload, JSON_UNESCAPED_UNICODE),
    ]);

    return $stmt->rowCount() > 0;
}

function webhook_insert_order_event(
    PDO $pdo,
    int $orderId,
    string $eventType,
    string $title,
    string $message,
    ?string $oldStatus,
    ?string $newStatus,
    ?string $oldPaymentStatus,
    ?string $newPaymentStatus
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
                old_payment_status,
                new_payment_status,
                created_at
            )
            VALUES
            (
                :order_id,
                NULL,
                :event_type,
                :title,
                :message,
                :old_status,
                :new_status,
                :old_payment_status,
                :new_payment_status,
                NOW()
            )
        ");

        $stmt->execute([
            "order_id" => $orderId,
            "event_type" => $eventType,
            "title" => $title,
            "message" => $message,
            "old_status" => $oldStatus,
            "new_status" => $newStatus,
            "old_payment_status" => $oldPaymentStatus,
            "new_payment_status" => $newPaymentStatus,
        ]);
    } catch (Throwable $e) {
        // 契約イベントテーブル差異があってもWebhook全体は止めない
    }
}

function webhook_handle_checkout_completed(PDO $pdo, array $event, array $session): array
{
    $orderId = webhook_extract_order_id_from_session($session);

    if (!$orderId) {
        return [
            "ok" => false,
            "message" => "hc_order_id を取得できませんでした。",
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            status,
            payment_status
        FROM game_server_orders
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "id" => $orderId,
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        return [
            "ok" => false,
            "message" => "対象契約が見つかりません。",
        ];
    }

    $eventId = (string)($event["id"] ?? "");
    $eventType = (string)($event["type"] ?? "checkout.session.completed");

    if ($eventId === "") {
        return [
            "ok" => false,
            "message" => "Stripe event id がありません。",
        ];
    }

    $sessionId = (string)($session["id"] ?? "");
    $sessionStatus = (string)($session["status"] ?? "");
    $sessionPaymentStatus = (string)($session["payment_status"] ?? "");
    $subscriptionId = isset($session["subscription"]) ? (string)$session["subscription"] : null;
    $customerId = isset($session["customer"]) ? (string)$session["customer"] : null;
    $amountTotal = isset($session["amount_total"]) ? (int)$session["amount_total"] : null;
    $currency = strtolower((string)($session["currency"] ?? "jpy"));

    $inserted = webhook_insert_payment_event(
        $pdo,
        (int)$order["id"],
        (int)$order["user_id"],
        $eventType,
        $sessionPaymentStatus ?: null,
        $amountTotal,
        $currency,
        $eventId,
        $sessionId ?: null,
        "Stripe Checkout Session completed: " . ($sessionId ?: "-"),
        $event
    );

    if (!$inserted) {
        return [
            "ok" => true,
            "message" => "duplicate event ignored",
        ];
    }

    $oldStatus = (string)$order["status"];
    $oldPaymentStatus = (string)$order["payment_status"];

    $newPaymentStatus = $oldPaymentStatus;
    $newStatus = $oldStatus;

    if ($sessionStatus === "complete" || $sessionPaymentStatus === "paid") {
        $newPaymentStatus = "paid";

        if (in_array($oldStatus, ["pending_payment", "paid"], true)) {
            $newStatus = "paid";
        }
    }

    $update = $pdo->prepare("
        UPDATE game_server_orders
        SET
            payment_status = :payment_status,
            status = :status,
            stripe_checkout_session_id = COALESCE(:stripe_checkout_session_id, stripe_checkout_session_id),
            stripe_subscription_id = COALESCE(:stripe_subscription_id, stripe_subscription_id),
            stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id)
        WHERE id = :id
    ");

    $update->execute([
        "id" => (int)$order["id"],
        "payment_status" => $newPaymentStatus,
        "status" => $newStatus,
        "stripe_checkout_session_id" => $sessionId ?: null,
        "stripe_subscription_id" => $subscriptionId ?: null,
        "stripe_customer_id" => $customerId ?: null,
    ]);

    webhook_insert_order_event(
        $pdo,
        (int)$order["id"],
        "payment_paid",
        "Stripeで支払いが完了しました",
        "Checkout Session: " . ($sessionId ?: "-") . "\nSubscription: " . ($subscriptionId ?: "-") . "\nCustomer: " . ($customerId ?: "-"),
        $oldStatus,
        $newStatus,
        $oldPaymentStatus,
        $newPaymentStatus
    );

    return [
        "ok" => true,
        "message" => "checkout.session.completed processed",
        "order_id" => (int)$order["id"],
    ];
}

function webhook_find_order_by_subscription(PDO $pdo, ?string $subscriptionId, ?string $customerId): ?array
{
    if ($subscriptionId) {
        $stmt = $pdo->prepare("
            SELECT id, user_id, status, payment_status
            FROM game_server_orders
            WHERE stripe_subscription_id = :subscription_id
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            "subscription_id" => $subscriptionId,
        ]);

        $order = $stmt->fetch();

        if ($order) {
            return $order;
        }
    }

    if ($customerId) {
        $stmt = $pdo->prepare("
            SELECT id, user_id, status, payment_status
            FROM game_server_orders
            WHERE stripe_customer_id = :customer_id
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            "customer_id" => $customerId,
        ]);

        $order = $stmt->fetch();

        if ($order) {
            return $order;
        }
    }

    return null;
}

function webhook_handle_invoice_payment(PDO $pdo, array $event, array $invoice, bool $isSuccess): array
{
    $eventId = (string)($event["id"] ?? "");
    $eventType = (string)($event["type"] ?? "");

    if ($eventId === "") {
        return [
            "ok" => false,
            "message" => "Stripe event id がありません。",
        ];
    }

    $invoiceId = (string)($invoice["id"] ?? "");
    $subscriptionId = isset($invoice["subscription"]) ? (string)$invoice["subscription"] : null;
    $customerId = isset($invoice["customer"]) ? (string)$invoice["customer"] : null;
    $amountPaid = isset($invoice["amount_paid"]) ? (int)$invoice["amount_paid"] : null;
    $amountDue = isset($invoice["amount_due"]) ? (int)$invoice["amount_due"] : null;
    $currency = strtolower((string)($invoice["currency"] ?? "jpy"));

    $order = webhook_find_order_by_subscription($pdo, $subscriptionId, $customerId);

    $orderId = $order ? (int)$order["id"] : null;
    $userId = $order ? (int)$order["user_id"] : null;
    $newPaymentStatus = $isSuccess ? "paid" : "failed";
    $amount = $isSuccess ? $amountPaid : $amountDue;

    $inserted = webhook_insert_payment_event(
        $pdo,
        $orderId,
        $userId,
        $eventType,
        $newPaymentStatus,
        $amount,
        $currency,
        $eventId,
        $invoiceId ?: null,
        "Stripe invoice event: " . $eventType,
        $event
    );

    if (!$inserted) {
        return [
            "ok" => true,
            "message" => "duplicate event ignored",
        ];
    }

    if ($order) {
        $oldPaymentStatus = (string)$order["payment_status"];
        $oldStatus = (string)$order["status"];
        $newStatus = $oldStatus;

        $update = $pdo->prepare("
            UPDATE game_server_orders
            SET payment_status = :payment_status
            WHERE id = :id
        ");

        $update->execute([
            "id" => (int)$order["id"],
            "payment_status" => $newPaymentStatus,
        ]);

        webhook_insert_order_event(
            $pdo,
            (int)$order["id"],
            $isSuccess ? "invoice_payment_succeeded" : "invoice_payment_failed",
            $isSuccess ? "Stripeの継続支払いが成功しました" : "Stripeの継続支払いに失敗しました",
            "Invoice: " . ($invoiceId ?: "-") . "\nSubscription: " . ($subscriptionId ?: "-") . "\nCustomer: " . ($customerId ?: "-"),
            $oldStatus,
            $newStatus,
            $oldPaymentStatus,
            $newPaymentStatus
        );
    }

    return [
        "ok" => true,
        "message" => $eventType . " processed",
        "order_id" => $orderId,
    ];
}

function webhook_handle_subscription_deleted(PDO $pdo, array $event, array $subscription): array
{
    $eventId = (string)($event["id"] ?? "");
    $subscriptionId = (string)($subscription["id"] ?? "");

    if ($eventId === "") {
        return [
            "ok" => false,
            "message" => "Stripe event id がありません。",
        ];
    }

    $order = webhook_find_order_by_subscription($pdo, $subscriptionId ?: null, null);

    $orderId = $order ? (int)$order["id"] : null;
    $userId = $order ? (int)$order["user_id"] : null;

    $inserted = webhook_insert_payment_event(
        $pdo,
        $orderId,
        $userId,
        "customer.subscription.deleted",
        "cancelled",
        null,
        "jpy",
        $eventId,
        $subscriptionId ?: null,
        "Stripe subscription deleted: " . ($subscriptionId ?: "-"),
        $event
    );

    if (!$inserted) {
        return [
            "ok" => true,
            "message" => "duplicate event ignored",
        ];
    }

    if ($order) {
        $oldStatus = (string)$order["status"];
        $oldPaymentStatus = (string)$order["payment_status"];

        $update = $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'cancelled',
                payment_status = 'cancelled'
            WHERE id = :id
        ");

        $update->execute([
            "id" => (int)$order["id"],
        ]);

        webhook_insert_order_event(
            $pdo,
            (int)$order["id"],
            "subscription_deleted",
            "Stripeサブスクリプションが削除されました",
            "Subscription: " . ($subscriptionId ?: "-"),
            $oldStatus,
            "cancelled",
            $oldPaymentStatus,
            "cancelled"
        );
    }

    return [
        "ok" => true,
        "message" => "subscription deleted processed",
        "order_id" => $orderId,
    ];
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    webhook_response(405, [
        "ok" => false,
        "error" => "Method Not Allowed",
    ]);
}

$payload = file_get_contents("php://input");

if ($payload === false || $payload === "") {
    webhook_response(400, [
        "ok" => false,
        "error" => "empty payload",
    ]);
}

$signatureHeader = $_SERVER["HTTP_STRIPE_SIGNATURE"] ?? "";

try {
    $secret = hc_stripe_webhook_secret();

    if (!hc_stripe_verify_webhook_signature($payload, $signatureHeader, $secret)) {
        webhook_response(400, [
            "ok" => false,
            "error" => "invalid signature",
        ]);
    }

    $event = json_decode($payload, true);

    if (!is_array($event)) {
        webhook_response(400, [
            "ok" => false,
            "error" => "invalid json",
        ]);
    }

    $eventType = (string)($event["type"] ?? "");
    $object = $event["data"]["object"] ?? null;

    if (!is_array($object)) {
        webhook_response(400, [
            "ok" => false,
            "error" => "invalid snapshot object",
        ]);
    }

    webhook_ensure_schema($pdo);

    $pdo->beginTransaction();

    $result = match ($eventType) {
        "checkout.session.completed" => webhook_handle_checkout_completed($pdo, $event, $object),
        "invoice.payment_succeeded" => webhook_handle_invoice_payment($pdo, $event, $object, true),
        "invoice.payment_failed" => webhook_handle_invoice_payment($pdo, $event, $object, false),
        "customer.subscription.deleted" => webhook_handle_subscription_deleted($pdo, $event, $object),
        default => [
            "ok" => true,
            "message" => "ignored event: " . $eventType,
        ],
    };

    if (!in_array($eventType, [
        "checkout.session.completed",
        "invoice.payment_succeeded",
        "invoice.payment_failed",
        "customer.subscription.deleted",
    ], true)) {
        $providerEventId = (string)($event["id"] ?? "");

        if ($providerEventId !== "") {
            webhook_insert_payment_event(
                $pdo,
                null,
                null,
                $eventType,
                null,
                null,
                "jpy",
                $providerEventId,
                isset($object["id"]) ? (string)$object["id"] : null,
                "Unhandled Stripe snapshot event: " . $eventType,
                $event
            );
        }
    }

    $pdo->commit();

    webhook_response(200, $result);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    webhook_response(500, [
        "ok" => false,
        "error" => $e->getMessage(),
    ]);
}
