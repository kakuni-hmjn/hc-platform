<?php
session_start();

require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";

$currentUser = current_user();

if (!$currentUser) {
    header("Location: /login/?redirect=/dashboard/");
    exit;
}

$pageTitle = "マイページ | HC Platform";
$pageDescription = "HC Platformのマイページです。アカウント情報、契約中サーバー、注文、請求情報などを確認できます。";
$pageCss = "/dashboard/dashboard.css";

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="dashboard-page">

    <section class="dashboard-hero">
        <div class="container dashboard-hero-grid">

            <div class="dashboard-copy reveal">
                <p class="eyebrow">Dashboard</p>
                <h1>マイページ</h1>
                <p>
                    HC Platformで利用中のサービス、契約中サーバー、注文状況、アカウント情報を確認できます。
                </p>
            </div>

            <aside class="dashboard-status-card reveal">
                <span>HC Account</span>
                <h2><?php echo h($currentUser["username"] ?? "User"); ?></h2>
                <p>
                    ログイン中のアカウントで利用中のサービス情報を表示しています。
                </p>
            </aside>

        </div>
    </section>

    <section class="section dashboard-section">
        <div class="container">

            <div class="dashboard-panel reveal">

                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Menu</p>
                        <h2>管理メニュー</h2>
                    </div>
                </div>

                <div class="dashboard-action-grid">

                    <a href="/dashboard/servers/" class="dashboard-action-card reveal">
                        <span>Game Servers</span>
                        <h3>契約中サーバー</h3>
                        <p>
                            ゲームサーバーレンタルで作成されたサーバー、申込状況、契約状態を確認できます。
                        </p>
                    </a>

                    <a href="/account/" class="dashboard-action-card reveal">
                        <span>Account</span>
                        <h3>アカウント情報</h3>
                        <p>
                            ユーザー名、メールアドレス、ログイン情報など、HC Accountの情報を確認します。
                        </p>
                    </a>

                    <a href="/billing/" class="dashboard-action-card reveal">
                        <span>Billing</span>
                        <h3>請求・支払い</h3>
                        <p>
                            支払い状況、請求情報、契約の更新状況などを確認します。
                        </p>
                    </a>

                    <a href="/order/" class="dashboard-action-card reveal">
                        <span>Order</span>
                        <h3>サービス申込</h3>
                        <p>
                            ゲームサーバーや各種レンタルサービスの申し込みページへ移動します。
                        </p>
                    </a>

                    <a href="/services/" class="dashboard-action-card reveal">
                        <span>Services</span>
                        <h3>サービス一覧</h3>
                        <p>
                            HC Platformで提供予定・開発中のサービス一覧を確認できます。
                        </p>
                    </a>

                    <a href="/contact/" class="dashboard-action-card reveal">
                        <span>Support</span>
                        <h3>お問い合わせ</h3>
                        <p>
                            サービス利用中の相談、サーバー構成の相談、サポート依頼はこちらから送信できます。
                        </p>
                    </a>

                </div>

            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>