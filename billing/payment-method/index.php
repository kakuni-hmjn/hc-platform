<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = require_login();

$pageTitle = "支払い方法変更 | HC Platform";
$pageDescription = "HC Platformの支払い方法変更ページです。";
$pageCss = "/billing/payment-method/payment-method.css";

$pdo = db();

$orderId = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$order = null;
$errors = [];

function pm_status_label(string $status): string
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

function pm_payment_label(string $status): string
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

function pm_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = $amount ?: $fallbackAmount ?: 0;
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function pm_datetime(?string $value): string
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
                gso.plan_id,
                gso.server_name,
                gso.status,
                gso.payment_status,
                gso.amount,
                gso.currency,
                gso.billing_type,
                gso.created_at,
                gso.auto_renew_cancelled,

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

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="payment-method-page">
    <section class="payment-method-hero">
        <div class="container payment-method-hero-grid">
            <div class="payment-method-copy reveal">
                <p class="eyebrow">Billing / Payment Method</p>
                <h1>支払い方法変更</h1>
                <p>
                    このページでは契約ごとの支払い方法を変更できるようにする予定です。
                    現在は決済連携前のため、変更機能は準備中です。
                </p>
            </div>

            <aside class="payment-method-status-card reveal">
                <span>Payment Method</span>
                <h2>準備中</h2>
                <p>Stripe連携後に有効化予定です。</p>
            </aside>
        </div>
    </section>

    <section class="section payment-method-section">
        <div class="container">
            <div class="payment-method-toolbar">
                <a href="/billing/" class="back-button">請求・支払いへ戻る</a>
                <?php if ($order): ?>
                    <a href="/dashboard/servers/detail/?id=<?php echo h((string)$order["id"]); ?>" class="sub-button">
                        契約詳細へ
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($errors): ?>
                <div class="payment-method-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="payment-method-grid reveal">
                    <section class="payment-method-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Contract</p>
                                <h2>対象契約</h2>
                            </div>
                        </div>

                        <dl class="contract-detail-list">
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
                                <dt>月額料金</dt>
                                <dd>
                                    <?php echo h(pm_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?>
                                </dd>
                            </div>

                            <div>
                                <dt>契約状態</dt>
                                <dd><?php echo h(pm_status_label((string)$order["status"])); ?></dd>
                            </div>

                            <div>
                                <dt>支払い状態</dt>
                                <dd><?php echo h(pm_payment_label((string)$order["payment_status"])); ?></dd>
                            </div>

                            <div>
                                <dt>申込日時</dt>
                                <dd><?php echo h(pm_datetime((string)$order["created_at"])); ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="payment-method-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Change</p>
                                <h2>変更方法</h2>
                            </div>
                        </div>

                        <div class="method-placeholder">
                            <div class="method-icon">payment</div>
                            <h3>オンライン決済連携後に利用できます</h3>
                            <p>
                                Stripe Customer Portal または Checkout連携を追加後、
                                この画面からカード変更・支払い方法変更を行えるようにします。
                            </p>

                            <button type="button" disabled>
                                支払い方法を変更する 準備中
                            </button>
                        </div>
                    </section>

                    <section class="payment-method-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Notice</p>
                                <h2>今後追加する予定</h2>
                            </div>
                        </div>

                        <div class="future-list">
                            <article>
                                <strong>カード変更</strong>
                                <p>登録済みカードを変更できるようにします。</p>
                            </article>

                            <article>
                                <strong>請求先情報変更</strong>
                                <p>氏名、住所、請求先メールなどを変更できるようにします。</p>
                            </article>

                            <article>
                                <strong>請求書・領収書</strong>
                                <p>決済完了後の請求書や領収書を確認できるようにします。</p>
                            </article>

                            <article>
                                <strong>自動更新管理</strong>
                                <p>契約の自動更新やキャンセル予定を確認できるようにします。</p>
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
