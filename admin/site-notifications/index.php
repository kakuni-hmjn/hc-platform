<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "全体通知管理 | HC Platform";
$pageDescription = "HC Platformの全体宛通知管理ページです。";
$pageCss = "/admin/site-notifications/site-notifications.css";

$pdo = db();

$errors = [];
$successMessage = "";

if (empty($_SESSION["site_notifications_token"])) {
    $_SESSION["site_notifications_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["site_notifications_token"];

$statusOptions = [
    "published" => "公開",
    "draft" => "下書き",
    "hidden" => "非表示",
];

function sn_ensure_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_notifications (
            id SERIAL PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            body TEXT,
            link_url VARCHAR(255),
            target_scope VARCHAR(40) NOT NULL DEFAULT 'all',
            status VARCHAR(40) NOT NULL DEFAULT 'published',
            priority INTEGER NOT NULL DEFAULT 0,
            published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )
    ");
}

function sn_normalize_published_at(string $value): string
{
    $value = trim($value);

    if ($value === "") {
        return date("Y-m-d H:i:s");
    }

    try {
        return (new DateTime($value))->format("Y-m-d H:i:s");
    } catch (Throwable $e) {
        throw new RuntimeException("公開日時が不正です。");
    }
}

function sn_validate_internal_url(?string $url): ?string
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
    sn_ensure_table($pdo);
} catch (Throwable $e) {
    $errors[] = "全体通知テーブルの準備に失敗しました。";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$errors) {
    $token = (string)($_POST["csrf_token"] ?? "");
    $action = trim((string)($_POST["action"] ?? ""));

    if (!hash_equals($csrfToken, $token)) {
        $errors[] = "不正な操作です。もう一度やり直してください。";
    } else {
        try {
            if ($action === "add") {
                $title = trim((string)($_POST["title"] ?? ""));
                $body = trim((string)($_POST["body"] ?? ""));
                $linkUrl = sn_validate_internal_url($_POST["link_url"] ?? "");
                $status = trim((string)($_POST["status"] ?? "published"));
                $priority = (int)($_POST["priority"] ?? 0);
                $publishedAt = sn_normalize_published_at((string)($_POST["published_at"] ?? ""));

                if ($title === "") {
                    throw new RuntimeException("タイトルを入力してください。");
                }

                if (!array_key_exists($status, $statusOptions)) {
                    throw new RuntimeException("公開状態が不正です。");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO site_notifications
                    (
                        title,
                        body,
                        link_url,
                        target_scope,
                        status,
                        priority,
                        published_at,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        :title,
                        :body,
                        :link_url,
                        'all',
                        :status,
                        :priority,
                        :published_at,
                        NOW(),
                        NOW()
                    )
                ");

                $stmt->execute([
                    "title" => $title,
                    "body" => $body !== "" ? $body : null,
                    "link_url" => $linkUrl,
                    "status" => $status,
                    "priority" => $priority,
                    "published_at" => $publishedAt,
                ]);

                $successMessage = "全体宛通知を追加しました。";
            } else {
                throw new RuntimeException("不明な操作です。");
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="site-notifications-page">
    <section class="site-notifications-hero">
        <div class="container site-notifications-hero-grid">
            <div class="site-notifications-copy reveal">
                <p class="eyebrow">Admin / Site Notifications</p>
                <h1>全体通知管理</h1>
                <p>
                    ログイン中ユーザーの通知欄に表示する、全体宛のお知らせを作成します。
                    通知一覧は表示しません。
                </p>
            </div>

            <aside class="site-notifications-status-card reveal">
                <span>Create</span>
                <h2>全体通知</h2>
                <p>全ユーザー向けのお知らせを作成します。</p>
            </aside>
        </div>
    </section>

    <section class="section site-notifications-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                <a href="/dashboard/notifications/#global" class="sub-button">表示確認</a>
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

            <section class="add-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Create</p>
                        <h2>全体宛通知を追加</h2>
                    </div>
                </div>

                <form method="post" action="/admin/site-notifications/" class="notification-form">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="form-grid">
                        <div>
                            <label>タイトル</label>
                            <input type="text" name="title" placeholder="例: メンテナンスのお知らせ" required>
                        </div>

                        <div>
                            <label>リンクURL</label>
                            <input type="text" name="link_url" placeholder="例: /news/">
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

                        <div>
                            <label>公開日時</label>
                            <input type="datetime-local" name="published_at" value="<?php echo h(date("Y-m-d\TH:i")); ?>">
                        </div>
                    </div>

                    <div>
                        <label>本文</label>
                        <textarea name="body" rows="4" placeholder="通知本文"></textarea>
                    </div>

                    <button type="submit" class="save-button">追加する</button>
                </form>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
