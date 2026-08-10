<?php

session_start();

require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/permissions.php";
require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/pterodactyl.php";



$dashboardRoleUser = $currentUser ?? $user ?? [];

if (!$dashboardRoleUser && function_exists("current_user")) {
    $dashboardRoleUser = current_user() ?? [];
}

$currentRole = (string)($dashboardRoleUser["role"] ?? "user");

$roleLabels = [
    "owner" => "オーナー",
    "admin" => "管理者",
    "developer" => "開発者",
    "staff" => "スタッフ",
    "user" => "ユーザー",
];

$currentRoleLabel = $roleLabels[$currentRole] ?? $currentRole;
$currentRoleClass = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($currentRole));

$dashboardRoleUser = $currentUser ?? $user ?? [];

if (!$dashboardRoleUser && function_exists("current_user")) {
    $dashboardRoleUser = current_user() ?? [];
}

$currentUser = current_user();

if (!$currentUser) {
    header("Location: /login/?redirect=/dashboard/");
    exit;
}

$pageTitle = "マイページ | HC Platform";
$pageDescription = "HC Platformのマイページです。アカウント情報、契約中サーバー、通知、注文、請求情報などを確認できます。";
$pageCss = "/dashboard/dashboard.css";

$pdo = db();

$activeServerCount = 0;
$personalEventUnreadCount = 0;
$directUnreadCount = 0;
$personalUnreadCount = 0;
$globalUnreadCount = 0;
$totalUnreadNotificationCount = 0;
$supportChatCount = 0;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM ptero_servers ps
        JOIN game_server_orders gso ON gso.id = ps.order_id
        WHERE ps.user_id = :user_id
          AND ps.status != 'deleted'
          AND gso.status NOT IN ('cancelled', 'expired')
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $row = $stmt->fetch();
    $activeServerCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $activeServerCount = 0;
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM server_order_events soe
        JOIN game_server_orders gso ON gso.id = soe.order_id
        LEFT JOIN user_notification_reads unr
          ON unr.user_id = :user_id
         AND unr.notification_type = 'personal_event'
         AND unr.notification_id = soe.id
        WHERE gso.user_id = :user_id
          AND unr.id IS NULL
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $row = $stmt->fetch();
    $personalEventUnreadCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $personalEventUnreadCount = 0;
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM user_direct_notifications udn
        LEFT JOIN user_notification_reads unr
          ON unr.user_id = :user_id
         AND unr.notification_type = 'direct_notice'
         AND unr.notification_id = udn.id
        WHERE udn.user_id = :user_id
          AND udn.status = 'published'
          AND udn.published_at <= NOW()
          AND unr.id IS NULL
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $row = $stmt->fetch();
    $directUnreadCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $directUnreadCount = 0;
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM site_notifications sn
        LEFT JOIN user_notification_reads unr
          ON unr.user_id = :user_id
         AND unr.notification_type = 'global_notice'
         AND unr.notification_id = sn.id
        WHERE sn.status = 'published'
          AND sn.target_scope = 'all'
          AND sn.published_at <= NOW()
          AND unr.id IS NULL
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $row = $stmt->fetch();
    $globalUnreadCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $globalUnreadCount = 0;
}

$personalUnreadCount = $personalEventUnreadCount + $directUnreadCount;
$totalUnreadNotificationCount = $personalUnreadCount + $globalUnreadCount;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM contacts
        WHERE user_id = :user_id
    ");
    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);
    $row = $stmt->fetch();
    $supportChatCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $supportChatCount = 0;
}

require_once __DIR__ . "/../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="dashboard-page">
    <section class="dashboard-hero">
        <div class="container dashboard-hero-grid">
            <div class="dashboard-copy reveal">
                <p class="eyebrow">Dashboard</p>
                <h1>マイページ</h1>
                <p>
                    HC Platformで利用中のサービス、契約中サーバー、通知、注文状況、アカウント情報を確認できます。
                </p>

</div>

            <aside class="dashboard-status-card reveal">
                <span>HC Account</span>
                <h2><?php echo h($currentUser["username"] ?? "User"); ?></h2>
                <p>
                    ログイン中のアカウントで利用中のサービス情報を表示しています。
                </p>

<div class="dashboard-role-badge dashboard-role-badge--<?= h($currentRoleClass) ?>">
  <?= h($currentRoleLabel) ?>
</div>


<div class="dashboard-mini-stats">
                    <?php if ($activeServerCount > 0): ?>
                        <div>
                            <strong><?php echo h((string)$activeServerCount); ?></strong>
                            <small>契約中サーバー</small>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalUnreadNotificationCount > 0): ?>
                        <div class="has-unread">
                            <strong><?php echo h((string)$totalUnreadNotificationCount); ?></strong>
                            <small>未読通知</small>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </section>

    <section class="section dashboard-section">
        <div class="container">
            <div class="dashboard-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Menu</p>
                        <h2>管理メニュー</h2>
                    </div>
                </div>

                <div class="dashboard-action-grid">
                    <a href="/dashboard/servers/" class="dashboard-action-card reveal">
                        <span>Game Servers</span>

                        <div class="dashboard-card-title-line">
                            <h3>契約中サーバー</h3>

                            <?php if ($activeServerCount > 0): ?>
                                <strong class="dashboard-card-count">
                                    <?php echo h((string)$activeServerCount); ?> 件
                                </strong>
                            <?php endif; ?>
                        </div>

                        <p>
                            ゲームサーバーレンタルで作成されたサーバー、申込状況、契約状態を確認できます。
                        </p>
                    </a>

                    <a href="/dashboard/notifications/" class="dashboard-action-card dashboard-notification-card reveal">
                        <span>Notifications</span>

                        <div class="dashboard-card-title-line">
                            <h3>通知</h3>

                            <?php if ($totalUnreadNotificationCount > 0): ?>
                                <strong class="dashboard-unread-count">
                                    未読 <?php echo h((string)$totalUnreadNotificationCount); ?> 件
                                </strong>
                            <?php endif; ?>
                        </div>

                        <p>
                            あなた宛の契約通知・個別通知と、運営からの全体宛通知を確認できます。
                        </p>

                        <?php if ($totalUnreadNotificationCount > 0): ?>
                            <div class="dashboard-notification-breakdown">
                                <small>あなた宛 <?php echo h((string)$personalUnreadCount); ?> 件</small>
                                <small>全体宛 <?php echo h((string)$globalUnreadCount); ?> 件</small>
                            </div>
                        <?php endif; ?>
                    </a>

                    <a href="/account/" class="dashboard-action-card reveal">
                        <span>Account</span>
                        <h3>アカウント情報</h3>
                        <p>
                            ユーザー名、メールアドレス、ログイン情報など、HC Accountの情報を確認します。
                        </p>
                    </a>

                    <a href="/billing/" class="dashboard-action-card reveal">
                        <span>Billing</span>
                        <h3>請求・支払い</h3>
                        <p>
                            支払い状況、請求情報、契約の更新状況などを確認します。
                        </p>
                    </a>

                    <a href="/order/" class="dashboard-action-card reveal">
                        <span>Order</span>
                        <h3>サービス申込</h3>
                        <p>
                            ゲームサーバーや各種レンタルサービスの申し込みページへ移動します。
                        </p>
                    </a>

                    <a href="/services/" class="dashboard-action-card reveal">
                        <span>Services</span>
                        <h3>サービス一覧</h3>
                        <p>
                            HC Platformで提供予定・開発中のサービス一覧を確認できます。
                        </p>
                    </a>

                    <a href="/dashboard/support/" class="dashboard-action-card reveal">
                        <span>Support Chat</span>
                        <div class="dashboard-card-title-line">
                            <h3>サポートチャット</h3>

                            <?php if ($supportChatCount > 0): ?>
                                <strong class="dashboard-card-count">
                                    <?php echo h((string)$supportChatCount); ?> 件
                                </strong>
                            <?php endif; ?>
                        </div>
                        <p>
                            スタッフとの個別チャットを確認し、そのまま返信できます。新しい相談もここから開始できます。
                        </p>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php include __DIR__ . "/../parts/dashboard/ptero-account-card.php"; ?>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
