<?php
$footerUser = null;

if (function_exists("current_user")) {
    $footerUser = current_user();
}
?>

<footer class="site-footer">
    <div class="footer-inner">

        <div class="footer-main">

            <div class="footer-brand-block">
                <a href="/" class="footer-brand" aria-label="HC Platform トップページ">
                    <span class="footer-logo">
                        <?php if (file_exists(__DIR__ . "/../../assets/logo.png")): ?>
                            <img src="/assets/logo.png" alt="HC Platform">
                        <?php else: ?>
                            HC
                        <?php endif; ?>
                    </span>

                    <span class="footer-brand-text">
                        <strong>HC Platform</strong>
                        <small>HCと共にある生活</small>
                    </span>
                </a>

                <p class="footer-description">
                    HC Platformは、ゲームサーバー、Webサービス、クリエイター支援、
                    コミュニティ運営を通して、遊びと創作を支えるためのプラットフォームです。
                </p>

                <div class="footer-company">
                    <span>Operation</span>
                    <strong>HMJn company</strong>
                </div>
            </div>

            <nav class="footer-nav" aria-label="フッターナビゲーション">

                <div class="footer-nav-group">
                    <h2>Site</h2>
                    <a href="/">トップページ</a>
                    <a href="/services/">事業一覧</a>
                    <a href="/news/">お知らせ</a>
                    <a href="/contact/">お問い合わせ</a>
                </div>

                <div class="footer-nav-group">
                    <h2>Account</h2>

                    <?php if ($footerUser): ?>
                        <a href="/dashboard/">マイページ</a>
                        <a href="/account/">アカウント情報</a>
                        <a href="/logout/">ログアウト</a>
                    <?php else: ?>
                        <a href="/login/">ログイン</a>
                        <a href="/register/">新規登録</a>
                    <?php endif; ?>
                </div>

                <div class="footer-nav-group">
                    <h2>Information</h2>
                    <a href="/company/">運営情報</a>
                    <a href="/terms/">利用規約</a>
                    <a href="/privacy/">プライバシーポリシー</a>
                </div>

            </nav>

        </div>

        <div class="footer-bottom">
            <p>
                © <?php echo date("Y"); ?> HMJn company / HC Platform. All rights reserved.
            </p>

            <div class="footer-bottom-links">
                <a href="/terms/">Terms</a>
                <a href="/privacy/">Privacy</a>
                <a href="/contact/">Contact</a>
            </div>
        </div>

    </div>
</footer>