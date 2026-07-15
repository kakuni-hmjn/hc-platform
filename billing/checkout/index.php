<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = require_login();

$pageTitle = "支払い手続き | HC Platform";
$pageDescription = "HC Platformの支払い手続きページです。";
$pageCss = "/billing/checkout/checkout.css";

$pdo = db();

$orderId = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$order = null;
$errors = [];

$autoStartCheckout = (string)($_GET["auto"] ?? "") === "1";

$flash = $_SESSION["checkout_flash"] ?? null;
unset($_SESSION["checkout_flash"]);

if (empty($_SESSION["checkout_token"])) {
    $_SESSION["checkout_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["checkout_token"];

function checkout_status_label(string $status): string
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

function checkout_payment_label(string $status): string
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

function checkout_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = (int)($amount ?: $fallbackAmount ?: 0);
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function checkout_datetime(?string $value): string
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

function checkout_memory_label(?int $memoryMb): string
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

function checkout_cpu_label(?int $cpuLimit): string
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

                gsp.name AS plan_name,
                gsp.price_monthly,
                gsp.memory_mb,
                gsp.cpu_limit
            FROM game_server_orders gso
            JOIN game_server_plans gsp ON gsp.id = gso.plan_id
            WHERE gso.id = :order_id
              AND gso.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            "order_id" => $orderId,
            "user_id" => (int)$currentUser["id"],
        ]);

        $order = $stmt->fetch();

        if (!$order) {
            $errors[] = "契約が見つからないか、表示権限がありません。";
        }
    } catch (Throwable $e) {
        $errors[] = "契約情報の取得中にエラーが発生しました。";
    }
}

$isAlreadyPaid = $order && (string)$order["payment_status"] === "paid";

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="checkout-page">
    <section class="checkout-hero">
        <div class="container checkout-hero-grid">
            <div class="checkout-copy reveal">
                <p class="eyebrow">Billing / Checkout</p>
                <h1>支払い手続き</h1>
                <p>
                    契約の支払いを行うためのページです。
                    現在は決済連携前のため、Stripe Checkout連携後に実際の支払いボタンを有効化します。
                </p>
            </div>

            <aside class="checkout-status-card reveal">
                <span>Checkout</span>
                <h2><?php echo $order ? h(checkout_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])) : "-"; ?></h2>
                <p><?php echo $order ? h(checkout_payment_label((string)$order["payment_status"])) : "表示できません"; ?></p>
            </aside>
        </div>
    </section>

    <section class="section checkout-section">
        <div class="container">
            <div class="checkout-toolbar">
                <a href="/billing/" class="back-button">請求・支払いへ戻る</a>

                <?php if ($order): ?>
                    <a href="/billing/invoice/?order_id=<?php echo h((string)$order["id"]); ?>" class="sub-button">請求詳細へ</a>
                    <a href="/dashboard/servers/detail/?id=<?php echo h((string)$order["id"]); ?>" class="sub-button">契約詳細へ</a>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="checkout-flash checkout-flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="checkout-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="checkout-grid reveal">
                    <section class="checkout-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Target Contract</p>
                                <h2>支払い対象</h2>
                            </div>
                        </div>

                        <dl class="checkout-detail-list">
                            <div>
                                <dt>契約ID</dt>
                                <dd>#<?php echo h((string)$order["id"]); ?></dd>
                            </div>

                            <div>
                                <dt>サーバー名</dt>
                                <dd><?php echo h((string)($order["server_name"] ?: "名称未設定")); ?></dd>
                            </div>

                            <div>
                                <dt>プラン</dt>
                                <dd><?php echo h((string)$order["plan_name"]); ?></dd>
                            </div>

                            <div>
                                <dt>スペック</dt>
                                <dd>
                                    <?php echo h(checkout_memory_label((int)$order["memory_mb"])); ?>
                                    /
                                    <?php echo h(checkout_cpu_label((int)$order["cpu_limit"])); ?>
                                </dd>
                            </div>

                            <div>
                                <dt>契約状態</dt>
                                <dd><?php echo h(checkout_status_label((string)$order["status"])); ?></dd>
                            </div>

                            <div>
                                <dt>支払い状態</dt>
                                <dd><?php echo h(checkout_payment_label((string)$order["payment_status"])); ?></dd>
                            </div>

                            <div>
                                <dt>申込日時</dt>
                                <dd><?php echo h(checkout_datetime((string)$order["created_at"])); ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="checkout-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Payment</p>
                                <h2>支払い</h2>
                            </div>
                        </div>

                        <div class="checkout-box <?php echo $isAlreadyPaid ? 'is-paid' : ''; ?>">
                            <div class="checkout-icon">payments</div>

                            <?php if ($isAlreadyPaid): ?>
                                <h3>この契約は支払い済みです</h3>
                                <p>
                                    現在この契約は支払い済みとして記録されています。
                                    請求詳細または契約詳細から状態を確認できます。
                                </p>

                                <a href="/billing/invoice/?order_id=<?php echo h((string)$order["id"]); ?>" class="checkout-main-link">
                                    請求詳細を見る
                                </a>
                            <?php else: ?>
                                <h3>Stripeでオンライン支払い</h3>
                                <p>
                                    Stripeの安全な決済ページへ移動します。
                                    カード情報はStripe上で安全に処理されます。
                                </p>

                                <form method="post" action="/billing/checkout/create" class="checkout-start-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                    <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                                    <button type="submit">
                                        Stripeで支払いへ進む
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="checkout-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Flow</p>
                                <h2>今後の支払いフロー</h2>
                            </div>
                        </div>

                        <div class="checkout-flow">
                            <article>
                                <span>1</span>
                                <strong>支払いページ作成</strong>
                                <p>契約IDをもとにStripe Checkout Sessionを作成します。</p>
                            </article>

                            <article>
                                <span>2</span>
                                <strong>Stripeで支払い</strong>
                                <p>カードなどの支払い方法をStripe側で入力します。</p>
                            </article>

                            <article>
                                <span>3</span>
                                <strong>Webhookで反映</strong>
                                <p>支払い完了イベントを受け取り、契約の支払い状態を更新します。</p>
                            </article>

                            <article>
                                <span>4</span>
                                <strong>サーバー作成へ</strong>
                                <p>支払い完了後、サーバー作成処理へ進みます。</p>
                            </article>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script>
(() => {
    const shouldAutoStart = <?php echo ($order && !$isAlreadyPaid && $autoStartCheckout) ? "true" : "false"; ?>;

    if (!shouldAutoStart) {
        return;
    }

    const form = document.querySelector(".checkout-start-form");

    if (!form) {
        return;
    }

    setTimeout(() => {
        form.submit();
    }, 500);
})();
</script>

</body>
</html>
