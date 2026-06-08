<?php

session_start();

require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/permissions.php";

$user = require_role("staff");

$pageTitle = "スタッフページ | HC Platform";
$pageDescription = "HC Platformのスタッフ向けダッシュボードです。";
$pageCss = "/staff/staff.css";

$pdo = db();

$openContactCount = 0;
$inProgressContactCount = 0;
$totalUserCount = 0;
$unverifiedUserCount = 0;
$pendingServerOrderCount = 0;
$pendingPlanChangeCount = 0;
$recentContacts = [];

try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) FILTER (WHERE status = 'open') AS open_count,
            COUNT(*) FILTER (WHERE status = 'in_progress') AS in_progress_count
        FROM contacts
    ");
    $row = $stmt->fetch();

    $openContactCount = (int)($row["open_count"] ?? 0);
    $inProgressContactCount = (int)($row["in_progress_count"] ?? 0);
} catch (Throwable $e) {
    $openContactCount = 0;
    $inProgressContactCount = 0;
}

try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_count,
            COUNT(*) FILTER (WHERE email_verified = false) AS unverified_count
        FROM users
        WHERE deleted_at IS NULL
    ");
    $row = $stmt->fetch();

    $totalUserCount = (int)($row["total_count"] ?? 0);
    $unverifiedUserCount = (int)($row["unverified_count"] ?? 0);
} catch (Throwable $e) {
    $totalUserCount = 0;
    $unverifiedUserCount = 0;
}

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM game_server_orders
        WHERE status IN ('pending_payment', 'paid', 'creating', 'provision_failed')
    ");
    $row = $stmt->fetch();

    $pendingServerOrderCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $pendingServerOrderCount = 0;
}

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM server_order_plan_change_requests
        WHERE status = 'pending'
    ");
    $row = $stmt->fetch();

    $pendingPlanChangeCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $pendingPlanChangeCount = 0;
}

try {
    $stmt = $pdo->query("
        SELECT
            c.id,
            c.name,
            c.email,
            c.category,
            c.subject,
            c.status,
            c.created_at,
            u.username
        FROM contacts c
        LEFT JOIN users u ON u.id = c.user_id
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT 5
    ");

    $recentContacts = $stmt->fetchAll();
} catch (Throwable $e) {
    $recentContacts = [];
}

function staff_contact_status_label(string $status): string
{
    return match ($status) {
        "open" => "未対応",
        "in_progress" => "対応中",
        "closed" => "完了",
        default => $status,
    };
}

function staff_contact_category_label(string $category): string
{
    return match ($category) {
        "general" => "一般",
        "account" => "アカウント",
        "service" => "サービス",
        "billing" => "契約・支払い",
        "bug" => "不具合",
        "other" => "その他",
        default => $category,
    };
}

function staff_datetime(?string $value): string
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

require_once __DIR__ . "/../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="staff-page">
    <section class="staff-hero">
        <div class="container staff-hero-grid">
            <div class="staff-copy reveal">
                <p class="eyebrow">Staff Console</p>
                <h1>スタッフページ</h1>
                <p>
                    問い合わせ確認、ユーザー確認、契約状況の確認など、
                    運営スタッフ向けの対応作業をまとめるページです。
                </p>

                <div class="staff-quick-links">
                    <a href="/staff/contacts/">問い合わせ確認</a>
                    <a href="/staff/users/">ユーザー確認</a>
                    <a href="/staff/server-orders/">申込確認</a>
                </div>
            </div>

            <aside class="staff-status-card reveal">
                <span>ログイン中</span>
                <h2><?php echo h((string)$user["username"]); ?></h2>
                <p><?php echo h(role_label((string)$user["role"])); ?></p>

                <div class="staff-mini-stats">
                    <div class="<?php echo $openContactCount > 0 ? 'has-alert' : ''; ?>">
                        <strong><?php echo h((string)$openContactCount); ?></strong>
                        <small>未対応問い合わせ</small>
                    </div>

                    <div>
                        <strong><?php echo h((string)$inProgressContactCount); ?></strong>
                        <small>対応中問い合わせ</small>
                    </div>

                    <div>
                        <strong><?php echo h((string)$totalUserCount); ?></strong>
                        <small>有効ユーザー</small>
                    </div>

                    <div class="<?php echo $pendingServerOrderCount > 0 ? 'has-alert' : ''; ?>">
                        <strong><?php echo h((string)$pendingServerOrderCount); ?></strong>
                        <small>確認対象申込</small>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="section staff-section">
        <div class="container">
            <div class="section-head reveal">
                <p class="eyebrow">Operation</p>
                <h2>スタッフメニュー</h2>
            </div>

            <div class="staff-menu-grid reveal">
                <a href="/staff/contacts/" class="staff-menu-card staff-card-contact">
                    <span class="staff-card-mark" data-icon="contact_support"></span>
                    <h3>問い合わせ確認</h3>
                    <p>
                        ユーザーや外部から送信された問い合わせを確認します。
                        未対応・対応中・完了の状態を追跡できます。
                    </p>

                    <?php if ($openContactCount > 0): ?>
                        <strong class="staff-card-badge">
                            未対応 <?php echo h((string)$openContactCount); ?> 件
                        </strong>
                    <?php endif; ?>
                </a>

                <a href="/staff/users/" class="staff-menu-card staff-card-user">
                    <span class="staff-card-mark" data-icon="group"></span>
                    <h3>ユーザー確認</h3>
                    <p>
                        登録ユーザー、権限、状態、メール認証状況を確認します。
                        権限変更などの管理操作は管理者ページで行います。
                    </p>

                    <?php if ($unverifiedUserCount > 0): ?>
                        <strong class="staff-sub-badge">
                            未認証 <?php echo h((string)$unverifiedUserCount); ?> 件
                        </strong>
                    <?php endif; ?>
                </a>

                <a href="/staff/server-orders/" class="staff-menu-card staff-card-order">
                    <span class="staff-card-mark" data-icon="dns"></span>
                    <h3>申込・契約確認</h3>
                    <p>
                        ゲームサーバー申込、決済状態、作成状態を確認します。
                        スタッフは閲覧・メモ・ユーザー連絡を中心に使います。
                    </p>

                    <?php if ($pendingServerOrderCount > 0): ?>
                        <strong class="staff-card-badge">
                            確認 <?php echo h((string)$pendingServerOrderCount); ?> 件
                        </strong>
                    <?php endif; ?>
                </a>

                <a href="/dashboard/notifications/" class="staff-menu-card staff-card-notice">
                    <span class="staff-card-mark" data-icon="notifications"></span>
                    <h3>通知確認</h3>
                    <p>
                        自分宛・全体宛の通知を確認します。
                        運営連絡や作業依頼の確認に使います。
                    </p>
                </a>

                <a href="/admin/user-notifications/" class="staff-menu-card staff-card-direct">
                    <span class="staff-card-mark" data-icon="person"></span>
                    <h3>個別通知送信</h3>
                    <p>
                        必要に応じてユーザーへ個別通知を送信します。
                        支払い確認や設定確認の連絡に使います。
                    </p>
                </a>

                <a href="/dashboard/" class="staff-menu-card staff-card-dashboard">
                    <span class="staff-card-mark" data-icon="home"></span>
                    <h3>マイページへ戻る</h3>
                    <p>
                        通常のアカウントダッシュボードへ戻ります。
                    </p>
                </a>
            </div>
        </div>
    </section>

    <section class="section staff-recent-section">
        <div class="container">
            <div class="staff-recent-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Recent Contacts</p>
                        <h2>最近の問い合わせ</h2>
                    </div>

                    <a href="/staff/contacts/" class="panel-link">一覧を見る</a>
                </div>

                <?php if (!$recentContacts): ?>
                    <div class="staff-empty-box">
                        <h3>問い合わせはまだありません。</h3>
                        <p>問い合わせが送信されると、ここに直近の内容が表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="staff-recent-list">
                        <?php foreach ($recentContacts as $contact): ?>
                            <a href="/staff/contacts/detail/?id=<?php echo h((string)$contact["id"]); ?>" class="staff-recent-item status-<?php echo h((string)$contact["status"]); ?>">
                                <div>
                                    <span>
                                        <?php echo h(staff_datetime((string)$contact["created_at"])); ?>
                                        /
                                        <?php echo h(staff_contact_category_label((string)$contact["category"])); ?>
                                    </span>
                                    <strong><?php echo h((string)$contact["subject"]); ?></strong>
                                    <p>
                                        <?php echo h((string)($contact["username"] ?: $contact["name"])); ?>
                                        /
                                        <?php echo h((string)$contact["email"]); ?>
                                    </p>
                                </div>

                                <em><?php echo h(staff_contact_status_label((string)$contact["status"])); ?></em>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
