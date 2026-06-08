<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$adminUser = require_role("admin");

$pageTitle = "請求・支払い管理 | HC Platform";
$pageDescription = "HC Platformの管理者向け請求・支払い確認ページです。";
$pageCss = "/admin/billing/billing.css";

$pdo = db();

$errors = [];
$orders = [];

$statusFilter = trim((string)($_GET["payment_status"] ?? ""));
$orderStatusFilter = trim((string)($_GET["status"] ?? ""));
$keyword = trim((string)($_GET["q"] ?? ""));

$paymentStatuses = [
    "unpaid",
    "checkout_created",
    "paid",
    "failed",
    "refunded",
    "cancelled",
];

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

function admin_billing_status_label(string $status): string
{
    return match ($status) {
        "pending_payment" => "決済待ち",
        "paid" => "決済済み",
        "creating" => "作成中",
        "active" => "契約中",
        "provision_failed" => "作成失敗",
        "suspended" => "停止中",
        "cancelled" => "キャンセル済み",
        "expired" => "期限切れ",
        default => $status,
    };
}

function admin_billing_payment_label(string $status): string
{
    return match ($status) {
        "unpaid" => "未払い",
        "checkout_created" => "決済ページ作成済み",
        "paid" => "支払い済み",
        "failed" => "支払い失敗",
        "refunded" => "返金済み",
        "cancelled" => "支払いキャンセル",
        default => $status,
    };
}

function admin_billing_payment_class(string $status): string
{
    return match ($status) {
        "paid" => "paid",
        "unpaid", "checkout_created" => "pending",
        "failed" => "failed",
        "refunded", "cancelled" => "muted",
        default => "muted",
    };
}

function admin_billing_order_class(string $status): string
{
    return match ($status) {
        "active" => "active",
        "pending_payment", "paid", "creating" => "pending",
        "provision_failed" => "failed",
        "cancelled", "expired", "suspended" => "muted",
        default => "muted",
    };
}

function admin_billing_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = (int)($amount ?: $fallbackAmount ?: 0);
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function admin_billing_amount(?int $amount, ?int $fallbackAmount = 0): int
{
    return (int)($amount ?: $fallbackAmount ?: 0);
}

function admin_billing_datetime(?string $value): string
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

function admin_billing_next_payment_date(array $order): string
{
    $status = (string)($order["status"] ?? "");
    $paymentStatus = (string)($order["payment_status"] ?? "");
    $createdAt = (string)($order["created_at"] ?? "");

    if (in_array($status, ["cancelled", "expired", "suspended"], true)) {
        return "-";
    }

    if ($paymentStatus !== "paid" && $status === "pending_payment") {
        return "初回支払い後";
    }

    if ($createdAt === "") {
        return "-";
    }

    try {
        $base = new DateTime($createdAt);
        $now = new DateTime();

        while ($base <= $now) {
            $base->modify("+1 month");
        }

        return $base->format("Y/m/d");
    } catch (Throwable $e) {
        return "-";
    }
}

$where = [];
$params = [];

if ($statusFilter !== "" && in_array($statusFilter, $paymentStatuses, true)) {
    $where[] = "gso.payment_status = :payment_status";
    $params["payment_status"] = $statusFilter;
}

if ($orderStatusFilter !== "" && in_array($orderStatusFilter, $orderStatuses, true)) {
    $where[] = "gso.status = :status";
    $params["status"] = $orderStatusFilter;
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
            u.role AS user_role,
            u.status AS user_status
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        LEFT JOIN users u ON u.id = gso.user_id
        {$whereSql}
        ORDER BY gso.created_at DESC, gso.id DESC
        LIMIT 300
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "請求・支払い情報の取得中にエラーが発生しました。";
}

$totalAmount = 0;
$paidAmount = 0;
$unpaidAmount = 0;
$failedAmount = 0;
$paidCount = 0;
$needsPaymentCount = 0;
$failedCount = 0;

foreach ($orders as $order) {
    $amount = admin_billing_amount((int)$order["amount"], (int)$order["price_monthly"]);
    $paymentStatus = (string)$order["payment_status"];

    $totalAmount += $amount;

    if ($paymentStatus === "paid") {
        $paidCount++;
        $paidAmount += $amount;
    }

    if (in_array($paymentStatus, ["unpaid", "checkout_created"], true)) {
        $needsPaymentCount++;
        $unpaidAmount += $amount;
    }

    if ($paymentStatus === "failed") {
        $failedCount++;
        $failedAmount += $amount;
    }
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="admin-billing-page">
    <section class="admin-billing-hero">
        <div class="container admin-billing-hero-grid">
            <div class="admin-billing-copy reveal">
                <p class="eyebrow">Admin / Billing</p>
                <h1>請求・支払い管理</h1>
                <p>
                    契約ごとの支払い状態、金額、ユーザー、次回支払い予定を確認します。
                    このページは閲覧専用です。支払い状態の手動変更は行いません。
                </p>
            </div>

            <aside class="admin-billing-status-card reveal">
                <span>表示中の合計</span>
                <h2>¥<?php echo h(number_format($totalAmount)); ?></h2>
                <p>
                    表示 <?php echo h((string)count($orders)); ?> 件 /
                    支払い確認 <?php echo h((string)$needsPaymentCount); ?> 件
                </p>
            </aside>
        </div>
    </section>

    <section class="section admin-billing-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                <a href="/admin/server-orders/" class="sub-button">申込管理へ</a>
                <a href="/billing/" class="sub-button">ユーザー側表示確認</a>
            </div>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="billing-summary-grid reveal">
                <article class="summary-card">
                    <span>Paid</span>
                    <strong><?php echo h((string)$paidCount); ?> 件</strong>
                    <p>支払い済み合計: ¥<?php echo h(number_format($paidAmount)); ?></p>
                </article>

                <article class="summary-card <?php echo $needsPaymentCount > 0 ? 'has-warning' : ''; ?>">
                    <span>Need Payment</span>
                    <strong><?php echo h((string)$needsPaymentCount); ?> 件</strong>
                    <p>未払い・Checkout作成済み: ¥<?php echo h(number_format($unpaidAmount)); ?></p>
                </article>

                <article class="summary-card <?php echo $failedCount > 0 ? 'has-alert' : ''; ?>">
                    <span>Failed</span>
                    <strong><?php echo h((string)$failedCount); ?> 件</strong>
                    <p>支払い失敗合計: ¥<?php echo h(number_format($failedAmount)); ?></p>
                </article>
            </div>

            <section class="billing-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Billing List</p>
                        <h2>支払い状態一覧</h2>
                    </div>
                </div>

                <form method="get" action="/admin/billing/" class="filter-bar">
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
                        <label>支払い状態</label>
                        <select name="payment_status">
                            <option value="">すべて</option>
                            <?php foreach ($paymentStatuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? "selected" : ""; ?>>
                                    <?php echo h(admin_billing_payment_label($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>契約状態</label>
                        <select name="status">
                            <option value="">すべて</option>
                            <?php foreach ($orderStatuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $orderStatusFilter === $status ? "selected" : ""; ?>>
                                    <?php echo h(admin_billing_status_label($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit">絞り込み</button>
                    <a href="/admin/billing/">リセット</a>
                </form>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>支払い情報はまだありません。</h3>
                        <p>ゲームサーバー契約が作成されると、ここに支払い状態が表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="billing-card-list">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderStatus = (string)$order["status"];
                            $paymentStatus = (string)$order["payment_status"];
                            $orderClass = admin_billing_order_class($orderStatus);
                            $paymentClass = admin_billing_payment_class($paymentStatus);
                            ?>
                            <article class="billing-card status-<?php echo h($orderClass); ?>">
                                <div class="billing-card-head">
                                    <div>
                                        <span class="contract-id">契約 #<?php echo h((string)$order["id"]); ?></span>

                                        <strong class="contract-status status-<?php echo h($orderClass); ?>">
                                            <?php echo h(admin_billing_status_label($orderStatus)); ?>
                                        </strong>

                                        <strong class="payment-status payment-<?php echo h($paymentClass); ?>">
                                            <?php echo h(admin_billing_payment_label($paymentStatus)); ?>
                                        </strong>
                                    </div>

                                    <small><?php echo h(admin_billing_datetime((string)$order["created_at"])); ?></small>
                                </div>

                                <div class="billing-card-main">
                                    <div class="billing-main-info">
                                        <h3><?php echo h((string)($order["server_name"] ?: "名称未設定")); ?></h3>
                                        <p>
                                            <?php echo h((string)($order["username"] ?: "不明なユーザー")); ?>
                                            /
                                            <?php echo h((string)($order["email"] ?: "-")); ?>
                                        </p>
                                    </div>

                                    <div class="billing-plan-info">
                                        <span>プラン</span>
                                        <strong><?php echo h((string)$order["plan_name"]); ?></strong>
                                        <p><?php echo h((string)($order["billing_type"] ?: "monthly")); ?></p>
                                    </div>

                                    <div class="billing-price-info">
                                        <span>金額</span>
                                        <strong>
                                            <?php echo h(admin_billing_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?>
                                        </strong>
                                        <p>次回: <?php echo h(admin_billing_next_payment_date($order)); ?></p>
                                    </div>

                                    <div class="billing-actions">
                                        <a href="/admin/server-orders/detail/?id=<?php echo h((string)$order["id"]); ?>" class="primary-action">
                                            契約詳細
                                        </a>

                                        <a href="/admin/billing/invoice/?order_id=<?php echo h((string)$order["id"]); ?>" class="secondary-action">
                                            請求詳細
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
