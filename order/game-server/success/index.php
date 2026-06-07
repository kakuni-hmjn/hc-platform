<?php
session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/stripe.php";
require_once __DIR__ . "/../../../lib/game_server_payment.php";

$currentUser = current_user();

if (!$currentUser) {
    header("Location: /login/?redirect=/order/game-server/");
    exit;
}

$pageTitle = "申込完了 | HC Platform";
$pageDescription = "ゲームサーバーレンタルの申込が完了しました。";
$pageCss = "/order/game-server/order.css";

$pdo = db();
$config = stripe_config();

$order = null;
$debugMessages = [];
$message = "決済が完了しました。サーバー作成処理を進めています。";

$orderId = isset($_GET["order_id"]) ? (int)$_GET["order_id"] : 0;
$sessionId = isset($_GET["session_id"]) ? trim($_GET["session_id"]) : "";
$isMock = isset($_GET["mock"]) && $_GET["mock"] === "1";

try {
    if ($isMock && !empty($config["mock"]) && $orderId > 0) {
        $order = get_game_server_order_by_id($pdo, $orderId);

        if (!$order) {
            $message = "注文情報が見つかりませんでした。";
            $debugMessages[] = "order_id={$orderId} の注文が見つかりません。";
        } elseif ((int)$order["user_id"] !== (int)$currentUser["id"]) {
            $message = "この注文を確認する権限がありません。";
            $debugMessages[] = "ログインユーザーと注文ユーザーが一致しません。";
        } else {
            $sessionForMark = (string)$order["stripe_checkout_session_id"];

            $marked = mark_game_server_order_paid_by_session($pdo, $sessionForMark, [
                "customer" => "cus_mock_" . $order["user_id"],
                "subscription" => $order["billing_type"] === "auto_subscription" ? "sub_mock_" . $order["id"] : null,
                "payment_intent" => $order["billing_type"] === "manual_renewal" ? "pi_mock_" . $order["id"] : null,
            ]);

            if ($marked) {
                $order = get_game_server_order_by_id($pdo, $orderId);
                $message = "Mock決済が完了しました。注文ステータスを更新しました。";
            } else {
                $message = "Mock決済の更新に失敗しました。";
                $debugMessages[] = "mark_game_server_order_paid_by_session が false を返しました。";
            }
        }
    } elseif ($sessionId !== "") {
        $message = "決済が完了しました。Webhookで注文情報を更新します。";
    } else {
        $message = "申込完了ページを表示しています。注文IDまたはセッションIDがありません。";
    }
} catch (Throwable $e) {
    $message = "申込完了処理中にエラーが発生しました。";
    $debugMessages[] = $e->getMessage();
}

require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="order-page">
    <section class="order-hero">
        <div class="container">
            <div class="order-panel reveal">
                <p class="eyebrow">Success</p>
                <h1>申込を受け付けました</h1>
                <p><?php echo h($message); ?></p>

                <?php if ($order): ?>
                    <div class="success-summary">
                        <div>
                            <span>注文ID</span>
                            <strong>#<?php echo h((string)$order["id"]); ?></strong>
                        </div>

                        <div>
                            <span>プラン</span>
                            <strong><?php echo h((string)$order["plan_name"]); ?></strong>
                        </div>

                        <div>
                            <span>サーバー名</span>
                            <strong><?php echo h((string)$order["server_name"]); ?></strong>
                        </div>

                        <div>
                            <span>状態</span>
                            <strong>
                                <?php echo h((string)$order["status"]); ?>
                                /
                                <?php echo h((string)$order["payment_status"]); ?>
                            </strong>
                        </div>

                        <?php if (!empty($order["stripe_checkout_session_id"])): ?>
                            <div>
                                <span>Checkout Session</span>
                                <strong><?php echo h((string)$order["stripe_checkout_session_id"]); ?></strong>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($order["provision_error"])): ?>
                            <div>
                                <span>作成エラー</span>
                                <strong><?php echo h((string)$order["provision_error"]); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($debugMessages): ?>
                    <div class="order-alert">
                        <?php foreach ($debugMessages as $debug): ?>
                            <p><?php echo h($debug); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="submit-row">
                    <a href="/dashboard/" class="submit-button">マイページへ</a>
                    <a href="/services/rental/game-server/" class="back-button">プランへ戻る</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>