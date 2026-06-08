<?php

session_start();

require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/db.php";

$currentUser = require_login();

$pageTitle = "請求・支払い | HC Platform";
$pageDescription = "HC Platformの請求・支払い情報ページです。";
$pageCss = "/billing/billing.css";

$pdo = db();

$orders = [];
$errors = [];

function billing_status_label(string $status): string
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

function billing_payment_label(string $status): string
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

function billing_payment_class(string $status): string
{
    return match ($status) {
        "paid" => "paid",
        "unpaid", "checkout_created" => "pending",
        "failed" => "failed",
        "refunded", "cancelled" => "muted",
        default => "muted",
    };
}

function billing_order_class(string $status): string
{
    return match ($status) {
        "active" => "active",
        "pending_payment", "paid", "creating" => "pending",
        "provision_failed" => "failed",
        "cancelled", "expired", "suspended" => "muted",
        default => "muted",
    };
}

function billing_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = $amount ?: $fallbackAmount ?: 0;
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function billing_amount_value(?int $amount, ?int $fallbackAmount = 0): int
{
    return (int)($amount ?: $fallbackAmount ?: 0);
}

function billing_datetime(?string $value): string
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

function billing_date(?string $value): string
{
    if (!$value) {
        return "-";
    }

    try {
        return (new DateTime($value))->format("Y/m/d");
    } catch (Throwable $e) {
        return $value;
    }
}

function billing_next_payment_date(array $order): string
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

function billing_memory_label(?int $memoryMb): string
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

function billing_cpu_label(?int $cpuLimit): string
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
    $stmt = $pdo->prepare("
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
            gsp.cpu_limit
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        WHERE gso.user_id = :user_id
        ORDER BY gso.created_at DESC, gso.id DESC
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "請求・支払い情報の取得中にエラーが発生しました。";
}

$monthlyTotal = 0;
$activeBillingCount = 0;
$needsPaymentCount = 0;
$paidCount = 0;

foreach ($orders as $order) {
    $status = (string)$order["status"];
    $paymentStatus = (string)$order["payment_status"];

    if (in_array($status, ["pending_payment", "paid", "creating", "active"], true)) {
        $monthlyTotal += billing_amount_value((int)$order["amount"], (int)$order["price_monthly"]);
        $activeBillingCount++;
    }

    if (in_array($paymentStatus, ["unpaid", "checkout_created", "failed"], true)) {
        $needsPaymentCount++;
    }

    if ($paymentStatus === "paid") {
        $paidCount++;
    }
}

require_once __DIR__ . "/../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="billing-page">
    <section class="billing-hero">
        <div class="container billing-hero-grid">
            <div class="billing-copy reveal">
                <p class="eyebrow">Dashboard / Billing</p>
                <h1>請求・支払い</h1>
                <p>
                    契約中サービスの月額料金、支払い状態、次回支払い予定を確認できます。
                    支払い方法変更と正式な請求履歴は、決済連携後に有効化します。
                </p>
            </div>

            <aside class="billing-status-card reveal">
                <span>月額見込み</span>
                <h2>¥<?php echo h(number_format($monthlyTotal)); ?></h2>
                <p>
                    対象契約 <?php echo h((string)$activeBillingCount); ?> 件 /
                    支払い確認 <?php echo h((string)$needsPaymentCount); ?> 件
                </p>
            </aside>
        </div>
    </section>

    <section class="section billing-section">
        <div class="container">
            <div class="billing-toolbar">
                <a href="/dashboard/" class="back-button">ダッシュボードへ戻る</a>
                <a href="/dashboard/servers/" class="sub-button">契約中サーバーへ</a>
                <a href="/billing/profile/" class="sub-button">請求先情報</a>
                <a href="/order/" class="sub-button">サービス申込へ</a>
            </div>

            <?php if ($errors): ?>
                <div class="billing-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="billing-summary-grid reveal">
                <article class="summary-card">
                    <span>Monthly</span>
                    <strong>¥<?php echo h(number_format($monthlyTotal)); ?></strong>
                    <p>現在の契約から計算した月額見込みです。</p>
                </article>

                <article class="summary-card <?php echo $needsPaymentCount > 0 ? 'has-alert' : ''; ?>">
                    <span>Payment Check</span>
                    <strong><?php echo h((string)$needsPaymentCount); ?> 件</strong>
                    <p>未払い・決済ページ作成済み・支払い失敗の契約数です。</p>
                </article>

                <article class="summary-card">
                    <span>Paid</span>
                    <strong><?php echo h((string)$paidCount); ?> 件</strong>
                    <p>支払い済みとして記録されている契約数です。</p>
                </article>
            </div>

            <section class="billing-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Contracts</p>
                        <h2>契約ごとの支払い状況</h2>
                    </div>
                </div>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>請求対象の契約はまだありません。</h3>
                        <p>ゲームサーバーなどのサービスを申し込むと、ここに支払い状況が表示されます。</p>
                        <a href="/order/" class="primary-link">サービスを申し込む</a>
                    </div>
                <?php else: ?>
                    <div class="billing-contract-list">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderStatus = (string)$order["status"];
                            $paymentStatus = (string)$order["payment_status"];
                            $orderClass = billing_order_class($orderStatus);
                            $paymentClass = billing_payment_class($paymentStatus);
                            ?>
                            <article class="billing-contract-card status-<?php echo h($orderClass); ?>">
                                <div class="contract-head">
                                    <div>
                                        <span class="contract-id">契約 #<?php echo h((string)$order["id"]); ?></span>
                                        <strong class="contract-status status-<?php echo h($orderClass); ?>">
                                            <?php echo h(billing_status_label($orderStatus)); ?>
                                        </strong>
                                        <strong class="payment-status payment-<?php echo h($paymentClass); ?>">
                                            <?php echo h(billing_payment_label($paymentStatus)); ?>
                                        </strong>
                                    </div>

                                    <small>
                                        申込:
                                        <?php echo h(billing_datetime((string)$order["created_at"])); ?>
                                    </small>
                                </div>

                                <div class="contract-main">
                                    <div class="contract-title">
                                        <h3><?php echo h((string)($order["server_name"] ?: "名称未設定")); ?></h3>
                                        <p>
                                            <?php echo h((string)$order["plan_name"]); ?>
                                            /
                                            <?php echo h(billing_memory_label((int)$order["memory_mb"])); ?>
                                            /
                                            <?php echo h(billing_cpu_label((int)$order["cpu_limit"])); ?>
                                        </p>
                                    </div>

                                    <div class="contract-price">
                                        <span>月額料金</span>
                                        <strong>
                                            <?php echo h(billing_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?>
                                        </strong>
                                        <p><?php echo h((string)($order["billing_type"] ?: "monthly")); ?></p>
                                    </div>

                                    <div class="contract-next">
                                        <span>次回支払い予定</span>
                                        <strong><?php echo h(billing_next_payment_date($order)); ?></strong>
                                        <p>
                                            <?php if (!empty($order["auto_renew_cancelled"])): ?>
                                                自動更新停止中
                                            <?php else: ?>
                                                仮表示
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <div class="contract-actions">
                                        <a href="/dashboard/servers/detail/?id=<?php echo h((string)$order["id"]); ?>" class="primary-action">
                                            契約詳細
                                        </a>

                                        <a href="/dashboard/servers/detail/?id=<?php echo h((string)$order["id"]); ?>#plan-change" class="secondary-action">
                                            契約変更
                                        </a>

                                        <button type="button" class="disabled-action" disabled>
                                            支払い方法変更 準備中
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="billing-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">History</p>
                        <h2>請求履歴</h2>
                    </div>
                </div>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>請求履歴はまだありません。</h3>
                        <p>契約作成後、支払い履歴がここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="invoice-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="invoice-item">
                                <div>
                                    <span>
                                        <?php echo h(billing_datetime((string)$order["created_at"])); ?>
                                    </span>
                                    <strong>
                                        <?php echo h((string)$order["plan_name"]); ?>
                                        /
                                        契約 #<?php echo h((string)$order["id"]); ?>
                                    </strong>
                                    <p>
                                        現在は契約情報から作成した仮の履歴です。
                                        正式な請求書番号・領収書は決済連携後に表示します。
                                    </p>
                                </div>

                                <div class="invoice-side">
                                    <strong>
                                        <?php echo h(billing_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?>
                                    </strong>
                                    <span class="payment-status payment-<?php echo h(billing_payment_class((string)$order["payment_status"])); ?>">
                                        <?php echo h(billing_payment_label((string)$order["payment_status"])); ?>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
