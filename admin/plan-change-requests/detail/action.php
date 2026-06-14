<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("admin");
$pdo = db();

function pc_admin_flash(string $type, string $message): void
{
    $_SESSION["plan_change_admin_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function pc_redirect_detail(int $requestId): void
{
    header("Location: /admin/plan-change-requests/detail/?id=" . $requestId);
    exit;
}

function pc_add_event_safe(
    PDO $pdo,
    int $orderId,
    int $actorUserId,
    string $eventType,
    string $title,
    string $message,
    ?string $oldStatus = null,
    ?string $newStatus = null,
    ?string $oldPaymentStatus = null,
    ?string $newPaymentStatus = null
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
            "ip_address" => client_ip() ?? null,
        ]);
    } catch (Throwable $e) {
        // 履歴テーブルが未作成でも処理は継続
    }
}

function pc_append_admin_note(?string $currentNote, string $newNote, string $adminName): ?string
{
    $newNote = trim($newNote);

    if ($newNote === "") {
        return $currentNote;
    }

    $line = "[" . date("Y/m/d H:i") . " / " . $adminName . "]\n" . $newNote;

    $currentNote = trim((string)($currentNote ?? ""));

    if ($currentNote === "") {
        return $line;
    }

    return $currentNote . "\n\n" . $line;
}

function pc_apply_plan_change(PDO $pdo, array $request): void
{
    $orderUpdate = $pdo->prepare("
        UPDATE game_server_orders
        SET
            plan_id = :requested_plan_id,
            amount = :requested_price_monthly,
            updated_at = NOW()
        WHERE id = :order_id
    ");

    $orderUpdate->execute([
        "requested_plan_id" => (int)$request["requested_plan_id"],
        "requested_price_monthly" => (int)$request["requested_price_monthly"],
        "order_id" => (int)$request["order_id"],
    ]);

    $serverUpdate = $pdo->prepare("
        UPDATE ptero_servers
        SET
            plan_id = :requested_plan_id,
            updated_at = NOW()
        WHERE order_id = :order_id
    ");

    $serverUpdate->execute([
        "requested_plan_id" => (int)$request["requested_plan_id"],
        "order_id" => (int)$request["order_id"],
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$requestId = filter_input(INPUT_POST, "request_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$action = trim((string)($_POST["action"] ?? ""));
$csrfToken = (string)($_POST["csrf_token"] ?? "");
$adminNote = trim((string)($_POST["admin_note"] ?? ""));

if (!$requestId) {
    pc_admin_flash("error", "申請IDが不正です。");
    header("Location: /admin/plan-change-requests/");
    exit;
}

if (
    empty($_SESSION["plan_change_admin_token"]) ||
    !hash_equals((string)$_SESSION["plan_change_admin_token"], $csrfToken)
) {
    pc_admin_flash("error", "不正な操作です。もう一度やり直してください。");
    pc_redirect_detail($requestId);
}

if (!in_array($action, ["process", "reject", "add_note", "apply_approved"], true)) {
    pc_admin_flash("error", "不明な操作です。");
    pc_redirect_detail($requestId);
}

if (mb_strlen($adminNote) > 3000) {
    pc_admin_flash("error", "管理者メモは3000文字以内で入力してください。");
    pc_redirect_detail($requestId);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            r.*,

            gso.id AS order_id_ref,
            gso.status AS order_status,
            gso.payment_status AS order_payment_status,
            gso.server_name,

            current_plan.name AS current_plan_name,
            current_plan.price_monthly AS current_price_monthly,

            requested_plan.name AS requested_plan_name,
            requested_plan.price_monthly AS requested_price_monthly,
            requested_plan.memory_mb AS requested_memory_mb,
            requested_plan.cpu_limit AS requested_cpu_limit,
            requested_plan.disk_mb AS requested_disk_mb
        FROM server_order_plan_change_requests r
        JOIN game_server_orders gso ON gso.id = r.order_id
        JOIN game_server_plans current_plan ON current_plan.id = r.current_plan_id
        JOIN game_server_plans requested_plan ON requested_plan.id = r.requested_plan_id
        WHERE r.id = :id
        FOR UPDATE
    ");

    $stmt->execute([
        "id" => $requestId,
    ]);

    $request = $stmt->fetch();

    if (!$request) {
        throw new RuntimeException("指定された申請が見つかりません。");
    }

    $orderId = (int)$request["order_id"];
    $adminName = (string)($user["username"] ?? "admin");

    if ($action === "add_note") {
        if ($adminNote === "") {
            throw new RuntimeException("管理者メモを入力してください。");
        }

        $newAdminNote = pc_append_admin_note($request["admin_note"] ?? null, $adminNote, $adminName);

        $update = $pdo->prepare("
            UPDATE server_order_plan_change_requests
            SET
                admin_note = :admin_note,
                updated_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            "admin_note" => $newAdminNote,
            "id" => $requestId,
        ]);

        pc_add_event_safe(
            $pdo,
            $orderId,
            (int)$user["id"],
            "admin_plan_change_note",
            "プラン変更申請メモ追加",
            $adminNote,
            (string)$request["order_status"],
            (string)$request["order_status"],
            (string)$request["order_payment_status"],
            (string)$request["order_payment_status"]
        );

        $pdo->commit();

        pc_admin_flash("success", "管理者メモを追加しました。");
        pc_redirect_detail($requestId);
    }

    if ($action === "reject") {
        if ((string)$request["status"] !== "pending" && (string)$request["status"] !== "approved") {
            throw new RuntimeException("この申請は却下できない状態です。");
        }

        $message = $adminNote !== "" ? $adminNote : "管理者によりプラン変更申請を却下しました。";
        $newAdminNote = pc_append_admin_note($request["admin_note"] ?? null, $message, $adminName);

        $update = $pdo->prepare("
            UPDATE server_order_plan_change_requests
            SET
                status = 'rejected',
                admin_note = :admin_note,
                rejected_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            "admin_note" => $newAdminNote,
            "id" => $requestId,
        ]);

        pc_add_event_safe(
            $pdo,
            $orderId,
            (int)$user["id"],
            "admin_plan_change_rejected",
            "プラン変更申請を却下",
            $message,
            (string)$request["order_status"],
            (string)$request["order_status"],
            (string)$request["order_payment_status"],
            (string)$request["order_payment_status"]
        );

        $pdo->commit();

        pc_admin_flash("success", "プラン変更申請を却下しました。");
        pc_redirect_detail($requestId);
    }

    if ($action === "process") {
        if ((string)$request["status"] !== "pending") {
            throw new RuntimeException("この申請は申請中ではありません。");
        }

        $changeType = (string)$request["change_type"];

        if ($changeType === "next_renewal") {
            $message = $adminNote !== ""
                ? $adminNote
                : "次回更新時のプラン変更として承認しました。現時点では契約プランは変更していません。";

            $newAdminNote = pc_append_admin_note($request["admin_note"] ?? null, $message, $adminName);

            $update = $pdo->prepare("
                UPDATE server_order_plan_change_requests
                SET
                    status = 'approved',
                    admin_note = :admin_note,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                "admin_note" => $newAdminNote,
                "id" => $requestId,
            ]);

            pc_add_event_safe(
                $pdo,
                $orderId,
                (int)$user["id"],
                "admin_plan_change_approved",
                "次回更新時のプラン変更を承認",
                (string)$request["current_plan_name"] . " から " . (string)$request["requested_plan_name"] . " への次回更新時変更を承認しました。\n\n" . $message,
                (string)$request["order_status"],
                (string)$request["order_status"],
                (string)$request["order_payment_status"],
                (string)$request["order_payment_status"]
            );

            $pdo->commit();

            pc_admin_flash("success", "次回更新時のプラン変更として承認しました。契約プランはまだ変更していません。");
            pc_redirect_detail($requestId);
        }

        if ($changeType === "immediate") {
            $message = $adminNote !== ""
                ? $adminNote
                : "今すぐ変更として、管理者によりプラン変更を契約へ反映しました。";

            $message .= "\n\n今すぐ変更のため、変更先プラン1ヶ月分 " . number_format((int)$request["requested_price_monthly"]) . "円 の請求確認が必要です。";

            pc_apply_plan_change($pdo, $request);

            $newAdminNote = pc_append_admin_note($request["admin_note"] ?? null, $message, $adminName);

            $requestUpdate = $pdo->prepare("
                UPDATE server_order_plan_change_requests
                SET
                    status = 'processed',
                    admin_note = :admin_note,
                    approved_at = COALESCE(approved_at, NOW()),
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");

            $requestUpdate->execute([
                "admin_note" => $newAdminNote,
                "id" => $requestId,
            ]);

            pc_add_event_safe(
                $pdo,
                $orderId,
                (int)$user["id"],
                "admin_plan_change_processed",
                "今すぐプラン変更を反映",
                (string)$request["current_plan_name"] . " から " . (string)$request["requested_plan_name"] . " へ変更しました。\n\n" . $message,
                (string)$request["order_status"],
                (string)$request["order_status"],
                (string)$request["order_payment_status"],
                (string)$request["order_payment_status"]
            );

            $pdo->commit();

            pc_admin_flash("success", "今すぐプラン変更を契約へ反映しました。");
            pc_redirect_detail($requestId);
        }

        throw new RuntimeException("変更タイミングが不正です。");
    }

    if ($action === "apply_approved") {
        if ((string)$request["status"] !== "approved") {
            throw new RuntimeException("承認済みの申請のみ反映できます。");
        }

        $message = $adminNote !== ""
            ? $adminNote
            : "承認済みの次回更新プラン変更を契約へ反映しました。";

        pc_apply_plan_change($pdo, $request);

        $newAdminNote = pc_append_admin_note($request["admin_note"] ?? null, $message, $adminName);

        $requestUpdate = $pdo->prepare("
            UPDATE server_order_plan_change_requests
            SET
                status = 'processed',
                admin_note = :admin_note,
                processed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $requestUpdate->execute([
            "admin_note" => $newAdminNote,
            "id" => $requestId,
        ]);

        pc_add_event_safe(
            $pdo,
            $orderId,
            (int)$user["id"],
            "admin_plan_change_applied",
            "承認済みプラン変更を反映",
            (string)$request["current_plan_name"] . " から " . (string)$request["requested_plan_name"] . " へ変更しました。\n\n" . $message,
            (string)$request["order_status"],
            (string)$request["order_status"],
            (string)$request["order_payment_status"],
            (string)$request["order_payment_status"]
        );

        $pdo->commit();

        pc_admin_flash("success", "承認済みのプラン変更を契約へ反映しました。");
        pc_redirect_detail($requestId);
    }

    throw new RuntimeException("処理できませんでした。");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    pc_admin_flash("error", $e->getMessage());
    pc_redirect_detail($requestId);
}
