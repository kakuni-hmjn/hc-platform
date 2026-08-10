<?php

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/pterodactyl.php";
require_once __DIR__ . "/notifications.php";

function hc_server_approval_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        ALTER TABLE game_server_orders
        ADD COLUMN IF NOT EXISTS approval_requested_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS approval_started_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS approved_by INTEGER NULL,
        ADD COLUMN IF NOT EXISTS approved_via VARCHAR(30) NULL,
        ADD COLUMN IF NOT EXISTS approval_error TEXT NULL,
        ADD COLUMN IF NOT EXISTS approval_attempts INTEGER NOT NULL DEFAULT 0
    ");

    $pdo->exec("
        ALTER TABLE ptero_servers
        ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMP NULL
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS server_order_events (
            id BIGSERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL,
            actor_user_id INTEGER NULL,
            event_type VARCHAR(80) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NULL,
            old_status VARCHAR(50) NULL,
            new_status VARCHAR(50) NULL,
            old_payment_status VARCHAR(50) NULL,
            new_payment_status VARCHAR(50) NULL,
            metadata_json JSONB NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        ALTER TABLE server_order_events
        ADD COLUMN IF NOT EXISTS metadata_json JSONB NULL
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_server_order_events_order_id
        ON server_order_events(order_id)
    ");
}

function hc_server_approval_record_event(
    PDO $pdo,
    int $orderId,
    ?int $actorUserId,
    string $eventType,
    string $title,
    ?string $message,
    ?string $oldStatus,
    ?string $newStatus,
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
                metadata_json,
                created_at
            )
            VALUES (
                :order_id,
                :actor_user_id,
                :event_type,
                :title,
                :message,
                :old_status,
                :new_status,
                CAST(:metadata_json AS JSONB),
                NOW()
            )
        ");

        $stmt->execute([
            "order_id" => $orderId,
            "actor_user_id" => $actorUserId,
            "event_type" => $eventType,
            "title" => $title,
            "message" => $message,
            "old_status" => $oldStatus,
            "new_status" => $newStatus,
            "metadata_json" => json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ]);
    } catch (Throwable $e) {
        error_log(
            "[server approval event] order={$orderId} "
            . $e->getMessage()
        );
    }
}

function hc_server_approval_unsuspend(int $pteroServerId): array
{
    if ($pteroServerId <= 0) {
        return [
            "ok" => false,
            "error" => "PterodactylサーバーIDが不正です。",
        ];
    }

    if (function_exists("ptero_is_mock") && hc_ptero_mock()) {
        return [
            "ok" => true,
            "mock" => true,
            "error" => null,
        ];
    }

    $path = "/api/application/servers/"
        . rawurlencode((string)$pteroServerId)
        . "/unsuspend";

    try {
        if (function_exists("ptero_request")) {
            $result = ptero_request("POST", $path, []);
        } elseif (function_exists("hc_ptero_request")) {
            $result = hc_ptero_request("POST", $path, []);
        } else {
            throw new RuntimeException(
                "Pterodactyl APIリクエスト関数が見つかりません。"
            );
        }

        if (
            is_array($result)
            && array_key_exists("ok", $result)
            && empty($result["ok"])
        ) {
            return [
                "ok" => false,
                "error" => $result["error"]
                    ?? "Pterodactylサーバーの再開に失敗しました。",
            ];
        }

        return [
            "ok" => true,
            "mock" => is_array($result)
                ? !empty($result["mock"])
                : false,
            "error" => null,
        ];
    } catch (Throwable $e) {
        return [
            "ok" => false,
            "error" => $e->getMessage(),
        ];
    }
}

function hc_approve_game_server_order(
    PDO $pdo,
    int $orderId,
    int $actorUserId,
    string $source = "web"
): array {
    if ($orderId <= 0) {
        return [
            "ok" => false,
            "error" => "注文IDが不正です。",
        ];
    }

    if ($actorUserId <= 0) {
        return [
            "ok" => false,
            "error" => "承認者IDが不正です。",
        ];
    }

    if (!in_array($source, ["web", "discord", "api"], true)) {
        $source = "web";
    }

    hc_server_approval_ensure_schema($pdo);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                gso.id,
                gso.user_id,
                gso.status,
                gso.payment_status,
                gso.server_name,
                gso.approval_attempts,
                ps.id AS local_server_id,
                ps.ptero_server_id,
                ps.status AS ptero_server_status
            FROM game_server_orders gso
            LEFT JOIN ptero_servers ps
                ON ps.order_id = gso.id
            WHERE gso.id = :order_id
            LIMIT 1
            FOR UPDATE OF gso
        ");

        $stmt->execute([
            "order_id" => $orderId,
        ]);

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new RuntimeException("注文が見つかりません。");
        }

        $oldStatus = (string)$order["status"];

        if ($oldStatus === "active") {
            $pdo->commit();

            return [
                "ok" => true,
                "already" => true,
                "error" => null,
            ];
        }

        if (!in_array($oldStatus, ["pending_approval", "approval_failed"], true)) {
            throw new RuntimeException(
                "この注文は承認待ちではありません。現在の状態: "
                . $oldStatus
            );
        }

        if ((string)$order["payment_status"] !== "paid") {
            throw new RuntimeException(
                "支払い済みでないため承認できません。"
            );
        }

        $pteroServerId = (int)($order["ptero_server_id"] ?? 0);

        if ($pteroServerId <= 0) {
            throw new RuntimeException(
                "PterodactylサーバーIDが登録されていません。"
            );
        }

        $claim = $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'activating',
                approval_started_at = NOW(),
                approval_attempts = approval_attempts + 1,
                approval_error = NULL,
                updated_at = NOW()
            WHERE id = :order_id
              AND status IN ('pending_approval', 'approval_failed')
        ");

        $claim->execute([
            "order_id" => $orderId,
        ]);

        if ($claim->rowCount() !== 1) {
            throw new RuntimeException(
                "別の処理がすでに承認を開始しています。"
            );
        }

        $pdo->commit();

        $unsuspend = hc_server_approval_unsuspend($pteroServerId);

        if (empty($unsuspend["ok"])) {
            $error = $unsuspend["error"]
                ?? "Pterodactylサーバーの再開に失敗しました。";

            $pdo->beginTransaction();

            $pdo->prepare("
                UPDATE game_server_orders
                SET
                    status = 'approval_failed',
                    approval_error = :approval_error,
                    updated_at = NOW()
                WHERE id = :order_id
                  AND status = 'activating'
            ")->execute([
                "order_id" => $orderId,
                "approval_error" => $error,
            ]);

            hc_server_approval_record_event(
                $pdo,
                $orderId,
                $actorUserId,
                "server_approval_failed",
                "サーバー承認処理に失敗しました",
                $error,
                "activating",
                "approval_failed",
                [
                    "source" => $source,
                    "ptero_server_id" => $pteroServerId,
                ]
            );

            $pdo->commit();

            return [
                "ok" => false,
                "error" => $error,
            ];
        }

        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'active',
                approved_at = NOW(),
                approved_by = :approved_by,
                approved_via = :approved_via,
                approval_error = NULL,
                updated_at = NOW()
            WHERE id = :order_id
              AND status = 'activating'
        ")->execute([
            "order_id" => $orderId,
            "approved_by" => $actorUserId,
            "approved_via" => $source,
        ]);

        $pdo->prepare("
            UPDATE ptero_servers
            SET
                status = 'active',
                suspended_at = NULL,
                updated_at = NOW()
            WHERE order_id = :order_id
        ")->execute([
            "order_id" => $orderId,
        ]);

        hc_server_approval_record_event(
            $pdo,
            $orderId,
            $actorUserId,
            "server_approved",
            "ゲームサーバーを承認しました",
            "管理者承認によりゲームサーバーが利用可能になりました。",
            $oldStatus,
            "active",
            [
                "source" => $source,
                "ptero_server_id" => $pteroServerId,
                "mock" => !empty($unsuspend["mock"]),
            ]
        );

        hc_notify_user(
            $pdo,
            (int)$order["user_id"],
            "ゲームサーバーが利用可能になりました",
            "「" . (string)$order["server_name"] . "」の準備と管理者確認が完了しました。ゲームサーバーパネルから利用を開始できます。",
            "/dashboard/ptero-account/",
            "success",
            "server_activated:order:" . $orderId
        );

        $pdo->commit();

        return [
            "ok" => true,
            "already" => false,
            "order_id" => $orderId,
            "ptero_server_id" => $pteroServerId,
            "status" => "active",
            "source" => $source,
            "error" => null,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            "ok" => false,
            "error" => $e->getMessage(),
        ];
    }
}
