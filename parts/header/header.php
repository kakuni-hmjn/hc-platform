<?php
$headerUser = null;

if (function_exists("current_user")) {
    $headerUser = current_user();
}

$headerRole = $headerUser["role"] ?? "";
$isAdmin = in_array($headerRole, ["admin", "owner"], true);
$isStaff = in_array($headerRole, ["staff", "developer", "admin", "owner"], true);
$isDeveloper = in_array($headerRole, ["developer", "admin", "owner"], true);
?>

<header class="site-header">
    <div class="header-inner">

        <a href="/" class="header-brand" aria-label="HC Platform トップページ">
            <span class="brand-logo">
                <?php if (file_exists(__DIR__ . "/../../assets/logo.png")): ?>
                    <img src="/assets/logo.png" alt="HC Platform">
                <?php else: ?>
                    HC
                <?php endif; ?>
            </span>

            <span class="brand-text">
                <strong>HC Platform</strong>
                <small>HCと共にある生活</small>
            </span>
        </a>

        <div class="header-actions">

            <button
                type="button"
                class="theme-switch"
                id="themeSwitch"
                aria-label="ライトモードとダークモードを切り替え"
                aria-pressed="false"
            >
                <span class="theme-switch-track">
                    <span class="theme-switch-thumb"></span>
                </span>
            </button>

            <div class="header-auth desktop-auth">
                <?php if ($headerUser): ?>
                    <a href="/logout/" class="header-link">ログアウト</a>
                    <a href="/dashboard/" class="header-button">マイページ</a>
                <?php else: ?>
                    <a href="/login/" class="header-link">ログイン</a>
                    <a href="/register/" class="header-button">新規登録</a>
                <?php endif; ?>
            </div>

            <button
                type="button"
                class="menu-toggle"
                id="menuToggle"
                aria-label="メニューを開く"
                aria-expanded="false"
                aria-controls="headerDrawer"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

        </div>

    </div>

    <div class="drawer-backdrop" id="drawerBackdrop"></div>

    <aside class="header-drawer" id="headerDrawer" aria-hidden="true">
        <div class="drawer-head">
            <div>
                <p>Menu</p>
                <h2>HC Platform</h2>
            </div>

            <button type="button" class="drawer-close" id="drawerClose" aria-label="メニューを閉じる">
                ×
            </button>
        </div>

        <nav class="drawer-nav" aria-label="サイトメニュー">
            <a href="/">
                <span>Top</span>
                <strong>トップページ</strong>
            </a>

            <a href="/services/">
                <span>Services</span>
                <strong>事業一覧</strong>
            </a>

            <a href="/contact/">
                <span>Contact</span>
                <strong>お問い合わせ</strong>
            </a>

            <a href="/company/">
                <span>Company</span>
                <strong>運営情報</strong>
            </a>

            <a href="/terms/">
                <span>Terms</span>
                <strong>利用規約</strong>
            </a>

            <a href="/privacy/">
                <span>Privacy</span>
                <strong>プライバシーポリシー</strong>
            </a>
        </nav>

        <div class="drawer-account">
            <p>Account</p>

            <?php if ($headerUser): ?>
                <a href="/dashboard/" class="drawer-account-button primary">マイページ</a>
                <a href="/logout/" class="drawer-account-button ghost">ログアウト</a>
            <?php else: ?>
                <a href="/login/" class="drawer-account-button ghost">ログイン</a>
                <a href="/register/" class="drawer-account-button primary">新規登録</a>
            <?php endif; ?>
        </div>

        <?php if ($isStaff || $isAdmin || $isDeveloper): ?>
            <div class="drawer-admin">
                <p>Operation</p>

                <?php if ($isStaff): ?>
                    <a href="/staff/">スタッフページ</a>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <a href="/admin/">管理者ページ</a>
                    <a href="/admin/services/">事業管理</a>
                <?php endif; ?>

                <?php if ($isDeveloper): ?>
                    <a href="/dev/">開発者ページ</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
</header>