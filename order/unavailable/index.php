<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/order_access.php";

$currentUser = require_login();
$pdo = db();

$serviceKey = trim((string)($_GET["service"] ?? "game_server"));
$setting = hc_order_get_setting($pdo, $serviceKey);

$pageTitle = "申込受付停止中 | HC Platform";
$pageDescription = "現在、新規申込受付を一時停止しています。";
$pageCss = "/order/unavailable/unavailable.css";

$message = hc_order_disabled_message($pdo, $serviceKey);

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="order-unavailable-page">
    <section class="order-unavailable-section">
        <div class="container">
            <div class="order-unavailable-card reveal">
                <div class="order-unavailable-icon">
                    construction
                </div>

                <p class="eyebrow">Order Temporarily Closed</p>

                <h1>申込受付を一時停止しています</h1>

                <div class="service-badge">
                    <?php echo h((string)($setting["service_name"] ?? "サービス")); ?>
                </div>

                <p class="order-unavailable-message">
                    <?php echo nl2br(h($message)); ?>
                </p>

                <div class="order-unavailable-actions">
                    <a href="/dashboard/" class="primary-action">
                        マイページへ戻る
                    </a>

                    <a href="/" class="secondary-action">
                        トップへ戻る
                    </a>
                </div>

                <?php if (hc_order_user_can_bypass($currentUser)): ?>
                    <div class="admin-bypass-card">
                        <div>
                            <strong>管理者向け</strong>
                            <p>
                                現在このサービスは一般ユーザー向けに申込停止中です。
                                管理者権限では申込ページの確認・テストが可能です。
                            </p>
                        </div>

                        <a href="/order/game-server/">
                            管理者として申込ページを開く
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
