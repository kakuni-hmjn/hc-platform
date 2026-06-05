<?php
session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = current_user();

$slug = trim($_GET["slug"] ?? "");

$errors = [];
$item = null;

function news_detail_category_label(string $category): string
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
    if ($slug === "") {
        $errors[] = "お知らせが指定されていません。";
    } else {
        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                slug,
                category,
                summary,
                body,
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
            WHERE slug = :slug
              AND status = 'published'
              AND published_at IS NOT NULL
              AND published_at <= NOW()
            LIMIT 1
        ");

        $stmt->execute([
            ":slug" => $slug,
        ]);

        $item = $stmt->fetch();

        if (!$item) {
            $errors[] = "お知らせが見つかりませんでした。";
        }
    }
} catch (Throwable $e) {
    $errors[] = "お知らせ情報の取得中にエラーが発生しました。";
}

$pageTitle = $item ? $item["title"] . " | HC Platform" : "お知らせ詳細 | HC Platform";
$pageDescription = $item ? $item["summary"] : "HC Platformのお知らせ詳細ページです。";
$pageCss = "/news/detail/detail.css";

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="news-detail-page">

    <section class="news-detail-hero">
        <div class="container">

            <?php if ($errors): ?>
                <div class="detail-error reveal">
                    <p class="eyebrow">News</p>
                    <h1>お知らせを表示できません</h1>

                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>

                    <a href="/news/" class="button primary">お知らせ一覧へ戻る</a>
                </div>
            <?php else: ?>
                <article class="detail-article reveal">

                    <div class="detail-meta">
                        <span class="category-badge category-<?php echo h($item["category"]); ?>">
                            <?php echo h(news_detail_category_label($item["category"])); ?>
                        </span>

                        <?php if (!empty($item["is_pinned"])): ?>
                            <span class="pin-badge">重要</span>
                        <?php endif; ?>

                        <time datetime="<?php echo h(date("Y-m-d", strtotime($item["published_at"]))); ?>">
                            <?php echo h(date("Y.m.d H:i", strtotime($item["published_at"]))); ?>
                        </time>
                    </div>

                    <h1><?php echo h($item["title"]); ?></h1>

                    <p class="detail-summary">
                        <?php echo h($item["summary"]); ?>
                    </p>

                    <?php if (!empty($item["has_image"]) && !empty($item["image_path"])): ?>
                        <div class="detail-image">
                            <img src="<?php echo h($item["image_path"]); ?>" alt="<?php echo h($item["title"]); ?>">
                        </div>
                    <?php endif; ?>

                    <div class="detail-body">
                        <?php
                        $paragraphs = preg_split("/\r\n|\r|\n/", $item["body"]);
                        foreach ($paragraphs as $paragraph):
                            $paragraph = trim($paragraph);
                            if ($paragraph === "") {
                                continue;
                            }
                        ?>
                            <p><?php echo nl2br(h($paragraph)); ?></p>
                        <?php endforeach; ?>
                    </div>

                    <div class="detail-actions">
                        <?php if (!empty($item["has_related_link"]) && !empty($item["related_url"]) && !empty($item["related_button_text"])): ?>
                            <a href="<?php echo h($item["related_url"]); ?>" class="button primary">
                                <?php echo h($item["related_button_text"]); ?>
                            </a>
                        <?php endif; ?>

                        <a href="/news/" class="button ghost">お知らせ一覧へ戻る</a>
                    </div>

                </article>
            <?php endif; ?>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>