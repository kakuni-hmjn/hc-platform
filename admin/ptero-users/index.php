<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/ptero_users.php";
require_once __DIR__ . "/../../lib/pterodactyl.php";

$adminUser = require_role("admin");

$pageTitle = "ゲームサーバーパネルユーザー紐付け | HC Platform";
$pageDescription = "HCユーザーとゲームサーバーパネルユーザーの紐付けを管理します。";
$pageCss = "/admin/ptero-users/ptero-users.css";

$pdo = db();

$users = [];
$errors = [];

$flash = $_SESSION["admin_ptero_users_flash"] ?? null;
unset($_SESSION["admin_ptero_users_flash"]);

if (empty($_SESSION["admin_ptero_users_token"])) {
    $_SESSION["admin_ptero_users_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["admin_ptero_users_token"];

function admin_ptero_datetime(?string $value): string
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

try {
    hc_ptero_user_links_ensure_schema($pdo);

    $stmt = $pdo->query("
        SELECT
            u.id AS user_id,
            u.username AS hc_username,
            u.email AS hc_email,
            u.role,
            u.status AS hc_status,
            u.created_at AS user_created_at,

            pul.id AS link_id,
            pul.ptero_user_id,
            pul.ptero_external_id,
            pul.ptero_uuid,
            pul.username AS ptero_username,
            pul.email AS ptero_email,
            pul.status AS link_status,
            pul.initial_password,
            pul.initial_password_created_at,
            pul.password_setup_completed_at,
            pul.last_synced_at,

            COUNT(ps.id) AS server_count
        FROM users u
        LEFT JOIN ptero_user_links pul ON pul.user_id = u.id
        LEFT JOIN game_server_orders gso ON gso.user_id = u.id
        LEFT JOIN ptero_servers ps ON ps.order_id = gso.id AND ps.deleted_at IS NULL
        GROUP BY
            u.id,
            u.username,
            u.email,
            u.role,
            u.status,
            u.created_at,
            pul.id,
            pul.ptero_user_id,
            pul.ptero_external_id,
            pul.ptero_uuid,
            pul.username,
            pul.email,
            pul.status,
            pul.initial_password,
            pul.initial_password_created_at,
            pul.password_setup_completed_at,
            pul.last_synced_at
        ORDER BY u.id ASC
    ");

    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "ゲームサーバーパネルユーザー紐付け情報の取得に失敗しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="admin-ptero-users-page">
    <section class="admin-ptero-users-hero">
        <div class="container admin-ptero-users-hero-grid">
            <div class="admin-ptero-users-copy reveal">
                <p class="eyebrow">Admin / ゲームサーバーパネル Users</p>
                <h1>ゲームサーバーパネルユーザー紐付け</h1>
                <p>
                    HCユーザーとゲームサーバーパネルユーザーの対応関係を管理します。
                    既存ゲームサーバーパネルアカウントがある場合は、ここから手動で紐付けできます。
                </p>
            </div>

            <aside class="admin-ptero-users-status-card reveal">
                <span>User Links</span>
                <h2><?php echo h((string)count($users)); ?> 件</h2>
                <p>HCユーザーとゲームサーバーパネルユーザーの連携状態です。</p>
            </aside>
        </div>
    </section>

    <section class="section admin-ptero-users-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                <a href="/admin/server-orders/provision/" class="sub-button">ゲームサーバーパネル作成へ</a>
                <a href="/admin/ptero/" class="sub-button">ゲームサーバーパネル確認へ</a>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="admin-ptero-users-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Links</p>
                        <h2>ユーザー紐付け一覧</h2>
                    </div>
                </div>

                <?php if (!$users): ?>
                    <div class="empty-box">
                        <h3>ユーザーがありません。</h3>
                        <p>HCアカウントが作成されるとここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="ptero-user-card-list">
                        <?php foreach ($users as $row): ?>
                            <?php
                            $isLinked = !empty($row["ptero_user_id"]);
                            $hasInitialPassword = !empty($row["initial_password"]);
                            $externalId = (string)($row["ptero_external_id"] ?: ("hc_user_" . (string)$row["user_id"]));
                            ?>
                            <article class="ptero-user-card <?php echo $isLinked ? "is-linked" : "is-not-linked"; ?>">
                                <div class="ptero-user-card-head">
                                    <div>
                                        <span class="user-id">HC User #<?php echo h((string)$row["user_id"]); ?></span>
                                        <strong class="link-badge">
                                            <?php echo $isLinked ? "連携済み" : "未連携"; ?>
                                        </strong>

                                        <?php if ($hasInitialPassword): ?>
                                            <strong class="password-badge">初回PWあり</strong>
                                        <?php endif; ?>
                                    </div>

                                    <small>登録: <?php echo h(admin_ptero_datetime((string)$row["user_created_at"])); ?></small>
                                </div>

                                <div class="ptero-user-main">
                                    <div class="hc-user-info">
                                        <h3><?php echo h((string)$row["hc_username"]); ?></h3>
                                        <p><?php echo h((string)$row["hc_email"]); ?></p>
                                    </div>

                                    <div class="ptero-link-info">
                                        <span>パネルユーザー</span>
                                        <strong>
                                            <?php if ($isLinked): ?>
                                                #<?php echo h((string)$row["ptero_user_id"]); ?>
                                                /
                                                <?php echo h((string)($row["ptero_username"] ?: "-")); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </strong>
                                        <p>
                                            <?php echo h((string)($row["ptero_email"] ?: "未連携")); ?>
                                        </p>
                                    </div>

                                    <div class="ptero-link-info">
                                        <span>External ID</span>
                                        <strong><?php echo h($externalId); ?></strong>
                                        <p>サーバー数: <?php echo h((string)((int)$row["server_count"])); ?> 件</p>
                                    </div>
                                </div>

                                <form method="post" action="/admin/ptero-users/link.php" class="ptero-link-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo h((string)$row["user_id"]); ?>">

                                    <div class="form-grid">
                                        <label>
                                            <span>パネルユーザーID</span>
                                            <input
                                                type="number"
                                                name="ptero_user_id"
                                                min="1"
                                                value="<?php echo h((string)($row["ptero_user_id"] ?: "")); ?>"
                                                required
                                                placeholder="例: 12"
                                            >
                                        </label>

                                        <label>
                                            <span>パネルユーザー名</span>
                                            <input
                                                type="text"
                                                name="ptero_username"
                                                value="<?php echo h((string)($row["ptero_username"] ?: ("hc" . (string)$row["user_id"] . "_" . preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$row["hc_username"]))))); ?>"
                                                required
                                            >
                                        </label>

                                        <label>
                                            <span>パネルメール</span>
                                            <input
                                                type="email"
                                                name="ptero_email"
                                                value="<?php echo h((string)($row["ptero_email"] ?: $row["hc_email"])); ?>"
                                                required
                                            >
                                        </label>

                                        <label>
                                            <span>External ID</span>
                                            <input
                                                type="text"
                                                name="ptero_external_id"
                                                value="<?php echo h($externalId); ?>"
                                                required
                                            >
                                        </label>

                                        <label>
                                            <span>パネルUUID 任意</span>
                                            <input
                                                type="text"
                                                name="ptero_uuid"
                                                value="<?php echo h((string)($row["ptero_uuid"] ?: "")); ?>"
                                                placeholder="空でもOK"
                                            >
                                        </label>
                                    </div>

                                    <div class="ptero-user-actions">
                                        <button type="submit" class="primary-action">
                                            紐付け保存
                                        </button>

                                        <?php if ($isLinked): ?>
                                            <button type="submit" formaction="/admin/ptero-users/reset-password.php" class="secondary-action">
                                                初回パスワード再発行
                                            </button>

                                            <button type="submit" formaction="/admin/ptero-users/unlink.php" class="danger-action">
                                                紐付け解除
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
