<?php
$currentPage = basename($_SERVER["PHP_SELF"]);

require_once __DIR__ . "/../../lib/auth.php";

$headerUser = current_user();
?>
<header class="site-header">
    <div class="container header-inner">
        <a href="/" class="brand">
            <span class="brand-logo-img">
                <img src="/assets/logo.png" alt="HC Platform ロゴ">
            </span>
            <span class="brand-name">HC Platform</span>
        </a>

        <nav class="desktop-nav">
            <a href="/#concept">コンセプト</a>
            <a href="/#service">サービス</a>
            <a href="/#plans">予定プラン</a>
            <a href="/operator/">運営情報</a>
            <a href="/contact/">お問い合わせ</a>

            <?php if ($headerUser): ?>
                <a href="/dashboard/">ダッシュボード</a>
                <a href="/billing/">契約・購入履歴</a>
            <?php else: ?>
                <a href="/#status">準備状況</a>
            <?php endif; ?>
        </nav>

        <div class="header-actions">
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="テーマ切り替え">
                <span class="theme-icon">🌙</span>
                <span class="theme-text">Dark</span>
            </button>

            <div class="auth-actions">
                <?php if ($headerUser): ?>
                    <a href="/logout/" class="login-link">ログアウト</a>
                    <a href="/dashboard/" class="register-link">マイページ</a>
                <?php else: ?>
                    <a href="/login/" class="login-link">ログイン</a>
                    <a href="/register/" class="register-link">新規登録</a>
                <?php endif; ?>
            </div>
        </div>

        <button class="menu-button" id="menuButton" type="button" aria-label="メニューを開く">
            <span></span>
            <span></span>
        </button>
    </div>

    <nav class="mobile-nav" id="mobileNav">
        <a href="/#concept">コンセプト</a>
        <a href="/#service">サービス</a>
        <a href="/#plans">予定プラン</a>
        <a href="/operator/">運営情報</a>
        <a href="/contact/">お問い合わせ</a>
        <?php if ($headerUser): ?>
            <a href="/dashboard/">ダッシュボード</a>
            <a href="/account/">プロフィール設定</a>
            <a href="/billing/">契約・購入履歴</a>
            <a href="/order/">新規プラン契約</a>

            <div class="mobile-auth">
                <a href="/dashboard/">マイページ</a>
                <a href="/logout/">ログアウト</a>
            </div>
        <?php else: ?>
            <a href="/#status">準備状況</a>

            <div class="mobile-auth">
                <a href="/login/">ログイン</a>
                <a href="/register/">新規登録</a>
            </div>
        <?php endif; ?>
    </nav>
</header>