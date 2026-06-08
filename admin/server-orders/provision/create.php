<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";
require_once __DIR__ . "/../../../lib/pterodactyl.php";
require_once __DIR__ . "/../../../lib/ptero_users.php";
require_once __DIR__ . "/../../../lib/notifications.php";

$adminUser = require_role("admin");
$pdo = db();

function provision_flash(string $type, string $message): void
{
    $_SESSION["provision_orders_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function provision_redirect(): void
{
    header("Location: /admin/server-orders/provision/");
    exit;
}

function provision_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ptero_servers (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL REFERENCES game_server_orders(id) ON DELETE CASCADE,
            node_id INTEGER NULL,
            ptero_user_id INTEGER NULL,
            ptero_server_id INTEGER NULL,
            ptero_identifier VARCHAR(80),
            ptero_uuid VARCHAR(120),
            name VARCHAR(160),
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        )
    ");

    $pdo->exec("
        ALTER TABLE ptero_servers
        ADD COLUMN IF NOT EXISTS ptero_allocation_id INTEGER NULL
    ");

    $pdo->exec("
        ALTER TABLE game_server_orders
        ADD COLUMN IF NOT EXISTS selected_node_id INTEGER NULL,
        ADD COLUMN IF NOT EXISTS provisioning_started_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS provisioned_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS failed_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS provision_error TEXT NULL,
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL
    ");
}

function provision_insert_event(
    PDO $pdo,
    int $orderId,
    ?int $actorUserId,
    string $eventType,
    string $title,
    string $message,
    ?string $oldStatus,
    ?string $newStatus
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
                :event_type,
                :title,
                :message,
                :old_status,
                :new_status,
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
        ]);
    } catch (Throwable $e) {
    }
}

function provision_table_has_column(PDO $pdo, string $tableName, string $columnName): bool
{
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS count
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = :table_name
              AND column_name = :column_name
        ");

        $stmt->execute([
            "table_name" => $tableName,
            "column_name" => $columnName,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function provision_fetch_candidate_nodes(PDO $pdo, ?int $selectedNodeId): array
{
    if ($selectedNodeId && $selectedNodeId > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    name,
                    ptero_node_id
                FROM ptero_nodes
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                "id" => $selectedNodeId,
            ]);

            $node = $stmt->fetch();

            if ($node) {
                return [$node];
            }
        } catch (Throwable $e) {
            if (!hc_ptero_mock()) {
                throw new RuntimeException("選択済みNodeの取得に失敗しました: " . $e->getMessage());
            }
        }

        if (hc_ptero_mock()) {
            return [[
                "id" => null,
                "name" => "Mock Node",
                "ptero_node_id" => 1,
            ]];
        }

        throw new RuntimeException("選択済みNodeが見つかりません。selected_node_id=" . (string)$selectedNodeId);
    }

    try {
        $hasIsActive = provision_table_has_column($pdo, "ptero_nodes", "is_active");

        $where = $hasIsActive ? "WHERE is_active = true" : "";

        $stmt = $pdo->query("
            SELECT
                id,
                name,
                ptero_node_id
            FROM ptero_nodes
            {$where}
            ORDER BY id ASC
        ");

        $nodes = $stmt->fetchAll();

        if ($nodes) {
            return $nodes;
        }
    } catch (Throwable $e) {
        if (!hc_ptero_mock()) {
            throw new RuntimeException("Node一覧の取得に失敗しました: " . $e->getMessage());
        }
    }

    if (hc_ptero_mock()) {
        return [[
            "id" => null,
            "name" => "Mock Node",
            "ptero_node_id" => 1,
        ]];
    }

    throw new RuntimeException("利用可能なゲームサーバーパネル Nodeがありません。先にNodeを登録してください。");
}

function provision_choose_node_and_allocation(PDO $pdo, ?int $selectedNodeId): array
{
    $nodes = provision_fetch_candidate_nodes($pdo, $selectedNodeId);
    $errors = [];

    foreach ($nodes as $node) {
        $localNodeId = isset($node["id"]) && $node["id"] !== null ? (int)$node["id"] : null;
        $nodeName = (string)($node["name"] ?? "Node");
        $pteroNodeId = (int)($node["ptero_node_id"] ?? 0);

        if ($pteroNodeId <= 0 && hc_ptero_mock()) {
            $pteroNodeId = 1;
        }

        if ($pteroNodeId <= 0) {
            $errors[] = "{$nodeName}: ゲームサーバーパネル Node IDが未設定です。";
            continue;
        }

        try {
            $allocation = hc_ptero_find_free_allocation($pteroNodeId);

            return [
                "node" => [
                    "id" => $localNodeId,
                    "name" => $nodeName,
                    "ptero_node_id" => $pteroNodeId,
                ],
                "allocation" => $allocation,
            ];
        } catch (Throwable $e) {
            $errors[] = "{$nodeName} / パネルNode #{$pteroNodeId}: " . $e->getMessage();

            if ($selectedNodeId && $selectedNodeId > 0) {
                break;
            }
        }
    }

    if ($errors) {
        throw new RuntimeException("空きAllocationがあるNodeを選択できませんでした。\n" . implode("\n", $errors));
    }

    throw new RuntimeException("空きAllocationがあるNodeを選択できませんでした。");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (empty($_SESSION["provision_orders_token"]) || !hash_equals((string)$_SESSION["provision_orders_token"], $token)) {
    provision_flash("error", "不正な操作です。もう一度やり直してください。");
    provision_redirect();
}

$orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$startupCommand = trim((string)($_POST["startup_command"] ?? ""));
$dockerImage = trim((string)($_POST["docker_image"] ?? ""));
$environmentJson = trim((string)($_POST["environment_json"] ?? ""));

if (!$orderId) {
    provision_flash("error", "契約IDが不正です。");
    provision_redirect();
}

$markProvisionFailed = false;

try {
    provision_ensure_schema($pdo);
    hc_ptero_user_links_ensure_schema($pdo);

    $environment = json_decode($environmentJson, true);

    if (!is_array($environment)) {
        throw new RuntimeException("Environment JSONが不正です。");
    }

    if ($startupCommand === "") {
        throw new RuntimeException("Startup Commandを入力してください。");
    }

    if ($dockerImage === "") {
        throw new RuntimeException("Docker Imageを入力してください。");
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            gso.id,
            gso.user_id,
            gso.selected_node_id,
            gso.server_name,
            gso.status,
            gso.payment_status,
            gso.created_at,

            gsp.name AS plan_name,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,
            gsp.backup_limit,
            gsp.database_limit,
            gsp.allocation_limit,
            gsp.ptero_egg_id,

            u.username,
            u.email
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        LEFT JOIN users u ON u.id = gso.user_id
        WHERE gso.id = :id
        FOR UPDATE OF gso
    ");

    $stmt->execute([
        "id" => $orderId,
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        throw new RuntimeException("契約が見つかりません。");
    }

    $existsStmt = $pdo->prepare("
        SELECT id
        FROM ptero_servers
        WHERE order_id = :order_id
          AND deleted_at IS NULL
        LIMIT 1
    ");

    $existsStmt->execute([
        "order_id" => (int)$order["id"],
    ]);

    $existingパネルServer = $existsStmt->fetch();

    if ($existingパネルServer) {
        throw new RuntimeException("この契約はすでにゲームサーバーが登録されています。");
    }

    $oldStatus = (string)$order["status"];

    if ((string)$order["payment_status"] !== "paid") {
        throw new RuntimeException("支払い完了済みの契約だけ作成できます。");
    }

    if ($oldStatus !== "creating") {
        throw new RuntimeException("契約状態がcreatingの契約だけ作成できます。現在の状態: " . $oldStatus);
    }

    $eggId = (int)($order["ptero_egg_id"] ?? 0);

    if ($eggId <= 0) {
        throw new RuntimeException("プランにゲームサーバーパネル Egg IDが設定されていません。");
    }

    $nodeSelection = provision_choose_node_and_allocation(
        $pdo,
        !empty($order["selected_node_id"]) ? (int)$order["selected_node_id"] : null
    );

    $selectedNode = $nodeSelection["node"];
    $allocation = $nodeSelection["allocation"];

    $localNodeId = $selectedNode["id"] !== null ? (int)$selectedNode["id"] : null;
    $pteroNodeId = (int)$selectedNode["ptero_node_id"];
    $allocationId = (int)$allocation["id"];

    if ($allocationId <= 0) {
        throw new RuntimeException("空きAllocation IDを取得できませんでした。");
    }

    $pteroUser = hc_ptero_ensure_user_for_hc_user($pdo, (int)$order["user_id"]);
    $pteroUserId = (int)$pteroUser["ptero_user_id"];

    if ($pteroUserId <= 0) {
        throw new RuntimeException("ゲームサーバーパネルユーザーIDを取得できませんでした。");
    }

    $serverName = trim((string)($order["server_name"] ?: ""));

    if ($serverName === "") {
        $serverName = "HC Server #" . (string)$order["id"];
    }

    $payload = [
        "name" => $serverName,
        "user" => $pteroUserId,
        "egg" => $eggId,
        "docker_image" => $dockerImage,
        "startup" => $startupCommand,
        "environment" => $environment,
        "limits" => [
            "memory" => (int)$order["memory_mb"],
            "swap" => 0,
            "disk" => (int)$order["disk_mb"],
            "io" => 500,
            "cpu" => (int)$order["cpu_limit"],
        ],
        "feature_limits" => [
            "databases" => (int)($order["database_limit"] ?? 0),
            "backups" => (int)($order["backup_limit"] ?? 0),
            "allocations" => (int)($order["allocation_limit"] ?? 1),
        ],
        "allocation" => [
            "default" => $allocationId,
        ],
        "start_on_completion" => true,
        "external_id" => "hc_order_" . (string)$order["id"],
    ];

    $markProvisionFailed = true;

    $response = hc_ptero_create_server($payload);
    $attributes = $response["attributes"] ?? [];

    $pteroServerId = isset($attributes["id"]) ? (int)$attributes["id"] : null;
    $pteroUuid = isset($attributes["uuid"]) ? (string)$attributes["uuid"] : null;
    $pteroIdentifier = isset($attributes["identifier"]) ? (string)$attributes["identifier"] : null;
    $pteroName = isset($attributes["name"]) ? (string)$attributes["name"] : $serverName;

    if (!$pteroServerId && !$pteroIdentifier) {
        throw new RuntimeException("ゲームサーバー作成レスポンスからIDを取得できませんでした。");
    }

    $insert = $pdo->prepare("
        INSERT INTO ptero_servers
        (
            order_id,
            node_id,
            ptero_user_id,
            ptero_server_id,
            ptero_identifier,
            ptero_uuid,
            ptero_allocation_id,
            name,
            status,
            created_at,
            updated_at
        )
        VALUES
        (
            :order_id,
            :node_id,
            :ptero_user_id,
            :ptero_server_id,
            :ptero_identifier,
            :ptero_uuid,
            :ptero_allocation_id,
            :name,
            'active',
            NOW(),
            NOW()
        )
    ");

    $insert->execute([
        "order_id" => (int)$order["id"],
        "node_id" => $localNodeId,
        "ptero_user_id" => $pteroUserId,
        "ptero_server_id" => $pteroServerId,
        "ptero_identifier" => $pteroIdentifier,
        "ptero_uuid" => $pteroUuid,
        "ptero_allocation_id" => $allocationId,
        "name" => $pteroName,
    ]);

    $newStatus = "active";

    $update = $pdo->prepare("
        UPDATE game_server_orders
        SET
            status = :status,
            selected_node_id = COALESCE(selected_node_id, :selected_node_id),
            provisioned_at = NOW(),
            provisioning_started_at = COALESCE(provisioning_started_at, NOW()),
            provision_error = NULL,
            updated_at = NOW()
        WHERE id = :id
    ");

    $update->execute([
        "id" => (int)$order["id"],
        "status" => $newStatus,
        "selected_node_id" => $localNodeId,
    ]);

    $allocationLabel = trim((string)($allocation["alias"] ?: $allocation["ip"]) . ":" . (string)$allocation["port"]);
    $nodeLabel = (string)$selectedNode["name"] . " / パネルNode #" . (string)$pteroNodeId;

    provision_insert_event(
        $pdo,
        (int)$order["id"],
        (int)$adminUser["id"],
        "server_provisioned",
        "ゲームサーバーを作成しました",
        "パネルユーザーID: " . $pteroUserId
        . "\nパネルユーザー Source: " . (string)($pteroUser["source"] ?? "-")
        . "\nSelected Node: " . $nodeLabel
        . "\nAllocation ID: " . $allocationId
        . "\nAllocation: " . $allocationLabel
        . "\nパネルサーバーID: " . ($pteroServerId ?: "-")
        . "\nIdentifier: " . ($pteroIdentifier ?: "-")
        . "\nUUID: " . ($pteroUuid ?: "-"),
        $oldStatus,
        $newStatus
    );

    try {
        hc_notify_user(
            $pdo,
            (int)$order["user_id"],
            "サーバー作成が完了しました",
            "契約 #" . (string)$order["id"] . " のサーバー作成が完了しました。サーバーパネルとゲームサーバーパネルログイン情報を確認してください。",
            "/dashboard/servers/detail/?id=" . rawurlencode((string)$order["id"]),
            "success"
        );
    } catch (Throwable $notifyError) {
    }

    $pdo->commit();

    provision_flash(
        "success",
        "契約 #" . $orderId . " のゲームサーバーを作成しました。Node: " . $nodeLabel . " / Allocation ID: " . $allocationId
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($markProvisionFailed) {
        try {
            $stmt = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    status = 'provision_failed',
                    failed_at = NOW(),
                    provision_error = :error,
                    updated_at = NOW()
                WHERE id = :id
                  AND status = 'creating'
            ");

            $stmt->execute([
                "id" => $orderId,
                "error" => mb_substr($e->getMessage(), 0, 2000),
            ]);

            provision_insert_event(
                $pdo,
                $orderId,
                (int)$adminUser["id"],
                "server_provision_failed",
                "ゲームサーバー作成に失敗しました",
                $e->getMessage(),
                "creating",
                "provision_failed"
            );
        } catch (Throwable $inner) {
        }
    }

    provision_flash("error", "ゲームサーバーパネル作成に失敗しました: " . $e->getMessage());
}

provision_redirect();
