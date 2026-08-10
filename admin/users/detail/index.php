<?php
session_start();

require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/customers/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "ユーザー詳細 | HC Platform";
$pageDescription = "HC Platformの管理者向けユーザー詳細ページです。";
$pageCss = "/admin/users/detail/detail.css";

$errors = [];
$targetUser = null;
$userId = (int)($_GET["id"] ?? 0);
$loginLogs = [];

if ($userId <= 0) {
    $errors[] = "ユーザーIDが正しくありません。";
}

try {
    if (!$errors) {
        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT
                id,
                username,
                email,
                role,
                status,
                email_verified,
                email_verified_at,
                register_ip,
                last_login,
                terms_accepted,
                terms_accepted_at,
                login_failed_count,
                locked_until,
                deleted_at,
                created_at
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ":id" => $userId,
        ]);

        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            $errors[] = "対象ユーザーが見つかりません。";
        } else {
            $logStmt = $pdo->prepare("
                SELECT
                    id,
                    user_id,
                    email,
                    ip_address,
                    result,
                    message,
                    created_at
                FROM login_logs
                WHERE user_id = :user_id
                OR email = :email
                ORDER BY created_at DESC
                LIMIT 20
            ");

            $logStmt->execute([
                ":user_id" => $targetUser["id"],
                ":email" => $targetUser["email"],
            ]);

            $loginLogs = $logStmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    $errors[] = "ユーザー情報の取得中にエラーが発生しました。";
}

function detail_datetime($value): string
{
    if (empty($value)) {
        return "未記録";
    }

    return date("Y/m/d H:i", strtotime($value));
}

require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="user-detail-page">

    <section class="user-detail-hero">
        <div class="container user-detail-hero-grid">

            <div class="user-detail-copy reveal">
                <p class="eyebrow">Admin / User Detail</p>
                <h1>ユーザー詳細</h1>
                <p>
                    HC Accountに登録されているユーザーの詳細情報を確認できます。
                    権限変更や状態変更はユーザー一覧ページから行えます。
                </p>
            </div>

            <aside class="user-detail-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section user-detail-section">
        <div class="container">

            <?php if ($errors): ?>
                <div class="detail-alert reveal">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>

                    <a href="/admin/users/" class="back-button">ユーザー一覧へ戻る</a>
                </div>
            <?php endif; ?>

            <?php if ($targetUser): ?>
                <div class="detail-layout">

                    <section class="detail-main-card reveal">
                        <div class="detail-profile">
                            <div class="detail-avatar">
                                <?php echo h(mb_substr($targetUser["username"], 0, 1)); ?>
                            </div>

                            <div>
                                <p class="eyebrow">User</p>
                                <h2><?php echo h($targetUser["username"]); ?></h2>
                                <p><?php echo h($targetUser["email"]); ?></p>
                            </div>
                        </div>

                        <div class="detail-badges">
                            <span class="role-badge <?php echo h(role_badge_class($targetUser["role"])); ?>">
                                <?php echo h(role_label($targetUser["role"])); ?>
                            </span>

                            <?php if ($targetUser["status"] === "active"): ?>
                                <span class="status-badge active">有効</span>
                            <?php elseif ($targetUser["status"] === "deleted"): ?>
                                <span class="status-badge deleted">削除済み</span>
                            <?php else: ?>
                                <span class="status-badge inactive"><?php echo h($targetUser["status"]); ?></span>
                            <?php endif; ?>

                            <?php if (!empty($targetUser["email_verified"])): ?>
                                <span class="verify-badge verified">メール認証済み</span>
                            <?php else: ?>
                                <span class="verify-badge unverified">メール未認証</span>
                            <?php endif; ?>
                        </div>

                        <div class="detail-info-grid">
                            <div>
                                <span>ID</span>
                                <strong>#<?php echo h((string)$targetUser["id"]); ?></strong>
                            </div>

                            <div>
                                <span>ユーザー名</span>
                                <strong><?php echo h($targetUser["username"]); ?></strong>
                            </div>

                            <div>
                                <span>メールアドレス</span>
                                <strong><?php echo h($targetUser["email"]); ?></strong>
                            </div>

                            <div>
                                <span>権限</span>
                                <strong><?php echo h(role_label($targetUser["role"])); ?></strong>
                            </div>

                            <div>
                                <span>状態</span>
                                <strong><?php echo h($targetUser["status"]); ?></strong>
                            </div>

                            <div>
                                <span>メール認証</span>
                                <strong><?php echo !empty($targetUser["email_verified"]) ? "認証済み" : "未認証"; ?></strong>
                            </div>

                            <div>
                                <span>メール認証日時</span>
                                <strong><?php echo h(detail_datetime($targetUser["email_verified_at"])); ?></strong>
                            </div>

                            <div>
                                <span>登録IP</span>
                                <strong><?php echo h($targetUser["register_ip"] ?? "未記録"); ?></strong>
                            </div>

                            <div>
                                <span>利用規約同意</span>
                                <strong><?php echo !empty($targetUser["terms_accepted"]) ? "同意済み" : "未同意"; ?></strong>
                            </div>

                            <div>
                                <span>利用規約同意日時</span>
                                <strong><?php echo h(detail_datetime($targetUser["terms_accepted_at"])); ?></strong>
                            </div>

                            <div>
                                <span>ログイン失敗回数</span>
                                <strong><?php echo h((string)$targetUser["login_failed_count"]); ?></strong>
                            </div>

                            <div>
                                <span>ロック解除予定</span>
                                <strong><?php echo h(detail_datetime($targetUser["locked_until"])); ?></strong>
                            </div>

                            <div>
                                <span>登録日</span>
                                <strong><?php echo h(detail_datetime($targetUser["created_at"])); ?></strong>
                            </div>

                            <div>
                                <span>最終ログイン</span>
                                <strong><?php echo h(detail_datetime($targetUser["last_login"])); ?></strong>
                            </div>

                            <div>
                                <span>削除日時</span>
                                <strong><?php echo h(detail_datetime($targetUser["deleted_at"])); ?></strong>
                            </div>
                        </div>
                                                <section class="login-history-section">
                            <div class="login-history-head">
                                <div>
                                    <p class="eyebrow">Login History</p>
                                    <h3>ログイン履歴</h3>
                                </div>
                                <span>直近20件</span>
                            </div>

                            <div class="login-history-table-wrap">
                                <table class="login-history-table">
                                    <thead>
                                        <tr>
                                            <th>日時</th>
                                            <th>結果</th>
                                            <th>メール</th>
                                            <th>IPアドレス</th>
                                            <th>メッセージ</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php if (!$loginLogs): ?>
                                            <tr>
                                                <td colspan="5" class="empty-cell">
                                                    ログイン履歴はまだありません。
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                        <?php foreach ($loginLogs as $log): ?>
                                            <tr>
                                                <td>
                                                    <?php echo h(detail_datetime($log["created_at"])); ?>
                                                </td>

                                                <td>
                                                    <?php if ($log["result"] === "success"): ?>
                                                        <span class="log-result success">成功</span>
                                                    <?php elseif ($log["result"] === "failed" || $log["result"] === "failure"): ?>
                                                        <span class="log-result failed">失敗</span>
                                                    <?php elseif ($log["result"] === "locked"): ?>
                                                        <span class="log-result locked">ロック</span>
                                                    <?php else: ?>
                                                        <span class="log-result other"><?php echo h($log["result"] ?? "-"); ?></span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>
                                                    <?php echo h($log["email"] ?? "-"); ?>
                                                </td>

                                                <td>
                                                    <?php echo h($log["ip_address"] ?? "-"); ?>
                                                </td>

                                                <td>
                                                    <?php echo h($log["message"] ?? "-"); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </section>

                    <aside class="detail-side-card reveal">
                        <h3>管理操作</h3>
                        <p>
                            権限変更・状態変更はユーザー一覧ページから行えます。
                            詳細ページでは対象ユーザーの確認を行います。
                        </p>

                        <div class="detail-actions">
                            <a href="/admin/users/" class="button primary">ユーザー一覧へ戻る</a>
                            <a href="/admin/" class="button ghost">管理者ページへ戻る</a>
                        </div>
                    </aside>

                </div>
            <?php endif; ?>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
