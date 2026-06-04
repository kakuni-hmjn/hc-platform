<?php
$pageTitle = "アクセス権限がありません | HC Platform";
$pageDescription = "このページを表示する権限がありません。";
$pageCss = "/common/auth.css";

require_once __DIR__ . "/head.php";
?>
<body>
<?php include __DIR__ . "/header/header.php"; ?>

<main class="auth-page">
    <div class="container auth-grid">
        <div class="auth-copy reveal">
            <p class="eyebrow">403 Forbidden</p>
            <h1>アクセス権限がありません</h1>
            <p>
                このページを表示するには、必要な権限がありません。
                権限が必要な場合は運営へお問い合わせください。
            </p>
            <div class="home-actions">
                <a href="/dashboard/" class="button primary">ダッシュボードへ戻る</a>
                <a href="/" class="button ghost">トップへ戻る</a>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . "/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
