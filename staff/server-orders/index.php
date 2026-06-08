<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("staff");

$pageTitle = "申込・契約確認 | HC Platform";
$pageDescription = "スタッフ向けのゲームサーバー申込・契約確認ページです。";
$pageCss = "/staff/server-orders/server-orders.css";

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

function staff_order_status_label(string $status): string
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

function staff_order_payment_label(string $status): string
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

function staff_order_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = $amount ?: $fallbackAmount ?: 0;
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function staff_order_memory_label(?int $memoryMb): string
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

function staff_order_cpu_label(?int $cpuLimit): string
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

function staff_order_datetime(?string $value): string
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
            ), 0) AS pending_plan_change_request_count,

            COALESCE((
                SELECT COUNT(*)
                FROM server_order_events e
                WHERE e.order_id = gso.id
            ), 0) AS event_count
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
    $errors[] = "申込・契約一覧の取得中にエラーが発生しました。";
}

$totalCount = count($orders);
$activeCount = 0;
$pendingCount = 0;
$failedCount = 0;

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
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="staff-orders-page">
    <section class="staff-orders-hero">
        <div class="container staff-orders-hero-grid">
            <div class="staff-orders-copy reveal">
                <p class="eyebrow">Staff / Server Orders</p>
                <h1>申込・契約確認</h1>
                <p>
                    ゲームサーバーの申込、決済状態、作成状態、ユーザー情報を確認します。
                    スタッフページでは状態変更や削除などの管理操作は行いません。
                </p>
            </div>

            <aside class="staff-orders-status-card reveal">
                <span>表示中の契約</span>
                <h2><?php echo h((string)$totalCount); ?> 件</h2>
                <p>
                    稼働中 <?php echo h((string)$activeCount); ?> 件 /
                    確認対象 <?php echo h((string)$pendingCount); ?> 件 /
                    失敗 <?php echo h((string)$failedCount); ?> 件
                </p>
            </aside>
        </div>
    </section>

    <section class="section staff-orders-section">
        <div class="container">
            <div class="toolbar">
                <a href="/staff/" class="back-button">スタッフページへ戻る</a>
                <a href="/staff/contacts/" class="sub-button">問い合わせ確認</a>
            </div>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="orders-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Contracts</p>
                        <h2>契約カード一覧</h2>
                    </div>
                </div>

                <form method="get" action="/staff/server-orders/" class="filter-bar">
                    <div>
                        <label>検索</label>
                        <input
                            type="search"
                            name="q"
                            value="<?php echo h($keyword); ?>"
                            placeholder="契約ID / ユーザー / メール / サーバー名 / プラン"
                        >
                    </div>

                    <div>
                        <label>状態</label>
                        <select name="status">
                            <option value="">すべて</option>
                            <?php foreach ($orderStatuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? "selected" : ""; ?>>
                                    <?php echo h(staff_order_status_label($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit">絞り込み</button>
                    <a href="/staff/server-orders/">リセット</a>
                </form>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>契約はまだありません。</h3>
                        <p>ユーザーがゲームサーバーを申し込むと、ここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="order-card-list">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $status = (string)$order["status"];
                            $pendingPlanChange = (int)($order["pending_plan_change_request_count"] ?? 0);
                            ?>
                            <article class="order-card status-<?php echo h($status); ?>">
                                <div class="order-status-line">
                                    <div>
                                        <span class="order-id">#<?php echo h((string)$order["id"]); ?></span>
                                        <strong class="status-badge status-<?php echo h($status); ?>">
                                            <?php echo h(staff_order_status_label($status)); ?>
                                        </strong>

                                        <?php if ($pendingPlanChange > 0): ?>
                                            <strong class="request-badge">
                                                プラン変更 <?php echo h((string)$pendingPlanChange); ?> 件
                                            </strong>
                                        <?php endif; ?>
                                    </div>

                                    <small><?php echo h(staff_order_datetime((string)$order["created_at"])); ?></small>
                                </div>

                                <div class="order-main-row">
                                    <div class="order-main-info">
                                        <h3><?php echo h((string)($order["server_name"] ?: "名称未設定")); ?></h3>
                                        <p>
                                            <?php echo h((string)($order["username"] ?: "不明なユーザー")); ?>
                                            /
                                            <?php echo h((string)($order["email"] ?: "-")); ?>
                                        </p>
                                    </div>

                                    <div class="order-plan-info">
                                        <span>プラン</span>
                                        <strong><?php echo h((string)$order["plan_name"]); ?></strong>
                                        <p>
                                            <?php echo h(staff_order_memory_label((int)$order["memory_mb"])); ?>
                                            /
                                            <?php echo h(staff_order_cpu_label((int)$order["cpu_limit"])); ?>
                                        </p>
                                    </div>

                                    <div class="order-payment-info">
                                        <span>料金 / 決済</span>
                                        <strong>
                                            <?php echo h(staff_order_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?>
                                        </strong>
                                        <p><?php echo h(staff_order_payment_label((string)$order["payment_status"])); ?></p>
                                    </div>

                                    <div class="order-actions">
                                        <a href="/staff/server-orders/detail/?id=<?php echo h((string)$order["id"]); ?>">
                                            詳細を見る
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
