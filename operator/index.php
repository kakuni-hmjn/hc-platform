<?php
$pageTitle = "運営情報 | HC Platform";
$pageDescription = "HC Platformの運営情報ページです。運営名、サービス状況、公開準備中の内容について掲載しています。";
$pageCss = "/operator/operator.css";

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="operator-page">

    <section class="operator-hero">
        <div class="operator-hero-bg"></div>

        <div class="container">
            <div class="operator-hero-content reveal">
                <p class="eyebrow">Operator</p>
                <h1>運営情報</h1>
                <p>
                    HC Platformは、HMJn companyが運営するサービスサイトです。
                    ゲーム、制作、配信、コミュニティ活動を支えるインフラサービスとして、
                    正式公開に向けて準備を進めています。
                </p>
            </div>
        </div>
    </section>

    <section class="section operator-section">
        <div class="container operator-grid">

            <section class="operator-main-card reveal">
                <div class="operator-card-head">
                    <div>
                        <p class="eyebrow">Service</p>
                        <h2>HC Platformについて</h2>
                    </div>
                    <span>公開準備中</span>
                </div>

                <p>
                    HC Platformは、ゲームコミュニティ、クリエイター活動、配信活動などを支えるための
                    インフラサービスブランドです。まずはゲームサーバーレンタルを中心に、
                    活動の裏側で必要になる仕組みを一つずつ整えていきます。
                </p>

                <div class="operator-info-list">
                    <div>
                        <span>サイト名</span>
                        <strong>HC Platform</strong>
                    </div>

                    <div>
                        <span>運営名</span>
                        <strong>HMJn company</strong>
                    </div>

                    <div>
                        <span>提供予定サービス</span>
                        <strong>ゲームサーバーレンタル</strong>
                    </div>

                    <div>
                        <span>現在の状態</span>
                        <strong>正式公開に向けて準備中</strong>
                    </div>
                </div>
            </section>

            <aside class="operator-side reveal">
                <article class="side-card">
                    <h3>公開準備ステータス</h3>

                    <div class="status-list">
                        <div>
                            <span>HC Account</span>
                            <strong>対応済み</strong>
                        </div>
                        <div>
                            <span>メール認証</span>
                            <strong>対応済み</strong>
                        </div>
                        <div>
                            <span>プラン契約</span>
                            <strong>準備中</strong>
                        </div>
                        <div>
                            <span>決済連携</span>
                            <strong>準備中</strong>
                        </div>
                        <div>
                            <span>サーバー自動作成</span>
                            <strong>準備中</strong>
                        </div>
                    </div>
                </article>

                <article class="side-card notice-card">
                    <h3>お問い合わせについて</h3>
                    <p>
                        お問い合わせ窓口は正式公開に向けて準備中です。
                        公開後、サポート・契約・技術的な連絡先を順次掲載します。
                    </p>
                </article>
            </aside>

        </div>
    </section>

    <section class="section company-preview-section">
        <div class="container">
            <div class="company-preview-card reveal">
                <div>
                    <p class="eyebrow">HMJn company</p>
                    <h2>運営紹介ページは準備中です</h2>
                    <p>
                        HMJn companyの活動内容、運営方針、今後の展開について紹介するページを準備しています。
                        現在はHC Platformの公開準備を優先して進めています。
                    </p>
                </div>

                <a href="/company/" class="operator-button">運営紹介ページへ</a>
            </div>
        </div>
    </section>

    <section class="section roadmap-section">
        <div class="container">
            <div class="section-heading reveal">
                <p class="eyebrow">Roadmap</p>
                <h2>今後の予定</h2>
                <p>
                    機能を一つずつ整備し、正式公開に向けて準備を進めています。
                </p>
            </div>

            <div class="roadmap-grid">
                <article class="roadmap-card reveal">
                    <span>01</span>
                    <h3>アカウント基盤</h3>
                    <p>新規登録、メール認証、ログイン、パスワード再設定などの基本機能を整備。</p>
                </article>

                <article class="roadmap-card reveal">
                    <span>02</span>
                    <h3>契約・注文機能</h3>
                    <p>プラン選択、注文作成、購入履歴、契約状況の表示機能を追加予定。</p>
                </article>

                <article class="roadmap-card reveal">
                    <span>03</span>
                    <h3>決済・サーバー連携</h3>
                    <p>支払い完了後にPterodactyl Panel側へ連携する仕組みを準備予定。</p>
                </article>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/operator/operator.js"></script>
</body>
</html>