<?php
session_start();

require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "管理者ページ | HC Platform";
$pageDescription = "HC Platformの管理者専用ページです。";
$pageCss = "/admin/admin.css";

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="admin-page">
    <section class="admin-hero">
        <div class="container admin-hero-grid">
            <div class="admin-copy reveal">
                <p class="eyebrow">Admin</p>
                <h1>管理者ページ</h1>
                <p>
                    HC Platformの管理者向けページです。
                    ユーザー管理、契約管理、注文管理、システム状態確認などをここに追加していきます。
                </p>
            </div>

            <aside class="admin-status-card reveal">
                <span>ログイン中</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>
        </div>
    </section>

    <section class="section admin-section">
        <div class="container">
            <div class="section-heading reveal">
                <p class="eyebrow">Management</p>
                <h2>管理メニュー</h2>
                <p>今後、管理機能をここに追加していきます。</p>
            </div>

            <div class="admin-menu-grid">
                <a href="/admin/users/" class="admin-menu-card reveal">
                    <span>01</span>
                    <h3>ユーザー管理</h3>
                    <p>ユーザー一覧、権限変更、アカウント状態の確認を行う機能を追加予定です。</p>
                </a>

                <a href="#" class="admin-menu-card reveal">
                    <span>02</span>
                    <h3>契約管理</h3>
                    <p>契約中サービス、注文履歴、支払い状況を確認する機能を追加予定です。</p>
                </a>

                <a href="/admin/news/" class="admin-menu-card reveal">
                    <span>03</span>
                    <h3>お知らせ管理</h3>
                    <p>トップページやお知らせ一覧に表示するお知らせ情報を管理します。</p>
                </a>

                <a href="/staff/" class="admin-menu-card reveal">
                    <span>04</span>
                    <h3>スタッフページ</h3>
                    <p>運営・サポート向けの作業ページへ移動します。</p>
                </a>

                <a href="/dev/" class="admin-menu-card reveal">
                    <span>05</span>
                    <h3>開発者ページ</h3>
                    <p>開発・保守向けの確認ページへ移動します。</p>
                </a>

                <a href="/admin/services/" class="admin-menu-card reveal">
                    <span>06</span>
                    <h3>事業管理</h3>
                    <p>トップページや事業一覧に表示する事業情報を追加・編集できます。</p>
                </a>

                <a href="/dashboard/" class="admin-menu-card reveal">
                    <span>07</span>
                    <h3>ダッシュボードへ戻る</h3>
                    <p>通常のアカウントダッシュボードへ戻ります。</p>
                </a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>