<?php
session_start();

require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/csrf.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/admin/site/news/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "お知らせ編集 | HC Platform";
$pageDescription = "HC Platformの管理者向けお知らせ編集ページです。";
$pageCss = "/admin/news/edit/edit.css";

$errors = [];
$messages = [];

$newsId = (int)($_GET["id"] ?? 0);
$isEdit = $newsId > 0;

$categoryLabels = [
    "site_update" => "サイト更新",
    "feature" => "機能追加",
    "important" => "重要",
    "maintenance" => "メンテナンス",
    "service" => "サービス",
    "other" => "その他",
];

$statusLabels = [
    "draft" => "下書き",
    "published" => "公開",
    "hidden" => "非公開",
];

$title = "";
$slug = "";
$category = "other";
$summary = "";
$body = "";

$hasImage = false;
$imagePath = "";

$hasRelatedLink = false;
$relatedUrl = "";
$relatedButtonText = "";

$isPinned = false;
$status = "draft";
$publishedAt = date("Y-m-d\TH:i");

function format_datetime_local(?string $value): string
{
    if (empty($value)) {
        return "";
    }

    return date("Y-m-d\TH:i", strtotime($value));
}

try {
    $pdo = db();

    if ($isEdit && $_SERVER["REQUEST_METHOD"] !== "POST") {
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
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ":id" => $newsId,
        ]);

        $item = $stmt->fetch();

        if (!$item) {
            $errors[] = "対象のお知らせが見つかりません。";
            $isEdit = false;
        } else {
            $title = $item["title"] ?? "";
            $slug = $item["slug"] ?? "";
            $category = $item["category"] ?? "other";
            $summary = $item["summary"] ?? "";
            $body = $item["body"] ?? "";

            $hasImage = !empty($item["has_image"]);
            $imagePath = $item["image_path"] ?? "";

            $hasRelatedLink = !empty($item["has_related_link"]);
            $relatedUrl = $item["related_url"] ?? "";
            $relatedButtonText = $item["related_button_text"] ?? "";

            $isPinned = !empty($item["is_pinned"]);
            $status = $item["status"] ?? "draft";
            $publishedAt = format_datetime_local($item["published_at"] ?? null);
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $csrfToken = $_POST["csrf_token"] ?? "";

        $title = trim($_POST["title"] ?? "");
        $slug = trim($_POST["slug"] ?? "");
        $category = $_POST["category"] ?? "other";
        $summary = trim($_POST["summary"] ?? "");
        $body = trim($_POST["body"] ?? "");

        $hasImage = isset($_POST["has_image"]) && $_POST["has_image"] === "1";
        $imagePath = trim($_POST["image_path"] ?? "");

        $hasRelatedLink = isset($_POST["has_related_link"]) && $_POST["has_related_link"] === "1";
        $relatedUrl = trim($_POST["related_url"] ?? "");
        $relatedButtonText = trim($_POST["related_button_text"] ?? "");

        $isPinned = isset($_POST["is_pinned"]) && $_POST["is_pinned"] === "1";
        $status = $_POST["status"] ?? "draft";
        $publishedAt = trim($_POST["published_at"] ?? "");

        if (!csrf_check($csrfToken)) {
            $errors[] = "不正なリクエストです。もう一度お試しください。";
        }

        if ($title === "") {
            $errors[] = "タイトルを入力してください。";
        } elseif (mb_strlen($title) > 180) {
            $errors[] = "タイトルは180文字以内で入力してください。";
        }

        if ($slug === "") {
            $errors[] = "slugを入力してください。";
        } elseif (!preg_match("/^[a-z0-9-]+$/", $slug)) {
            $errors[] = "slugは半角英数字とハイフンのみで入力してください。";
        } elseif (mb_strlen($slug) > 140) {
            $errors[] = "slugは140文字以内で入力してください。";
        }

        if (!array_key_exists($category, $categoryLabels)) {
            $errors[] = "カテゴリが正しくありません。";
        }

        if ($summary === "") {
            $errors[] = "概要を入力してください。";
        }

        if ($body === "") {
            $errors[] = "詳細本文を入力してください。";
        }

        if (!array_key_exists($status, $statusLabels)) {
            $errors[] = "公開状態が正しくありません。";
        }

        if ($publishedAt === "") {
            $errors[] = "公開日時を入力してください。";
        } else {
            $timestamp = strtotime($publishedAt);
            if ($timestamp === false) {
                $errors[] = "公開日時が正しくありません。";
            }
        }

        if ($hasImage) {
            if ($imagePath === "") {
                $errors[] = "画像を添付する場合は、画像パスを入力してください。";
            } elseif (mb_strlen($imagePath) > 255) {
                $errors[] = "画像パスは255文字以内で入力してください。";
            } elseif (!preg_match("#^/#", $imagePath) && !filter_var($imagePath, FILTER_VALIDATE_URL)) {
                $errors[] = "画像パスは /storage/news/example.png のようなパス、またはURLで入力してください。";
            }
        } else {
            $imagePath = "";
        }

        if ($hasRelatedLink) {
            if ($relatedUrl === "") {
                $errors[] = "関連ページボタンを表示する場合は、関連ページURLを入力してください。";
            } elseif (mb_strlen($relatedUrl) > 255) {
                $errors[] = "関連ページURLは255文字以内で入力してください。";
            } elseif (!preg_match("#^/#", $relatedUrl) && !filter_var($relatedUrl, FILTER_VALIDATE_URL)) {
                $errors[] = "関連ページURLは /services/ のようなパス、またはURLで入力してください。";
            }

            if ($relatedButtonText === "") {
                $errors[] = "関連ページボタンを表示する場合は、ボタン文言を入力してください。";
            } elseif (mb_strlen($relatedButtonText) > 80) {
                $errors[] = "ボタン文言は80文字以内で入力してください。";
            }
        } else {
            $relatedUrl = "";
            $relatedButtonText = "";
        }

        if (!$errors) {
            $dupStmt = $pdo->prepare("
                SELECT id
                FROM news
                WHERE slug = :slug
                  AND id <> :id
                LIMIT 1
            ");

            $dupStmt->execute([
                ":slug" => $slug,
                ":id" => $newsId,
            ]);

            if ($dupStmt->fetch()) {
                $errors[] = "このslugはすでに使用されています。";
            }
        }

        if (!$errors) {
            $publishedAtSql = date("Y-m-d H:i:s", strtotime($publishedAt));

            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE news
                    SET
                        title = :title,
                        slug = :slug,
                        category = :category,
                        summary = :summary,
                        body = :body,
                        has_image = :has_image,
                        image_path = :image_path,
                        has_related_link = :has_related_link,
                        related_url = :related_url,
                        related_button_text = :related_button_text,
                        is_pinned = :is_pinned,
                        status = :status,
                        published_at = :published_at,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ":title" => $title,
                    ":slug" => $slug,
                    ":category" => $category,
                    ":summary" => $summary,
                    ":body" => $body,
                    ":has_image" => $hasImage ? 1 : 0,
                    ":image_path" => $hasImage ? $imagePath : null,
                    ":has_related_link" => $hasRelatedLink ? 1 : 0,
                    ":related_url" => $hasRelatedLink ? $relatedUrl : null,
                    ":related_button_text" => $hasRelatedLink ? $relatedButtonText : null,
                    ":is_pinned" => $isPinned ? 1 : 0,
                    ":status" => $status,
                    ":published_at" => $publishedAtSql,
                    ":id" => $newsId,
                ]);

                $messages[] = "お知らせを更新しました。";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO news (
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
                        published_at
                    ) VALUES (
                        :title,
                        :slug,
                        :category,
                        :summary,
                        :body,
                        :has_image,
                        :image_path,
                        :has_related_link,
                        :related_url,
                        :related_button_text,
                        :is_pinned,
                        :status,
                        :published_at
                    )
                ");

                $stmt->execute([
                    ":title" => $title,
                    ":slug" => $slug,
                    ":category" => $category,
                    ":summary" => $summary,
                    ":body" => $body,
                    ":has_image" => $hasImage ? 1 : 0,
                    ":image_path" => $hasImage ? $imagePath : null,
                    ":has_related_link" => $hasRelatedLink ? 1 : 0,
                    ":related_url" => $hasRelatedLink ? $relatedUrl : null,
                    ":related_button_text" => $hasRelatedLink ? $relatedButtonText : null,
                    ":is_pinned" => $isPinned ? 1 : 0,
                    ":status" => $status,
                    ":published_at" => $publishedAtSql,
                ]);

                $newsId = (int)$pdo->lastInsertId();
                $isEdit = true;

                $messages[] = "お知らせを作成しました。";
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = "お知らせ情報の処理中にエラーが発生しました。";
}

require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="news-edit-page">

    <section class="news-edit-hero">
        <div class="container news-edit-hero-grid">

            <div class="news-edit-copy reveal">
                <p class="eyebrow">Admin / News</p>
                <h1><?php echo $isEdit ? "お知らせ編集" : "お知らせ追加"; ?></h1>
                <p>
                    トップページやお知らせ一覧に表示する内容を管理します。
                    画像や関連ページボタンは、必要な時だけチェックして入力できます。
                </p>
            </div>

            <aside class="news-edit-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section news-edit-section">
        <div class="container">

            <?php if ($messages): ?>
                <div class="edit-success reveal">
                    <?php foreach ($messages as $msg): ?>
                        <p><?php echo h($msg); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="edit-alert reveal">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="edit-layout">

                <section class="edit-panel reveal">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Form</p>
                            <h2><?php echo $isEdit ? "お知らせを編集" : "新しいお知らせを追加"; ?></h2>
                        </div>

                        <a href="/admin/news/" class="back-button">一覧へ戻る</a>
                    </div>

                    <form
                        action="<?php echo $isEdit ? "/admin/news/edit/?id=" . h((string)$newsId) : "/admin/news/edit/"; ?>"
                        method="post"
                        class="news-edit-form"
                    >
                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="title">タイトル</label>
                                <input
                                    id="title"
                                    type="text"
                                    name="title"
                                    value="<?php echo h($title); ?>"
                                    maxlength="180"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="slug">slug</label>
                                <input
                                    id="slug"
                                    type="text"
                                    name="slug"
                                    value="<?php echo h($slug); ?>"
                                    maxlength="140"
                                    placeholder="top-page-renewal"
                                    required
                                >
                                <small>半角英数字とハイフンのみ。詳細ページ用の識別子です。</small>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="category">カテゴリ</label>
                                <select id="category" name="category">
                                    <?php foreach ($categoryLabels as $value => $text): ?>
                                        <option value="<?php echo h($value); ?>" <?php echo $category === $value ? "selected" : ""; ?>>
                                            <?php echo h($text); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="status">公開状態</label>
                                <select id="status" name="status">
                                    <?php foreach ($statusLabels as $value => $text): ?>
                                        <option value="<?php echo h($value); ?>" <?php echo $status === $value ? "selected" : ""; ?>>
                                            <?php echo h($text); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="published_at">公開日時</label>
                            <input
                                id="published_at"
                                type="datetime-local"
                                name="published_at"
                                value="<?php echo h($publishedAt); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="summary">概要</label>
                            <textarea
                                id="summary"
                                name="summary"
                                rows="4"
                                required
                            ><?php echo h($summary); ?></textarea>
                            <small>トップページや一覧に表示する短い説明文です。</small>
                        </div>

                        <div class="form-group">
                            <label for="body">詳細本文</label>
                            <textarea
                                id="body"
                                name="body"
                                rows="10"
                                required
                            ><?php echo h($body); ?></textarea>
                        </div>

                        <div class="option-box">
                            <label class="checkbox-label">
                                <input
                                    type="checkbox"
                                    name="has_image"
                                    value="1"
                                    id="has_image"
                                    <?php echo $hasImage ? "checked" : ""; ?>
                                >
                                <span>画像を添付する</span>
                            </label>

                            <p>チェックを入れた場合のみ、画像パスを使用します。</p>
                        </div>

                        <div class="form-group image-path-group">
                            <label for="image_path">画像パス</label>
                            <input
                                id="image_path"
                                type="text"
                                name="image_path"
                                value="<?php echo h($imagePath); ?>"
                                maxlength="255"
                                placeholder="/storage/news/example.png"
                            >
                            <small>最初は画像パス手入力で運用します。アップロード機能は後で追加できます。</small>
                        </div>

                        <div class="option-box">
                            <label class="checkbox-label">
                                <input
                                    type="checkbox"
                                    name="has_related_link"
                                    value="1"
                                    id="has_related_link"
                                    <?php echo $hasRelatedLink ? "checked" : ""; ?>
                                >
                                <span>関連ページボタンを表示する</span>
                            </label>

                            <p>変更したページや関連するページへ移動するボタンを表示します。</p>
                        </div>

                        <div class="related-link-group">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="related_url">関連ページURL</label>
                                    <input
                                        id="related_url"
                                        type="text"
                                        name="related_url"
                                        value="<?php echo h($relatedUrl); ?>"
                                        maxlength="255"
                                        placeholder="/services/"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="related_button_text">ボタン文言</label>
                                    <input
                                        id="related_button_text"
                                        type="text"
                                        name="related_button_text"
                                        value="<?php echo h($relatedButtonText); ?>"
                                        maxlength="80"
                                        placeholder="事業一覧を見る"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="option-box">
                            <label class="checkbox-label">
                                <input
                                    type="checkbox"
                                    name="is_pinned"
                                    value="1"
                                    <?php echo $isPinned ? "checked" : ""; ?>
                                >
                                <span>重要なお知らせとして固定表示する</span>
                            </label>

                            <p>トップページや一覧で優先的に表示したい場合に使います。</p>
                        </div>

                        <button type="submit" class="submit-button">
                            <?php echo $isEdit ? "更新する" : "作成する"; ?>
                        </button>
                    </form>
                </section>

                <aside class="edit-side-card reveal">
                    <h3>入力ルール</h3>

                    <div class="side-list">
                        <div>
                            <span>公開状態</span>
                            <p>公開にすると、公開日時が現在時刻以前の場合に一般ページへ表示されます。</p>
                        </div>

                        <div>
                            <span>画像</span>
                            <p>必要な時だけチェックを入れて、画像パスを入力します。</p>
                        </div>

                        <div>
                            <span>関連ページ</span>
                            <p>変更したページなどに誘導したい場合だけ、関連ボタンを表示します。</p>
                        </div>

                        <div>
                            <span>重要固定</span>
                            <p>重要なお知らせを上に表示したい時に使います。</p>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                        <div class="side-actions">
                            <a href="/admin/news/" class="button ghost">一覧へ戻る</a>
                            <?php if ($status === "published"): ?>
                                <a href="/news/detail/?slug=<?php echo h($slug); ?>" class="button primary" target="_blank" rel="noopener">
                                    公開ページを確認
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </aside>

            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    const imageCheckbox = document.getElementById("has_image");
    const imageGroup = document.querySelector(".image-path-group");
    const imageInput = document.getElementById("image_path");

    const linkCheckbox = document.getElementById("has_related_link");
    const linkGroup = document.querySelector(".related-link-group");
    const relatedUrl = document.getElementById("related_url");
    const relatedButtonText = document.getElementById("related_button_text");

    const syncImage = () => {
        if (!imageCheckbox || !imageGroup || !imageInput) return;

        if (imageCheckbox.checked) {
            imageGroup.classList.remove("is-hidden");
            imageInput.disabled = false;
        } else {
            imageGroup.classList.add("is-hidden");
            imageInput.disabled = true;
        }
    };

    const syncRelated = () => {
        if (!linkCheckbox || !linkGroup || !relatedUrl || !relatedButtonText) return;

        if (linkCheckbox.checked) {
            linkGroup.classList.remove("is-hidden");
            relatedUrl.disabled = false;
            relatedButtonText.disabled = false;
        } else {
            linkGroup.classList.add("is-hidden");
            relatedUrl.disabled = true;
            relatedButtonText.disabled = true;
        }
    };

    if (imageCheckbox) {
        imageCheckbox.addEventListener("change", syncImage);
        syncImage();
    }

    if (linkCheckbox) {
        linkCheckbox.addEventListener("change", syncRelated);
        syncRelated();
    }
});
</script>
</body>
</html>
