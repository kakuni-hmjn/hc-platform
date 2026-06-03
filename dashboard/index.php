<?php
session_start();

require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/db.php";

$user = require_login();

$pageTitle = "ダッシュボード | HC Platform";
$pageDescription = "HC Accountのダッシュボードです。";
$pageCss = "/dashboard/dashboard.css";

$stats = [
    "subscriptions" => 0,
    "orders" => 0,
    "servers" => 0,
];

try {
    $pdo = db();

    /*
     * 契約・注文・サーバー系テーブルは後で作る予定。
     * 今は存在しない可能性があるため、固定値0で表示します。
     */
} catch (Throwable $e) {
    // ダッシュボード表示自体は止めない
}

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="dashboard-page">

    <section class="dashboard-hero">
        <div class="container dashboard-grid">

            <div class="dashboard-copy reveal">
                <p class="eyebrow">HC Account</p>
                    <h1 class="title-stack">
                        <span>アカウント</span>
                        <span>ダッシュボード</span>
                    </h1>
                <p>
                    ようこそ、<?php echo h($user["username"]); ?> さん。
                    ここではプロフィール情報、アカウント状態、契約状況へのリンクを確認できます。
                </p>
            </div>

            <aside class="account-summary-card reveal">
                <div class="summary-head">
                    <div>
                        <span>Account Status</span>
                        <h2>アカウント状態</h2>
                    </div>

                    <?php if ($user["status"] === "active"): ?>
                        <strong class="status-badge active">有効</strong>
                    <?php else: ?>
                        <strong class="status-badge inactive">停止中</strong>
                    <?php endif; ?>
                </div>

                <div class="summary-user">
                    <div class="avatar">
                        <?php echo h(mb_substr($user["username"], 0, 1)); ?>
                    </div>

                    <div>
                        <h3><?php echo h($user["username"]); ?></h3>
                        <p><?php echo h($user["email"]); ?></p>
                    </div>
                </div>

                <div class="summary-list">
                    <div>
                        <span>メール認証</span>
                        <strong>
                            <?php echo !empty($user["email_verified"]) ? "認証済み" : "未認証"; ?>
                        </strong>
                    </div>

                    <div>
                        <span>権限</span>
                        <strong><?php echo h($user["role"]); ?></strong>
                    </div>

                    <div>
                        <span>登録日</span>
                        <strong>
                            <?php echo h(date("Y/m/d", strtotime($user["created_at"]))); ?>
                        </strong>
                    </div>

                    <div>
                        <span>最終ログイン</span>
                        <strong>
                            <?php echo !empty($user["last_login"]) ? h(date("Y/m/d H:i", strtotime($user["last_login"]))) : "未記録"; ?>
                        </strong>
                    </div>
                </div>

                <a href="/account/" class="summary-button">プロフィール設定へ</a>
            </aside>

        </div>
    </section>

    <section class="section dashboard-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Overview</p>
                <h2>アカウント関連メニュー</h2>
                <p>
                    サーバー契約や購入履歴は専用ページで管理します。
                    ダッシュボードでは、各ページへの入口と現在の状態を確認できます。
                </p>
            </div>

            <div class="dashboard-stats">
                <article class="stat-card reveal">
                    <span>契約中サービス</span>
                    <strong><?php echo h((string)$stats["subscriptions"]); ?></strong>
                    <p>現在契約中のサービス数</p>
                </article>

                <article class="stat-card reveal">
                    <span>購入履歴</span>
                    <strong><?php echo h((string)$stats["orders"]); ?></strong>
                    <p>注文・購入履歴の件数</p>
                </article>

                <article class="stat-card reveal">
                    <span>サーバー</span>
                    <strong><?php echo h((string)$stats["servers"]); ?></strong>
                    <p>作成済みサーバー数</p>
                </article>
            </div>

            <div class="dashboard-menu">
                <a href="/account/" class="menu-card reveal">
                    <span>01</span>
                    <h3>プロフィール設定</h3>
                    <p>ユーザー名、メールアドレス、パスワードなどのアカウント情報を管理します。</p>
                </a>

                <a href="/billing/" class="menu-card reveal">
                    <span>02</span>
                    <h3>契約・購入履歴</h3>
                    <p>契約中のサービス、注文履歴、支払い履歴を確認できるページです。</p>
                </a>

                <a href="/order/" class="menu-card reveal">
                    <span>03</span>
                    <h3>新規プラン契約</h3>
                    <p>ゲームサーバーレンタルのプラン選択・申し込みを行うページです。</p>
                </a>

                <a href="#" class="menu-card reveal">
                    <span>04</span>
                    <h3>ゲームサーバー管理パネル</h3>
                    <p>契約後のサーバー操作、コンソール、ファイル管理は専用管理パネルで行えます。</p>
                </a>
            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/dashboard/dashboard.js"></script>
</body>
</html>