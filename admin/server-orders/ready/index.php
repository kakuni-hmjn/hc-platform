<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$adminUser = require_role("admin");

$pageTitle = "サーバー作成待ち | HC Platform";
$pageDescription = "支払い完了済みでサーバー作成待ちの契約一覧です。";
$pageCss = "/admin/server-orders/ready/ready.css";

$pdo = db();

$orders = [];
$errors = [];

$flash = $_SESSION["ready_orders_flash"] ?? null;
unset($_SESSION["ready_orders_flash"]);

if (empty($_SESSION["ready_orders_token"])) {
    $_SESSION["ready_orders_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["ready_orders_token"];

function ready_datetime(?string $value): string
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

function ready_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = (int)($amount ?: $fallbackAmount ?: 0);
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function ready_memory_label(?int $memoryMb): string
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

function ready_cpu_label(?int $cpuLimit): string
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

try {
    $stmt = $pdo->query("
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
            gso.stripe_checkout_session_id,
            gso.stripe_subscription_id,
            gso.stripe_customer_id,

            gsp.name AS plan_name,
            gsp.price_monthly,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,

            u.username,
            u.email
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        LEFT JOIN users u ON u.id = gso.user_id
        WHERE gso.payment_status = 'paid'
          AND gso.status = 'paid'
        ORDER BY gso.created_at ASC, gso.id ASC
    ");

    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "サーバー作成待ち一覧の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="ready-orders-page">
    <section class="ready-hero">
        <div class="container ready-hero-grid">
            <div class="ready-copy reveal">
                <p class="eyebrow">Admin / Provisioning Queue</p>
                <h1>サーバー作成待ち</h1>
                <p>
                    支払い完了済みで、まだサーバー作成が開始されていない契約を確認します。
                    ここでは状態を「作成中」に進めるだけで、ゲームサーバーパネル自動作成は次の段階で追加します。
                </p>
            </div>

            <aside class="ready-status-card reveal">
                <span>Ready</span>
                <h2><?php echo h((string)count($orders)); ?> 件</h2>
                <p>支払い完了済み・作成開始待ちの契約です。</p>
            </aside>
        </div>
    </section>

    <section class="section ready-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                <a href="/admin/server-orders/" class="sub-button">申込管理へ</a>
                <a href="/admin/billing/" class="sub-button">請求・支払い管理へ</a>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="ready-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Queue</p>
                        <h2>作成待ち契約一覧</h2>
                    </div>
                </div>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>作成待ちの契約はありません。</h3>
                        <p>支払いが完了し、契約状態がpaidになった契約がここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="ready-card-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="ready-card">
                                <div class="ready-card-head">
                                    <div>
                                        <span class="order-id">契約 #<?php echo h((string)$order["id"]); ?></span>
                                        <strong class="status-badge">支払い完了</strong>
                                    </div>

                                    <small><?php echo h(ready_datetime((string)$order["created_at"])); ?></small>
                                </div>

                                <div class="ready-card-main">
                                    <div class="main-info">
                                        <h3><?php echo h((string)($order["server_name"] ?: "名称未設定")); ?></h3>
                                        <p>
                                            <?php echo h((string)($order["username"] ?: "不明なユーザー")); ?>
                                            /
                                            <?php echo h((string)($order["email"] ?: "-")); ?>
                                        </p>
                                    </div>

                                    <div class="plan-info">
                                        <span>プラン</span>
                                        <strong><?php echo h((string)$order["plan_name"]); ?></strong>
                                        <p>
                                            <?php echo h(ready_memory_label((int)$order["memory_mb"])); ?>
                                            /
                                            <?php echo h(ready_cpu_label((int)$order["cpu_limit"])); ?>
                                        </p>
                                    </div>

                                    <div class="price-info">
                                        <span>料金</span>
                                        <strong>
                                            <?php echo h(ready_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?>
                                        </strong>
                                        <p><?php echo h((string)($order["billing_type"] ?: "monthly")); ?></p>
                                    </div>

                                    <div class="ready-actions">
                                        <a href="/admin/server-orders/detail/?id=<?php echo h((string)$order["id"]); ?>" class="secondary-action">
                                            詳細
                                        </a>

                                        <form method="post" action="/admin/server-orders/ready/start.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">

                                            <button type="submit" class="primary-action">
                                                作成開始へ進める
                                            </button>
                                        </form>
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

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
