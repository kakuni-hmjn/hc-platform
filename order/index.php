<?php
session_start();

require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../data/site-data.php";

$user = require_login();

$pageTitle = "新規プラン契約 | HC Platform";
$pageDescription = "HC Platformのゲームサーバーレンタルプラン契約ページです。";
$pageCss = "/order/order.css";

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="order-page">

    <section class="order-hero">
        <div class="container order-grid">

            <div class="order-copy reveal">
                <p class="eyebrow">Order</p>
                <h1>新規プラン契約</h1>
                <p>
                    ゲームサーバーレンタルのプランを選択できます。
                    現在はサービス公開準備中のため、申し込み・決済機能はまだ有効化していません。
                </p>
            </div>

            <aside class="order-summary-card reveal">
                <div class="summary-head">
                    <div>
                        <span>HC Account</span>
                        <h2>契約者情報</h2>
                    </div>
                    <strong>ログイン中</strong>
                </div>

                <div class="summary-list">
                    <div>
                        <span>ユーザー名</span>
                        <strong><?php echo h($user["username"]); ?></strong>
                    </div>

                    <div>
                        <span>メールアドレス</span>
                        <strong><?php echo h($user["email"]); ?></strong>
                    </div>

                    <div>
                        <span>アカウント状態</span>
                        <strong><?php echo h($user["status"]); ?></strong>
                    </div>
                </div>

                <a href="/billing/" class="summary-button">契約・購入履歴を見る</a>
            </aside>

        </div>
    </section>

    <section class="section order-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Plans</p>
                <h2>ゲームサーバー向けプラン</h2>
                <p>
                    正式提供前の予定プランです。サービス開始時には、構成や価格が変更される場合があります。
                </p>
            </div>

            <div class="order-plan-grid">
                <?php foreach ($plans as $index => $plan): ?>
                    <article class="order-plan-card <?php echo isset($plan["type"]) && $plan["type"] === "dedicated" ? "dedicated-order-plan" : ""; ?> reveal">
                        <div class="plan-head">
                            <span><?php echo h($plan["tag"]); ?></span>

                            <?php if ($index === 1): ?>
                                <small>おすすめ</small>
                            <?php endif; ?>
                        </div>

                        <h3><?php echo h($plan["name"]); ?></h3>

                        <p class="plan-price">
                            <?php echo h($plan["price"]); ?>
                            <?php if ($plan["price"] !== "要相談"): ?>
                                <span>/ 月</span>
                            <?php endif; ?>
                        </p>

                        <p class="plan-spec"><?php echo h($plan["spec"]); ?></p>
                        <p class="plan-desc"><?php echo h($plan["desc"]); ?></p>

                        <?php if (isset($plan["type"]) && $plan["type"] === "dedicated"): ?>
                            <button type="button" class="plan-button disabled" disabled>問い合わせ準備中</button>
                        <?php else: ?>
                            <button type="button" class="plan-button disabled" disabled>申し込み準備中</button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section order-notice-section">
        <div class="container">
            <div class="order-notice reveal">
                <div>
                    <p class="eyebrow">Payment</p>
                    <h2>決済機能は準備中です</h2>
                    <p>
                        今後、プラン選択後に決済へ進み、支払い完了後にPterodactyl側のアカウント作成・サーバー作成へ進む流れにします。
                    </p>
                </div>

                <a href="/dashboard/" class="notice-button">ダッシュボードへ戻る</a>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/order/order.js"></script>
</body>
</html>