<?php
$pageTitle = "運営紹介 | HMJn company";
$pageDescription = "HMJn companyの運営紹介ページです。現在準備中です。";
$pageCss = "/company/company.css";

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="company-page">

    <section class="company-hero">
        <div class="container">
            <div class="company-hero-content reveal">
                <p class="eyebrow">HMJn company</p>
                <h1>
                    <span>運営紹介ページは</span>
                    <span>準備中です。</span>
                </h1>
                <p>
                    HMJn companyの活動内容、運営方針、今後の展開について紹介するページを準備しています。
                    現在はHC Platformの正式公開に向けて、アカウント基盤やサービスページの整備を進めています。
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
                <h2>公開準備中の内容</h2>

                <div class="company-status-grid">
                    <div>
                        <span>01</span>
                        <h3>運営方針</h3>
                        <p>HC Platformを通じて提供していくサービス方針を掲載予定です。</p>
                    </div>

                    <div>
                        <span>02</span>
                        <h3>サービス展開</h3>
                        <p>ゲームサーバー、制作支援、配信活動を支える仕組みを整理して掲載予定です。</p>
                    </div>

                    <div>
                        <span>03</span>
                        <h3>お問い合わせ</h3>
                        <p>正式公開に合わせて、問い合わせ窓口やサポート情報を掲載予定です。</p>
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