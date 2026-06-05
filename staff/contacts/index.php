<?php
session_start();

require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("staff");

$pageTitle = "お問い合わせ確認 | HC Platform";
$pageDescription = "HC Platformのスタッフ向けお問い合わせ確認ページです。";
$pageCss = "/staff/contacts/contacts.css";

$errors = [];
$keyword = trim($_GET["q"] ?? "");
$status = $_GET["status"] ?? "";

$allowedStatuses = [
    "" => "すべて",
    "open" => "未対応",
    "in_progress" => "対応中",
    "closed" => "完了",
];

if (!array_key_exists($status, $allowedStatuses)) {
    $status = "";
}

$contacts = [];

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

try {
    $pdo = db();

    $sql = "
        SELECT
            c.id,
            c.user_id,
            c.name,
            c.email,
            c.category,
            c.subject,
            c.status,
            c.ip_address,
            c.created_at,
            c.updated_at,
            u.username
        FROM contacts c
        LEFT JOIN users u ON u.id = c.user_id
        WHERE 1 = 1
    ";

    $params = [];

    if ($keyword !== "") {
        $sql .= "
            AND (
                c.name ILIKE :keyword
                OR c.email ILIKE :keyword
                OR c.subject ILIKE :keyword
                OR c.message ILIKE :keyword
                OR u.username ILIKE :keyword
            )
        ";

        $params[":keyword"] = "%" . $keyword . "%";
    }

    if ($status !== "") {
        $sql .= " AND c.status = :status";
        $params[":status"] = $status;
    }

    $sql .= "
        ORDER BY c.created_at DESC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $contacts = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "お問い合わせ情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="staff-contacts-page">

    <section class="staff-contacts-hero">
        <div class="container staff-contacts-hero-grid">

            <div class="staff-contacts-copy reveal">
                <p class="eyebrow">Staff / Contacts</p>
                <h1>お問い合わせ確認</h1>
                <p>
                    ユーザーや外部から送信されたお問い合わせを確認できます。
                    詳細ページから対応状態を変更できます。
                </p>
            </div>

            <aside class="staff-contacts-status-card reveal">
                <span>スタッフアクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section staff-contacts-section">
        <div class="container">

            <div class="contacts-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Contacts</p>
                        <h2>お問い合わせ一覧</h2>
                    </div>

                    <a href="/staff/" class="back-button">スタッフページへ戻る</a>
                </div>

                <form action="/staff/contacts/" method="get" class="contact-search-form">
                    <input
                        type="text"
                        name="q"
                        value="<?php echo h($keyword); ?>"
                        placeholder="名前・メール・件名・本文で検索"
                    >

                    <select name="status">
                        <?php foreach ($allowedStatuses as $key => $label): ?>
                            <option value="<?php echo h($key); ?>" <?php echo $status === $key ? "selected" : ""; ?>>
                                <?php echo h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">検索</button>

                    <?php if ($keyword !== "" || $status !== ""): ?>
                        <a href="/staff/contacts/" class="clear-button">クリア</a>
                    <?php endif; ?>
                </form>

                <?php if ($errors): ?>
                    <div class="staff-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="contacts-table-wrap">
                    <table class="contacts-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>件名</th>
                                <th>送信者</th>
                                <th>種別</th>
                                <th>状態</th>
                                <th>送信日時</th>
                                <th>操作</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!$contacts): ?>
                                <tr>
                                    <td colspan="7" class="empty-cell">
                                        お問い合わせはありません。
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo h((string)$contact["id"]); ?></strong>
                                    </td>

                                    <td>
                                        <div class="subject-cell">
                                            <strong><?php echo h($contact["subject"]); ?></strong>
                                            <span>
                                                <?php echo !empty($contact["username"])
                                                    ? "ログイン: " . h($contact["username"])
                                                    : "未ログイン";
                                                ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="sender-cell">
                                            <strong><?php echo h($contact["name"]); ?></strong>
                                            <span><?php echo h($contact["email"]); ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="category-badge">
                                            <?php echo h(contact_category_label($contact["category"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="contact-status status-<?php echo h($contact["status"]); ?>">
                                            <?php echo h(contact_status_label($contact["status"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo h(date("Y/m/d H:i", strtotime($contact["created_at"]))); ?>
                                    </td>

                                    <td>
                                        <a
                                            class="detail-link-button"
                                            href="/staff/contacts/detail/?id=<?php echo h((string)$contact["id"]); ?>"
                                        >
                                            詳細
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="table-note">
                    最大100件まで表示しています。詳細ページからお問い合わせ内容の確認と対応状態の変更ができます。
                </p>
            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>