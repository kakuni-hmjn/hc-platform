<?php
session_start();

require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/csrf.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/customers/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "ユーザー管理 | HC Platform";
$pageDescription = "HC Platformの管理者向けユーザー管理ページです。";
$pageCss = "/admin/users/users.css";

$errors = [];
$messages = [];
$keyword = trim($_GET["q"] ?? "");

$allowedRoles = [
    "user" => "一般ユーザー",
    "staff" => "スタッフ",
    "developer" => "デベロッパー",
    "admin" => "管理者",
    "owner" => "オーナー",
];

$allowedStatuses = [
    "active" => "有効",
    "suspended" => "停止中",
    "deleted" => "削除済み",
];

try {
    $pdo = db();

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $csrfToken = $_POST["csrf_token"] ?? "";
        $action = $_POST["action"] ?? "";
        $targetUserId = (int)($_POST["user_id"] ?? 0);

        if (!csrf_check($csrfToken)) {
            $errors[] = "不正なリクエストです。もう一度お試しください。";
        }

        if ($targetUserId <= 0) {
            $errors[] = "対象ユーザーが正しくありません。";
        }

        if ($targetUserId === (int)$user["id"]) {
            $errors[] = "自分自身の権限や状態はこの画面から変更できません。";
        }

        if (!$errors) {
            $stmt = $pdo->prepare("
                SELECT id, username, email, role, status
                FROM users
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                ":id" => $targetUserId,
            ]);

            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                $errors[] = "対象ユーザーが見つかりません。";
            } elseif ($targetUser["role"] === "owner" && $user["role"] !== "owner") {
                $errors[] = "オーナー権限のユーザーは、オーナーのみ変更できます。";
            } else {
                if ($action === "update_role") {
                    $newRole = $_POST["role"] ?? "";

                    if (!array_key_exists($newRole, $allowedRoles)) {
                        $errors[] = "指定された権限が正しくありません。";
                    }

                    if ($newRole === "owner" && $user["role"] !== "owner") {
                        $errors[] = "オーナー権限を付与できるのはオーナーのみです。";
                    }

                    if (!$errors) {
                        $update = $pdo->prepare("
                            UPDATE users
                            SET role = :role
                            WHERE id = :id
                        ");

                        $update->execute([
                            ":role" => $newRole,
                            ":id" => $targetUserId,
                        ]);

                        $messages[] = h($targetUser["username"]) . " さんの権限を変更しました。";
                    }
                } elseif ($action === "update_status") {
                    $newStatus = $_POST["status"] ?? "";

                    if (!array_key_exists($newStatus, $allowedStatuses)) {
                        $errors[] = "指定された状態が正しくありません。";
                    }

                    if ($targetUser["role"] === "owner" && $user["role"] !== "owner") {
                        $errors[] = "オーナー権限のユーザー状態は、オーナーのみ変更できます。";
                    }

                    if (!$errors) {
                        $update = $pdo->prepare("
                            UPDATE users
                            SET
                                status = :status,
                                deleted_at = CASE
                                    WHEN :status_for_deleted = 'deleted' THEN COALESCE(deleted_at, NOW())
                                    ELSE NULL
                                END
                            WHERE id = :id
                        ");

                        $update->execute([
                            ":status" => $newStatus,
                            ":status_for_deleted" => $newStatus,
                            ":id" => $targetUserId,
                        ]);

                        $messages[] = h($targetUser["username"]) . " さんのアカウント状態を変更しました。";
                    }
                } else {
                    $errors[] = "操作内容が正しくありません。";
                }
            }
        }
    }

    if ($keyword !== "") {
        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                email,
                role,
                status,
                email_verified,
                created_at,
                last_login,
                deleted_at
            FROM users
            WHERE username ILIKE :keyword
               OR email ILIKE :keyword
            ORDER BY id DESC
            LIMIT 100
        ");

        $stmt->execute([
            ":keyword" => "%" . $keyword . "%",
        ]);
    } else {
        $stmt = $pdo->query("
            SELECT
                id,
                username,
                email,
                role,
                status,
                email_verified,
                created_at,
                last_login,
                deleted_at
            FROM users
            ORDER BY id DESC
            LIMIT 100
        ");
    }

    $users = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "ユーザー情報の処理中にエラーが発生しました。";
    $users = [];
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="admin-users-page">

    <section class="admin-users-hero">
        <div class="container admin-users-hero-grid">

            <div class="admin-users-copy reveal">
                <p class="eyebrow">Admin / Users</p>
                <h1>ユーザー管理</h1>
                <p>
                    HC Accountに登録されているユーザーを確認し、権限やアカウント状態を変更できます。
                </p>
            </div>

            <aside class="admin-users-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section admin-users-section">
        <div class="container">

            <div class="admin-users-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Search</p>
                        <h2>ユーザー検索</h2>
                    </div>
                    <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                </div>

                <form action="/admin/users/" method="get" class="user-search-form">
                    <input
                        type="text"
                        name="q"
                        value="<?php echo h($keyword); ?>"
                        placeholder="ユーザー名またはメールアドレスで検索"
                    >
                    <button type="submit">検索</button>

                    <?php if ($keyword !== ""): ?>
                        <a href="/admin/users/" class="clear-button">クリア</a>
                    <?php endif; ?>
                </form>

                <?php if ($messages): ?>
                    <div class="admin-success">
                        <?php foreach ($messages as $message): ?>
                            <p><?php echo $message; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="admin-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="users-table-wrap">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ユーザー</th>
                                <th>権限</th>
                                <th>状態</th>
                                <th>メール認証</th>
                                <th>登録日</th>
                                <th>最終ログイン</th>
                                <th>操作</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!$users): ?>
                                <tr>
                                    <td colspan="8" class="empty-cell">
                                        ユーザーが見つかりません。
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($users as $targetUser): ?>
                                <?php
                                    $isSelf = (int)$targetUser["id"] === (int)$user["id"];
                                    $isOwnerTarget = $targetUser["role"] === "owner";
                                    $canEdit = !$isSelf && (!$isOwnerTarget || $user["role"] === "owner");
                                ?>

                                <tr>
                                    <td>
                                        <strong>#<?php echo h((string)$targetUser["id"]); ?></strong>
                                    </td>

                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                <?php echo h(mb_substr($targetUser["username"], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo h($targetUser["username"]); ?></strong>
                                                <span><?php echo h($targetUser["email"]); ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="role-badge <?php echo h(role_badge_class($targetUser["role"])); ?>">
                                            <?php echo h(role_label($targetUser["role"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($targetUser["status"] === "active"): ?>
                                            <span class="status-badge active">有効</span>
                                        <?php elseif ($targetUser["status"] === "deleted"): ?>
                                            <span class="status-badge deleted">削除済み</span>
                                        <?php else: ?>
                                            <span class="status-badge inactive"><?php echo h($targetUser["status"]); ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($targetUser["email_verified"])): ?>
                                            <span class="verify-badge verified">認証済み</span>
                                        <?php else: ?>
                                            <span class="verify-badge unverified">未認証</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php echo h(date("Y/m/d H:i", strtotime($targetUser["created_at"]))); ?>
                                    </td>

                                    <td>
                                        <?php echo !empty($targetUser["last_login"])
                                            ? h(date("Y/m/d H:i", strtotime($targetUser["last_login"])))
                                            : "未記録";
                                        ?>
                                    </td>

                                    <td>
                                        <div class="user-actions">
                                            <a class="detail-link-button" href="/admin/users/detail/?id=<?php echo h((string)$targetUser["id"]); ?>">
                                                詳細
                                            </a>
                                            <?php if ($canEdit): ?>
                                            <div class="user-actions">
                                                <form action="/admin/users/<?php echo $keyword !== "" ? "?q=" . urlencode($keyword) : ""; ?>" method="post" class="inline-action-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="update_role">
                                                    <input type="hidden" name="user_id" value="<?php echo h((string)$targetUser["id"]); ?>">

                                                    <select name="role">
                                                        <?php foreach ($allowedRoles as $roleValue => $roleName): ?>
                                                            <?php if ($roleValue === "owner" && $user["role"] !== "owner") continue; ?>
                                                            <option value="<?php echo h($roleValue); ?>" <?php echo $targetUser["role"] === $roleValue ? "selected" : ""; ?>>
                                                                <?php echo h($roleName); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <button type="submit">権限変更</button>
                                                </form>

                                                <form action="/admin/users/<?php echo $keyword !== "" ? "?q=" . urlencode($keyword) : ""; ?>" method="post" class="inline-action-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="user_id" value="<?php echo h((string)$targetUser["id"]); ?>">

                                                    <select name="status">
                                                        <?php foreach ($allowedStatuses as $statusValue => $statusName): ?>
                                                            <option value="<?php echo h($statusValue); ?>" <?php echo $targetUser["status"] === $statusValue ? "selected" : ""; ?>>
                                                                <?php echo h($statusName); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <button type="submit">状態変更</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="locked-note">
                                                <?php echo $isSelf ? "自分自身" : "保護中"; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="table-note">
                    最大100件まで表示しています。自分自身の権限・状態は変更できません。
                </p>
            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
