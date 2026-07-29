<?php

require_once __DIR__ . "/game_server_provisioning.php";
require_once __DIR__ . "/provisioning_jobs.php";
require_once __DIR__ . "/notifications.php";

function game_server_payment_record_event(
    PDO $pdo,
    int $orderId,
    string $eventType,
    string $title,
    ?string $message,
    ?string $oldStatus,
    ?string $newStatus,
    ?string $oldPaymentStatus,
    ?string $newPaymentStatus,
    array $metadata = []
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO server_order_events (
                order_id,
                actor_user_id,
                event_type,
                title,
                message,
                old_status,
                new_status,
                old_payment_status,
                new_payment_status,
                metadata_json,
                created_at
            )
            VALUES (
                :order_id,
                NULL,
                :event_type,
                :title,
                :message,
                :old_status,
                :new_status,
                :old_payment_status,
                :new_payment_status,
                CAST(:metadata_json AS JSONB),
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
            "metadata_json" => json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ]);
    } catch (Throwable $e) {
        error_log(
            "[game server payment event] order={$orderId} "
            . $e->getMessage()
        );
    }
}

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
        /*
         * Webhook再送時、支払い済みでも作成が未完了なら再試行する。
         */
        if (in_array(
            (string)($order["status"] ?? ""),
            ["paid", "provision_failed"],
            true
        )) {
            $result = provision_game_server_order(
                $pdo,
                (int)$order["id"]
            );

            if (empty($result["ok"])) {
                error_log(
                    "[game server provisioning retry] order="
                    . (int)$order["id"]
                    . " "
                    . (string)($result["error"] ?? "unknown error")
                );
            }
        }

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

    $orderId = (int)$order["id"];

    game_server_payment_record_event(
        $pdo,
        $orderId,
        "payment_completed",
        "Stripe支払いを確認しました",
        "Stripe Checkoutの支払い完了を確認しました。",
        (string)($order["status"] ?? "pending_payment"),
        "paid",
        (string)($order["payment_status"] ?? "unpaid"),
        "paid",
        [
            "checkout_session_id" => $checkoutSessionId,
            "stripe_customer_id" => $stripeData["customer"] ?? null,
            "stripe_subscription_id" => $stripeData["subscription"] ?? null,
            "stripe_payment_intent_id" => $stripeData["payment_intent"] ?? null,
        ]
    );

    game_server_payment_record_event(
        $pdo,
        $orderId,
        "provisioning_requested",
        "サーバー自動作成を開始します",
        "支払い完了により自動作成処理を開始しました。",
        "paid",
        "creating",
        "paid",
        "paid"
    );

    $enqueueResult = hc_enqueue_provisioning_job(
        $pdo,
        $orderId
    );

    if (empty($enqueueResult["ok"])) {
        game_server_payment_record_event(
            $pdo,
            $orderId,
            "provisioning_queue_failed",
            "自動作成ジョブの登録に失敗しました",
            (string)($enqueueResult["error"] ?? "不明なエラー"),
            "paid",
            "provision_failed",
            "paid",
            "paid"
        );

        $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'provision_failed',
                provision_error = :error,
                failed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            "id" => $orderId,
            "error" => $enqueueResult["error"]
                ?? "自動作成ジョブの登録に失敗しました。",
        ]);

        return true;
    }

    game_server_payment_record_event(
        $pdo,
        $orderId,
        "provisioning_queued",
        "サーバー自動作成ジョブを登録しました",
        "バックグラウンド処理によるゲームサーバー作成を待機しています。",
        "paid",
        "paid",
        "paid",
        "paid",
        [
            "job_id" => $enqueueResult["job"]["id"] ?? null,
        ]
    );

    hc_notify_user(
        $pdo,
        (int)$order["user_id"],
        "お支払いを確認しました",
        "「" . (string)$order["server_name"] . "」のゲームサーバー作成を開始します。",
        "/dashboard/servers/detail/?id=" . $orderId,
        "info",
        "server_provisioning_queued:order:" . $orderId
    );

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
