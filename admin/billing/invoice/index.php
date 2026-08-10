<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$adminUser = require_role("admin");

header('Location: /staff/admin/billing/invoices/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "請求詳細確認 | HC Platform";
$pageDescription = "管理者向けの請求詳細確認ページです。";
$pageCss = "/billing/invoice/invoice.css";

$pdo = db();

$orderId = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$order = null;
$errors = [];

function invoice_status_label(string $status): string
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

function invoice_payment_label(string $status): string
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

function invoice_payment_class(string $status): string
{
    return match ($status) {
        "paid" => "paid",
        "unpaid", "checkout_created" => "pending",
        "failed" => "failed",
        "refunded", "cancelled" => "muted",
        default => "muted",
    };
}

function invoice_price_value(?int $amount, ?int $fallbackAmount = 0): int
{
    return (int)($amount ?: $fallbackAmount ?: 0);
}

function invoice_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = invoice_price_value($amount, $fallbackAmount);
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function invoice_date(?string $value): string
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

function invoice_number(array $order): string
{
    $createdAt = (string)($order["created_at"] ?? "");
    $orderId = (int)($order["id"] ?? 0);

    try {
        $date = (new DateTime($createdAt))->format("Ymd");
    } catch (Throwable $e) {
        $date = date("Ymd");
    }

    return "HC-" . $date . "-" . str_pad((string)$orderId, 6, "0", STR_PAD_LEFT);
}

function invoice_memory_label(?int $memoryMb): string
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

function invoice_cpu_label(?int $cpuLimit): string
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

if (!$orderId) {
    $errors[] = "契約IDが指定されていません。";
} else {
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
                gsp.cpu_limit,
                gsp.disk_mb,

                u.username,
                u.email
            FROM game_server_orders gso
            JOIN game_server_plans gsp ON gsp.id = gso.plan_id
            LEFT JOIN users u ON u.id = gso.user_id
            WHERE gso.id = :order_id
            LIMIT 1
        ");

        $stmt->execute([
            "order_id" => $orderId,
        ]);

        $order = $stmt->fetch();

        if (!$order) {
            $errors[] = "請求情報が見つかりません。";
        }
    } catch (Throwable $e) {
        $errors[] = "請求情報の取得中にエラーが発生しました。";
    }
}

$subtotal = $order ? invoice_price_value((int)$order["amount"], (int)$order["price_monthly"]) : 0;
$total = $subtotal;

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="invoice-page">
    <section class="invoice-hero">
        <div class="container invoice-hero-grid">
            <div class="invoice-copy reveal">
                <p class="eyebrow">Admin / Billing / Invoice</p>
                <h1>請求詳細確認</h1>
                <p>
                    管理者向けの請求詳細確認ページです。
                    ユーザー側の請求詳細とは別に、全ユーザーの契約を確認できます。
                </p>
            </div>

            <aside class="invoice-status-card reveal">
                <span>Admin Invoice</span>
                <h2><?php echo $order ? h(invoice_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])) : "-"; ?></h2>
                <p>
                    <?php if ($order): ?>
                        <?php echo h(invoice_payment_label((string)$order["payment_status"])); ?>
                    <?php else: ?>
                        表示できません
                    <?php endif; ?>
                </p>
            </aside>
        </div>
    </section>

    <section class="section invoice-section">
        <div class="container">
            <div class="invoice-toolbar">
                <a href="/admin/billing/" class="back-button">請求・支払い管理へ戻る</a>

                <?php if ($order): ?>
                    <a href="/admin/server-orders/detail/?id=<?php echo h((string)$order["id"]); ?>" class="sub-button">契約詳細へ</a>
                    <a href="/admin/users/" class="sub-button">ユーザー管理へ</a>
                <?php endif; ?>
            </div>

            <?php if ($errors): ?>
                <div class="invoice-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <section class="invoice-document reveal">
                    <div class="invoice-document-head">
                        <div>
                            <p class="eyebrow">HC Platform</p>
                            <h2>請求詳細確認</h2>
                            <span>仮請求番号: <?php echo h(invoice_number($order)); ?></span>
                        </div>

                        <strong class="payment-badge payment-<?php echo h(invoice_payment_class((string)$order["payment_status"])); ?>">
                            <?php echo h(invoice_payment_label((string)$order["payment_status"])); ?>
                        </strong>
                    </div>

                    <div class="invoice-meta-grid">
                        <section>
                            <h3>請求先ユーザー</h3>
                            <p><?php echo h((string)($order["username"] ?: "不明なユーザー")); ?></p>
                            <p><?php echo h((string)($order["email"] ?: "-")); ?></p>
                        </section>

                        <section>
                            <h3>請求情報</h3>
                            <dl>
                                <div>
                                    <dt>契約ID</dt>
                                    <dd>#<?php echo h((string)$order["id"]); ?></dd>
                                </div>

                                <div>
                                    <dt>ユーザーID</dt>
                                    <dd>#<?php echo h((string)$order["user_id"]); ?></dd>
                                </div>

                                <div>
                                    <dt>作成日</dt>
                                    <dd><?php echo h(invoice_date((string)$order["created_at"])); ?></dd>
                                </div>

                                <div>
                                    <dt>契約状態</dt>
                                    <dd><?php echo h(invoice_status_label((string)$order["status"])); ?></dd>
                                </div>

                                <div>
                                    <dt>請求種別</dt>
                                    <dd><?php echo h((string)($order["billing_type"] ?: "monthly")); ?></dd>
                                </div>
                            </dl>
                        </section>
                    </div>

                    <div class="invoice-item-table">
                        <div class="invoice-table-head">
                            <span>内容</span>
                            <span>数量</span>
                            <span>単価</span>
                            <span>金額</span>
                        </div>

                        <div class="invoice-table-row">
                            <div>
                                <strong><?php echo h((string)$order["plan_name"]); ?></strong>
                                <p>
                                    <?php echo h((string)($order["server_name"] ?: "名称未設定")); ?>
                                    /
                                    <?php echo h(invoice_memory_label((int)$order["memory_mb"])); ?>
                                    /
                                    <?php echo h(invoice_cpu_label((int)$order["cpu_limit"])); ?>
                                </p>
                            </div>

                            <span>1</span>
                            <span><?php echo h(invoice_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?></span>
                            <span><?php echo h(invoice_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?></span>
                        </div>
                    </div>

                    <div class="invoice-total-box">
                        <dl>
                            <div>
                                <dt>小計</dt>
                                <dd>¥<?php echo h(number_format($subtotal)); ?></dd>
                            </div>

                            <div>
                                <dt>税・手数料</dt>
                                <dd>決済連携後に確定</dd>
                            </div>

                            <div class="total-row">
                                <dt>合計</dt>
                                <dd>¥<?php echo h(number_format($total)); ?></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="invoice-notice">
                        <strong>これは管理者向けの仮請求詳細です。</strong>
                        <p>
                            現在は契約情報から作成した仮表示です。
                            Stripe連携後、正式な請求書・領収書・決済イベントを表示します。
                        </p>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
