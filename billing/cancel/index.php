<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = require_login();

$pageTitle = "支払いキャンセル | HC Platform";
$pageDescription = "HC Platformの支払いキャンセルページです。";
$pageCss = "/billing/result/result.css";

$pdo = db();

$orderId = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$order = null;
$errors = [];

function brc_status_label(string $status): string
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

function brc_payment_label(string $status): string
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

function brc_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = (int)($amount ?: $fallbackAmount ?: 0);
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function brc_datetime(?string $value): string
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

if (!$orderId) {
    $errors[] = "契約IDが指定されていません。";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                gso.id,
                gso.user_id,
                gso.server_name,
                gso.status,
                gso.payment_status,
                gso.amount,
                gso.currency,
                gso.billing_type,
                gso.created_at,

                gsp.name AS plan_name,
                gsp.price_monthly
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

require_once __DIR__ . "/../../parts/head.php";
?>

<body class="result-cancel">
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="billing-result-page">
    <section class="billing-result-hero">
        <div class="container billing-result-hero-grid">
            <div class="billing-result-copy reveal">
                <p class="eyebrow">Billing / Cancel</p>
                <h1>支払いキャンセル</h1>
                <p>
                    支払い手続きがキャンセルされた場合に表示するページです。
                    もう一度支払いへ進むか、請求・支払いページへ戻れます。
                </p>
            </div>

            <aside class="billing-result-status-card reveal">
                <span>Payment Result</span>
                <h2>キャンセル</h2>
                <p>支払いは完了していません。</p>
            </aside>
        </div>
    </section>

    <section class="section billing-result-section">
        <div class="container">
            <div class="billing-result-toolbar">
                <a href="/billing/" class="back-button">請求・支払いへ戻る</a>

                <?php if ($order): ?>
                    <a href="/billing/checkout/?order_id=<?php echo h((string)$order["id"]); ?>" class="sub-button">もう一度支払いへ</a>
                    <a href="/dashboard/servers/detail/?id=<?php echo h((string)$order["id"]); ?>" class="sub-button">契約詳細へ</a>
                <?php endif; ?>
            </div>

            <?php if ($errors): ?>
                <div class="billing-result-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="billing-result-grid reveal">
                    <section class="billing-result-panel">
                        <div class="result-box">
                            <div class="result-icon">warning</div>
                            <h3>支払いは完了していません</h3>
                            <p>
                                支払いページを閉じた、またはキャンセルした可能性があります。
                                必要な場合はもう一度支払い手続きを行ってください。
                            </p>

                            <div class="result-actions">
                                <a href="/billing/checkout/?order_id=<?php echo h((string)$order["id"]); ?>" class="retry-action">
                                    もう一度支払いへ進む
                                </a>

                                <a href="/billing/" class="secondary-action">
                                    請求・支払いへ戻る
                                </a>
                            </div>
                        </div>
                    </section>

                    <section class="billing-result-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Contract</p>
                                <h2>対象契約</h2>
                            </div>
                        </div>

                        <dl class="result-detail-list">
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
                                <dt>金額</dt>
                                <dd><?php echo h(brc_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?></dd>
                            </div>

                            <div>
                                <dt>契約状態</dt>
                                <dd><?php echo h(brc_status_label((string)$order["status"])); ?></dd>
                            </div>

                            <div>
                                <dt>支払い状態</dt>
                                <dd><?php echo h(brc_payment_label((string)$order["payment_status"])); ?></dd>
                            </div>

                            <div>
                                <dt>申込日時</dt>
                                <dd><?php echo h(brc_datetime((string)$order["created_at"])); ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="billing-result-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Next</p>
                                <h2>次にできること</h2>
                            </div>
                        </div>

                        <div class="next-flow">
                            <article>
                                <span>1</span>
                                <strong>再支払い</strong>
                                <p>もう一度支払いページへ進みます。</p>
                            </article>

                            <article>
                                <span>2</span>
                                <strong>契約確認</strong>
                                <p>契約詳細からサーバー名や申込内容を確認できます。</p>
                            </article>

                            <article>
                                <span>3</span>
                                <strong>問い合わせ</strong>
                                <p>支払いで問題がある場合はお問い合わせから相談できます。</p>
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
</body>
</html>
