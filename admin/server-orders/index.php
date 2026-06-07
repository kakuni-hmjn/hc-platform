<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "ゲームサーバー契約管理 | HC Platform";
$pageDescription = "HC Platformの管理者向けゲームサーバー契約一覧ページです。";
$pageCss = "/admin/server-orders/server-orders.css";

$pdo = db();

$errors = [];
$orders = [];

$statusFilter = trim((string)($_GET["status"] ?? ""));
$keyword = trim((string)($_GET["q"] ?? ""));

$orderStatuses = [
    "pending_payment",
    "paid",
    "creating",
    "active",
    "provision_failed",
    "suspended",
    "cancelled",
    "expired",
];

function server_orders_status_label(string $status): string
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

function server_orders_payment_label(string $status): string
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

function server_orders_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = $amount ?: $fallbackAmount ?: 0;
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function server_orders_memory_label(?int $memoryMb): string
{
    if (!$memoryMb || $memoryMb <= 0) {
        return "-";
    }

    $gb = $memoryMb / 1024;

    if (floor($gb) == $gb) {
        return (string)(int)$gb . "GB";
    }

    return number_format($gb, 1) . "GB";
}

function server_orders_cpu_label(?int $cpuLimit): string
{
    if (!$cpuLimit || $cpuLimit <= 0) {
        return "無制限";
    }

    $vcpu = $cpuLimit / 100;

    if (floor($vcpu) == $vcpu) {
        return (string)(int)$vcpu . "vCPU";
    }

    return number_format($vcpu, 1) . "vCPU";
}

$where = [];
$params = [];

if ($statusFilter !== "" && in_array($statusFilter, $orderStatuses, true)) {
    $where[] = "gso.status = :status";
    $params["status"] = $statusFilter;
}

if ($keyword !== "") {
    $where[] = "(
        CAST(gso.id AS TEXT) ILIKE :keyword
        OR gso.server_name ILIKE :keyword
        OR u.username ILIKE :keyword
        OR u.email ILIKE :keyword
        OR gsp.name ILIKE :keyword
        OR gsp.slug ILIKE :keyword
        OR gso.stripe_checkout_session_id ILIKE :keyword
        OR gso.stripe_subscription_id ILIKE :keyword
    )";
    $params["keyword"] = "%" . $keyword . "%";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

try {
    $sql = "
        SELECT
            gso.id,
            gso.user_id,
            gso.plan_id,
            gso.server_name,
            gso.status,
            gso.payment_status,
            gso.amount,
            gso.currency,
            gso.billing_type,
            gso.created_at,
            gso.cancel_requested_at,
            gso.cancel_effective_at,
            gso.auto_renew_cancelled,

            gsp.name AS plan_name,
            gsp.slug AS plan_slug,
            gsp.price_monthly,
            gsp.memory_mb,
            gsp.cpu_limit,

            u.username,
            u.email,
            u.status AS user_status,

            COALESCE((
                SELECT COUNT(*)
                FROM server_order_plan_change_requests r
                WHERE r.order_id = gso.id
            ), 0) AS plan_change_request_count,

            COALESCE((
                SELECT COUNT(*)
                FROM server_order_plan_change_requests r
                WHERE r.order_id = gso.id
                  AND r.status = 'pending'
            ), 0) AS pending_plan_change_request_count
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        LEFT JOIN users u ON u.id = gso.user_id
        {$whereSql}
        ORDER BY gso.created_at DESC, gso.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "契約一覧の取得中にエラーが発生しました。";
}

$totalCount = count($orders);
$activeCount = 0;
$pendingCount = 0;
$failedCount = 0;
$pendingPlanChangeCount = 0;

foreach ($orders as $order) {
    $status = (string)$order["status"];

    if ($status === "active") {
        $activeCount++;
    }

    if (in_array($status, ["pending_payment", "paid", "creating"], true)) {
        $pendingCount++;
    }

    if ($status === "provision_failed") {
        $failedCount++;
    }

    $pendingPlanChangeCount += (int)($order["pending_plan_change_request_count"] ?? 0);
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
                <h1>ゲームサーバー契約管理</h1>
                <p>
                    契約一覧をカード形式で表示します。
                    左端の色で契約状態を確認し、関連するプラン変更申請へ移動できます。
                </p>
            </div>

            <aside class="server-orders-status-card reveal">
                <span>表示中の契約</span>
                <h2><?php echo h((string)$totalCount); ?> 件</h2>
                <p>
                    稼働中 <?php echo h((string)$activeCount); ?> 件 /
                    処理中 <?php echo h((string)$pendingCount); ?> 件 /
                    失敗 <?php echo h((string)$failedCount); ?> 件
                </p>

                <?php if ($pendingPlanChangeCount > 0): ?>
                    <strong class="admin-side-badge">
                        プラン変更申請 <?php echo h((string)$pendingPlanChangeCount); ?> 件
                    </strong>
                <?php endif; ?>
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
                        <p class="eyebrow">Contracts</p>
                        <h2>契約カード一覧</h2>
                    </div>

                    <div class="panel-actions">
                        <a href="/admin/plan-change-requests/" class="sub-button">プラン変更申請管理</a>
                        <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                    </div>
                </div>

                <form action="/admin/server-orders/" method="get" class="filter-bar">
                    <div class="filter-search">
                        <label for="q">検索</label>
                        <input
                            type="search"
                            id="q"
                            name="q"
                            value="<?php echo h($keyword); ?>"
                            placeholder="契約ID / サーバー名 / ユーザー / メール / プラン"
                        >
                    </div>

                    <div>
                        <label for="status">状態</label>
                        <select id="status" name="status">
                            <option value="">すべて</option>
                            <?php foreach ($orderStatuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? "selected" : ""; ?>>
                                    <?php echo h(server_orders_status_label($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit">絞り込み</button>
                    <a href="/admin/server-orders/">リセット</a>
                </form>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>契約はまだありません。</h3>
                        <p>ユーザーがゲームサーバーを申し込むと、ここにカード形式で表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="orders-card-list">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $status = (string)$order["status"];
                            $paymentStatus = (string)$order["payment_status"];

                            $username = (string)($order["username"] ?: "User #" . $order["user_id"]);
                            $email = (string)($order["email"] ?: "-");
                            $serverName = (string)($order["server_name"] ?: "-");
                            $planName = (string)($order["plan_name"] ?: "Plan #" . $order["plan_id"]);

                            $memoryLabel = server_orders_memory_label(isset($order["memory_mb"]) ? (int)$order["memory_mb"] : null);
                            $cpuLabel = server_orders_cpu_label(isset($order["cpu_limit"]) ? (int)$order["cpu_limit"] : null);

                            $price = server_orders_price(
                                isset($order["amount"]) ? (int)$order["amount"] : 0,
                                isset($order["price_monthly"]) ? (int)$order["price_monthly"] : 0,
                                (string)($order["currency"] ?: "jpy")
                            );

                            $hasCancel =
                                !empty($order["auto_renew_cancelled"])
                                || !empty($order["cancel_requested_at"])
                                || !empty($order["cancel_effective_at"])
                                || $status === "cancelled";

                            $planRequestCount = (int)($order["plan_change_request_count"] ?? 0);
                            $pendingPlanRequests = (int)($order["pending_plan_change_request_count"] ?? 0);

                            $cardClasses = "server-order-card status-" . $status;
                            if ($hasCancel) {
                                $cardClasses .= " is-cancel-related";
                            }
                            ?>
                            <article class="<?php echo h($cardClasses); ?>">
                                <div class="card-status-area">
                                    <div class="contract-id-row">
                                        <span class="contract-id">#<?php echo h((string)$order["id"]); ?></span>
                                        <span class="status-pill status-<?php echo h($status); ?>">
                                            <?php echo h(server_orders_status_label($status)); ?>
                                        </span>
                                    </div>

                                    <p title="<?php echo h($serverName); ?>">
                                        <?php echo h($serverName); ?>
                                    </p>

                                    <?php if ($pendingPlanRequests > 0): ?>
                                        <strong class="plan-request-badge">
                                            プラン変更 <?php echo h((string)$pendingPlanRequests); ?> 件
                                        </strong>
                                    <?php endif; ?>
                                </div>

                                <div class="card-info-area">
                                    <div class="info-item">
                                        <span>ユーザー</span>
                                        <strong title="<?php echo h($username); ?>">
                                            <?php echo h($username); ?>
                                        </strong>
                                        <small title="<?php echo h($email); ?>">
                                            <?php echo h($email); ?>
                                        </small>
                                    </div>

                                    <div class="info-item">
                                        <span>プラン</span>
                                        <strong title="<?php echo h($planName); ?>">
                                            <?php echo h($planName); ?>
                                        </strong>
                                        <small>
                                            <?php echo h($memoryLabel); ?> / <?php echo h($cpuLabel); ?>
                                        </small>
                                    </div>

                                    <div class="info-item price-item">
                                        <span>料金</span>
                                        <strong><?php echo h($price); ?></strong>
                                        <small>
                                            <?php echo h(server_orders_payment_label($paymentStatus)); ?>
                                            <?php if ($hasCancel): ?>
                                                / 解約関連
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="card-action-area multi-actions">
                                    <a class="detail-button" href="/admin/server-orders/detail/?id=<?php echo h((string)$order["id"]); ?>">
                                        詳細を見る
                                    </a>

                                    <?php if ($planRequestCount > 0): ?>
                                        <a class="secondary-detail-button" href="/admin/plan-change-requests/?q=<?php echo h((string)$order["id"]); ?>">
                                            関連申請
                                        </a>
                                    <?php endif; ?>
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
