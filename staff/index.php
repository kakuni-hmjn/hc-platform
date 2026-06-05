<?php
session_start();

require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/permissions.php";

$user = require_role("staff");

$pageTitle = "スタッフページ | HC Platform";
$pageDescription = "HC Platformの運営スタッフ専用ページです。";
$pageCss = "/staff/staff.css";

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="staff-page">
    <section class="staff-hero">
        <div class="container staff-hero-grid">
            <div class="staff-copy reveal">
                <p class="eyebrow">Staff</p>
                <h1>スタッフページ</h1>
                <p>
                    運営スタッフ向けの専用ページです。
                    問い合わせ対応、ユーザー確認、サービス状況確認などをここに追加していきます。
                </p>
            </div>

            <aside class="staff-status-card reveal">
                <span>権限</span>
                <h2><?php echo h(role_label($user["role"])); ?></h2>
                <p><?php echo h($user["email"]); ?></p>
            </aside>
        </div>
    </section>

    <section class="section staff-section">
        <div class="container">
            <div class="section-heading reveal">
                <p class="eyebrow">Operation</p>
                <h2>運営メニュー</h2>
                <p>スタッフ向けの確認・対応機能を追加していきます。</p>
            </div>

            <div class="staff-menu-grid">
                <a href="/staff/contacts/" class="staff-menu-card reveal">
                    <span>01</span>
                    <h3>問い合わせ確認</h3>
                    <p>お問い合わせやサポート対応の確認を行います。</p>
                </a>

                <a href="/staff/users/" class="staff-menu-card reveal">
                    <span>02</span>
                    <h3>ユーザー確認</h3>
                    <p>ユーザー情報、登録状態、メール認証状況を確認できます。</p>
                </a>

                <article class="staff-menu-card reveal">
                    <span>03</span>
                    <h3>サービス状態</h3>
                    <p>サービス公開状況やメンテナンス状態を確認する機能を追加予定です。</p>
                </article>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>