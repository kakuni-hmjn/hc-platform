<?php
session_start();

require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "お知らせ管理 | HC Platform";
$pageDescription = "HC Platformの管理者向けお知らせ管理ページです。";
$pageCss = "/admin/news/news.css";

$errors = [];
$newsItems = [];

$keyword = trim($_GET["q"] ?? "");
$category = $_GET["category"] ?? "";
$status = $_GET["status"] ?? "";

$categoryLabels = [
    "" => "すべて",
    "site_update" => "サイト更新",
    "feature" => "機能追加",
    "important" => "重要",
    "maintenance" => "メンテナンス",
    "service" => "サービス",
    "other" => "その他",
];

$statusLabels = [
    "" => "すべて",
    "published" => "公開",
    "draft" => "下書き",
    "hidden" => "非公開",
];

if (!array_key_exists($category, $categoryLabels)) {
    $category = "";
}

if (!array_key_exists($status, $statusLabels)) {
    $status = "";
}

function admin_news_category_label(string $category): string
{
    $labels = [
        "site_update" => "サイト更新",
        "feature" => "機能追加",
        "important" => "重要",
        "maintenance" => "メンテナンス",
        "service" => "サービス",
        "other" => "その他",
    ];

    return $labels[$category] ?? "その他";
}

function admin_news_status_label(string $status): string
{
    $labels = [
        "published" => "公開",
        "draft" => "下書き",
        "hidden" => "非公開",
    ];

    return $labels[$status] ?? $status;
}

try {
    $pdo = db();

    $sql = "
        SELECT
            id,
            title,
            slug,
            category,
            summary,
            has_image,
            image_path,
            has_related_link,
            related_url,
            related_button_text,
            is_pinned,
            status,
            published_at,
            created_at,
            updated_at
        FROM news
        WHERE 1 = 1
    ";

    $params = [];

    if ($keyword !== "") {
        $sql .= "
            AND (
                    title ILIKE :keyword
                 OR slug ILIKE :keyword
                 OR summary ILIKE :keyword
                 OR body ILIKE :keyword
            )
        ";

        $params[":keyword"] = "%" . $keyword . "%";
    }

    if ($category !== "") {
        $sql .= " AND category = :category";
        $params[":category"] = $category;
    }

    if ($status !== "") {
        $sql .= " AND status = :status";
        $params[":status"] = $status;
    }

    $sql .= "
        ORDER BY
            is_pinned DESC,
            published_at DESC NULLS LAST,
            id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $newsItems = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "お知らせ情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="admin-news-page">

    <section class="admin-news-hero">
        <div class="container admin-news-hero-grid">

            <div class="admin-news-copy reveal">
                <p class="eyebrow">Admin / News</p>
                <h1>お知らせ管理</h1>
                <p>
                    HC Platformのトップページやお知らせ一覧に表示する情報を管理できます。
                    カテゴリ、画像、関連ページ、公開状態を確認できます。
                </p>
            </div>

            <aside class="admin-news-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section admin-news-section">
        <div class="container">

            <div class="news-panel reveal">

                <div class="panel-head">
                    <div>
                        <p class="eyebrow">News</p>
                        <h2>お知らせ一覧</h2>
                    </div>

                    <div class="panel-actions">
                        <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                        <a href="/admin/news/edit/" class="create-button">新規追加</a>
                    </div>
                </div>

                <form action="/admin/news/" method="get" class="news-search-form">
                    <input
                        type="text"
                        name="q"
                        value="<?php echo h($keyword); ?>"
                        placeholder="タイトル・slug・概要・本文で検索"
                    >

                    <select name="category">
                        <?php foreach ($categoryLabels as $value => $label): ?>
                            <option value="<?php echo h($value); ?>" <?php echo $category === $value ? "selected" : ""; ?>>
                                <?php echo h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status">
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?php echo h($value); ?>" <?php echo $status === $value ? "selected" : ""; ?>>
                                <?php echo h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">検索</button>

                    <?php if ($keyword !== "" || $category !== "" || $status !== ""): ?>
                        <a href="/admin/news/" class="clear-button">クリア</a>
                    <?php endif; ?>
                </form>

                <?php if ($errors): ?>
                    <div class="admin-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="news-table-wrap">
                    <table class="news-table">
                        <thead>
                            <tr>
                                <th>日付</th>
                                <th>お知らせ</th>
                                <th>カテゴリ</th>
                                <th>画像</th>
                                <th>関連ページ</th>
                                <th>固定</th>
                                <th>公開状態</th>
                                <th>操作</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!$newsItems): ?>
                                <tr>
                                    <td colspan="8" class="empty-cell">
                                        お知らせ情報が見つかりません。
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($newsItems as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item["published_at"])): ?>
                                            <strong><?php echo h(date("Y/m/d", strtotime($item["published_at"]))); ?></strong>
                                            <span class="table-subtext"><?php echo h(date("H:i", strtotime($item["published_at"]))); ?></span>
                                        <?php else: ?>
                                            <strong>未設定</strong>
                                            <span class="table-subtext">公開日時なし</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="news-cell">
                                            <strong><?php echo h($item["title"]); ?></strong>
                                            <span><?php echo h($item["slug"]); ?></span>
                                            <p><?php echo h($item["summary"]); ?></p>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="category-badge category-<?php echo h($item["category"]); ?>">
                                            <?php echo h(admin_news_category_label($item["category"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (!empty($item["has_image"]) && !empty($item["image_path"])): ?>
                                            <span class="yes-badge">あり</span>
                                        <?php else: ?>
                                            <span class="none-badge">なし</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($item["has_related_link"]) && !empty($item["related_url"])): ?>
                                            <a class="related-badge" href="<?php echo h($item["related_url"]); ?>" target="_blank" rel="noopener">
                                                あり
                                            </a>
                                        <?php else: ?>
                                            <span class="none-badge">なし</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($item["is_pinned"])): ?>
                                            <span class="pin-badge">固定</span>
                                        <?php else: ?>
                                            <span class="none-badge">通常</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?php echo h($item["status"]); ?>">
                                            <?php echo h(admin_news_status_label($item["status"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a class="edit-button" href="/admin/news/edit/?id=<?php echo h((string)$item["id"]); ?>">
                                            編集
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="table-note">
                    一般公開されるのは公開状態が「公開」で、公開日時が現在時刻以前のお知らせです。
                    画像・関連ページはチェックを入れたお知らせだけ表示されます。
                </p>

            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>