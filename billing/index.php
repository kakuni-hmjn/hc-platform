<?php
session_start();

require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/helpers.php";

$user = require_login();

$pageTitle = "契約・購入履歴 | HC Platform";
$pageDescription = "HC Accountの契約状況・購入履歴ページです。";
$pageCss = "/billing/billing.css";

$subscriptions = [];
$orders = [];
$payments = [];

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="billing-page">

    <section class="billing-hero">
        <div class="container billing-grid">

            <div class="billing-copy reveal">
                <p class="eyebrow">Billing</p>
                <h1>契約・購入履歴</h1>
                <p>
                    契約中のサービス、購入履歴、支払い履歴を確認できます。
                    サーバーの新規契約やプラン選択は、専用ページから行います。
                </p>
            </div>

            <aside class="billing-summary-card reveal">
                <div class="summary-head">
                    <div>
                        <span>Billing Status</span>
                        <h2>現在の契約状況</h2>
                    </div>
                    <strong>準備中</strong>
                </div>

                <div class="billing-stats">
                    <div>
                        <span>契約中サービス</span>
                        <strong><?php echo count($subscriptions); ?></strong>
                    </div>
                    <div>
                        <span>購入履歴</span>
                        <strong><?php echo count($orders); ?></strong>
                    </div>
                    <div>
                        <span>支払い履歴</span>
                        <strong><?php echo count($payments); ?></strong>
                    </div>
                </div>

                <a href="/order/" class="billing-main-button">新規プラン契約へ</a>
            </aside>

        </div>
    </section>

    <section class="section billing-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Subscriptions</p>
                <h2>契約中サービス</h2>
                <p>
                    現在契約中のゲームサーバープランがここに表示されます。
                </p>
            </div>

            <?php if ($subscriptions): ?>
                <div class="billing-table-wrap reveal">
                    <table class="billing-table">
                        <thead>
                            <tr>
                                <th>サービス</th>
                                <th>プラン</th>
                                <th>状態</th>
                                <th>次回更新</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $subscription): ?>
                                <tr>
                                    <td><?php echo h($subscription["service_name"]); ?></td>
                                    <td><?php echo h($subscription["plan_name"]); ?></td>
                                    <td><?php echo h($subscription["status"]); ?></td>
                                    <td><?php echo h($subscription["next_billing_at"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-card reveal">
                    <span>0</span>
                    <h3>契約中のサービスはありません</h3>
                    <p>
                        現在契約中のサービスはありません。
                        サービス開始後、プランを契約するとここに表示されます。
                    </p>
                    <a href="/order/">プランを見る</a>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <section class="section billing-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Orders</p>
                <h2>購入履歴</h2>
                <p>
                    過去に申し込んだプランや注文情報を確認できます。
                </p>
            </div>

            <?php if ($orders): ?>
                <div class="billing-table-wrap reveal">
                    <table class="billing-table">
                        <thead>
                            <tr>
                                <th>注文番号</th>
                                <th>内容</th>
                                <th>金額</th>
                                <th>状態</th>
                                <th>注文日</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo h($order["order_code"]); ?></td>
                                    <td><?php echo h($order["item_name"]); ?></td>
                                    <td><?php echo h($order["amount"]); ?></td>
                                    <td><?php echo h($order["status"]); ?></td>
                                    <td><?php echo h($order["created_at"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-card reveal">
                    <span>0</span>
                    <h3>購入履歴はまだありません</h3>
                    <p>
                        まだ購入履歴はありません。
                        今後、プラン契約や支払いが完了するとここに表示されます。
                    </p>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <section class="section billing-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Payments</p>
                <h2>支払い履歴</h2>
                <p>
                    決済完了済みの支払い情報を確認できます。
                </p>
            </div>

            <?php if ($payments): ?>
                <div class="billing-table-wrap reveal">
                    <table class="billing-table">
                        <thead>
                            <tr>
                                <th>決済ID</th>
                                <th>金額</th>
                                <th>決済方法</th>
                                <th>状態</th>
                                <th>支払い日</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo h($payment["payment_code"]); ?></td>
                                    <td><?php echo h($payment["amount"]); ?></td>
                                    <td><?php echo h($payment["method"]); ?></td>
                                    <td><?php echo h($payment["status"]); ?></td>
                                    <td><?php echo h($payment["paid_at"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-card reveal">
                    <span>0</span>
                    <h3>支払い履歴はまだありません</h3>
                    <p>
                        支払いが完了すると、ここに決済履歴が表示されます。
                    </p>
                </div>
            <?php endif; ?>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/billing/billing.js"></script>
</body>
</html>