<?php

session_start();

require_once __DIR__ . "/../../../../lib/helpers.php";
require_once __DIR__ . "/../../../../lib/auth.php";
require_once __DIR__ . "/../../../../lib/db.php";
require_once __DIR__ . "/../../../../lib/permissions.php";
require_once __DIR__ . "/../../../../lib/provisioning_jobs.php";

$adminUser = require_role("admin");
$pdo = db();

function provision_failed_retry_flash(
    string $type,
    string $message
): void {
    $_SESSION["provision_failed_flash"] = [
        "type" => $type,
        "message" => $message,
    ];
}

function provision_failed_retry_redirect(): void
{
    header("Location: /admin/server-orders/provision/failed/");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$token = (string)($_POST["csrf_token"] ?? "");

if (
    empty($_SESSION["provision_failed_token"])
    || !hash_equals(
        (string)$_SESSION["provision_failed_token"],
        $token
    )
) {
    provision_failed_retry_flash(
        "error",
        "不正な操作です。もう一度やり直してください。"
    );

    provision_failed_retry_redirect();
}

$orderId = filter_input(
    INPUT_POST,
    "order_id",
    FILTER_VALIDATE_INT,
    [
        "options" => [
            "min_range" => 1,
        ],
    ]
);

if (!$orderId) {
    provision_failed_retry_flash(
        "error",
        "注文IDが不正です。"
    );

    provision_failed_retry_redirect();
}

try {
    hc_provisioning_jobs_ensure_schema($pdo);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            server_name,
            status,
            payment_status
        FROM game_server_orders
        WHERE id = :order_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        "order_id" => (int)$orderId,
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new RuntimeException(
            "対象の注文が見つかりません。"
        );
    }

    if ((string)$order["status"] !== "provision_failed") {
        throw new RuntimeException(
            "この注文は自動作成失敗状態ではありません。現在の状態: "
            . (string)$order["status"]
        );
    }

    if ((string)$order["payment_status"] !== "paid") {
        throw new RuntimeException(
            "支払い済みでないため再試行できません。"
        );
    }

    /*
     * 作成途中で残ったローカルサーバー情報を削除する。
     * activeなレコードは安全のため削除しない。
     */
    $deleteServer = $pdo->prepare("
        DELETE FROM ptero_servers
        WHERE order_id = :order_id
          AND status != 'active'
    ");

    $deleteServer->execute([
        "order_id" => (int)$orderId,
    ]);

    $resetOrder = $pdo->prepare("
        UPDATE game_server_orders
        SET
            status = 'paid',
            provision_error = NULL,
            failed_at = NULL,
            provisioning_started_at = NULL,
            provisioned_at = NULL,
            approval_requested_at = NULL,
            approval_started_at = NULL,
            approval_error = NULL,
            updated_at = NOW()
        WHERE id = :order_id
    ");

    $resetOrder->execute([
        "order_id" => (int)$orderId,
    ]);

    /*
     * 既存ジョブがあれば初期状態へ戻す。
     */
    $resetJob = $pdo->prepare("
        UPDATE provisioning_jobs
        SET
            status = 'pending',
            attempts = 0,
            last_error = NULL,
            available_at = NOW(),
            started_at = NULL,
            completed_at = NULL,
            locked_at = NULL,
            worker_id = NULL,
            updated_at = NOW()
        WHERE order_id = :order_id
          AND job_type = 'provision_server'
    ");

    $resetJob->execute([
        "order_id" => (int)$orderId,
    ]);

    /*
     * ジョブがまだ存在しない場合は新規作成する。
     */
    if ($resetJob->rowCount() === 0) {
        $insertJob = $pdo->prepare("
            INSERT INTO provisioning_jobs (
                order_id,
                job_type,
                status,
                attempts,
                max_attempts,
                available_at,
                created_at,
                updated_at
            )
            VALUES (
                :order_id,
                'provision_server',
                'pending',
                0,
                5,
                NOW(),
                NOW(),
                NOW()
            )
        ");

        $insertJob->execute([
            "order_id" => (int)$orderId,
        ]);
    }

    try {
        $event = $pdo->prepare("
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
                'provisioning_retry_queued',
                'サーバー自動作成を再試行します',
                '管理者操作によりProvisioningジョブを再登録しました。',
                'provision_failed',
                'paid',
                CAST(:metadata_json AS JSONB),
                NOW()
            )
        ");

        $event->execute([
            "order_id" => (int)$orderId,
            "actor_user_id" => (int)$adminUser["id"],
            "metadata_json" => json_encode(
                [
                    "source" => "web",
                    "admin_user_id" => (int)$adminUser["id"],
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);
    } catch (Throwable $eventError) {
        error_log(
            "[provision retry event] order={$orderId} "
            . $eventError->getMessage()
        );
    }

    $pdo->commit();

    provision_failed_retry_flash(
        "success",
        "サーバー自動作成の再試行ジョブを登録しました。"
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    provision_failed_retry_flash(
        "error",
        "再試行ジョブの登録に失敗しました: "
        . $e->getMessage()
    );
}

provision_failed_retry_redirect();
