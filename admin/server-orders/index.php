<?php
session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "ゲームサーバー申込管理 | HC Platform";
$pageDescription = "HC Platformの管理者向けゲームサーバー申込・契約管理ページです。";
$pageCss = "/admin/server-orders/server-orders.css";

$pdo = db();

$errors = [];
$orders = [];

$statusFilter = $_GET["status"] ?? "";
$paymentFilter = $_GET["payment_status"] ?? "";
$billingFilter = $_GET["billing_type"] ?? "";

function format_mb_to_gb(int $mb): string
{
    if ($mb <= 0) {
        return "0GB";
    }

    $gb = $mb / 1024;

    if (floor($gb) == $gb) {
        return (string)(int)$gb . "GB";
    }

    return number_format($gb, 1) . "GB";
}

function format_cpu_to_vcpu(int $cpuLimit): string
{
    if ($cpuLimit <= 0) {
        return "無制限";
    }

    $vcpu = $cpuLimit / 100;

    if (floor($vcpu) == $vcpu) {
        return (string)(int)$vcpu . "vCPU";
    }

    return number_format($vcpu, 1) . "vCPU";
}

function billing_type_label_admin(string $billingType): string
{
    return match ($billingType) {
        "auto_subscription" => "自動更新",
        "manual_renewal" => "手動更新",
        default => $billingType,
    };
}

function order_status_label_admin(string $status): string
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

function payment_status_label_admin(string $status): string
{
    return match ($status) {
        "unpaid" => "未払い",
        "checkout_created" => "Checkout",
        "paid" => "支払い済み",
        "failed" => "失敗",
        "refunded" => "返金済み",
        "cancelled" => "キャンセル",
        default => $status,
    };
}

$where = [];
$params = [];

if ($statusFilter !== "") {
    $where[] = "gso.status = :status";
    $params["status"] = $statusFilter;
}

if ($paymentFilter !== "") {
    $where[] = "gso.payment_status = :payment_status";
    $params["payment_status"] = $paymentFilter;
}

if ($billingFilter !== "") {
    $where[] = "gso.billing_type = :billing_type";
    $params["billing_type"] = $billingFilter;
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

try {
    $sql = "
        SELECT
            gso.id,
            gso.user_id,
            gso.plan_id,
            gso.server_name,
            gso.minecraft_type,
            gso.server_software,
            gso.minecraft_version,
            gso.billing_type,
            gso.status,
            gso.payment_status,
            gso.stripe_checkout_session_id,
            gso.stripe_subscription_id,
            gso.amount,
            gso.currency,
            gso.expires_at,
            gso.next_payment_due_at,
            gso.provision_error,
            gso.created_at,
            gso.paid_at,
            gso.provisioned_at,
            gso.cancel_requested_at,
            gso.cancel_effective_at,
            gso.cancel_reason,
            gso.refund_policy_agreed,
            gso.auto_renew_cancelled,

            gsp.name AS plan_name,
            gsp.price_monthly,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,

            pn.name AS node_name,
            pn.label AS node_label,
            pn.cpu_type AS node_cpu_type,
            pn.ptero_node_id,

            ps.id AS ptero_server_local_id,
            ps.ptero_user_id,
            ps.ptero_server_id,
            ps.ptero_identifier,
            ps.ptero_uuid,
            ps.status AS ptero_server_status,

            u.username,
            u.email
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        LEFT JOIN ptero_nodes pn ON pn.id = gso.selected_node_id
        LEFT JOIN ptero_servers ps ON ps.order_id = gso.id
        LEFT JOIN users u ON u.id = gso.user_id
        {$whereSql}
        ORDER BY gso.created_at DESC, gso.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "申込情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="server-orders-page">

    <section class="server-orders-hero">
        <div class="container server-orders-hero-grid">

            <div class="server-orders-copy reveal">
                <p class="eyebrow">Admin / Server Orders</p>
                <h1>ゲームサーバー申込管理</h1>
                <p>
                    申込、決済、Pterodactyl作成状態、解約申請を一覧で確認します。
                </p>
            </div>

            <aside class="server-orders-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section server-orders-section">
        <div class="container">

            <?php if ($errors): ?>
                <div class="admin-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="orders-panel reveal">

                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Orders</p>
                        <h2>申込一覧</h2>
                    </div>

                    <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                </div>

                <form action="/admin/server-orders/" method="get" class="filter-bar">
                    <div>
                        <label for="status">注文状態</label>
                        <select id="status" name="status">
                            <option value="">すべて</option>
                            <?php foreach (["pending_payment", "paid", "creating", "active", "provision_failed", "suspended", "cancelled", "expired"] as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? "selected" : ""; ?>>
                                    <?php echo h(order_status_label_admin($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="payment_status">決済状態</label>
                        <select id="payment_status" name="payment_status">
                            <option value="">すべて</option>
                            <?php foreach (["unpaid", "checkout_created", "paid", "failed", "refunded", "cancelled"] as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $paymentFilter === $status ? "selected" : ""; ?>>
                                    <?php echo h(payment_status_label_admin($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="billing_type">請求タイプ</label>
                        <select id="billing_type" name="billing_type">
                            <option value="">すべて</option>
                            <option value="auto_subscription" <?php echo $billingFilter === "auto_subscription" ? "selected" : ""; ?>>自動更新</option>
                            <option value="manual_renewal" <?php echo $billingFilter === "manual_renewal" ? "selected" : ""; ?>>手動更新</option>
                        </select>
                    </div>

                    <button type="submit">絞り込み</button>
                    <a href="/admin/server-orders/">リセット</a>
                </form>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>申込はまだありません。</h3>
                        <p>ユーザーがゲームサーバー申込を行うと、ここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="orders-card-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="order-row-card status-<?php echo h((string)$order["status"]); ?> <?php echo !empty($order["auto_renew_cancelled"]) ? "is-cancelled" : ""; ?>">

                                <div class="order-row-main">
                                    <div class="order-row-title">
                                        <span class="order-id">#<?php echo h((string)$order["id"]); ?></span>

                                        <div>
                                            <h3><?php echo h((string)$order["server_name"]); ?></h3>
                                            <p>
                                                <?php echo h((string)$order["minecraft_type"]); ?>
                                                /
                                                <?php echo h((string)$order["server_software"]); ?>
                                                <?php if (!empty($order["minecraft_version"])): ?>
                                                    /
                                                    <?php echo h((string)$order["minecraft_version"]); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="order-row-badges">
                                        <span class="status-pill status-<?php echo h((string)$order["status"]); ?>">
                                            <?php echo !empty($order["auto_renew_cancelled"]) ? "解約申請済み" : h(order_status_label_admin((string)$order["status"])); ?>
                                        </span>

                                        <span class="payment-pill payment-<?php echo h((string)$order["payment_status"]); ?>">
                                            <?php echo h(payment_status_label_admin((string)$order["payment_status"])); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="order-row-info">
                                    <div>
                                        <span>ユーザー</span>
                                        <strong><?php echo h((string)($order["username"] ?? "User #" . $order["user_id"])); ?></strong>
                                        <small><?php echo h((string)($order["email"] ?? "")); ?></small>
                                    </div>

                                    <div>
                                        <span>プラン</span>
                                        <strong><?php echo h((string)$order["plan_name"]); ?></strong>
                                        <small>
                                            <?php echo h(format_mb_to_gb((int)$order["memory_mb"])); ?>
                                            /
                                            <?php echo h(format_cpu_to_vcpu((int)$order["cpu_limit"])); ?>
                                            /
                                            <?php echo h(format_mb_to_gb((int)$order["disk_mb"])); ?>
                                        </small>
                                    </div>

                                    <div>
                                        <span>料金</span>
                                        <strong>¥<?php echo h(number_format((int)$order["amount"])); ?> / 月</strong>
                                        <small><?php echo h(billing_type_label_admin((string)$order["billing_type"])); ?></small>
                                    </div>

                                    <div>
                                        <span>Node</span>
                                        <strong><?php echo h((string)($order["node_label"] ?? "未設定")); ?></strong>
                                        <small>
                                            <?php echo h((string)($order["node_name"] ?? "")); ?>
                                            <?php if (!empty($order["ptero_node_id"])): ?>
                                                / Ptero #<?php echo h((string)$order["ptero_node_id"]); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>

                                    <div>
                                        <span>Pterodactyl</span>
                                        <strong><?php echo h((string)($order["ptero_identifier"] ?? "未作成")); ?></strong>
                                        <small>
                                            <?php if (!empty($order["ptero_server_id"])): ?>
                                                Server #<?php echo h((string)$order["ptero_server_id"]); ?>
                                            <?php elseif (!empty($order["provision_error"])): ?>
                                                作成エラー
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </small>
                                    </div>

                                    <div>
                                        <span>契約</span>
                                        <strong>
                                            <?php if (!empty($order["cancel_effective_at"])): ?>
                                                終了予定あり
                                            <?php elseif (!empty($order["expires_at"])): ?>
                                                手動更新
                                            <?php elseif (!empty($order["next_payment_due_at"])): ?>
                                                自動更新
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </strong>
                                        <small>
                                            <?php if (!empty($order["cancel_effective_at"])): ?>
                                                <?php echo h((string)$order["cancel_effective_at"]); ?>
                                            <?php elseif (!empty($order["expires_at"])): ?>
                                                期限 <?php echo h((string)$order["expires_at"]); ?>
                                            <?php elseif (!empty($order["next_payment_due_at"])): ?>
                                                次回 <?php echo h((string)$order["next_payment_due_at"]); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>

                                <?php if (!empty($order["provision_error"]) || !empty($order["cancel_reason"])): ?>
                                    <div class="order-row-notes">
                                        <?php if (!empty($order["provision_error"])): ?>
                                            <p class="note-error">
                                                <strong>作成エラー:</strong>
                                                <?php echo h((string)$order["provision_error"]); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($order["cancel_reason"])): ?>
                                            <p class="note-cancel">
                                                <strong>解約理由:</strong>
                                                <?php echo h((string)$order["cancel_reason"]); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="order-row-footer">
                                    <span>
                                        作成日時:
                                        <?php echo h((string)$order["created_at"]); ?>
                                    </span>

                                    <a href="/admin/server-orders/detail/?id=<?php echo h((string)$order["id"]); ?>">
                                        詳細を見る
                                        <span>→</span>
                                    </a>
                                </div>

                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>