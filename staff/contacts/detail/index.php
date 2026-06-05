<?php
session_start();

require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/csrf.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("staff");

$pageTitle = "お問い合わせ詳細 | HC Platform";
$pageDescription = "HC Platformのスタッフ向けお問い合わせ詳細ページです。";
$pageCss = "/staff/contacts/detail/detail.css";

$errors = [];
$messages = [];
$contact = null;

$contactId = (int)($_GET["id"] ?? 0);

$allowedStatuses = [
    "open" => "未対応",
    "in_progress" => "対応中",
    "closed" => "完了",
];

function contact_category_label(string $category): string
{
    $labels = [
        "general" => "一般",
        "account" => "アカウント",
        "service" => "サービス",
        "billing" => "契約・支払い",
        "bug" => "不具合",
        "other" => "その他",
    ];

    return $labels[$category] ?? $category;
}

function contact_status_label(string $status): string
{
    $labels = [
        "open" => "未対応",
        "in_progress" => "対応中",
        "closed" => "完了",
    ];

    return $labels[$status] ?? $status;
}

function contact_datetime($value): string
{
    if (empty($value)) {
        return "未記録";
    }

    return date("Y/m/d H:i", strtotime($value));
}

if ($contactId <= 0) {
    $errors[] = "お問い合わせIDが正しくありません。";
}

try {
    $pdo = db();

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $csrfToken = $_POST["csrf_token"] ?? "";
        $newStatus = $_POST["status"] ?? "";

        if (!csrf_check($csrfToken)) {
            $errors[] = "不正なリクエストです。もう一度お試しください。";
        }

        if (!array_key_exists($newStatus, $allowedStatuses)) {
            $errors[] = "指定された対応状態が正しくありません。";
        }

        if (!$errors && $contactId > 0) {
            $stmt = $pdo->prepare("
                UPDATE contacts
                SET
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                ":status" => $newStatus,
                ":id" => $contactId,
            ]);

            $messages[] = "お問い合わせの対応状態を変更しました。";
        }
    }

    if (!$errors || $contactId > 0) {
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.user_id,
                c.name,
                c.email,
                c.category,
                c.subject,
                c.message,
                c.status,
                c.ip_address,
                c.created_at,
                c.updated_at,
                u.username,
                u.role,
                u.status AS user_status
            FROM contacts c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ":id" => $contactId,
        ]);

        $contact = $stmt->fetch();

        if (!$contact) {
            $errors[] = "お問い合わせが見つかりません。";
        }
    }
} catch (Throwable $e) {
    $errors[] = "お問い合わせ情報の処理中にエラーが発生しました。";
}

require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="contact-detail-page">

    <section class="contact-detail-hero">
        <div class="container contact-detail-hero-grid">
            <div class="contact-detail-copy reveal">
                <p class="eyebrow">Staff / Contact Detail</p>
                <h1>お問い合わせ詳細</h1>
                <p>
                    送信されたお問い合わせ内容を確認し、対応状態を変更できます。
                </p>
            </div>

            <aside class="contact-detail-status-card reveal">
                <span>スタッフアクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>
        </div>
    </section>

    <section class="section contact-detail-section">
        <div class="container">

            <?php if ($messages): ?>
                <div class="detail-success reveal">
                    <?php foreach ($messages as $msg): ?>
                        <p><?php echo h($msg); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="detail-alert reveal">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>

                    <a href="/staff/contacts/" class="back-button">お問い合わせ一覧へ戻る</a>
                </div>
            <?php endif; ?>

            <?php if ($contact): ?>
                <div class="contact-detail-layout">

                    <section class="contact-main-card reveal">
                        <div class="contact-title-block">
                            <p class="eyebrow">Contact #<?php echo h((string)$contact["id"]); ?></p>
                            <h2><?php echo h($contact["subject"]); ?></h2>

                            <div class="contact-badges">
                                <span class="category-badge">
                                    <?php echo h(contact_category_label($contact["category"])); ?>
                                </span>

                                <span class="contact-status status-<?php echo h($contact["status"]); ?>">
                                    <?php echo h(contact_status_label($contact["status"])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="message-box">
                            <h3>お問い合わせ内容</h3>
                            <p><?php echo nl2br(h($contact["message"])); ?></p>
                        </div>

                        <div class="contact-info-grid">
                            <div>
                                <span>送信者名</span>
                                <strong><?php echo h($contact["name"]); ?></strong>
                            </div>

                            <div>
                                <span>メールアドレス</span>
                                <strong><?php echo h($contact["email"]); ?></strong>
                            </div>

                            <div>
                                <span>ログインユーザー</span>
                                <strong>
                                    <?php echo !empty($contact["username"])
                                        ? h($contact["username"])
                                        : "未ログイン";
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <span>ユーザーID</span>
                                <strong>
                                    <?php echo !empty($contact["user_id"])
                                        ? "#" . h((string)$contact["user_id"])
                                        : "なし";
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <span>IPアドレス</span>
                                <strong><?php echo h($contact["ip_address"] ?? "未記録"); ?></strong>
                            </div>

                            <div>
                                <span>送信日時</span>
                                <strong><?php echo h(contact_datetime($contact["created_at"])); ?></strong>
                            </div>

                            <div>
                                <span>更新日時</span>
                                <strong><?php echo h(contact_datetime($contact["updated_at"])); ?></strong>
                            </div>

                            <div>
                                <span>現在の状態</span>
                                <strong><?php echo h(contact_status_label($contact["status"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <aside class="contact-side-card reveal">
                        <h3>対応状態を変更</h3>
                        <p>
                            対応状況に合わせて状態を更新してください。
                        </p>

                        <form action="/staff/contacts/detail/?id=<?php echo h((string)$contact["id"]); ?>" method="post" class="status-form">
                            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                            <label for="status">対応状態</label>
                            <select id="status" name="status">
                                <?php foreach ($allowedStatuses as $statusValue => $statusLabel): ?>
                                    <option value="<?php echo h($statusValue); ?>" <?php echo $contact["status"] === $statusValue ? "selected" : ""; ?>>
                                        <?php echo h($statusLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit">状態を更新</button>
                        </form>

                        <div class="side-actions">
                            <a href="/staff/contacts/" class="button primary">一覧へ戻る</a>

                            <?php if (!empty($contact["user_id"])): ?>
                                <a href="/admin/users/detail/?id=<?php echo h((string)$contact["user_id"]); ?>" class="button ghost">
                                    ユーザー詳細
                                </a>
                            <?php endif; ?>
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