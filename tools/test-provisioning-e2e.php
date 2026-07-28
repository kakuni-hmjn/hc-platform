<?php

require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/provisioning_jobs.php";
require_once __DIR__ . "/../lib/game_server_provisioning.php";
require_once __DIR__ . "/../lib/game_server_approval.php";

$pdo = db();

function fail(string $message): never
{
    fwrite(STDERR, "[NG] {$message}\n");
    exit(1);
}

function ok(string $message): void
{
    echo "[OK] {$message}\n";
}

try {
    $user = $pdo->query("
        SELECT id, username, email
        FROM users
        ORDER BY
            CASE role
                WHEN 'owner' THEN 1
                WHEN 'admin' THEN 2
                ELSE 3
            END,
            id
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        fail("テスト用ユーザーがありません。");
    }

    $plan = $pdo->query("
        SELECT *
        FROM game_server_plans
        ORDER BY
            CASE WHEN status = 'active' THEN 1 ELSE 2 END,
            sort_order,
            id
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        fail("テスト用プランがありません。");
    }

    $admin = $pdo->query("
        SELECT id, username
        FROM users
        WHERE role IN ('owner', 'admin')
        ORDER BY
            CASE role WHEN 'owner' THEN 1 ELSE 2 END,
            id
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        fail("承認に使用できる管理者がありません。");
    }

    $serverName = "E2E Mock Server " . date("md-His");

    $pdo->beginTransaction();

    $orderStmt = $pdo->prepare("
        INSERT INTO game_server_orders (
            user_id,
            plan_id,
            server_name,
            minecraft_type,
            server_software,
            minecraft_version,
            player_count_estimate,
            note,
            billing_type,
            billing_period,
            status,
            payment_status,
            amount,
            currency,
            paid_at,
            created_at,
            updated_at
        )
        VALUES (
            :user_id,
            :plan_id,
            :server_name,
            'java',
            'paper',
            '1.21.5',
            10,
            'E2Eテストで自動作成された注文です。',
            'auto_subscription',
            'monthly',
            'paid',
            'paid',
            :amount,
            'jpy',
            NOW(),
            NOW(),
            NOW()
        )
        RETURNING id
    ");

    $orderStmt->execute([
        "user_id" => (int)$user["id"],
        "plan_id" => (int)$plan["id"],
        "server_name" => $serverName,
        "amount" => (int)$plan["price_monthly"],
    ]);

    $orderId = (int)$orderStmt->fetchColumn();

    $enqueue = hc_enqueue_provisioning_job($pdo, $orderId);

    if (empty($enqueue["ok"])) {
        throw new RuntimeException(
            $enqueue["error"] ?? "ジョブ登録に失敗しました。"
        );
    }

    $pdo->commit();

    ok("注文を作成しました。Order #{$orderId}");
    ok("Provisioningジョブを登録しました。");

    $job = hc_claim_provisioning_job(
        $pdo,
        "e2e-test-" . getmypid()
    );

    if (!$job || (int)$job["order_id"] !== $orderId) {
        fail("作成したジョブを取得できませんでした。");
    }

    ok("Workerがジョブを取得しました。");

    $provision = provision_game_server_order($pdo, $orderId);

    if (empty($provision["ok"])) {
        hc_fail_provisioning_job(
            $pdo,
            (int)$job["id"],
            (string)($provision["error"] ?? "不明なエラー")
        );

        fail(
            "Provisioning失敗: "
            . (string)($provision["error"] ?? "不明なエラー")
        );
    }

    hc_complete_provisioning_job(
        $pdo,
        (int)$job["id"]
    );

    ok("Provisioningが完了しました。");

    $check = $pdo->prepare("
        SELECT
            gso.status AS order_status,
            ps.status AS server_status,
            ps.ptero_server_id
        FROM game_server_orders gso
        LEFT JOIN ptero_servers ps
            ON ps.order_id = gso.id
        WHERE gso.id = :order_id
    ");

    $check->execute([
        "order_id" => $orderId,
    ]);

    $state = $check->fetch(PDO::FETCH_ASSOC);

    if (
        !$state
        || $state["order_status"] !== "pending_approval"
        || $state["server_status"] !== "pending_approval"
    ) {
        fail("承認待ち状態への遷移に失敗しました。");
    }

    ok("注文とサーバーが承認待ちになりました。");

    $approval = hc_approve_game_server_order(
        $pdo,
        $orderId,
        (int)$admin["id"],
        "web"
    );

    if (empty($approval["ok"])) {
        fail(
            "承認失敗: "
            . (string)($approval["error"] ?? "不明なエラー")
        );
    }

    ok("管理者承認が完了しました。");

    $finalStmt = $pdo->prepare("
        SELECT
            gso.status AS order_status,
            ps.status AS server_status,
            gso.approved_at,
            gso.approved_by,
            gso.approved_via
        FROM game_server_orders gso
        LEFT JOIN ptero_servers ps
            ON ps.order_id = gso.id
        WHERE gso.id = :order_id
    ");

    $finalStmt->execute([
        "order_id" => $orderId,
    ]);

    $final = $finalStmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$final
        || $final["order_status"] !== "active"
        || $final["server_status"] !== "active"
    ) {
        fail("承認後のactive遷移に失敗しました。");
    }

    ok("注文とサーバーがactiveになりました。");

    $notificationStmt = $pdo->prepare("
        SELECT id, title, dedupe_key
        FROM user_direct_notifications
        WHERE user_id = :user_id
          AND dedupe_key = :dedupe_key
        LIMIT 1
    ");

    $notificationStmt->execute([
        "user_id" => (int)$user["id"],
        "dedupe_key" => "server_activated:order:" . $orderId,
    ]);

    $notification = $notificationStmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        fail("利用開始通知が作成されていません。");
    }

    ok("利用開始通知が作成されました。");

    echo "\n=== E2E TEST SUCCESS ===\n";
    echo "Order ID: {$orderId}\n";
    echo "Server Name: {$serverName}\n";
    echo "User: {$user["username"]}\n";
    echo "Admin: {$admin["username"]}\n";
    echo "Status: active\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail($e->getMessage());
}
