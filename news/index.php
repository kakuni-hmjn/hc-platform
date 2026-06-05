<?php
session_start();

require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/db.php";

$currentUser = current_user();

$pageTitle = "お知らせ | HC Platform";
$pageDescription = "HC Platformのお知らせ一覧ページです。サイト更新、機能追加、メンテナンス、サービス情報などを掲載します。";
$pageCss = "/news/news.css";

$errors = [];
$newsItems = [];

$keyword = trim($_GET["q"] ?? "");
$category = $_GET["category"] ?? "";

$categoryLabels = [
    "" => "すべて",
    "site_update" => "サイト更新",
    "feature" => "機能追加",
    "important" => "重要",
    "maintenance" => "メンテナンス",
    "service" => "サービス",
    "other" => "その他",
];

if (!array_key_exists($category, $categoryLabels)) {
    $category = "";
}

function news_category_label(string $category): string
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
        WHERE status = 'published'
          AND published_at IS NOT NULL
          AND published_at <= NOW()
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

    $sql .= "
        ORDER BY
            is_pinned DESC,
            published_at DESC,
            id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $newsItems = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "お知らせ情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="news-page">

    <section class="news-hero">
        <div class="container news-hero-grid">

            <div class="news-copy reveal">
                <p class="eyebrow">News</p>
                <h1>お知らせ</h1>
                <p>
                    HC Platformの更新情報、機能追加、メンテナンス、サービス情報などを掲載しています。
                </p>

                <div class="news-actions">
                    <a href="/services/" class="button primary">事業一覧を見る</a>
                    <a href="/contact/" class="button ghost">お問い合わせ</a>
                </div>
            </div>

            <aside class="news-status-card reveal">
                <span>Latest Information</span>
                <h2>更新情報をまとめて確認</h2>
                <p>
                    サイトの変更点や新機能、重要なお知らせをここから確認できます。
                </p>
            </aside>

        </div>
    </section>

    <section class="section news-list-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Information</p>
                <h2>お知らせ一覧</h2>
                <p>
                    キーワードやカテゴリで、必要なお知らせを探せます。
                </p>
            </div>

            <div class="news-panel reveal">

                <div class="news-panel-head">
                    <div>
                        <span>Search</span>
                        <h3>お知らせを探す</h3>
                    </div>

                    <a href="/contact/" class="panel-contact-button">相談する</a>
                </div>

                <form action="/news/" method="get" class="news-search-form">
                    <input
                        type="text"
                        name="q"
                        value="<?php echo h($keyword); ?>"
                        placeholder="キーワードで検索 例: トップページ / メンテナンス"
                    >

                    <select name="category">
                        <?php foreach ($categoryLabels as $value => $label): ?>
                            <option value="<?php echo h($value); ?>" <?php echo $category === $value ? "selected" : ""; ?>>
                                <?php echo h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">検索</button>

                    <?php if ($keyword !== "" || $category !== ""): ?>
                        <a href="/news/" class="clear-button">クリア</a>
                    <?php endif; ?>
                </form>

                <?php if ($errors): ?>
                    <div class="news-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($keyword !== "" || $category !== ""): ?>
                    <p class="search-result-text">
                        <?php if ($keyword !== ""): ?>
                            「<?php echo h($keyword); ?>」
                        <?php endif; ?>

                        <?php if ($category !== ""): ?>
                            <?php echo $keyword !== "" ? " / " : ""; ?>
                            <?php echo h($categoryLabels[$category]); ?>
                        <?php endif; ?>

                        の検索結果
                    </p>
                <?php endif; ?>

                <div class="news-list">
                    <?php if (!$newsItems && !$errors): ?>
                        <div class="empty-news">
                            <h3>お知らせが見つかりませんでした。</h3>
                            <p>
                                キーワードやカテゴリを変えて検索するか、お問い合わせからご相談ください。
                            </p>
                            <a href="/contact/" class="button primary">お問い合わせ</a>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($newsItems as $item): ?>
                        <article class="news-card reveal">
                            <?php if (!empty($item["has_image"]) && !empty($item["image_path"])): ?>
                                <a href="/news/detail/?slug=<?php echo h($item["slug"]); ?>" class="news-image">
                                    <img src="<?php echo h($item["image_path"]); ?>" alt="<?php echo h($item["title"]); ?>">
                                </a>
                            <?php endif; ?>

                            <div class="news-card-body">
                                <div class="news-meta">
                                    <span class="category-badge category-<?php echo h($item["category"]); ?>">
                                        <?php echo h(news_category_label($item["category"])); ?>
                                    </span>

                                    <?php if (!empty($item["is_pinned"])): ?>
                                        <span class="pin-badge">重要</span>
                                    <?php endif; ?>

                                    <time datetime="<?php echo h(date("Y-m-d", strtotime($item["published_at"]))); ?>">
                                        <?php echo h(date("Y.m.d", strtotime($item["published_at"]))); ?>
                                    </time>
                                </div>

                                <h3>
                                    <a href="/news/detail/?slug=<?php echo h($item["slug"]); ?>">
                                        <?php echo h($item["title"]); ?>
                                    </a>
                                </h3>

                                <p><?php echo h($item["summary"]); ?></p>

                                <div class="news-card-actions">
                                    <a href="/news/detail/?slug=<?php echo h($item["slug"]); ?>" class="news-detail-button">
                                        詳細を見る
                                        <span>→</span>
                                    </a>

                                    <?php if (!empty($item["has_related_link"]) && !empty($item["related_url"]) && !empty($item["related_button_text"])): ?>
                                        <a href="<?php echo h($item["related_url"]); ?>" class="news-related-button">
                                            <?php echo h($item["related_button_text"]); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>