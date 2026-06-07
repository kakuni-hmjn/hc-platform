<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = require_login();

$pageTitle = "通知一覧 | HC Platform";
$pageDescription = "HC Platformの通知一覧ページです。";
$pageCss = "/dashboard/notifications/notifications.css";

$pdo = db();

$errors = [];
$personalNotifications = [];
$globalNotifications = [];

if (empty($_SESSION["notification_read_token"])) {
    $_SESSION["notification_read_token"] = bin2hex(random_bytes(32));
}
$notificationReadToken = (string)$_SESSION["notification_read_token"];

function notifications_datetime(?string $value): string
{
    if (!$value) {
        return "-";
    }

    try {
        return (new DateTime($value))->format("Y/m/d H:i");
    } catch (Throwable $e) {
        return $value;
    }
}

function notifications_text($value): string
{
    $value = trim((string)($value ?? ""));
    return $value === "" ? "-" : $value;
}

function notifications_safe_internal_link(?string $url, string $fallback): string
{
    $url = trim((string)$url);

    if ($url === "" || !str_starts_with($url, "/") || str_contains($url, "://")) {
        return $fallback;
    }

    return $url;
}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM (
            SELECT
                'personal_event'::VARCHAR AS source_type,
                soe.id AS notification_id,
                soe.order_id AS order_id,
                soe.event_type AS event_type,
                soe.title AS title,
                soe.message AS message,
                soe.old_status AS old_status,
                soe.new_status AS new_status,
                soe.old_payment_status AS old_payment_status,
                soe.new_payment_status AS new_payment_status,
                soe.created_at AS created_at,
                NULL::VARCHAR AS link_url,

                gso.server_name AS context_name,
                gso.status AS order_status,
                gso.payment_status AS payment_status,

                actor.username AS actor_username,
                actor.role AS actor_role,

                unr.read_at AS read_at
            FROM server_order_events soe
            JOIN game_server_orders gso ON gso.id = soe.order_id
            LEFT JOIN users actor ON actor.id = soe.actor_user_id
            LEFT JOIN user_notification_reads unr
              ON unr.user_id = :user_id
             AND unr.notification_type = 'personal_event'
             AND unr.notification_id = soe.id
            WHERE gso.user_id = :user_id

            UNION ALL

            SELECT
                'direct_notice'::VARCHAR AS source_type,
                udn.id AS notification_id,
                NULL::INTEGER AS order_id,
                'direct_notice'::VARCHAR AS event_type,
                udn.title AS title,
                udn.body AS message,
                NULL::VARCHAR AS old_status,
                NULL::VARCHAR AS new_status,
                NULL::VARCHAR AS old_payment_status,
                NULL::VARCHAR AS new_payment_status,
                udn.published_at AS created_at,
                udn.link_url AS link_url,

                '個別通知'::VARCHAR AS context_name,
                NULL::VARCHAR AS order_status,
                NULL::VARCHAR AS payment_status,

                creator.username AS actor_username,
                creator.role AS actor_role,

                unr.read_at AS read_at
            FROM user_direct_notifications udn
            LEFT JOIN users creator ON creator.id = udn.created_by
            LEFT JOIN user_notification_reads unr
              ON unr.user_id = :user_id
             AND unr.notification_type = 'direct_notice'
             AND unr.notification_id = udn.id
            WHERE udn.user_id = :user_id
              AND udn.status = 'published'
              AND udn.published_at <= NOW()
        ) notifications
        ORDER BY created_at DESC, notification_id DESC
        LIMIT 100
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $personalNotifications = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "あなた宛通知の取得中にエラーが発生しました。";
}

try {
    $globalStmt = $pdo->prepare("
        SELECT
            sn.id,
            sn.title,
            sn.body,
            sn.link_url,
            sn.target_scope,
            sn.status,
            sn.priority,
            sn.published_at,
            sn.created_at,
            unr.read_at
        FROM site_notifications sn
        LEFT JOIN user_notification_reads unr
          ON unr.user_id = :user_id
         AND unr.notification_type = 'global_notice'
         AND unr.notification_id = sn.id
        WHERE sn.status = 'published'
          AND sn.target_scope = 'all'
          AND sn.published_at <= NOW()
        ORDER BY sn.priority DESC, sn.published_at DESC, sn.id DESC
        LIMIT 100
    ");

    $globalStmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $globalNotifications = $globalStmt->fetchAll();
} catch (Throwable $e) {
    $globalNotifications = [];
}

$personalUnreadCount = 0;
foreach ($personalNotifications as $notification) {
    if (empty($notification["read_at"])) {
        $personalUnreadCount++;
    }
}

$globalUnreadCount = 0;
foreach ($globalNotifications as $notification) {
    if (empty($notification["read_at"])) {
        $globalUnreadCount++;
    }
}

$totalCount = count($personalNotifications) + count($globalNotifications);
$totalUnreadCount = $personalUnreadCount + $globalUnreadCount;

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="notifications-page">
    <section class="notifications-hero">
        <div class="container notifications-hero-grid">
            <div class="notifications-copy reveal">
                <p class="eyebrow">Dashboard / Notifications</p>
                <h1>通知一覧</h1>
                <p>
                    あなた宛の契約通知・個別通知と、全体宛のお知らせを切り替えて確認できます。
                </p>
            </div>

            <aside class="notifications-status-card reveal">
                <span>通知件数</span>
                <h2><?php echo h((string)$totalCount); ?> 件</h2>
                <p>
                    未読 <?php echo h((string)$totalUnreadCount); ?> 件 /
                    あなた宛 <?php echo h((string)count($personalNotifications)); ?> 件 /
                    全体宛 <?php echo h((string)count($globalNotifications)); ?> 件
                </p>
            </aside>
        </div>
    </section>

    <section class="section notifications-section">
        <div class="container">
            <div class="notifications-toolbar">
                <a href="/dashboard/" class="back-button">ダッシュボードへ戻る</a>
                <a href="/dashboard/servers/" class="sub-button">契約中サーバーへ</a>

                <form method="post" action="/dashboard/notifications/mark-read.php" class="toolbar-read-form">
                    <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                    <input type="hidden" name="action" value="mark_all">
                    <input type="hidden" name="redirect" value="/dashboard/notifications/">
                    <button type="submit" <?php echo $totalUnreadCount <= 0 ? "disabled" : ""; ?>>
                        すべて既読にする
                    </button>
                </form>
            </div>

            <?php if ($errors): ?>
                <div class="notifications-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="notifications-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Notifications</p>
                        <h2>通知</h2>
                    </div>
                </div>

                <div class="notifications-page-tabs">
                    <input type="radio" id="notificationsTabPersonal" name="notifications_page_tab" checked>
                    <input type="radio" id="notificationsTabGlobal" name="notifications_page_tab">

                    <div class="notifications-page-tab-buttons">
                        <label for="notificationsTabPersonal">
                            <span>あなた宛</span>
                            <strong>
                                未読 <?php echo h((string)$personalUnreadCount); ?> / <?php echo h((string)count($personalNotifications)); ?> 件
                            </strong>
                        </label>

                        <label for="notificationsTabGlobal">
                            <span>全体宛</span>
                            <strong>
                                未読 <?php echo h((string)$globalUnreadCount); ?> / <?php echo h((string)count($globalNotifications)); ?> 件
                            </strong>
                        </label>
                    </div>

                    <section class="notifications-tab-panel personal-tab-panel" id="personal">
                        <div class="tab-panel-head">
                            <div>
                                <p class="eyebrow">Personal</p>
                                <h3>あなた宛</h3>
                            </div>

                            <form method="post" action="/dashboard/notifications/mark-read.php" class="panel-read-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                                <input type="hidden" name="action" value="mark_all_personal">
                                <input type="hidden" name="redirect" value="/dashboard/notifications/#personal">
                                <button type="submit" <?php echo $personalUnreadCount <= 0 ? "disabled" : ""; ?>>
                                    あなた宛を既読
                                </button>
                            </form>
                        </div>

                        <?php if (!$personalNotifications): ?>
                            <div class="empty-box">
                                <h3>あなた宛の通知はありません。</h3>
                                <p>契約操作、プラン変更、管理者からの個別通知がここに表示されます。</p>
                            </div>
                        <?php else: ?>
                            <div class="notification-list">
                                <?php foreach ($personalNotifications as $notification): ?>
                                    <?php
                                    $isRead = !empty($notification["read_at"]);
                                    $sourceType = (string)$notification["source_type"];
                                    $isDirect = $sourceType === "direct_notice";
                                    $formType = $isDirect ? "direct" : "personal_event";
                                    $linkUrl = $isDirect
                                        ? notifications_safe_internal_link($notification["link_url"] ?? "", "/dashboard/notifications/#personal")
                                        : "/dashboard/servers/detail/?id=" . rawurlencode((string)$notification["order_id"]);
                                    ?>
                                    <article class="notification-card <?php echo $isDirect ? 'direct-card' : 'event-' . h((string)$notification["event_type"]); ?> <?php echo $isRead ? 'is-read' : 'is-unread'; ?>">
                                        <div class="notification-dot <?php echo $isDirect ? 'direct-dot' : ''; ?>"></div>

                                        <div class="notification-content">
                                            <div class="notification-title-line">
                                                <div>
                                                    <span>
                                                        <?php echo h(notifications_datetime((string)$notification["created_at"])); ?>
                                                        /
                                                        <?php echo $isRead ? "既読" : "未読"; ?>
                                                        /
                                                        <?php echo $isDirect ? "個別通知" : "契約通知"; ?>
                                                    </span>
                                                    <h3><?php echo h((string)$notification["title"]); ?></h3>
                                                </div>

                                                <div class="notification-card-actions">
                                                    <?php if (!$isRead): ?>
                                                        <form method="post" action="/dashboard/notifications/mark-read.php">
                                                            <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                                                            <input type="hidden" name="action" value="mark_one">
                                                            <input type="hidden" name="type" value="<?php echo h($formType); ?>">
                                                            <input type="hidden" name="notification_id" value="<?php echo h((string)$notification["notification_id"]); ?>">
                                                            <input type="hidden" name="redirect" value="/dashboard/notifications/#personal">
                                                            <button type="submit" class="read-button">既読にする</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <a href="<?php echo h($linkUrl); ?>" class="detail-button">
                                                        <?php echo $isDirect ? "開く" : "契約詳細"; ?>
                                                    </a>
                                                </div>
                                            </div>

                                            <p class="server-name">
                                                <?php echo h(notifications_text($notification["context_name"])); ?>
                                                <?php if (!$isDirect): ?>
                                                    /
                                                    契約 #<?php echo h((string)$notification["order_id"]); ?>
                                                <?php endif; ?>
                                            </p>

                                            <?php if (!empty($notification["message"])): ?>
                                                <div class="notification-message">
                                                    <?php echo nl2br(h((string)$notification["message"])); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="notification-meta">
                                                <span>
                                                    送信:
                                                    <?php echo h((string)($notification["actor_username"] ?: "system")); ?>
                                                </span>

                                                <?php if (!$isDirect && (!empty($notification["old_status"]) || !empty($notification["new_status"]))): ?>
                                                    <span>
                                                        状態:
                                                        <?php echo h((string)($notification["old_status"] ?: "-")); ?>
                                                        →
                                                        <?php echo h((string)($notification["new_status"] ?: "-")); ?>
                                                    </span>
                                                <?php endif; ?>

                                                <?php if (!$isDirect && (!empty($notification["old_payment_status"]) || !empty($notification["new_payment_status"]))): ?>
                                                    <span>
                                                        決済:
                                                        <?php echo h((string)($notification["old_payment_status"] ?: "-")); ?>
                                                        →
                                                        <?php echo h((string)($notification["new_payment_status"] ?: "-")); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="notifications-tab-panel global-tab-panel" id="global">
                        <div class="tab-panel-head">
                            <div>
                                <p class="eyebrow">Global</p>
                                <h3>全体宛</h3>
                            </div>

                            <form method="post" action="/dashboard/notifications/mark-read.php" class="panel-read-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                                <input type="hidden" name="action" value="mark_all_global">
                                <input type="hidden" name="redirect" value="/dashboard/notifications/#global">
                                <button type="submit" <?php echo $globalUnreadCount <= 0 ? "disabled" : ""; ?>>
                                    全体宛を既読
                                </button>
                            </form>
                        </div>

                        <?php if (!$globalNotifications): ?>
                            <div class="empty-box">
                                <h3>全体宛の通知はありません。</h3>
                                <p>運営から全ユーザー向け通知があると、ここに表示されます。</p>
                            </div>
                        <?php else: ?>
                            <div class="notification-list">
                                <?php foreach ($globalNotifications as $notification): ?>
                                    <?php
                                    $isRead = !empty($notification["read_at"]);
                                    $linkUrl = notifications_safe_internal_link($notification["link_url"] ?? "", "/dashboard/notifications/#global");
                                    ?>
                                    <article class="notification-card global-card <?php echo $isRead ? 'is-read' : 'is-unread'; ?>">
                                        <div class="notification-dot global-dot"></div>

                                        <div class="notification-content">
                                            <div class="notification-title-line">
                                                <div>
                                                    <span>
                                                        <?php echo h(notifications_datetime((string)$notification["published_at"])); ?>
                                                        /
                                                        <?php echo $isRead ? "既読" : "未読"; ?>
                                                        /
                                                        全体通知
                                                    </span>
                                                    <h3><?php echo h((string)$notification["title"]); ?></h3>
                                                </div>

                                                <div class="notification-card-actions">
                                                    <?php if (!$isRead): ?>
                                                        <form method="post" action="/dashboard/notifications/mark-read.php">
                                                            <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                                                            <input type="hidden" name="action" value="mark_one">
                                                            <input type="hidden" name="type" value="global">
                                                            <input type="hidden" name="notification_id" value="<?php echo h((string)$notification["id"]); ?>">
                                                            <input type="hidden" name="redirect" value="/dashboard/notifications/#global">
                                                            <button type="submit" class="read-button">既読にする</button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <a href="<?php echo h($linkUrl); ?>" class="detail-button">
                                                        開く
                                                    </a>
                                                </div>
                                            </div>

                                            <?php if (!empty($notification["body"])): ?>
                                                <div class="notification-message">
                                                    <?php echo nl2br(h((string)$notification["body"])); ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="notification-meta">
                                                <span>全体宛</span>
                                                <span>優先度 <?php echo h((string)$notification["priority"]); ?></span>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
