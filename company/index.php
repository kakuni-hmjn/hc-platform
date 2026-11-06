<?php
$pageTitle = "運営紹介 | HMJn Co.";
$pageDescription = "HMJn Co.の運営概要、HC Platformの事業内容、現在のサービス提供状況、お問い合わせ方法をご案内します。";
$pageCss = "/company/company.css";

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="company-page">

    <section class="company-hero">
        <div class="container">
            <div class="company-hero-content reveal">
                <p class="eyebrow">HMJn Co.</p>
                <h1>HMJn Co.の運営紹介</h1>
                <p>
                    HMJn Co.はHC Platformを運営しています。Webサービスの提供状況や今後の取り組みを、各ページで確認できるよう案内しています。
                    アカウント基盤とお問い合わせ窓口を公開し、ゲームサーバーレンタルなどのサービスは提供準備を進めています。
                </p>

                <div class="company-actions">
                    <a href="/operator/" class="button primary">運営情報を見る</a>
                    <a href="/" class="button ghost">トップへ戻る</a>
                </div>
            </div>
        </div>
    </section>

    <section class="section company-status-section">
        <div class="container">
            <div class="company-status-card reveal">
                <p class="eyebrow">Coming Soon</p>
                <h2>運営方針と取り組み</h2>

                <div class="company-status-grid">
                    <div>
                        <span>01</span>
                        <h3>運営方針</h3>
                        <p>サービスごとに「提供中」「開発中」「計画中」を区別し、利用者が公開状況を確認しやすい案内を心がけています。</p>
                    </div>

                    <div>
                        <span>02</span>
                        <h3>サービス展開</h3>
                        <p>ゲームサーバーレンタルを中心に開発を進めています。今後の予定は事業一覧に掲載し、提供状況と区別してお知らせします。</p>
                    </div>

                    <div>
                        <span>03</span>
                        <h3>お問い合わせ</h3>
                        <p>サービスやアカウントに関するご連絡は、<a href="/contact/">お問い合わせフォーム</a>から受け付けています。</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>