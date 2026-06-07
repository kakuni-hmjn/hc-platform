<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/db.php";

$headerUser = current_user();

if (empty($_SESSION["notification_read_token"])) {
    $_SESSION["notification_read_token"] = bin2hex(random_bytes(32));
}
$notificationReadToken = (string)$_SESSION["notification_read_token"];

function header_default_operation_links(): array
{
    return [
        ["label" => "スタッフページ", "url" => "/staff/", "required_role" => "staff", "sort_order" => 10],
        ["label" => "管理者ページ", "url" => "/admin/", "required_role" => "admin", "sort_order" => 20],
        ["label" => "ゲームサーバー申込管理", "url" => "/admin/server-orders/", "required_role" => "admin", "sort_order" => 30],
        ["label" => "プラン変更申請管理", "url" => "/admin/plan-change-requests/", "required_role" => "admin", "sort_order" => 40],
        ["label" => "ゲームサーバープラン管理", "url" => "/admin/game-plans/", "required_role" => "admin", "sort_order" => 50],
        ["label" => "事業管理", "url" => "/admin/services/", "required_role" => "admin", "sort_order" => 60],
        ["label" => "お知らせ管理", "url" => "/admin/news/", "required_role" => "admin", "sort_order" => 70],
        ["label" => "全体通知管理", "url" => "/admin/site-notifications/", "required_role" => "admin", "sort_order" => 75],
        ["label" => "個別通知管理", "url" => "/admin/user-notifications/", "required_role" => "admin", "sort_order" => 76],
        ["label" => "Pterodactyl連携", "url" => "/admin/ptero/", "required_role" => "admin", "sort_order" => 80],
        ["label" => "開発者ページ", "url" => "/admin/dev/", "required_role" => "developer", "sort_order" => 90],
        ["label" => "ヘッダー表示設定", "url" => "/admin/header-settings/", "required_role" => "admin", "sort_order" => 100],
    ];
}

function header_operation_links(): array
{
    try {
        $pdo = db();

        $stmt = $pdo->query("
            SELECT label, url, required_role, sort_order
            FROM header_operation_links
            WHERE is_visible = true
            ORDER BY sort_order ASC, id ASC
        ");

        $links = $stmt->fetchAll();

        if ($links) {
            return $links;
        }
    } catch (Throwable $e) {
    }

    return header_default_operation_links();
}

function header_visible_operation_links(?array $user): array
{
    if (!$user) {
        return [];
    }

    $visible = [];

    foreach (header_operation_links() as $link) {
        $requiredRole = (string)($link["required_role"] ?? "staff");

        if (has_role($user, $requiredRole)) {
            $visible[] = $link;
        }
    }

    return $visible;
}

function header_notification_datetime(?string $value): string
{
    if (!$value) {
        return "";
    }

    try {
        return (new DateTime($value))->format("m/d H:i");
    } catch (Throwable $e) {
        return $value;
    }
}

function header_safe_internal_link(?string $url, string $fallback): string
{
    $url = trim((string)$url);

    if ($url === "" || !str_starts_with($url, "/") || str_contains($url, "://")) {
        return $fallback;
    }

    return $url;
}

function header_fetch_personal_notifications(?array $user): array
{
    if (!$user) {
        return ["items" => [], "unread_count" => 0];
    }

    try {
        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT *
            FROM (
                SELECT
                    'personal_event' AS source_type,
                    soe.id AS notification_id,
                    soe.order_id AS order_id,
                    soe.title AS title,
                    soe.message AS message,
                    soe.created_at AS created_at,
                    NULL::VARCHAR AS link_url,
                    gso.server_name AS context_name,
                    actor.username AS actor_username,
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
                    'direct_notice' AS source_type,
                    udn.id AS notification_id,
                    NULL::INTEGER AS order_id,
                    udn.title AS title,
                    udn.body AS message,
                    udn.published_at AS created_at,
                    udn.link_url AS link_url,
                    '個別通知' AS context_name,
                    creator.username AS actor_username,
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
            LIMIT 5
        ");

        $stmt->execute([
            "user_id" => (int)$user["id"],
        ]);

        $items = $stmt->fetchAll();

        $countStmt = $pdo->prepare("
            SELECT
                (
                    SELECT COUNT(*)
                    FROM server_order_events soe
                    JOIN game_server_orders gso ON gso.id = soe.order_id
                    LEFT JOIN user_notification_reads unr
                      ON unr.user_id = :user_id
                     AND unr.notification_type = 'personal_event'
                     AND unr.notification_id = soe.id
                    WHERE gso.user_id = :user_id
                      AND unr.id IS NULL
                )
                +
                (
                    SELECT COUNT(*)
                    FROM user_direct_notifications udn
                    LEFT JOIN user_notification_reads unr
                      ON unr.user_id = :user_id
                     AND unr.notification_type = 'direct_notice'
                     AND unr.notification_id = udn.id
                    WHERE udn.user_id = :user_id
                      AND udn.status = 'published'
                      AND udn.published_at <= NOW()
                      AND unr.id IS NULL
                ) AS count
        ");

        $countStmt->execute([
            "user_id" => (int)$user["id"],
        ]);

        $row = $countStmt->fetch();

        return [
            "items" => $items,
            "unread_count" => (int)($row["count"] ?? 0),
        ];
    } catch (Throwable $e) {
        return ["items" => [], "unread_count" => 0];
    }
}

function header_fetch_global_notifications(?array $user): array
{
    if (!$user) {
        return ["items" => [], "unread_count" => 0];
    }

    try {
        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT
                sn.id,
                sn.title,
                sn.body,
                sn.link_url,
                sn.priority,
                sn.published_at,
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
            LIMIT 5
        ");

        $stmt->execute([
            "user_id" => (int)$user["id"],
        ]);

        $items = $stmt->fetchAll();

        $countStmt = $pdo->prepare("
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

        $countStmt->execute([
            "user_id" => (int)$user["id"],
        ]);

        $row = $countStmt->fetch();

        return [
            "items" => $items,
            "unread_count" => (int)($row["count"] ?? 0),
        ];
    } catch (Throwable $e) {
        return ["items" => [], "unread_count" => 0];
    }
}

$operationLinks = header_visible_operation_links($headerUser);

$headerPersonalNotifications = header_fetch_personal_notifications($headerUser);
$headerPersonalNotificationItems = $headerPersonalNotifications["items"];
$headerPersonalUnreadCount = (int)$headerPersonalNotifications["unread_count"];

$headerGlobalNotifications = header_fetch_global_notifications($headerUser);
$headerGlobalNotificationItems = $headerGlobalNotifications["items"];
$headerGlobalUnreadCount = (int)$headerGlobalNotifications["unread_count"];

$headerUnreadCount = $headerPersonalUnreadCount + $headerGlobalUnreadCount;
?>

<header class="site-header">
    <div class="header-inner">
        <a href="/" class="header-brand" aria-label="HC Platform トップページ">
            <span class="brand-logo">HC</span>
            <span class="brand-text">
                <strong>HC Platform</strong>
                <small>HCと共にある生活</small>
            </span>
        </a>

        <div class="header-actions">
            <button class="theme-switch" id="themeSwitch" type="button" aria-label="テーマ切り替え" aria-pressed="false">
                <span class="theme-switch-track">
                    <span class="theme-switch-thumb"></span>
                </span>
            </button>

            <?php if ($headerUser): ?>
                <details class="header-notifications">
                    <summary aria-label="通知を開く">
                        <svg class="notification-bell-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 22a2.6 2.6 0 0 0 2.55-2.1h-5.1A2.6 2.6 0 0 0 12 22Z"></path>
                            <path d="M19.3 17.2 17.9 15.5V10.4A5.95 5.95 0 0 0 13.2 4.6V3.8a1.2 1.2 0 0 0-2.4 0v.8A5.95 5.95 0 0 0 6.1 10.4v5.1l-1.4 1.7a1.05 1.05 0 0 0 .8 1.7h13a1.05 1.05 0 0 0 .8-1.7Z"></path>
                        </svg>

                        <?php if ($headerUnreadCount > 0): ?>
                            <strong><?php echo h((string)min($headerUnreadCount, 99)); ?></strong>
                        <?php endif; ?>
                    </summary>

                    <div class="header-notification-dropdown">
                        <div class="notification-dropdown-head">
                            <div>
                                <span>Notifications</span>
                                <h3>通知</h3>
                            </div>

                            <?php if ($headerUnreadCount > 0): ?>
                                <strong>未読 <?php echo h((string)$headerUnreadCount); ?>件</strong>
                            <?php endif; ?>
                        </div>

                        <div class="notification-tabs">
                            <input type="radio" id="headerNotifyPersonal" name="header_notify_tab" checked>
                            <input type="radio" id="headerNotifyGlobal" name="header_notify_tab">

                            <div class="notification-tab-buttons">
                                <label for="headerNotifyPersonal">
                                    あなた宛
                                    <?php if ($headerPersonalUnreadCount > 0): ?>
                                        <span><?php echo h((string)$headerPersonalUnreadCount); ?></span>
                                    <?php endif; ?>
                                </label>

                                <label for="headerNotifyGlobal">
                                    全体宛
                                    <?php if ($headerGlobalUnreadCount > 0): ?>
                                        <span><?php echo h((string)$headerGlobalUnreadCount); ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>

                            <section class="notification-tab-panel personal-panel">
                                <form method="post" action="/dashboard/notifications/mark-read.php" class="notification-mark-all-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                                    <input type="hidden" name="action" value="mark_all_personal">
                                    <input type="hidden" name="redirect" value="<?php echo h((string)($_SERVER["REQUEST_URI"] ?? "/")); ?>">
                                    <button type="submit" <?php echo $headerPersonalUnreadCount <= 0 ? "disabled" : ""; ?>>
                                        あなた宛をすべて既読
                                    </button>
                                </form>

                                <?php if (!$headerPersonalNotificationItems): ?>
                                    <div class="notification-empty compact-empty">
                                        <strong>あなた宛の通知はありません。</strong>
                                    </div>
                                <?php else: ?>
                                    <div class="notification-mini-list">
                                        <?php foreach ($headerPersonalNotificationItems as $notification): ?>
                                            <?php
                                            $isRead = !empty($notification["read_at"]);
                                            $sourceType = (string)$notification["source_type"];
                                            $formType = $sourceType === "direct_notice" ? "direct" : "personal_event";

                                            if ($sourceType === "direct_notice") {
                                                $linkUrl = header_safe_internal_link($notification["link_url"] ?? "", "/dashboard/notifications/#personal");
                                            } else {
                                                $linkUrl = "/dashboard/servers/detail/?id=" . rawurlencode((string)$notification["order_id"]);
                                            }
                                            ?>
                                            <article class="notification-mini-item-wrap <?php echo $isRead ? 'is-read' : 'is-unread'; ?>">
                                                <a href="<?php echo h($linkUrl); ?>" class="notification-mini-item">
                                                    <span><?php echo h(header_notification_datetime((string)$notification["created_at"])); ?></span>
                                                    <strong><?php echo h((string)$notification["title"]); ?></strong>
                                                    <p><?php echo h((string)($notification["context_name"] ?: "あなた宛通知")); ?></p>
                                                </a>

                                                <?php if (!$isRead): ?>
                                                    <form method="post" action="/dashboard/notifications/mark-read.php" class="mini-read-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                                                        <input type="hidden" name="action" value="mark_one">
                                                        <input type="hidden" name="type" value="<?php echo h($formType); ?>">
                                                        <input type="hidden" name="notification_id" value="<?php echo h((string)$notification["notification_id"]); ?>">
                                                        <input type="hidden" name="redirect" value="<?php echo h((string)($_SERVER["REQUEST_URI"] ?? "/")); ?>">
                                                        <button type="submit">既読</button>
                                                    </form>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>

                            <section class="notification-tab-panel global-panel">
                                <form method="post" action="/dashboard/notifications/mark-read.php" class="notification-mark-all-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                                    <input type="hidden" name="action" value="mark_all_global">
                                    <input type="hidden" name="redirect" value="<?php echo h((string)($_SERVER["REQUEST_URI"] ?? "/")); ?>">
                                    <button type="submit" <?php echo $headerGlobalUnreadCount <= 0 ? "disabled" : ""; ?>>
                                        全体宛をすべて既読
                                    </button>
                                </form>

                                <?php if (!$headerGlobalNotificationItems): ?>
                                    <div class="notification-empty compact-empty">
                                        <strong>全体宛の通知はありません。</strong>
                                    </div>
                                <?php else: ?>
                                    <div class="notification-mini-list">
                                        <?php foreach ($headerGlobalNotificationItems as $notification): ?>
                                            <?php
                                            $isRead = !empty($notification["read_at"]);
                                            $linkUrl = header_safe_internal_link($notification["link_url"] ?? "", "/dashboard/notifications/#global");
                                            ?>
                                            <article class="notification-mini-item-wrap <?php echo $isRead ? 'is-read' : 'is-unread'; ?>">
                                                <a href="<?php echo h($linkUrl); ?>" class="notification-mini-item global-mini-item">
                                                    <span><?php echo h(header_notification_datetime((string)$notification["published_at"])); ?></span>
                                                    <strong><?php echo h((string)$notification["title"]); ?></strong>
                                                    <p><?php echo h((string)($notification["body"] ?: "全体向けのお知らせです。")); ?></p>
                                                </a>

                                                <?php if (!$isRead): ?>
                                                    <form method="post" action="/dashboard/notifications/mark-read.php" class="mini-read-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo h($notificationReadToken); ?>">
                                                        <input type="hidden" name="action" value="mark_one">
                                                        <input type="hidden" name="type" value="global">
                                                        <input type="hidden" name="notification_id" value="<?php echo h((string)$notification["id"]); ?>">
                                                        <input type="hidden" name="redirect" value="<?php echo h((string)($_SERVER["REQUEST_URI"] ?? "/")); ?>">
                                                        <button type="submit">既読</button>
                                                    </form>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        </div>

                        <a href="/dashboard/notifications/" class="notification-list-link">
                            通知一覧へ
                        </a>
                    </div>
                </details>
            <?php endif; ?>

            <div class="header-auth desktop-auth">
                <?php if ($headerUser): ?>
                    <a href="/logout/" class="header-link header-logout-outline">ログアウト</a>
                    <a href="/dashboard/" class="header-button header-mypage-button">マイページ</a>
                <?php else: ?>
                    <a href="/login/" class="header-link">ログイン</a>
                    <a href="/register/" class="header-button">新規登録</a>
                <?php endif; ?>
            </div>

            <button class="menu-toggle" id="menuToggle" type="button" aria-label="メニューを開く" aria-controls="headerDrawer" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </div>
</header>

<div class="drawer-backdrop" id="drawerBackdrop"></div>

<aside class="header-drawer" id="headerDrawer" aria-hidden="true">
    <div class="drawer-head">
        <div>
            <p>Menu</p>
            <h2>HC Platform</h2>
        </div>

        <button class="drawer-close" id="drawerClose" type="button" aria-label="メニューを閉じる">×</button>
    </div>

    <nav class="drawer-nav" aria-label="サイトメニュー">
        <a href="/"><span>Top</span><strong>トップページ</strong></a>
        <a href="/services/"><span>Services</span><strong>事業一覧</strong></a>
        <a href="/news/"><span>News</span><strong>お知らせ</strong></a>
        <a href="/contact/"><span>Contact</span><strong>お問い合わせ</strong></a>
        <a href="/operator/"><span>Company</span><strong>運営情報</strong></a>
        <a href="/terms/"><span>Terms</span><strong>利用規約</strong></a>
        <a href="/privacy/"><span>Privacy</span><strong>プライバシーポリシー</strong></a>
    </nav>

    <div class="drawer-account">
        <p>Account</p>

        <?php if ($headerUser): ?>
            <a href="/dashboard/" class="drawer-account-button primary">マイページ</a>
            <a href="/dashboard/servers/" class="drawer-account-button ghost">契約中サーバー</a>
            <a href="/dashboard/notifications/" class="drawer-account-button ghost">通知一覧</a>
            <a href="/logout/" class="drawer-account-button ghost">ログアウト</a>
        <?php else: ?>
            <a href="/login/" class="drawer-account-button primary">ログイン</a>
            <a href="/register/" class="drawer-account-button ghost">新規登録</a>
        <?php endif; ?>
    </div>

    <?php if ($operationLinks): ?>
        <div class="drawer-admin">
            <p>Operation</p>

            <?php foreach ($operationLinks as $link): ?>
                <a href="<?php echo h((string)$link["url"]); ?>">
                    <?php echo h((string)$link["label"]); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</aside>

<script>
(() => {
    window.__hcHeaderNotificationAsyncReady = true;

    const notificationBox = document.querySelector(".header-notifications");

    if (!notificationBox) {
        return;
    }

    let shouldReloadOnClose = false;
    let isSubmittingRead = false;

    const closeNotificationBox = () => {
        if (!notificationBox.open) {
            return;
        }

        notificationBox.open = false;

        if (shouldReloadOnClose) {
            window.location.reload();
        }
    };

    const markOneAsReadInUi = (form) => {
        const itemWrap = form.closest(".notification-mini-item-wrap");

        if (itemWrap) {
            itemWrap.classList.remove("is-unread");
            itemWrap.classList.add("is-read");
        }

        form.remove();
    };

    const markPanelAsReadInUi = (form) => {
        const panel = form.closest(".notification-tab-panel");

        if (!panel) {
            return;
        }

        panel.querySelectorAll(".notification-mini-item-wrap.is-unread").forEach((item) => {
            item.classList.remove("is-unread");
            item.classList.add("is-read");
        });

        panel.querySelectorAll(".mini-read-form").forEach((readForm) => {
            readForm.remove();
        });

        const button = form.querySelector("button");

        if (button) {
            button.disabled = true;
        }
    };

    const submitReadForm = async (form) => {
        if (isSubmittingRead) {
            return;
        }

        const actionInput = form.querySelector("input[name='action']");
        const action = actionInput ? actionInput.value : "";
        const submitButton = form.querySelector("button[type='submit'], button");

        isSubmittingRead = true;

        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json",
                },
                credentials: "same-origin",
            });

            const result = await response.json().catch(() => null);

            if (!response.ok || !result || !result.ok) {
                if (submitButton) {
                    submitButton.disabled = false;
                }

                return;
            }

            shouldReloadOnClose = true;

            if (action === "mark_one") {
                markOneAsReadInUi(form);
            }

            if (
                action === "mark_all_personal" ||
                action === "mark_all_global" ||
                action === "mark_all"
            ) {
                markPanelAsReadInUi(form);
            }
        } catch (error) {
            if (submitButton) {
                submitButton.disabled = false;
            }
        } finally {
            isSubmittingRead = false;
        }
    };

    document.addEventListener("submit", (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!notificationBox.contains(form)) {
            return;
        }

        if (!form.action.includes("/dashboard/notifications/mark-read.php")) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        submitReadForm(form);
    }, true);

    document.addEventListener("click", (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const readButton = target.closest(".mini-read-form button, .notification-mark-all-form button");

        if (readButton) {
            const form = readButton.closest("form");

            if (
                form &&
                notificationBox.contains(form) &&
                form.action.includes("/dashboard/notifications/mark-read.php")
            ) {
                event.preventDefault();
                event.stopPropagation();

                submitReadForm(form);
                return;
            }
        }

        if (!notificationBox.open) {
            return;
        }

        if (notificationBox.contains(target)) {
            return;
        }

        closeNotificationBox();
    }, true);

    notificationBox.addEventListener("toggle", () => {
        if (!notificationBox.open && shouldReloadOnClose) {
            window.location.reload();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") {
            return;
        }

        closeNotificationBox();
    });
})();
</script>
