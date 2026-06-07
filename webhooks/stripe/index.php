<?php

require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/stripe.php";
require_once __DIR__ . "/../../lib/game_server_payment.php";

$pdo = db();
$config = stripe_config();

$payload = file_get_contents("php://input");
$signature = $_SERVER["HTTP_STRIPE_SIGNATURE"] ?? "";

if ($payload === false || $payload === "") {
    http_response_code(400);
    echo "Empty payload";
    exit;
}

if (!empty($config["mock"])) {
    http_response_code(200);
    echo "Mock mode";
    exit;
}

if (empty($config["webhook_secret"])) {
    http_response_code(500);
    echo "Webhook secret is not configured";
    exit;
}

if (!stripe_load_sdk()) {
    http_response_code(500);
    echo "Stripe SDK is not available";
    exit;
}

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $signature,
        $config["webhook_secret"]
    );
} catch (Throwable $e) {
    http_response_code(400);
    echo "Invalid signature";
    exit;
}

$eventId = $event->id;
$eventType = $event->type;

try {
    $insertEvent = $pdo->prepare("
        INSERT INTO stripe_events (
            stripe_event_id,
            event_type,
            processed,
            payload,
            created_at
        ) VALUES (
            :stripe_event_id,
            :event_type,
            false,
            :payload,
            NOW()
        )
        ON CONFLICT (stripe_event_id) DO NOTHING
    ");

    $insertEvent->execute([
        "stripe_event_id" => $eventId,
        "event_type" => $eventType,
        "payload" => $payload,
    ]);

    if ($insertEvent->rowCount() === 0) {
        http_response_code(200);
        echo "Duplicate event";
        exit;
    }

    $pdo->beginTransaction();

    if ($eventType === "checkout.session.completed") {
        $session = $event->data->object;

        mark_game_server_order_paid_by_session($pdo, (string)$session->id, [
            "customer" => isset($session->customer) ? (string)$session->customer : null,
            "subscription" => isset($session->subscription) ? (string)$session->subscription : null,
            "payment_intent" => isset($session->payment_intent) ? (string)$session->payment_intent : null,
        ]);
    }

    if ($eventType === "checkout.session.expired") {
        $session = $event->data->object;
        $orderId = isset($session->metadata->order_id) ? (int)$session->metadata->order_id : 0;

        if ($orderId > 0) {
            mark_game_server_order_cancelled_by_id($pdo, $orderId);
        }
    }

    if ($eventType === "invoice.paid") {
        $invoice = $event->data->object;
        $subscriptionId = isset($invoice->subscription) ? (string)$invoice->subscription : "";

        if ($subscriptionId !== "") {
            $stmt = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    payment_status = 'paid',
                    next_payment_due_at = NOW() + INTERVAL '30 days',
                    updated_at = NOW()
                WHERE stripe_subscription_id = :stripe_subscription_id
            ");
            $stmt->execute([
                "stripe_subscription_id" => $subscriptionId,
            ]);
        }
    }

    if ($eventType === "invoice.payment_failed") {
        $invoice = $event->data->object;
        $subscriptionId = isset($invoice->subscription) ? (string)$invoice->subscription : "";

        if ($subscriptionId !== "") {
            $stmt = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    payment_status = 'failed',
                    updated_at = NOW()
                WHERE stripe_subscription_id = :stripe_subscription_id
            ");
            $stmt->execute([
                "stripe_subscription_id" => $subscriptionId,
            ]);
        }
    }

    if ($eventType === "customer.subscription.deleted") {
        $subscription = $event->data->object;
        $subscriptionId = (string)$subscription->id;

        $stmt = $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'cancelled',
                payment_status = 'cancelled',
                cancelled_at = NOW(),
                updated_at = NOW()
            WHERE stripe_subscription_id = :stripe_subscription_id
        ");
        $stmt->execute([
            "stripe_subscription_id" => $subscriptionId,
        ]);
    }

    $markProcessed = $pdo->prepare("
        UPDATE stripe_events
        SET
            processed = true,
            processed_at = NOW()
        WHERE stripe_event_id = :stripe_event_id
    ");
    $markProcessed->execute([
        "stripe_event_id" => $eventId,
    ]);

    $pdo->commit();

    http_response_code(200);
    echo "OK";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo "Webhook processing failed";
}
