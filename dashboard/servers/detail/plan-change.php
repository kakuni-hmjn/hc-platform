<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";

$currentUser = require_login();
$pdo = db();

function server_detail_flash(string $type, string $message): void
{
    $_SESSION["server_detail_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function redirect_server_detail(int $orderId): void
{
    header("Location: /dashboard/servers/detail/?id=" . $orderId);
    exit;
}

function add_user_order_event_safe(PDO $pdo, int $orderId, int $userId, string $title, string $message): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO server_order_events
            (
                order_id,
                actor_user_id,
                event_type,
                title,
                message,
                created_at
            )
            VALUES
            (
                :order_id,
                :actor_user_id,
                'user_plan_change_request',
                :title,
                :message,
                NOW()
            )
        ");

        $stmt->execute([
            "order_id" => $orderId,
            "actor_user_id" => $userId,
            "title" => $title,
            "message" => $message,
        ]);
    } catch (Throwable $e) {
        // 操作履歴テーブルが未作成でも、ユーザー操作自体は継続する
    }
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

$requestedPlanId = filter_input(INPUT_POST, "requested_plan_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$changeType = trim((string)($_POST["change_type"] ?? "next_renewal"));
$userNote = trim((string)($_POST["user_note"] ?? ""));
$csrfToken = (string)($_POST["csrf_token"] ?? "");
$immediateChargeAgreed = isset($_POST["immediate_charge_agreed"]) && (string)$_POST["immediate_charge_agreed"] === "1";

if (!$orderId) {
    server_detail_flash("error", "契約IDが不正です。");
    header("Location: /dashboard/servers/");
    exit;
}

if (
    empty($_SESSION["server_detail_action_token"]) ||
    !hash_equals((string)$_SESSION["server_detail_action_token"], $csrfToken)
) {
    server_detail_flash("error", "不正な操作です。もう一度やり直してください。");
    redirect_server_detail($orderId);
}

if (!$requestedPlanId) {
    server_detail_flash("error", "変更先プランを選択してください。");
    redirect_server_detail($orderId);
}

if (!in_array($changeType, ["next_renewal", "immediate"], true)) {
    server_detail_flash("error", "変更タイミングが不正です。");
    redirect_server_detail($orderId);
}

if ($changeType === "immediate" && !$immediateChargeAgreed) {
    server_detail_flash("error", "今すぐ変更する場合、変更先プランの1ヶ月分の料金が発生することへの同意が必要です。");
    redirect_server_detail($orderId);
}

if (mb_strlen($userNote) > 2000) {
    server_detail_flash("error", "メモは2000文字以内で入力してください。");
    redirect_server_detail($orderId);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            gso.id,
            gso.user_id,
            gso.plan_id,
            gso.status,
            gso.payment_status,
            current_plan.name AS current_plan_name
        FROM game_server_orders gso
        JOIN game_server_plans current_plan ON current_plan.id = gso.plan_id
        WHERE gso.id = :id
          AND gso.user_id = :user_id
        FOR UPDATE
    ");
    $stmt->execute([
        "id" => $orderId,
        "user_id" => (int)$currentUser["id"],
    ]);

    $order = $stmt->fetch();

    if (!$order) {
        throw new RuntimeException("契約が見つかりません。");
    }

    if (in_array((string)$order["status"], ["cancelled", "expired"], true)) {
        throw new RuntimeException("キャンセル済み、または期限切れの契約はプラン変更できません。");
    }

    if ((int)$order["plan_id"] === (int)$requestedPlanId) {
        throw new RuntimeException("現在のプランと同じプランは選択できません。");
    }

    $planStmt = $pdo->prepare("
        SELECT id, name, price_monthly, memory_mb, cpu_limit
        FROM game_server_plans
        WHERE id = :id
          AND status = 'published'
        LIMIT 1
    ");
    $planStmt->execute([
        "id" => $requestedPlanId,
    ]);

    $requestedPlan = $planStmt->fetch();

    if (!$requestedPlan) {
        throw new RuntimeException("変更先プランが見つかりません。");
    }

    $existingStmt = $pdo->prepare("
        SELECT id
        FROM server_order_plan_change_requests
        WHERE order_id = :order_id
          AND user_id = :user_id
          AND status = 'pending'
        ORDER BY id DESC
        LIMIT 1
    ");
    $existingStmt->execute([
        "order_id" => $orderId,
        "user_id" => (int)$currentUser["id"],
    ]);

    $existing = $existingStmt->fetch();

    $chargeNote = "";
    if ($changeType === "immediate") {
        $chargeNote = "今すぐ変更のため、変更先プラン「" . (string)$requestedPlan["name"] . "」の1ヶ月分の料金が発生することに同意済み。";
    }

    $finalUserNote = $userNote;
    if ($chargeNote !== "") {
        $finalUserNote = trim($chargeNote . "\n" . $userNote);
    }

    if ($existing) {
        $update = $pdo->prepare("
            UPDATE server_order_plan_change_requests
            SET
                current_plan_id = :current_plan_id,
                requested_plan_id = :requested_plan_id,
                change_type = :change_type,
                user_note = :user_note,
                updated_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            "current_plan_id" => (int)$order["plan_id"],
            "requested_plan_id" => (int)$requestedPlan["id"],
            "change_type" => $changeType,
            "user_note" => $finalUserNote !== "" ? $finalUserNote : null,
            "id" => (int)$existing["id"],
        ]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO server_order_plan_change_requests
            (
                order_id,
                user_id,
                current_plan_id,
                requested_plan_id,
                change_type,
                status,
                user_note,
                created_at
            )
            VALUES
            (
                :order_id,
                :user_id,
                :current_plan_id,
                :requested_plan_id,
                :change_type,
                'pending',
                :user_note,
                NOW()
            )
        ");

        $insert->execute([
            "order_id" => $orderId,
            "user_id" => (int)$currentUser["id"],
            "current_plan_id" => (int)$order["plan_id"],
            "requested_plan_id" => (int)$requestedPlan["id"],
            "change_type" => $changeType,
            "user_note" => $finalUserNote !== "" ? $finalUserNote : null,
        ]);
    }

    $typeLabel = $changeType === "immediate"
        ? "今すぐ変更"
        : "次回更新時に変更";

    add_user_order_event_safe(
        $pdo,
        $orderId,
        (int)$currentUser["id"],
        "プラン変更申請",
        (string)$order["current_plan_name"] . " から " . (string)$requestedPlan["name"] . " への変更申請を作成しました。変更タイミング: " . $typeLabel
    );

    $pdo->commit();

    if ($changeType === "immediate") {
        server_detail_flash("success", "プラン変更申請を送信しました。今すぐ変更の場合、変更先プランの1ヶ月分の料金が発生します。管理者の確認後に反映されます。");
    } else {
        server_detail_flash("success", "プラン変更申請を送信しました。次回更新時の変更として管理者が確認します。");
    }

    redirect_server_detail($orderId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    server_detail_flash("error", $e->getMessage());
    redirect_server_detail($orderId);
}
