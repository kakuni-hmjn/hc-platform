<?php
session_start();

require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("staff");

$pageTitle = "ユーザー確認 | HC Platform";
$pageDescription = "HC Platformのスタッフ向けユーザー確認ページです。";
$pageCss = "/staff/users/users.css";

$errors = [];
$keyword = trim($_GET["q"] ?? "");
$users = [];

try {
    $pdo = db();

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
    $errors[] = "ユーザー情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="staff-users-page">

    <section class="staff-users-hero">
        <div class="container staff-users-hero-grid">

            <div class="staff-users-copy reveal">
                <p class="eyebrow">Staff / Users</p>
                <h1>ユーザー確認</h1>
                <p>
                    HC Accountに登録されているユーザーを確認できます。
                    このページはスタッフ向けの閲覧専用ページです。
                </p>
            </div>

            <aside class="staff-users-status-card reveal">
                <span>スタッフアクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section staff-users-section">
        <div class="container">

            <div class="staff-users-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Search</p>
                        <h2>ユーザー検索</h2>
                    </div>
                    <a href="/staff/" class="back-button">スタッフページへ戻る</a>
                </div>

                <form action="/staff/users/" method="get" class="user-search-form">
                    <input
                        type="text"
                        name="q"
                        value="<?php echo h($keyword); ?>"
                        placeholder="ユーザー名またはメールアドレスで検索"
                    >
                    <button type="submit">検索</button>

                    <?php if ($keyword !== ""): ?>
                        <a href="/staff/users/" class="clear-button">クリア</a>
                    <?php endif; ?>
                </form>

                <?php if ($errors): ?>
                    <div class="staff-alert">
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
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!$users): ?>
                                <tr>
                                    <td colspan="7" class="empty-cell">
                                        ユーザーが見つかりません。
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($users as $targetUser): ?>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="table-note">
                    最大100件まで表示しています。権限変更やアカウント状態変更は管理者ページで行います。
                </p>
            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>