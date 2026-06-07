<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";

$currentUser = require_login();
$pdo = db();

header("Content-Type: text/html; charset=UTF-8");

$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

function event_datetime(?string $value): string
{
    if (!$value) {
        return "-";
    }

    try {
        return (new DateTime($value))->format("Y/m/d H:i");
    } catch (Throwable $e) {
        return $value;
    }
}

function event_type_label(string $eventType): string
{
    return match ($eventType) {
        "order_created" => "申込作成",
        "payment_checkout_created" => "決済ページ作成",
        "payment_paid" => "決済完了",
        "payment_failed" => "決済失敗",
        "server_provision_started" => "サーバー作成開始",
        "server_provisioned" => "サーバー作成完了",
        "server_provision_failed" => "サーバー作成失敗",
        "cancel_requested" => "キャンセル申請",
        "admin_cancel_processed" => "キャンセル処理",
        "plan_change_requested" => "プラン変更申請",
        "admin_plan_change_note" => "管理者メモ",
        "admin_plan_change_approved" => "プラン変更承認",
        "admin_plan_change_rejected" => "プラン変更却下",
        "admin_plan_change_processed" => "プラン変更反映",
        "admin_plan_change_applied" => "承認済み変更反映",
        default => $eventType,
    };
}

function event_class_name(string $eventType): string
{
    if (str_contains($eventType, "plan_change")) {
        return "event-plan";
    }

    if (str_contains($eventType, "payment")) {
        return "event-payment";
    }

    if (str_contains($eventType, "cancel")) {
        return "event-cancel";
    }

    if (str_contains($eventType, "provision") || str_contains($eventType, "server")) {
        return "event-server";
    }

    if (str_contains($eventType, "admin")) {
        return "event-admin";
    }

    return "event-default";
}

$events = [];
$errorMessage = "";

if (!$orderId) {
    $errorMessage = "契約IDが指定されていません。";
} else {
    try {
        $orderStmt = $pdo->prepare("
            SELECT id, server_name
            FROM game_server_orders
            WHERE id = :id
              AND user_id = :user_id
            LIMIT 1
        ");

        $orderStmt->execute([
            "id" => $orderId,
            "user_id" => (int)$currentUser["id"],
        ]);

        $order = $orderStmt->fetch();

        if (!$order) {
            $errorMessage = "この契約の履歴は表示できません。契約が見つからないか、表示権限がありません。";
        } else {
            $eventStmt = $pdo->prepare("
                SELECT
                    soe.id,
                    soe.event_type,
                    soe.title,
                    soe.message,
                    soe.old_status,
                    soe.new_status,
                    soe.old_payment_status,
                    soe.new_payment_status,
                    soe.ip_address,
                    soe.created_at,
                    actor.username AS actor_username,
                    actor.role AS actor_role
                FROM server_order_events soe
                LEFT JOIN users actor ON actor.id = soe.actor_user_id
                WHERE soe.order_id = :order_id
                ORDER BY soe.created_at DESC, soe.id DESC
                LIMIT 80
            ");

            $eventStmt->execute([
                "order_id" => $orderId,
            ]);

            $events = $eventStmt->fetchAll();
        }
    } catch (Throwable $e) {
        $errorMessage = "契約履歴の取得中にエラーが発生しました。";
    }
}

$latestText = "履歴はまだありません。";

if ($events) {
    $latest = $events[0];
    $latestTitle = trim((string)($latest["title"] ?? ""));

    if ($latestTitle === "") {
        $latestTitle = event_type_label((string)$latest["event_type"]);
    }

    $latestText = event_datetime((string)$latest["created_at"]) . " / " . $latestTitle;
}
?>

<details class="detail-panel wide-panel event-timeline-panel">
    <summary class="event-timeline-summary">
        <div>
            <p class="eyebrow">Timeline</p>
            <h2>契約履歴</h2>
            <small><?php echo h($latestText); ?></small>
        </div>

        <div class="event-summary-right">
            <span class="event-count">
                <?php echo h((string)count($events)); ?> 件
            </span>
            <strong class="event-toggle-label">開く</strong>
        </div>
    </summary>

    <div class="event-timeline-content">
        <?php if ($errorMessage !== ""): ?>
            <div class="info-box">
                <strong><?php echo h($errorMessage); ?></strong>
            </div>
        <?php elseif (!$events): ?>
            <div class="info-box">
                <strong>履歴はまだありません。</strong>
                <p>申込、決済、プラン変更、キャンセルなどの履歴がここに表示されます。</p>
            </div>
        <?php else: ?>
            <div class="event-timeline">
                <?php foreach ($events as $event): ?>
                    <?php
                    $eventType = (string)$event["event_type"];
                    $className = event_class_name($eventType);
                    $title = trim((string)($event["title"] ?? ""));

                    if ($title === "") {
                        $title = event_type_label($eventType);
                    }
                    ?>
                    <article class="event-item <?php echo h($className); ?>">
                        <div class="event-dot"></div>

                        <div class="event-body">
                            <div class="event-head">
                                <div>
                                    <span><?php echo h(event_datetime((string)$event["created_at"])); ?></span>
                                    <h3><?php echo h($title); ?></h3>
                                </div>

                                <strong><?php echo h(event_type_label($eventType)); ?></strong>
                            </div>

                            <?php if (!empty($event["message"])): ?>
                                <div class="event-message">
                                    <?php echo nl2br(h((string)$event["message"])); ?>
                                </div>
                            <?php endif; ?>

                            <div class="event-meta">
                                <span>
                                    操作:
                                    <?php echo h((string)($event["actor_username"] ?: "system")); ?>
                                </span>

                                <?php if (!empty($event["old_status"]) || !empty($event["new_status"])): ?>
                                    <span>
                                        状態:
                                        <?php echo h((string)($event["old_status"] ?: "-")); ?>
                                        →
                                        <?php echo h((string)($event["new_status"] ?: "-")); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($event["old_payment_status"]) || !empty($event["new_payment_status"])): ?>
                                    <span>
                                        決済:
                                        <?php echo h((string)($event["old_payment_status"] ?: "-")); ?>
                                        →
                                        <?php echo h((string)($event["new_payment_status"] ?: "-")); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</details>
