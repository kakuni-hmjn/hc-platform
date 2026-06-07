<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$adminUser = require_role("admin");

$pageTitle = "個別通知管理 | HC Platform";
$pageDescription = "特定ユーザーへあなた宛通知を送信します。";
$pageCss = "/admin/user-notifications/user-notifications.css";

$pdo = db();

$errors = [];
$successMessage = "";
$users = [];

if (empty($_SESSION["user_notifications_token"])) {
    $_SESSION["user_notifications_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["user_notifications_token"];

$statusOptions = [
    "published" => "公開",
    "draft" => "下書き",
    "hidden" => "非表示",
];

function un_validate_url(?string $url): ?string
{
    $url = trim((string)$url);

    if ($url === "") {
        return null;
    }

    if (!str_starts_with($url, "/") || str_contains($url, "://")) {
        throw new RuntimeException("リンクURLは / から始まる内部URLで入力してください。");
    }

    return $url;
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_direct_notifications (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            title VARCHAR(180) NOT NULL,
            body TEXT,
            link_url VARCHAR(255),
            status VARCHAR(40) NOT NULL DEFAULT 'published',
            priority INTEGER NOT NULL DEFAULT 0,
            published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )
    ");
} catch (Throwable $e) {
    $errors[] = "個別通知テーブルの準備に失敗しました。";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$errors) {
    $token = (string)($_POST["csrf_token"] ?? "");
    $action = trim((string)($_POST["action"] ?? ""));

    if (!hash_equals($csrfToken, $token)) {
        $errors[] = "不正な操作です。もう一度やり直してください。";
    } else {
        try {
            if ($action === "add") {
                $userId = filter_input(INPUT_POST, "user_id", FILTER_VALIDATE_INT, [
                    "options" => ["min_range" => 1],
                ]);
                $title = trim((string)($_POST["title"] ?? ""));
                $body = trim((string)($_POST["body"] ?? ""));
                $linkUrl = un_validate_url($_POST["link_url"] ?? "");
                $status = trim((string)($_POST["status"] ?? "published"));
                $priority = (int)($_POST["priority"] ?? 0);

                if (!$userId) {
                    throw new RuntimeException("送信先ユーザーを選択してください。");
                }

                if ($title === "") {
                    throw new RuntimeException("タイトルを入力してください。");
                }

                if (!array_key_exists($status, $statusOptions)) {
                    throw new RuntimeException("公開状態が不正です。");
                }

                $checkUser = $pdo->prepare("SELECT id FROM users WHERE id = :id LIMIT 1");
                $checkUser->execute(["id" => $userId]);

                if (!$checkUser->fetch()) {
                    throw new RuntimeException("送信先ユーザーが見つかりません。");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO user_direct_notifications
                    (
                        user_id,
                        created_by,
                        title,
                        body,
                        link_url,
                        status,
                        priority,
                        published_at,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        :user_id,
                        :created_by,
                        :title,
                        :body,
                        :link_url,
                        :status,
                        :priority,
                        NOW(),
                        NOW(),
                        NOW()
                    )
                ");

                $stmt->execute([
                    "user_id" => $userId,
                    "created_by" => (int)$adminUser["id"],
                    "title" => $title,
                    "body" => $body !== "" ? $body : null,
                    "link_url" => $linkUrl,
                    "status" => $status,
                    "priority" => $priority,
                ]);

                $successMessage = "個別通知を送信しました。";
            } else {
                throw new RuntimeException("不明な操作です。");
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

try {
    $users = $pdo->query("
        SELECT id, username, email, role
        FROM users
        ORDER BY id ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $errors[] = "ユーザー一覧の取得に失敗しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="user-notifications-page">
    <section class="user-notifications-hero">
        <div class="container user-notifications-hero-grid">
            <div class="user-notifications-copy reveal">
                <p class="eyebrow">Admin / User Notifications</p>
                <h1>個別通知管理</h1>
                <p>
                    特定ユーザーへ「あなた宛」の通知を送信します。
                    送信済み一覧は表示しません。
                </p>
            </div>

            <aside class="user-notifications-status-card reveal">
                <span>Send</span>
                <h2>個別通知</h2>
                <p>ユーザーを選択して通知を送信します。</p>
            </aside>
        </div>
    </section>

    <section class="section user-notifications-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                <a href="/dashboard/notifications/#personal" class="sub-button">表示確認</a>
            </div>

            <?php if ($successMessage !== ""): ?>
                <div class="flash-message flash-success">
                    <?php echo h($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="send-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Send</p>
                        <h2>個別通知を送信</h2>
                    </div>
                </div>

                <form method="post" action="/admin/user-notifications/" class="notification-form">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="form-grid">
                        <div>
                            <label>送信先ユーザー</label>
                            <select name="user_id" required>
                                <option value="">選択してください</option>
                                <?php foreach ($users as $targetUser): ?>
                                    <option value="<?php echo h((string)$targetUser["id"]); ?>">
                                        #<?php echo h((string)$targetUser["id"]); ?>
                                        /
                                        <?php echo h((string)$targetUser["username"]); ?>
                                        /
                                        <?php echo h((string)$targetUser["email"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>タイトル</label>
                            <input type="text" name="title" placeholder="例: サーバー作成について確認があります" required>
                        </div>

                        <div>
                            <label>リンクURL</label>
                            <input type="text" name="link_url" placeholder="例: /dashboard/servers/">
                        </div>

                        <div>
                            <label>状態</label>
                            <select name="status">
                                <?php foreach ($statusOptions as $status => $label): ?>
                                    <option value="<?php echo h($status); ?>" <?php echo $status === "published" ? "selected" : ""; ?>>
                                        <?php echo h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>優先度</label>
                            <input type="number" name="priority" value="0">
                        </div>
                    </div>

                    <div>
                        <label>本文</label>
                        <textarea name="body" rows="4" placeholder="通知本文"></textarea>
                    </div>

                    <button type="submit" class="save-button">送信する</button>
                </form>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
