<?php

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/pterodactyl.php";
require_once __DIR__ . "/ptero_users.php";
require_once __DIR__ . "/notifications.php";

function hc_provision_record_event(
    PDO $pdo,
    int $orderId,
    string $eventType,
    string $title,
    ?string $message = null,
    ?string $oldStatus = null,
    ?string $newStatus = null,
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
                NULL,
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
            "[provision event] order={$orderId} "
            . $e->getMessage()
        );
    }
}

function hc_provision_suspend_server(int $pteroServerId): array
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

    try {
        $path = "/api/application/servers/"
            . rawurlencode((string)$pteroServerId)
            . "/suspend";

        if (function_exists("ptero_request")) {
            $result = ptero_request("POST", $path, []);
        } elseif (function_exists("hc_ptero_request")) {
            $result = hc_ptero_request("POST", $path, []);
        } else {
            throw new RuntimeException(
                "Pterodactyl APIリクエスト関数が見つかりません。"
            );
        }

        if (is_array($result) && array_key_exists("ok", $result)) {
            return [
                "ok" => !empty($result["ok"]),
                "mock" => hc_ptero_mock(),
                "error" => $result["error"] ?? null,
            ];
        }

        return [
            "ok" => true,
            "mock" => false,
            "error" => null,
        ];
    } catch (Throwable $e) {
        return [
            "ok" => false,
            "error" => $e->getMessage(),
        ];
    }
}

function provision_game_server_order(PDO $pdo, int $orderId): array
{
    if ($orderId <= 0) {
        return [
            "ok" => false,
            "error" => "注文IDが不正です。",
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            gso.*,
            gsp.name AS plan_name,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,
            gsp.backup_limit,
            gsp.database_limit,
            gsp.allocation_limit,
            gsp.ptero_nest_id,
            gsp.ptero_egg_id,
            gsp.ptero_docker_image,
            gsp.ptero_startup_command,
            pn.ptero_node_id,
            pn.name AS node_name
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        LEFT JOIN ptero_nodes pn ON pn.id = gso.selected_node_id
        WHERE gso.id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "id" => $orderId,
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        return [
            "ok" => false,
            "error" => "注文が見つかりません。",
        ];
    }

    if (($order["payment_status"] ?? "") !== "paid") {
        return [
            "ok" => false,
            "error" => "決済が完了していないため作成できません。",
        ];
    }

    if (in_array($order["status"], ["creating", "pending_approval", "active"], true)) {
        return [
            "ok" => true,
            "already" => true,
            "error" => null,
        ];
    }

    $existingStmt = $pdo->prepare("
        SELECT id
        FROM ptero_servers
        WHERE order_id = :order_id
        LIMIT 1
    ");
    $existingStmt->execute([
        "order_id" => $orderId,
    ]);

    if ($existingStmt->fetch()) {
        return [
            "ok" => true,
            "already" => true,
            "error" => null,
        ];
    }

    $config = ptero_config();

    $nodeId = (int)($order["ptero_node_id"] ?: $config["default_node_id"]);
    $nestId = (int)($order["ptero_nest_id"] ?: $config["default_nest_id"]);
    $eggId = (int)($order["ptero_egg_id"] ?: $config["default_egg_id"]);

    if ($nodeId <= 0 || $nestId <= 0 || $eggId <= 0) {
        return [
            "ok" => false,
            "error" => "Pterodactyl作成に必要なNode/Nest/Egg IDが不足しています。",
        ];
    }

    try {
        $pteroUser = hc_ptero_ensure_user_for_hc_user(
            $pdo,
            (int)$order["user_id"]
        );

        $pteroUserId = (int)($pteroUser["ptero_user_id"] ?? 0);

        if ($pteroUserId <= 0) {
            throw new RuntimeException(
                "PterodactylユーザーIDを取得できませんでした。"
            );
        }

        hc_provision_record_event(
            $pdo,
            $orderId,
            "ptero_user_resolved",
            "Pterodactylユーザーを準備しました",
            "注文ユーザーに対応するPterodactylアカウントを取得しました。",
            "creating",
            "creating",
            [
                "hc_user_id" => (int)$order["user_id"],
                "ptero_user_id" => $pteroUserId,
                "ptero_username" => $pteroUser["ptero_username"] ?? null,
                "ptero_email" => $pteroUser["ptero_email"] ?? null,
                "link_action" => $pteroUser["action"] ?? null,
            ]
        );
    } catch (Throwable $e) {
        $error = "Pterodactylユーザーの準備に失敗しました: "
            . $e->getMessage();

        $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'provision_failed',
                provision_error = :provision_error,
                failed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            "id" => $orderId,
            "provision_error" => $error,
        ]);

        hc_provision_record_event(
            $pdo,
            $orderId,
            "ptero_server_create_failed",
            "Pterodactylサーバー作成に失敗しました",
            $error,
            "creating",
            "provision_failed",
            [
                "ptero_user_id" => $pteroUserId,
                "node_id" => $nodeId,
                "nest_id" => $nestId,
                "egg_id" => $eggId,
            ]
        );

        hc_notify_user(
            $pdo,
            (int)$order["user_id"],
            "ゲームサーバーの作成に時間がかかっています",
            "「" . (string)$order["server_name"] . "」の作成処理で問題が発生しました。運営側で確認と再試行を行います。",
            "/dashboard/servers/detail/?id=" . $orderId,
            "error",
            "server_provision_failed:order:" . $orderId
        );

        return [
            "ok" => false,
            "error" => $error,
        ];
    }

    $pdo->prepare("
        UPDATE game_server_orders
        SET
            status = 'creating',
            provisioning_started_at = NOW(),
            provision_error = NULL,
            failed_at = NULL,
            updated_at = NOW()
        WHERE id = :id
    ")->execute([
        "id" => $orderId,
    ]);

    hc_provision_record_event(
        $pdo,
        $orderId,
        "provisioning_started",
        "ゲームサーバー自動作成を開始しました",
        "Pterodactylユーザーとゲームサーバーの準備を開始しました。",
        (string)$order["status"],
        "creating",
        [
            "user_id" => (int)$order["user_id"],
            "plan_id" => (int)$order["plan_id"],
            "node_id" => $nodeId,
            "nest_id" => $nestId,
            "egg_id" => $eggId,
        ]
    );

    $serverName = (string)$order["server_name"];

    $payload = [
        "name" => $serverName,
        "user" => $pteroUserId,
        "egg" => $eggId,
        "docker_image" => $order["ptero_docker_image"] ?: "ghcr.io/pterodactyl/yolks:java_25",
        "startup" => $order["ptero_startup_command"] ?: "java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}",
        "environment" => [
            "SERVER_JARFILE" => "server.jar",
            "MINECRAFT_VERSION" => $order["minecraft_version"] ?: "latest",
            "BUILD_NUMBER" => "latest",
        ],
        "limits" => [
            "memory" => (int)$order["memory_mb"],
            "swap" => 0,
            "disk" => (int)$order["disk_mb"],
            "io" => 500,
            "cpu" => (int)$order["cpu_limit"],
        ],
        "feature_limits" => [
            "databases" => (int)$order["database_limit"],
            "allocations" => (int)$order["allocation_limit"],
            "backups" => (int)$order["backup_limit"],
        ],
        "allocation" => [
            "default" => null,
        ],
        "deploy" => [
            "locations" => [],
            "dedicated_ip" => false,
            "port_range" => [],
        ],
        "start_on_completion" => false,
        "external_id" => "hc-order-" . $orderId,
        "description" => "Created by HC Platform / Order #" . $orderId,
    ];

    if (!hc_ptero_mock()) {
        $payload["deploy"] = [
            "locations" => [(int)$config["default_location_id"]],
            "dedicated_ip" => false,
            "port_range" => [],
        ];

        unset($payload["allocation"]);
    }

    try {
        $result = hc_ptero_create_server($payload);
    } catch (Throwable $e) {
        $result = [];
        $createError = $e->getMessage();
    }

    $attr = $result["attributes"]
        ?? $result["data"]["attributes"]
        ?? [];

    if (empty($attr) || empty($attr["id"])) {
        $error = $createError
            ?? $result["error"]
            ?? "Pterodactylサーバー作成に失敗しました。";

        $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'provision_failed',
                provision_error = :provision_error,
                failed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            "id" => $orderId,
            "provision_error" => $error,
        ]);

        return [
            "ok" => false,
            "error" => $error,
        ];
    }

    $pteroServerId = $attr["id"] ?? null;
    $pteroIdentifier = $attr["identifier"] ?? null;
    $pteroUuid = $attr["uuid"] ?? null;

    hc_provision_record_event(
        $pdo,
        $orderId,
        "ptero_server_created",
        "Pterodactylサーバーを作成しました",
        "ゲームサーバー本体の作成が完了しました。",
        "creating",
        "creating",
        [
            "ptero_server_id" => $pteroServerId,
            "ptero_identifier" => $pteroIdentifier,
            "ptero_uuid" => $pteroUuid,
            "ptero_user_id" => $pteroUserId,
            "node_id" => $nodeId,
            "mock" => hc_ptero_mock(),
        ]
    );

    $insertServer = $pdo->prepare("
        INSERT INTO ptero_servers (
            user_id,
            order_id,
            plan_id,
            node_id,
            ptero_user_id,
            ptero_server_id,
            ptero_identifier,
            ptero_uuid,
            name,
            status,
            created_at
        ) VALUES (
            :user_id,
            :order_id,
            :plan_id,
            :node_id,
            :ptero_user_id,
            :ptero_server_id,
            :ptero_identifier,
            :ptero_uuid,
            :name,
            'pending_approval',
            NOW()
        )
    ");

    $insertServer->execute([
        "user_id" => (int)$order["user_id"],
        "order_id" => $orderId,
        "plan_id" => (int)$order["plan_id"],
        "node_id" => $order["selected_node_id"] !== null ? (int)$order["selected_node_id"] : null,
        "ptero_user_id" => $pteroUserId,
        "ptero_server_id" => $pteroServerId,
        "ptero_identifier" => $pteroIdentifier,
        "ptero_uuid" => $pteroUuid,
        "name" => $serverName,
    ]);

    $suspendResult = hc_provision_suspend_server(
        (int)$pteroServerId
    );

    if (empty($suspendResult["ok"])) {
        $error = "サーバー作成後のサスペンドに失敗しました: "
            . ($suspendResult["error"] ?? "不明なエラー");

        $pdo->prepare("
            UPDATE ptero_servers
            SET
                status = 'suspend_failed',
                updated_at = NOW()
            WHERE order_id = :order_id
        ")->execute([
            "order_id" => $orderId,
        ]);

        $pdo->prepare("
            UPDATE game_server_orders
            SET
                status = 'provision_failed',
                provision_error = :provision_error,
                failed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            "id" => $orderId,
            "provision_error" => $error,
        ]);

        return [
            "ok" => false,
            "error" => $error,
        ];
    }

    $pdo->prepare("
        UPDATE ptero_servers
        SET
            status = 'pending_approval',
            suspended_at = NOW(),
            updated_at = NOW()
        WHERE order_id = :order_id
    ")->execute([
        "order_id" => $orderId,
    ]);

    hc_provision_record_event(
        $pdo,
        $orderId,
        "ptero_server_suspended",
        "ゲームサーバーを承認待ち状態にしました",
        "管理者承認までPterodactylサーバーを停止状態にしました。",
        "creating",
        "creating",
        [
            "ptero_server_id" => $pteroServerId,
            "mock" => !empty($suspendResult["mock"]),
        ]
    );

    $pdo->prepare("
        UPDATE game_server_orders
        SET
            status = 'pending_approval',
            provision_error = NULL,
            provisioned_at = NOW(),
            approval_requested_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ")->execute([
        "id" => $orderId,
    ]);

    hc_provision_record_event(
        $pdo,
        $orderId,
        "approval_requested",
        "サーバー利用開始の承認待ちです",
        "自動作成が完了し、管理者による利用開始承認待ちになりました。",
        "creating",
        "pending_approval",
        [
            "ptero_server_id" => $pteroServerId,
            "ptero_identifier" => $pteroIdentifier,
            "ptero_uuid" => $pteroUuid,
            "ptero_user_id" => $pteroUserId,
        ]
    );

    hc_notify_user(
        $pdo,
        (int)$order["user_id"],
        "ゲームサーバーの作成が完了しました",
        "「" . (string)$order["server_name"] . "」は現在、管理者による最終確認中です。承認後に利用可能になります。",
        "/dashboard/servers/detail/?id=" . $orderId,
        "warning",
        "server_pending_approval:order:" . $orderId
    );

    return [
        "ok" => true,
        "mock" => hc_ptero_mock(),
        "pending_approval" => true,
        "ptero_server_id" => $pteroServerId,
        "ptero_identifier" => $pteroIdentifier,
        "ptero_uuid" => $pteroUuid,
        "error" => null,
    ];
}
