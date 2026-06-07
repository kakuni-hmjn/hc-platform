<?php

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/pterodactyl.php";

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

    if (in_array($order["status"], ["creating", "active"], true)) {
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
    $pteroUserId = (int)$config["default_user_id"];

    if ($nodeId <= 0 || $nestId <= 0 || $eggId <= 0 || $pteroUserId <= 0) {
        return [
            "ok" => false,
            "error" => "Pterodactyl作成に必要なNode/Nest/Egg/User IDが不足しています。",
        ];
    }

    $pdo->prepare("
        UPDATE game_server_orders
        SET
            status = 'creating',
            provisioning_started_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ")->execute([
        "id" => $orderId,
    ]);

    $serverName = (string)$order["server_name"];

    $payload = [
        "name" => $serverName,
        "user" => $pteroUserId,
        "egg" => $eggId,
        "docker_image" => $order["ptero_docker_image"] ?: "ghcr.io/pterodactyl/yolks:java_21",
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

    if (!ptero_is_mock()) {
        $payload["deploy"] = [
            "locations" => [(int)$config["default_location_id"]],
            "dedicated_ip" => false,
            "port_range" => [],
        ];

        unset($payload["allocation"]);
    }

    $result = ptero_create_server($payload);

    if (empty($result["ok"])) {
        $error = $result["error"] ?? "Pterodactylサーバー作成に失敗しました。";

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

    $attr = $result["data"]["attributes"] ?? [];

    $pteroServerId = $attr["id"] ?? null;
    $pteroIdentifier = $attr["identifier"] ?? null;
    $pteroUuid = $attr["uuid"] ?? null;

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
            'active',
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

    $pdo->prepare("
        UPDATE game_server_orders
        SET
            status = 'active',
            provisioned_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ")->execute([
        "id" => $orderId,
    ]);

    return [
        "ok" => true,
        "mock" => !empty($result["mock"]),
        "ptero_server_id" => $pteroServerId,
        "ptero_identifier" => $pteroIdentifier,
        "ptero_uuid" => $pteroUuid,
        "error" => null,
    ];
}
