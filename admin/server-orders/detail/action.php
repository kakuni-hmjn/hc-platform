<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("admin");
$pdo = db();

function redirect_detail(int $orderId): void
{
    header("Location: /admin/server-orders/detail/?id=" . $orderId);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION["server_order_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function add_order_event(
    PDO $pdo,
    int $orderId,
    int $actorUserId,
    string $eventType,
    string $title,
    ?string $message,
    ?string $oldStatus,
    ?string $newStatus,
    ?string $oldPaymentStatus,
    ?string $newPaymentStatus
): void {
    $ipAddress = client_ip() ?? null;

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
            ip_address,
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
            :old_payment_status,
            :new_payment_status,
            :ip_address,
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
        "old_payment_status" => $oldPaymentStatus,
        "new_payment_status" => $newPaymentStatus,
        "ip_address" => $ipAddress,
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$action = trim((string)($_POST["action"] ?? ""));
$note = trim((string)($_POST["note"] ?? ""));
$csrfToken = (string)($_POST["csrf_token"] ?? "");

if (!$orderId) {
    set_flash("error", "契約IDが不正です。");
    header("Location: /admin/server-orders/");
    exit;
}

if (
    empty($_SESSION["server_order_action_token"]) ||
    !hash_equals((string)$_SESSION["server_order_action_token"], $csrfToken)
) {
    set_flash("error", "不正な操作です。もう一度やり直してください。");
    redirect_detail($orderId);
}

$allowedActions = [
    "mark_paid",
    "start_creation",
    "mock_create",
    "mark_failed",
    "suspend",
    "resume",
    "request_cancel",
    "cancel",
    "add_note",
];

if (!in_array($action, $allowedActions, true)) {
    set_flash("error", "不明な操作です。");
    redirect_detail($orderId);
}

if (mb_strlen($note) > 3000) {
    set_flash("error", "メモは3000文字以内で入力してください。");
    redirect_detail($orderId);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM game_server_orders
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute([
        "id" => $orderId,
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        throw new RuntimeException("指定された契約が見つかりません。");
    }

    $oldStatus = (string)$order["status"];
    $oldPaymentStatus = (string)$order["payment_status"];

    $newStatus = $oldStatus;
    $newPaymentStatus = $oldPaymentStatus;
    $eventType = $action;
    $eventTitle = "";
    $eventMessage = $note !== "" ? $note : null;

    switch ($action) {
        case "mark_paid":
            $newPaymentStatus = "paid";
            $newStatus = $oldStatus === "pending_payment" ? "paid" : $oldStatus;

            $update = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    payment_status = 'paid',
                    status = :status,
                    paid_at = COALESCE(paid_at, NOW()),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                "status" => $newStatus,
                "id" => $orderId,
            ]);

            $eventTitle = "決済済みに変更";
            set_flash("success", "契約を決済済みに変更しました。");
            break;

        case "start_creation":
            if ($oldPaymentStatus !== "paid") {
                throw new RuntimeException("決済済みではないため、作成開始にできません。");
            }

            $newStatus = "creating";

            $update = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    status = 'creating',
                    provisioning_started_at = COALESCE(provisioning_started_at, NOW()),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                "id" => $orderId,
            ]);

            $eventTitle = "サーバー作成開始";
            set_flash("success", "サーバー作成中に変更しました。");
            break;

        case "mock_create":
            $nodeId = $order["selected_node_id"] !== null ? (int)$order["selected_node_id"] : null;

            if (!$nodeId) {
                $nodeStmt = $pdo->query("
                    SELECT id
                    FROM ptero_nodes
                    WHERE status = 'active'
                    ORDER BY sort_order ASC, id ASC
                    LIMIT 1
                ");
                $node = $nodeStmt->fetch();
                $nodeId = $node ? (int)$node["id"] : null;
            }

            $mockパネルServerId = 100000 + $orderId;
            $mockIdentifier = "mock" . str_pad((string)$orderId, 4, "0", STR_PAD_LEFT);
            $mockUuid = "mock-order-" . $orderId . "-" . bin2hex(random_bytes(6));

            $serverStmt = $pdo->prepare("
                INSERT INTO ptero_servers
                (
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
                    created_at,
                    updated_at
                )
                VALUES
                (
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
                    NOW(),
                    NOW()
                )
                ON CONFLICT (order_id) DO UPDATE SET
                    node_id = EXCLUDED.node_id,
                    ptero_user_id = EXCLUDED.ptero_user_id,
                    ptero_server_id = EXCLUDED.ptero_server_id,
                    ptero_identifier = EXCLUDED.ptero_identifier,
                    ptero_uuid = EXCLUDED.ptero_uuid,
                    name = EXCLUDED.name,
                    status = 'active',
                    deleted_at = NULL,
                    updated_at = NOW()
            ");

            $serverStmt->execute([
                "user_id" => (int)$order["user_id"],
                "order_id" => $orderId,
                "plan_id" => (int)$order["plan_id"],
                "node_id" => $nodeId,
                "ptero_user_id" => 1,
                "ptero_server_id" => $mockパネルServerId,
                "ptero_identifier" => $mockIdentifier,
                "ptero_uuid" => $mockUuid,
                "name" => (string)$order["server_name"],
            ]);

            $newStatus = "active";
            $newPaymentStatus = "paid";

            $update = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    selected_node_id = COALESCE(selected_node_id, :node_id),
                    payment_status = 'paid',
                    status = 'active',
                    paid_at = COALESCE(paid_at, NOW()),
                    provisioning_started_at = COALESCE(provisioning_started_at, NOW()),
                    provisioned_at = NOW(),
                    failed_at = NULL,
                    provision_error = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                "node_id" => $nodeId,
                "id" => $orderId,
            ]);

            $eventTitle = "Mockサーバー作成完了";
            $eventMessage = $eventMessage ?: "開発環境用のMock ゲームサーバーを作成しました。";
            set_flash("success", "Mockサーバーを作成して稼働中にしました。");
            break;

        case "mark_failed":
            $newStatus = "provision_failed";
            $errorMessage = $note !== "" ? $note : "管理者操作により作成失敗に変更されました。";

            $update = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    status = 'provision_failed',
                    failed_at = NOW(),
                    provision_error = :provision_error,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                "provision_error" => $errorMessage,
                "id" => $orderId,
            ]);

            $eventTitle = "作成失敗に変更";
            $eventMessage = $errorMessage;
            set_flash("success", "作成失敗に変更しました。");
            break;

        case "suspend":
            $newStatus = "suspended";

            $pdo->prepare("
                UPDATE game_server_orders
                SET status = 'suspended', updated_at = NOW()
                WHERE id = :id
            ")->execute([
                "id" => $orderId,
            ]);

            $pdo->prepare("
                UPDATE ptero_servers
                SET status = 'suspended', updated_at = NOW()
                WHERE order_id = :order_id
            ")->execute([
                "order_id" => $orderId,
            ]);

            $eventTitle = "サーバー停止";
            set_flash("success", "契約を停止中に変更しました。");
            break;

        case "resume":
            $newStatus = "active";

            $pdo->prepare("
                UPDATE game_server_orders
                SET status = 'active', updated_at = NOW()
                WHERE id = :id
            ")->execute([
                "id" => $orderId,
            ]);

            $pdo->prepare("
                UPDATE ptero_servers
                SET status = 'active', deleted_at = NULL, updated_at = NOW()
                WHERE order_id = :order_id
            ")->execute([
                "order_id" => $orderId,
            ]);

            $eventTitle = "サーバー再開";
            set_flash("success", "契約を稼働中に戻しました。");
            break;

        case "request_cancel":
            $update = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    auto_renew_cancelled = true,
                    cancel_requested_at = COALESCE(cancel_requested_at, NOW()),
                    cancel_effective_at = COALESCE(cancel_effective_at, next_payment_due_at, expires_at, NOW()),
                    cancel_reason = COALESCE(NULLIF(:cancel_reason, ''), cancel_reason),
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                "cancel_reason" => $note,
                "id" => $orderId,
            ]);

            $eventTitle = "解約予定を設定";
            set_flash("success", "解約予定を設定しました。");
            break;

        case "cancel":
            $newStatus = "cancelled";

            $newPaymentStatus = in_array($oldPaymentStatus, ["unpaid", "checkout_created", "failed"], true)
                ? "cancelled"
                : $oldPaymentStatus;

            $update = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    status = 'cancelled',
                    payment_status = :payment_status,
                    auto_renew_cancelled = true,
                    cancelled_at = COALESCE(cancelled_at, NOW()),
                    cancel_requested_at = COALESCE(cancel_requested_at, NOW()),
                    cancel_effective_at = COALESCE(cancel_effective_at, NOW()),
                    cancel_reason = COALESCE(NULLIF(:cancel_reason, ''), cancel_reason),
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                "payment_status" => $newPaymentStatus,
                "cancel_reason" => $note,
                "id" => $orderId,
            ]);

            $pdo->prepare("
                UPDATE ptero_servers
                SET status = 'cancelled', deleted_at = COALESCE(deleted_at, NOW()), updated_at = NOW()
                WHERE order_id = :order_id
            ")->execute([
                "order_id" => $orderId,
            ]);

            $eventTitle = "契約キャンセル";
            set_flash("success", "契約をキャンセルしました。");
            break;

        case "add_note":
            if ($note === "") {
                throw new RuntimeException("管理者メモを入力してください。");
            }

            $eventTitle = "管理者メモ";
            $eventMessage = $note;
            set_flash("success", "管理者メモを追加しました。");
            break;
    }

    add_order_event(
        $pdo,
        $orderId,
        (int)$user["id"],
        $eventType,
        $eventTitle,
        $eventMessage,
        $oldStatus,
        $newStatus,
        $oldPaymentStatus,
        $newPaymentStatus
    );

    $pdo->commit();
    redirect_detail($orderId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    set_flash("error", $e->getMessage());
    redirect_detail($orderId);
}
