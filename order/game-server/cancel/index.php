<?php
session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";

$pageTitle = "決済キャンセル | HC Platform";
$pageDescription = "ゲームサーバーレンタルの決済がキャンセルされました。";
$pageCss = "/order/game-server/order.css";

require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="order-page">
    <section class="order-hero">
        <div class="container">
            <div class="order-panel reveal">
                <p class="eyebrow">Cancel</p>
                <h1>決済がキャンセルされました</h1>
                <p>
                    決済は完了していません。内容を確認して、もう一度申し込みを行ってください。
                </p>

                <div class="submit-row">
                    <a href="/order/game-server/" class="submit-button">申込へ戻る</a>
                    <a href="/services/rental/game-server/" class="back-button">プランへ戻る</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>