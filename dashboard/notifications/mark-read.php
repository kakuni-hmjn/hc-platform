<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = require_login();
$pdo = db();

function notification_is_ajax(): bool
{
    $requestedWith = strtolower((string)($_SERVER["HTTP_X_REQUESTED_WITH"] ?? ""));
    $accept = strtolower((string)($_SERVER["HTTP_ACCEPT"] ?? ""));

    return $requestedWith === "xmlhttprequest" || str_contains($accept, "application/json");
}

function notification_json_response(bool $ok, string $message = "", array $extra = []): void
{
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(array_merge([
        "ok" => $ok,
        "message" => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function notification_read_redirect(string $url): void
{
    if ($url === "" || !str_starts_with($url, "/") || str_contains($url, "://")) {
        $url = "/dashboard/notifications/";
    }

    header("Location: " . $url);
    exit;
}

function notification_finish(bool $ok, string $message, string $redirect, array $extra = []): void
{
    if (notification_is_ajax()) {
        notification_json_response($ok, $message, $extra);
    }

    notification_read_redirect($redirect);
}

function ensure_notification_reads_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_notification_reads (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            notification_type VARCHAR(40) NOT NULL,
            notification_id INTEGER NOT NULL,
            read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (user_id, notification_type, notification_id)
        )
    ");
}

function mark_read(PDO $pdo, int $userId, string $notificationType, int $notificationId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO user_notification_reads
        (user_id, notification_type, notification_id, read_at, created_at)
        VALUES
        (:user_id, :notification_type, :notification_id, NOW(), NOW())
        ON CONFLICT (user_id, notification_type, notification_id)
        DO UPDATE SET read_at = NOW()
    ");

    $stmt->execute([
        "user_id" => $userId,
        "notification_type" => $notificationType,
        "notification_id" => $notificationId,
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    if (notification_is_ajax()) {
        http_response_code(405);
        notification_json_response(false, "Method Not Allowed");
    }

    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$redirect = trim((string)($_POST["redirect"] ?? "/dashboard/notifications/"));
$csrfToken = (string)($_POST["csrf_token"] ?? "");
$action = trim((string)($_POST["action"] ?? "mark_one"));
$type = trim((string)($_POST["type"] ?? ""));
$notificationId = filter_input(INPUT_POST, "notification_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

if (
    empty($_SESSION["notification_read_token"]) ||
    !hash_equals((string)$_SESSION["notification_read_token"], $csrfToken)
) {
    notification_finish(false, "不正な操作です。", $redirect);
}

try {
    ensure_notification_reads_table($pdo);

    if ($action === "mark_one") {
        if ($type === "personal") {
            $type = "personal_event";
        }

        if (!$notificationId || !in_array($type, ["personal_event", "direct", "global"], true)) {
            notification_finish(false, "通知IDが不正です。", $redirect);
        }

        if ($type === "personal_event") {
            $check = $pdo->prepare("
                SELECT soe.id
                FROM server_order_events soe
                JOIN game_server_orders gso ON gso.id = soe.order_id
                WHERE soe.id = :id
                  AND gso.user_id = :user_id
                LIMIT 1
            ");
            $check->execute([
                "id" => $notificationId,
                "user_id" => (int)$currentUser["id"],
            ]);

            if ($check->fetch()) {
                mark_read($pdo, (int)$currentUser["id"], "personal_event", $notificationId);
            }
        }

        if ($type === "direct") {
            $check = $pdo->prepare("
                SELECT id
                FROM user_direct_notifications
                WHERE id = :id
                  AND user_id = :user_id
                  AND status = 'published'
                  AND published_at <= NOW()
                LIMIT 1
            ");
            $check->execute([
                "id" => $notificationId,
                "user_id" => (int)$currentUser["id"],
            ]);

            if ($check->fetch()) {
                mark_read($pdo, (int)$currentUser["id"], "direct_notice", $notificationId);
            }
        }

        if ($type === "global") {
            $check = $pdo->prepare("
                SELECT id
                FROM site_notifications
                WHERE id = :id
                  AND status = 'published'
                  AND target_scope = 'all'
                  AND published_at <= NOW()
                LIMIT 1
            ");
            $check->execute([
                "id" => $notificationId,
            ]);

            if ($check->fetch()) {
                mark_read($pdo, (int)$currentUser["id"], "global_notice", $notificationId);
            }
        }

        notification_finish(true, "既読にしました。", $redirect, [
            "action" => $action,
            "type" => $type,
            "notification_id" => $notificationId,
        ]);
    }

    if ($action === "mark_all_personal" || $action === "mark_all") {
        $stmt = $pdo->prepare("
            INSERT INTO user_notification_reads
            (user_id, notification_type, notification_id, read_at, created_at)
            SELECT
                :user_id,
                'personal_event',
                soe.id,
                NOW(),
                NOW()
            FROM server_order_events soe
            JOIN game_server_orders gso ON gso.id = soe.order_id
            WHERE gso.user_id = :user_id
            ON CONFLICT (user_id, notification_type, notification_id)
            DO UPDATE SET read_at = NOW()
        ");
        $stmt->execute([
            "user_id" => (int)$currentUser["id"],
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO user_notification_reads
            (user_id, notification_type, notification_id, read_at, created_at)
            SELECT
                :user_id,
                'direct_notice',
                udn.id,
                NOW(),
                NOW()
            FROM user_direct_notifications udn
            WHERE udn.user_id = :user_id
              AND udn.status = 'published'
              AND udn.published_at <= NOW()
            ON CONFLICT (user_id, notification_type, notification_id)
            DO UPDATE SET read_at = NOW()
        ");
        $stmt->execute([
            "user_id" => (int)$currentUser["id"],
        ]);
    }

    if ($action === "mark_all_global" || $action === "mark_all") {
        $stmt = $pdo->prepare("
            INSERT INTO user_notification_reads
            (user_id, notification_type, notification_id, read_at, created_at)
            SELECT
                :user_id,
                'global_notice',
                sn.id,
                NOW(),
                NOW()
            FROM site_notifications sn
            WHERE sn.status = 'published'
              AND sn.target_scope = 'all'
              AND sn.published_at <= NOW()
            ON CONFLICT (user_id, notification_type, notification_id)
            DO UPDATE SET read_at = NOW()
        ");
        $stmt->execute([
            "user_id" => (int)$currentUser["id"],
        ]);
    }

    notification_finish(true, "既読にしました。", $redirect, [
        "action" => $action,
        "type" => $type,
    ]);
} catch (Throwable $e) {
    notification_finish(false, "既読処理に失敗しました。", $redirect);
}
