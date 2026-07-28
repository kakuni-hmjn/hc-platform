<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "契約詳細 | HC Platform";
$pageDescription = "HC Platformの管理者向けゲームサーバー契約詳細ページです。";
$pageCss = "/admin/server-orders/detail/detail.css";

$pdo = db();

if (empty($_SESSION["server_approval_token"])) {
    $_SESSION["server_approval_token"] = bin2hex(random_bytes(32));
}

$serverApprovalToken = (string)$_SESSION["server_approval_token"];


$errors = [];
$order = null;
$events = [];

$flash = $_SESSION["server_order_flash"] ?? null;
unset($_SESSION["server_order_flash"]);

if (empty($_SESSION["server_order_action_token"])) {
    $_SESSION["server_order_action_token"] = bin2hex(random_bytes(32));
}
$actionToken = (string)$_SESSION["server_order_action_token"];

$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

function detail_status_label(string $status): string
{
    return match ($status) {
        "pending_payment" => "決済待ち",
        "paid" => "決済済み",
        "creating" => "作成中",
        "active" => "稼働中",
        "provision_failed" => "作成失敗",
        "suspended" => "停止中",
        "cancelled" => "キャンセル",
        "expired" => "期限切れ",
        default => $status,
    };
}

function detail_payment_label(string $status): string
{
    return match ($status) {
        "unpaid" => "未払い",
        "checkout_created" => "Checkout作成済み",
        "paid" => "支払い済み",
        "failed" => "支払い失敗",
        "refunded" => "返金済み",
        "cancelled" => "支払いキャンセル",
        default => $status,
    };
}

function detail_billing_label(?string $billingType): string
{
    return match ((string)$billingType) {
        "auto_subscription" => "自動更新",
        "manual_renewal" => "手動更新",
        default => (string)($billingType ?: "-"),
    };
}

function detail_billing_period_label(?string $billingPeriod): string
{
    return match ((string)$billingPeriod) {
        "monthly" => "月額",
        "yearly" => "年額",
        default => (string)($billingPeriod ?: "-"),
    };
}

function detail_minecraft_type_label(?string $type): string
{
    return match ((string)$type) {
        "java" => "Java",
        "bedrock" => "Bedrock",
        "java_bedrock" => "Java + Bedrock",
        default => (string)($type ?: "-"),
    };
}

function detail_price(?int $amount, ?string $currency = "jpy"): string
{
    $amount = $amount ?? 0;
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($amount);
    }

    return strtoupper($currency) . " " . number_format($amount);
}

function detail_mb_to_gb(?int $mb): string
{
    if ($mb === null || $mb <= 0) {
        return "-";
    }

    $gb = $mb / 1024;

    if (floor($gb) == $gb) {
        return (string)(int)$gb . "GB";
    }

    return number_format($gb, 1) . "GB";
}

function detail_cpu_to_vcpu(?int $cpuLimit): string
{
    if ($cpuLimit === null || $cpuLimit <= 0) {
        return "無制限";
    }

    $vcpu = $cpuLimit / 100;

    if (floor($vcpu) == $vcpu) {
        return (string)(int)$vcpu . "vCPU";
    }

    return number_format($vcpu, 1) . "vCPU";
}

function detail_datetime(?string $value): string
{
    if (!$value) {
        return "-";
    }

    try {
        $dt = new DateTime($value);
        return $dt->format("Y/m/d H:i");
    } catch (Throwable $e) {
        return $value;
    }
}

function detail_text($value): string
{
    $value = trim((string)($value ?? ""));

    if ($value === "") {
        return "-";
    }

    return $value;
}

function detail_bool_label($value): string
{
    return in_array($value, [true, 1, "1", "t", "true", "yes"], true) ? "はい" : "いいえ";
}

if (!$orderId) {
    $errors[] = "契約IDが指定されていません。";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                gso.id,
                gso.user_id,
                gso.plan_id,
                gso.selected_node_id,

                gso.server_name,
                gso.minecraft_type,
                gso.server_software,
                gso.minecraft_version,
                gso.player_count_estimate,
                gso.note,

                gso.billing_type,
                gso.billing_period,
                gso.status,
                gso.payment_status,
                gso.amount,
                gso.currency,

                gso.stripe_checkout_session_id,
                gso.stripe_customer_id,
                gso.stripe_subscription_id,
                gso.stripe_payment_intent_id,

                gso.paid_at,
                gso.expires_at,
                gso.next_payment_due_at,

                gso.provisioning_started_at,
                gso.provisioned_at,
                gso.failed_at,
                gso.provision_error,

                gso.cancelled_at,
                gso.cancel_requested_at,
                gso.cancel_effective_at,
                gso.cancel_reason,
                gso.refund_policy_agreed,
                gso.auto_renew_cancelled,

                gso.created_at,
                gso.updated_at,

                gsp.name AS plan_name,
                gsp.slug AS plan_slug,
                gsp.description AS plan_description,
                gsp.price_monthly,
                gsp.memory_mb,
                gsp.cpu_limit,
                gsp.disk_mb,
                gsp.backup_limit,
                gsp.database_limit,
                gsp.allocation_limit,
                gsp.server_software_note,
                gsp.ptero_nest_id,
                gsp.ptero_egg_id,
                gsp.ptero_docker_image,
                gsp.ptero_startup_command,
                gsp.status AS plan_status,

                pn.id AS node_local_id,
                pn.ptero_node_id,
                pn.name AS node_name,
                pn.label AS node_label,
                pn.fqdn AS node_fqdn,
                pn.description AS node_description,
                pn.cpu_type AS node_cpu_type,
                pn.is_high_performance AS node_is_high_performance,
                pn.memory_mb AS node_memory_mb,
                pn.disk_mb AS node_disk_mb,
                pn.status AS node_status,

                ps.id AS ptero_server_local_id,
                ps.ptero_user_id,
                ps.ptero_server_id,
                ps.ptero_identifier,
                ps.ptero_uuid,
                ps.name AS ptero_server_name,
                ps.status AS ptero_server_status,
                ps.created_at AS ptero_created_at,
                ps.updated_at AS ptero_updated_at,
                ps.deleted_at AS ptero_deleted_at,

                u.username,
                u.email,
                u.role AS user_role,
                u.status AS user_status,
                u.email_verified,
                u.created_at AS user_created_at
            FROM game_server_orders gso
            JOIN game_server_plans gsp ON gsp.id = gso.plan_id
            LEFT JOIN ptero_servers ps ON ps.order_id = gso.id
            LEFT JOIN ptero_nodes pn ON pn.id = COALESCE(gso.selected_node_id, ps.node_id)
            LEFT JOIN users u ON u.id = gso.user_id
            WHERE gso.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            "id" => $orderId,
        ]);

        $order = $stmt->fetch();

        if (!$order) {
            $errors[] = "指定された契約が見つかりません。";
        }
    } catch (Throwable $e) {
        $errors[] = "契約詳細の取得中にエラーが発生しました。";
    }
}

if ($order) {
    try {
        $eventStmt = $pdo->prepare("
            SELECT
                soe.*,
                u.username AS actor_username,
                u.role AS actor_role
            FROM server_order_events soe
            LEFT JOIN users u ON u.id = soe.actor_user_id
            WHERE soe.order_id = :order_id
            ORDER BY soe.created_at DESC, soe.id DESC
        ");
        $eventStmt->execute([
            "order_id" => (int)$order["id"],
        ]);
        $events = $eventStmt->fetchAll();
    } catch (Throwable $e) {
        $events = [];
    }
}

$status = $order ? (string)$order["status"] : "";
$paymentStatus = $order ? (string)$order["payment_status"] : "";

$hasCancel = $order && (
    !empty($order["auto_renew_cancelled"])
    || !empty($order["cancel_requested_at"])
    || !empty($order["cancel_effective_at"])
    || !empty($order["cancelled_at"])
    || $status === "cancelled"
);

$detailCardClass = "detail-summary-card";

if ($order) {
    $detailCardClass .= " status-" . $status;

    if ($hasCancel) {
        $detailCardClass .= " is-cancel-related";
    }
}


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
        soe.metadata_json,
        soe.created_at,
        u.username AS actor_username
    FROM server_order_events soe
    LEFT JOIN users u
        ON u.id = soe.actor_user_id
    WHERE soe.order_id = :order_id
    ORDER BY soe.created_at DESC, soe.id DESC
");

$eventStmt->execute([
    "order_id" => $orderId,
]);

$orderEvents = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

function detail_event_label(string $eventType): string
{
    return match ($eventType) {
        "payment_completed" => "支払い完了",
        "provisioning_requested" => "自動作成要求",
        "provisioning_started" => "自動作成開始",
        "ptero_user_resolved" => "パネルユーザー準備",
        "ptero_server_created" => "サーバー作成完了",
        "ptero_server_suspended" => "承認待ち停止",
        "approval_requested" => "承認待ち",
        "server_approved" => "利用開始承認",
        "server_approval_failed" => "承認失敗",
        "provisioning_failed",
        "ptero_server_create_failed" => "自動作成失敗",
        default => $eventType,
    };
}

function detail_event_class(string $eventType): string
{
    if (
        str_contains($eventType, "failed")
        || str_contains($eventType, "error")
    ) {
        return "is-error";
    }

    if (
        $eventType === "server_approved"
        || $eventType === "payment_completed"
    ) {
        return "is-success";
    }

    if (
        $eventType === "approval_requested"
        || $eventType === "ptero_server_suspended"
    ) {
        return "is-warning";
    }

    return "is-info";
}

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="server-order-detail-page">
    <section class="detail-hero">
        <div class="container detail-hero-grid">
            <div class="detail-copy reveal">
                <p class="eyebrow">Admin / Server Order Detail</p>
                <h1>契約詳細</h1>
                <p>
                    ゲームサーバー契約のユーザー、プラン、決済、ゲームサーバーパネル、
                    Node、作成状態、解約情報を確認・操作します。
                </p>
            </div>

            <aside class="detail-admin-card reveal">
                <span>管理者</span>
                <h2><?php echo h((string)$user["username"]); ?></h2>
                <p><?php echo h(role_label((string)$user["role"])); ?></p>
            </aside>
        </div>
    </section>

    <section class="section detail-section">
        <div class="container">
            <div class="detail-toolbar">
                <a href="/admin/server-orders/" class="back-button">契約一覧へ戻る</a>
                <a href="/admin/" class="sub-button">管理者ページへ戻る</a>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="admin-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <article class="<?php echo h($detailCardClass); ?>">
                    <div class="summary-main">
                        <div class="summary-id-line">
                            <span class="contract-id">#<?php echo h((string)$order["id"]); ?></span>
                            <span class="status-pill status-<?php echo h($status); ?>">
                                <?php echo h(detail_status_label($status)); ?>
                            </span>
                            <span class="payment-pill payment-<?php echo h($paymentStatus); ?>">
                                <?php echo h(detail_payment_label($paymentStatus)); ?>
                            </span>

                            <?php if ($hasCancel): ?>
                                <span class="cancel-pill">解約関連</span>
                            <?php endif; ?>
                        </div>

                        <h2><?php echo h(detail_text($order["server_name"])); ?></h2>
                        <p>
                            <?php echo h(detail_minecraft_type_label($order["minecraft_type"])); ?>
                            /
                            <?php echo h(detail_text($order["server_software"])); ?>
                            /
                            <?php echo h(detail_text($order["minecraft_version"])); ?>
                        </p>
                    </div>

                    <div class="summary-price">
                        <span>請求金額</span>
                        <strong><?php echo h(detail_price((int)$order["amount"], (string)$order["currency"])); ?></strong>
                        <small>
                            <?php echo h(detail_billing_label($order["billing_type"])); ?>
                            /
                            <?php echo h(detail_billing_period_label($order["billing_period"])); ?>
                        </small>
                    </div>
                </article>

                <section class="detail-panel wide-panel action-panel">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Actions</p>
                            <h2>管理操作</h2>
                        </div>
                    </div>

                    <div class="action-grid">
                        <form method="post" action="/admin/server-orders/detail/action.php">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="mark_paid">
                            <button type="submit" class="action-button action-primary">決済済みにする</button>
                        </form>

                        <form method="post" action="/admin/server-orders/detail/action.php">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="start_creation">
                            <button type="submit" class="action-button action-primary">作成中にする</button>
                        </form>

                        <form method="post" action="/admin/server-orders/detail/action.php">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="mock_create">
                            <button type="submit" class="action-button action-success">Mock作成して稼働中にする</button>
                        </form>

                        <form method="post" action="/admin/server-orders/detail/action.php">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="suspend">
                            <button type="submit" class="action-button action-warning">停止中にする</button>
                        </form>

                        <form method="post" action="/admin/server-orders/detail/action.php">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="resume">
                            <button type="submit" class="action-button action-success">稼働中に戻す</button>
                        </form>

                        <form method="post" action="/admin/server-orders/detail/action.php">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="request_cancel">
                            <button type="submit" class="action-button action-warning">解約予定にする</button>
                        </form>
                    </div>

                    <div class="action-note-grid">
                        <form method="post" action="/admin/server-orders/detail/action.php" class="note-action-form">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="mark_failed">

                            <label>作成失敗理由</label>
                            <textarea name="note" rows="3" placeholder="例: Nodeの空き容量不足、ゲームサーバーパネル APIエラーなど"></textarea>
                            <button type="submit" class="action-button action-danger">作成失敗にする</button>
                        </form>

                        <form method="post" action="/admin/server-orders/detail/action.php" class="note-action-form">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="cancel">

                            <label>キャンセル理由</label>
                            <textarea name="note" rows="3" placeholder="例: ユーザー都合、未払い、管理者判断など"></textarea>
                            <button type="submit" class="action-button action-danger">契約をキャンセル</button>
                        </form>

                        <form method="post" action="/admin/server-orders/detail/action.php" class="note-action-form">
                            <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                            <input type="hidden" name="action" value="add_note">

                            <label>管理者メモ</label>
                            <textarea name="note" rows="3" placeholder="この契約に関するメモを残す"></textarea>
                            <button type="submit" class="action-button action-primary">メモを追加</button>
                        </form>
                    </div>
                </section>

                <div class="detail-grid">
                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Contract</p>
                                <h2>契約情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>契約ID</span>
                                <strong>#<?php echo h((string)$order["id"]); ?></strong>
                            </div>
                            <div>
                                <span>サーバー名</span>
                                <strong><?php echo h(detail_text($order["server_name"])); ?></strong>
                            </div>
                            <div>
                                <span>Minecraft種別</span>
                                <strong><?php echo h(detail_minecraft_type_label($order["minecraft_type"])); ?></strong>
                            </div>
                            <div>
                                <span>サーバーソフト</span>
                                <strong><?php echo h(detail_text($order["server_software"])); ?></strong>
                            </div>
                            <div>
                                <span>バージョン</span>
                                <strong><?php echo h(detail_text($order["minecraft_version"])); ?></strong>
                            </div>
                            <div>
                                <span>想定人数</span>
                                <strong><?php echo h(detail_text($order["player_count_estimate"])); ?></strong>
                            </div>
                            <div>
                                <span>契約状態</span>
                                <strong><?php echo h(detail_status_label($status)); ?></strong>
                            </div>
                            <div>
                                <span>決済状態</span>
                                <strong><?php echo h(detail_payment_label($paymentStatus)); ?></strong>
                            </div>
                            <div>
                                <span>契約タイプ</span>
                                <strong><?php echo h(detail_billing_label($order["billing_type"])); ?></strong>
                            </div>
                            <div>
                                <span>請求周期</span>
                                <strong><?php echo h(detail_billing_period_label($order["billing_period"])); ?></strong>
                            </div>
                        </div>

                        <?php if (!empty($order["note"])): ?>
                            <div class="note-box">
                                <span>申込メモ</span>
                                <p><?php echo nl2br(h((string)$order["note"])); ?></p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">User</p>
                                <h2>ユーザー情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>ユーザーID</span>
                                <strong><?php echo h((string)$order["user_id"]); ?></strong>
                            </div>
                            <div>
                                <span>ユーザー名</span>
                                <strong><?php echo h(detail_text($order["username"])); ?></strong>
                            </div>
                            <div>
                                <span>メール</span>
                                <strong><?php echo h(detail_text($order["email"])); ?></strong>
                            </div>
                            <div>
                                <span>権限</span>
                                <strong><?php echo h(detail_text($order["user_role"])); ?></strong>
                            </div>
                            <div>
                                <span>アカウント状態</span>
                                <strong><?php echo h(detail_text($order["user_status"])); ?></strong>
                            </div>
                            <div>
                                <span>メール認証</span>
                                <strong><?php echo h(detail_bool_label($order["email_verified"])); ?></strong>
                            </div>
                            <div>
                                <span>登録日時</span>
                                <strong><?php echo h(detail_datetime((string)$order["user_created_at"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Plan</p>
                                <h2>プラン・スペック</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>プランID</span>
                                <strong><?php echo h((string)$order["plan_id"]); ?></strong>
                            </div>
                            <div>
                                <span>プラン名</span>
                                <strong><?php echo h(detail_text($order["plan_name"])); ?></strong>
                            </div>
                            <div>
                                <span>月額</span>
                                <strong><?php echo h(detail_price((int)$order["price_monthly"], "jpy")); ?></strong>
                            </div>
                            <div>
                                <span>メモリ</span>
                                <strong><?php echo h(detail_mb_to_gb((int)$order["memory_mb"])); ?></strong>
                            </div>
                            <div>
                                <span>CPU</span>
                                <strong><?php echo h(detail_cpu_to_vcpu((int)$order["cpu_limit"])); ?></strong>
                            </div>
                            <div>
                                <span>ディスク</span>
                                <strong><?php echo h(detail_mb_to_gb((int)$order["disk_mb"])); ?></strong>
                            </div>
                            <div>
                                <span>バックアップ</span>
                                <strong><?php echo h((string)$order["backup_limit"]); ?></strong>
                            </div>
                            <div>
                                <span>DB</span>
                                <strong><?php echo h((string)$order["database_limit"]); ?></strong>
                            </div>
                            <div>
                                <span>Allocation</span>
                                <strong><?php echo h((string)$order["allocation_limit"]); ?></strong>
                            </div>
                            <div>
                                <span>Plan Status</span>
                                <strong><?php echo h(detail_text($order["plan_status"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Payment</p>
                                <h2>決済・Stripe</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>請求金額</span>
                                <strong><?php echo h(detail_price((int)$order["amount"], (string)$order["currency"])); ?></strong>
                            </div>
                            <div>
                                <span>通貨</span>
                                <strong><?php echo h(strtoupper((string)$order["currency"])); ?></strong>
                            </div>
                            <div>
                                <span>Checkout Session</span>
                                <strong><?php echo h(detail_text($order["stripe_checkout_session_id"])); ?></strong>
                            </div>
                            <div>
                                <span>Customer ID</span>
                                <strong><?php echo h(detail_text($order["stripe_customer_id"])); ?></strong>
                            </div>
                            <div>
                                <span>Subscription ID</span>
                                <strong><?php echo h(detail_text($order["stripe_subscription_id"])); ?></strong>
                            </div>
                            <div>
                                <span>Payment Intent</span>
                                <strong><?php echo h(detail_text($order["stripe_payment_intent_id"])); ?></strong>
                            </div>
                            <div>
                                <span>支払い日時</span>
                                <strong><?php echo h(detail_datetime((string)$order["paid_at"])); ?></strong>
                            </div>
                            <div>
                                <span>次回支払い</span>
                                <strong><?php echo h(detail_datetime((string)$order["next_payment_due_at"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">ゲームサーバーパネル</p>
                                <h2>ゲームサーバーパネル情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>Local ID</span>
                                <strong><?php echo h(detail_text($order["ptero_server_local_id"])); ?></strong>
                            </div>
                            <div>
                                <span>パネルユーザーID</span>
                                <strong><?php echo h(detail_text($order["ptero_user_id"])); ?></strong>
                            </div>
                            <div>
                                <span>パネルサーバーID</span>
                                <strong><?php echo h(detail_text($order["ptero_server_id"])); ?></strong>
                            </div>
                            <div>
                                <span>Identifier</span>
                                <strong><?php echo h(detail_text($order["ptero_identifier"])); ?></strong>
                            </div>
                            <div>
                                <span>UUID</span>
                                <strong><?php echo h(detail_text($order["ptero_uuid"])); ?></strong>
                            </div>
                            <div>
                                <span>Status</span>
                                <strong><?php echo h(detail_text($order["ptero_server_status"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Node</p>
                                <h2>Node情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>Node Local ID</span>
                                <strong><?php echo h(detail_text($order["node_local_id"])); ?></strong>
                            </div>
                            <div>
                                <span>パネルNode ID</span>
                                <strong><?php echo h(detail_text($order["ptero_node_id"])); ?></strong>
                            </div>
                            <div>
                                <span>Node名</span>
                                <strong><?php echo h(detail_text($order["node_name"])); ?></strong>
                            </div>
                            <div>
                                <span>Label</span>
                                <strong><?php echo h(detail_text($order["node_label"])); ?></strong>
                            </div>
                            <div>
                                <span>FQDN</span>
                                <strong><?php echo h(detail_text($order["node_fqdn"])); ?></strong>
                            </div>
                            <div>
                                <span>CPU Type</span>
                                <strong><?php echo h(detail_text($order["node_cpu_type"])); ?></strong>
                            </div>
                            <div>
                                <span>Node Memory</span>
                                <strong><?php echo h(detail_mb_to_gb((int)$order["node_memory_mb"])); ?></strong>
                            </div>
                            <div>
                                <span>Node Disk</span>
                                <strong><?php echo h(detail_mb_to_gb((int)$order["node_disk_mb"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Provisioning</p>
                                <h2>作成状況</h2>
                            </div>
                        </div>

                        <div class="timeline-grid">
                            <div class="timeline-item">
                                <span>申込作成</span>
                                <strong><?php echo h(detail_datetime((string)$order["created_at"])); ?></strong>
                            </div>
                            <div class="timeline-item">
                                <span>支払い完了</span>
                                <strong><?php echo h(detail_datetime((string)$order["paid_at"])); ?></strong>
                            </div>
                            <div class="timeline-item">
                                <span>作成開始</span>
                                <strong><?php echo h(detail_datetime((string)$order["provisioning_started_at"])); ?></strong>
                            </div>
                            <div class="timeline-item">
                                <span>作成完了</span>
                                <strong><?php echo h(detail_datetime((string)$order["provisioned_at"])); ?></strong>
                            </div>
                            <div class="timeline-item">
                                <span>作成失敗</span>
                                <strong><?php echo h(detail_datetime((string)$order["failed_at"])); ?></strong>
                            </div>
                            <div class="timeline-item">
                                <span>更新日時</span>
                                <strong><?php echo h(detail_datetime((string)$order["updated_at"])); ?></strong>
                            </div>
                        </div>

                        <?php if (!empty($order["provision_error"])): ?>
                            <div class="error-box">
                                <span>作成エラー</span>
                                <p><?php echo nl2br(h((string)$order["provision_error"])); ?></p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="detail-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Cancel</p>
                                <h2>解約・更新情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>自動更新停止</span>
                                <strong><?php echo h(detail_bool_label($order["auto_renew_cancelled"])); ?></strong>
                            </div>
                            <div>
                                <span>解約申請日時</span>
                                <strong><?php echo h(detail_datetime((string)$order["cancel_requested_at"])); ?></strong>
                            </div>
                            <div>
                                <span>解約予定日時</span>
                                <strong><?php echo h(detail_datetime((string)$order["cancel_effective_at"])); ?></strong>
                            </div>
                            <div>
                                <span>キャンセル日時</span>
                                <strong><?php echo h(detail_datetime((string)$order["cancelled_at"])); ?></strong>
                            </div>
                            <div>
                                <span>返金ポリシー同意</span>
                                <strong><?php echo h(detail_bool_label($order["refund_policy_agreed"])); ?></strong>
                            </div>
                            <div>
                                <span>期限</span>
                                <strong><?php echo h(detail_datetime((string)$order["expires_at"])); ?></strong>
                            </div>
                        </div>

                        <?php if (!empty($order["cancel_reason"])): ?>
                            <div class="cancel-box">
                                <span>解約理由</span>
                                <p><?php echo nl2br(h((string)$order["cancel_reason"])); ?></p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="detail-panel wide-panel event-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">History</p>
                                <h2>操作履歴</h2>
                            </div>
                        </div>

                        <?php if (!$events): ?>
                            <div class="empty-history">
                                <p>操作履歴はまだありません。</p>
                            </div>
                        <?php else: ?>
                            <div class="event-list">
                                <?php foreach ($events as $event): ?>
                                    <article class="event-item">
                                        <div class="event-dot"></div>

                                        <div class="event-content">
                                            <div class="event-title-line">
                                                <strong><?php echo h((string)$event["title"]); ?></strong>
                                                <span><?php echo h(detail_datetime((string)$event["created_at"])); ?></span>
                                            </div>

                                            <p>
                                                操作:
                                                <?php echo h((string)($event["actor_username"] ?: "system")); ?>
                                                <?php if (!empty($event["actor_role"])): ?>
                                                    / <?php echo h((string)$event["actor_role"]); ?>
                                                <?php endif; ?>
                                            </p>

                                            <?php if (!empty($event["old_status"]) || !empty($event["new_status"])): ?>
                                                <p>
                                                    状態:
                                                    <?php echo h((string)($event["old_status"] ?: "-")); ?>
                                                    →
                                                    <?php echo h((string)($event["new_status"] ?: "-")); ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if (!empty($event["old_payment_status"]) || !empty($event["new_payment_status"])): ?>
                                                <p>
                                                    決済:
                                                    <?php echo h((string)($event["old_payment_status"] ?: "-")); ?>
                                                    →
                                                    <?php echo h((string)($event["new_payment_status"] ?: "-")); ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if (!empty($event["message"])): ?>
                                                <div class="event-message">
                                                    <?php echo nl2br(h((string)$event["message"])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>



<?php if (in_array((string)$order["status"], ["pending_approval", "approval_failed"], true)): ?>
<section class="section detail-approval-section">
    <div class="container">
        <section class="detail-approval-card <?php echo $order["status"] === "approval_failed" ? "is-failed" : ""; ?>">
            <div class="detail-approval-copy">
                <p class="eyebrow">Server Approval</p>

                <h2>
                    <?php echo $order["status"] === "approval_failed"
                        ? "承認処理を再試行できます"
                        : "利用開始の承認待ちです"; ?>
                </h2>

                <p>
                    自動作成済みのゲームサーバーを確認し、
                    問題がなければ利用開始を承認してください。
                </p>

                <?php if (!empty($order["approval_error"])): ?>
                    <div class="detail-approval-error">
                        <?php echo h((string)$order["approval_error"]); ?>
                    </div>
                <?php endif; ?>
            </div>

            <form
                method="post"
                action="/admin/server-orders/approve"
                class="detail-approval-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo h($serverApprovalToken); ?>"
                >

                <input
                    type="hidden"
                    name="order_id"
                    value="<?php echo h((string)$order["id"]); ?>"
                >

                <button type="submit" class="primary-action">
                    <?php echo $order["status"] === "approval_failed"
                        ? "承認を再試行"
                        : "承認して利用開始"; ?>
                </button>
            </form>
        </section>
    </div>
</section>
<?php endif; ?>

<section class="section order-event-section">
    <div class="container">
        <section class="order-event-panel reveal">
            <div class="order-event-panel-head">
                <div>
                    <p class="eyebrow">Order Timeline</p>
                    <h2>処理履歴</h2>
                    <p>
                        支払い、自動作成、承認などの処理履歴を表示します。
                    </p>
                </div>

                <span class="order-event-count">
                    <?php echo h((string)count($orderEvents)); ?> 件
                </span>
            </div>

            <?php if (!$orderEvents): ?>
                <div class="order-event-empty">
                    <strong>処理履歴はありません</strong>
                    <p>この注文に記録されたイベントはまだありません。</p>
                </div>
            <?php else: ?>
                <div class="order-event-timeline">
                    <?php foreach ($orderEvents as $event): ?>
                        <article class="order-event-item <?php echo h(detail_event_class((string)$event["event_type"])); ?>">
                            <span class="order-event-dot"></span>

                            <div class="order-event-content">
                                <div class="order-event-head">
                                    <div>
                                        <span class="order-event-type">
                                            <?php echo h(detail_event_label((string)$event["event_type"])); ?>
                                        </span>

                                        <h3>
                                            <?php echo h((string)$event["title"]); ?>
                                        </h3>
                                    </div>

                                    <time>
                                        <?php echo h(detail_datetime((string)$event["created_at"])); ?>
                                    </time>
                                </div>

                                <?php if (!empty($event["message"])): ?>
                                    <p class="order-event-message">
                                        <?php echo nl2br(h((string)$event["message"])); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="order-event-meta">
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
                                            支払い:
                                            <?php echo h((string)($event["old_payment_status"] ?: "-")); ?>
                                            →
                                            <?php echo h((string)($event["new_payment_status"] ?: "-")); ?>
                                        </span>
                                    <?php endif; ?>

                                    <span>
                                        実行者:
                                        <?php echo h((string)($event["actor_username"] ?: "System")); ?>
                                    </span>
                                </div>

                                <?php if (!empty($event["metadata_json"])): ?>
                                    <details class="order-event-details">
                                        <summary>詳細データ</summary>
                                        <pre><?php
                                            $metadata = $event["metadata_json"];

                                            if (is_string($metadata)) {
                                                $decoded = json_decode($metadata, true);
                                                $metadata = $decoded ?? $metadata;
                                            }

                                            echo h(
                                                json_encode(
                                                    $metadata,
                                                    JSON_PRETTY_PRINT
                                                    | JSON_UNESCAPED_UNICODE
                                                    | JSON_UNESCAPED_SLASHES
                                                )
                                            );
                                        ?></pre>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
