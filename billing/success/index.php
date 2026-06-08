<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/stripe.php";

$currentUser = require_login();

$pageTitle = "支払い完了 | HC Platform";
$pageDescription = "HC Platformの支払い完了ページです。";
$pageCss = "/billing/result/result.css";

$pdo = db();

$orderId = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$sessionId = trim((string)($_GET["session_id"] ?? ""));

$order = null;
$errors = [];
$successMessage = "";

function br_status_label(string $status): string
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

function br_payment_label(string $status): string
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

function br_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = (int)($amount ?: $fallbackAmount ?: 0);
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function br_datetime(?string $value): string
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

function br_success_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_events (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NULL REFERENCES game_server_orders(id) ON DELETE SET NULL,
            user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            event_type VARCHAR(120) NOT NULL,
            payment_status VARCHAR(60),
            amount INTEGER,
            currency VARCHAR(12) NOT NULL DEFAULT 'jpy',
            provider VARCHAR(40) NOT NULL DEFAULT 'stripe',
            provider_event_id VARCHAR(180) UNIQUE,
            provider_object_id VARCHAR(180),
            message TEXT,
            raw_payload JSONB,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        ALTER TABLE game_server_orders
        ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(160),
        ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(160),
        ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(160)
    ");
}

function br_success_insert_payment_event(PDO $pdo, array $order, array $session): void
{
    try {
        $providerEventId = "success_page_" . (string)$session["id"];

        $stmt = $pdo->prepare("
            INSERT INTO payment_events
            (
                order_id,
                user_id,
                event_type,
                payment_status,
                amount,
                currency,
                provider,
                provider_event_id,
                provider_object_id,
                message,
                raw_payload,
                created_at
            )
            VALUES
            (
                :order_id,
                :user_id,
                'checkout.session.completed.success_page',
                :payment_status,
                :amount,
                :currency,
                'stripe',
                :provider_event_id,
                :provider_object_id,
                :message,
                :raw_payload,
                NOW()
            )
            ON CONFLICT (provider_event_id) DO NOTHING
        ");

        $stmt->execute([
            "order_id" => (int)$order["id"],
            "user_id" => (int)$order["user_id"],
            "payment_status" => (string)($session["payment_status"] ?? ""),
            "amount" => isset($session["amount_total"]) ? (int)$session["amount_total"] : null,
            "currency" => strtolower((string)($session["currency"] ?? "jpy")),
            "provider_event_id" => $providerEventId,
            "provider_object_id" => (string)$session["id"],
            "message" => "Success page fallback confirmation: " . (string)$session["id"],
            "raw_payload" => json_encode($session, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
    }
}

function br_success_insert_order_event(
    PDO $pdo,
    int $orderId,
    ?string $oldStatus,
    ?string $newStatus,
    ?string $oldPaymentStatus,
    ?string $newPaymentStatus,
    string $message
): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO server_order_events
            (
                order_id,
                actor_user_id,
                event_type,
                title,
                message,
                old_status,
                new_status,
                old_payment_status,
                new_payment_status,
                created_at
            )
            VALUES
            (
                :order_id,
                NULL,
                'payment_paid_success_page',
                '支払い完了を確認しました',
                :message,
                :old_status,
                :new_status,
                :old_payment_status,
                :new_payment_status,
                NOW()
            )
        ");

        $stmt->execute([
            "order_id" => $orderId,
            "message" => $message,
            "old_status" => $oldStatus,
            "new_status" => $newStatus,
            "old_payment_status" => $oldPaymentStatus,
            "new_payment_status" => $newPaymentStatus,
        ]);
    } catch (Throwable $e) {
    }
}

function br_success_fetch_order(PDO $pdo, int $orderId, int $userId): ?array
{
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
            gso.stripe_checkout_session_id,
            gso.stripe_subscription_id,
            gso.stripe_customer_id,

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
        "user_id" => $userId,
    ]);

    $order = $stmt->fetch();

    return $order ?: null;
}

if (!$orderId) {
    $errors[] = "契約IDが指定されていません。";
} else {
    try {
        br_success_ensure_schema($pdo);

        $order = br_success_fetch_order($pdo, $orderId, (int)$currentUser["id"]);

        if (!$order) {
            $errors[] = "契約が見つからないか、表示権限がありません。";
        }

        if ($order && $sessionId !== "") {
            $session = hc_stripe_retrieve_checkout_session($sessionId);

            $sessionOrderId = (string)($session["metadata"]["hc_order_id"] ?? $session["client_reference_id"] ?? "");
            $sessionUserId = (string)($session["metadata"]["hc_user_id"] ?? "");
            $sessionStatus = (string)($session["status"] ?? "");
            $sessionPaymentStatus = (string)($session["payment_status"] ?? "");
            $subscriptionId = isset($session["subscription"]) ? (string)$session["subscription"] : null;
            $customerId = isset($session["customer"]) ? (string)$session["customer"] : null;

            if ($sessionOrderId !== (string)$order["id"]) {
                throw new RuntimeException("Checkout Sessionの契約IDが一致しません。");
            }

            if ($sessionUserId !== "" && $sessionUserId !== (string)$currentUser["id"]) {
                throw new RuntimeException("Checkout SessionのユーザーIDが一致しません。");
            }

            if ($sessionStatus === "complete" || $sessionPaymentStatus === "paid") {
                $oldStatus = (string)$order["status"];
                $oldPaymentStatus = (string)$order["payment_status"];

                $newStatus = $oldStatus;
                if (in_array($oldStatus, ["pending_payment", "paid"], true)) {
                    $newStatus = "paid";
                }

                $newPaymentStatus = "paid";

                $stmt = $pdo->prepare("
                    UPDATE game_server_orders
                    SET
                        status = :status,
                        payment_status = :payment_status,
                        stripe_checkout_session_id = COALESCE(:stripe_checkout_session_id, stripe_checkout_session_id),
                        stripe_subscription_id = COALESCE(:stripe_subscription_id, stripe_subscription_id),
                        stripe_customer_id = COALESCE(:stripe_customer_id, stripe_customer_id)
                    WHERE id = :id
                      AND user_id = :user_id
                ");

                $stmt->execute([
                    "id" => (int)$order["id"],
                    "user_id" => (int)$currentUser["id"],
                    "status" => $newStatus,
                    "payment_status" => $newPaymentStatus,
                    "stripe_checkout_session_id" => $sessionId,
                    "stripe_subscription_id" => $subscriptionId ?: null,
                    "stripe_customer_id" => $customerId ?: null,
                ]);

                br_success_insert_payment_event($pdo, $order, $session);
                br_success_insert_order_event(
                    $pdo,
                    (int)$order["id"],
                    $oldStatus,
                    $newStatus,
                    $oldPaymentStatus,
                    $newPaymentStatus,
                    "Success page fallback confirmation\nCheckout Session: " . $sessionId . "\nSubscription: " . ($subscriptionId ?: "-") . "\nCustomer: " . ($customerId ?: "-")
                );

                $successMessage = "支払い完了を確認し、契約に反映しました。";
                $order = br_success_fetch_order($pdo, $orderId, (int)$currentUser["id"]);
            } else {
                $errors[] = "Stripe上ではまだ支払い完了を確認できませんでした。Webhook反映を待つか、請求ページを確認してください。";
            }
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body class="result-success">
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="billing-result-page">
    <section class="billing-result-hero">
        <div class="container billing-result-hero-grid">
            <div class="billing-result-copy reveal">
                <p class="eyebrow">Billing / Success</p>
                <h1>支払い完了</h1>
                <p>
                    Stripe Checkoutの支払い完了ページです。
                    Webhookと成功ページ確認の両方で、契約への反映を行います。
                </p>
            </div>

            <aside class="billing-result-status-card reveal">
                <span>Payment Result</span>
                <h2><?php echo $order ? h(br_payment_label((string)$order["payment_status"])) : "完了"; ?></h2>
                <p>
                    <?php if ($order): ?>
                        契約 #<?php echo h((string)$order["id"]); ?>
                    <?php else: ?>
                        支払い結果ページ
                    <?php endif; ?>
                </p>
            </aside>
        </div>
    </section>

    <section class="section billing-result-section">
        <div class="container">
            <div class="billing-result-toolbar">
                <a href="/billing/" class="back-button">請求・支払いへ戻る</a>

                <?php if ($order): ?>
                    <a href="/dashboard/servers/detail/?id=<?php echo h((string)$order["id"]); ?>" class="sub-button">契約詳細へ</a>
                    <a href="/billing/invoice/?order_id=<?php echo h((string)$order["id"]); ?>" class="sub-button">請求詳細へ</a>
                <?php endif; ?>
            </div>

            <?php if ($successMessage !== ""): ?>
                <div class="billing-result-success">
                    <?php echo h($successMessage); ?>
                </div>
            <?php endif; ?>

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
                            <div class="result-icon">check_circle</div>
                            <h3>支払い結果を確認しました</h3>
                            <p>
                                契約の支払い状態を確認しました。
                                反映に時間がかかる場合は、少し待ってから契約詳細を開き直してください。
                            </p>

                            <div class="result-actions">
                                <a href="/dashboard/servers/detail/?id=<?php echo h((string)$order["id"]); ?>" class="primary-action">
                                    契約詳細を見る
                                </a>

                                <a href="/billing/" class="secondary-action">
                                    請求・支払いを見る
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
                                <dd><?php echo h(br_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?></dd>
                            </div>

                            <div>
                                <dt>契約状態</dt>
                                <dd><?php echo h(br_status_label((string)$order["status"])); ?></dd>
                            </div>

                            <div>
                                <dt>支払い状態</dt>
                                <dd><?php echo h(br_payment_label((string)$order["payment_status"])); ?></dd>
                            </div>

                            <div>
                                <dt>申込日時</dt>
                                <dd><?php echo h(br_datetime((string)$order["created_at"])); ?></dd>
                            </div>
                        </dl>
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
