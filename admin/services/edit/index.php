<?php
session_start();

require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/csrf.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/admin/site/services/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "事業編集 | HC Platform";
$pageDescription = "HC Platformの管理者向け事業編集ページです。";
$pageCss = "/admin/services/edit/edit.css";

$errors = [];
$messages = [];

$serviceId = (int)($_GET["id"] ?? 0);
$isEdit = $serviceId > 0;

$statusLabels = [
    "draft" => "下書き",
    "published" => "公開",
    "hidden" => "非公開",
];

$phaseLabels = [
    "available" => "提供中",
    "developing" => "開発中",
    "planned" => "計画中",
];

$title = "";
$slug = "";
$label = "";
$summary = "";
$servicePhase = "planned";
$hasDetailPage = false;
$detailUrl = "";
$status = "draft";
$sortOrder = 0;

function service_edit_datetime($value): string
{
    if (empty($value)) {
        return "未記録";
    }

    return date("Y/m/d H:i", strtotime($value));
}

try {
    $pdo = db();

    if ($isEdit && $_SERVER["REQUEST_METHOD"] !== "POST") {
        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                slug,
                label,
                summary,
                service_phase,
                has_detail_page,
                detail_url,
                status,
                sort_order,
                created_at,
                updated_at
            FROM services
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ":id" => $serviceId,
        ]);

        $service = $stmt->fetch();

        if (!$service) {
            $errors[] = "対象の事業情報が見つかりません。";
            $isEdit = false;
        } else {
            $title = $service["title"] ?? "";
            $slug = $service["slug"] ?? "";
            $label = $service["label"] ?? "";
            $summary = $service["summary"] ?? "";
            $servicePhase = $service["service_phase"] ?? "planned";
            $hasDetailPage = !empty($service["has_detail_page"]);
            $detailUrl = $service["detail_url"] ?? "";
            $status = $service["status"] ?? "draft";
            $sortOrder = (int)($service["sort_order"] ?? 0);
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $csrfToken = $_POST["csrf_token"] ?? "";

        $title = trim($_POST["title"] ?? "");
        $slug = trim($_POST["slug"] ?? "");
        $label = trim($_POST["label"] ?? "");
        $summary = trim($_POST["summary"] ?? "");
        $servicePhase = $_POST["service_phase"] ?? "planned";
        $hasDetailPage = isset($_POST["has_detail_page"]) && $_POST["has_detail_page"] === "1";
        $detailUrl = trim($_POST["detail_url"] ?? "");
        $status = $_POST["status"] ?? "draft";
        $sortOrder = (int)($_POST["sort_order"] ?? 0);

        if (!csrf_check($csrfToken)) {
            $errors[] = "不正なリクエストです。もう一度お試しください。";
        }

        if ($title === "") {
            $errors[] = "事業名を入力してください。";
        } elseif (mb_strlen($title) > 160) {
            $errors[] = "事業名は160文字以内で入力してください。";
        }

        if ($slug === "") {
            $errors[] = "slugを入力してください。";
        } elseif (!preg_match("/^[a-z0-9-]+$/", $slug)) {
            $errors[] = "slugは半角英数字とハイフンのみで入力してください。";
        } elseif (mb_strlen($slug) > 120) {
            $errors[] = "slugは120文字以内で入力してください。";
        }

        if ($label !== "" && mb_strlen($label) > 100) {
            $errors[] = "英語ラベルは100文字以内で入力してください。";
        }

        if ($summary === "") {
            $errors[] = "概要を入力してください。";
        }

        if (!array_key_exists($servicePhase, $phaseLabels)) {
            $errors[] = "提供ステータスが正しくありません。";
        }

        if (!array_key_exists($status, $statusLabels)) {
            $errors[] = "公開状態が正しくありません。";
        }

        if ($hasDetailPage) {
            if ($detailUrl === "") {
                $errors[] = "詳細ページありの場合は、詳細ページURLを入力してください。";
            } elseif (mb_strlen($detailUrl) > 255) {
                $errors[] = "詳細ページURLは255文字以内で入力してください。";
            } elseif (!preg_match("#^/#", $detailUrl) && !filter_var($detailUrl, FILTER_VALIDATE_URL)) {
                $errors[] = "詳細ページURLは /services/example/ のようなパス、またはURLで入力してください。";
            }
        } else {
            $detailUrl = "";
        }

        if (!$errors) {
            $dupStmt = $pdo->prepare("
                SELECT id
                FROM services
                WHERE slug = :slug
                  AND id <> :id
                LIMIT 1
            ");

            $dupStmt->execute([
                ":slug" => $slug,
                ":id" => $serviceId,
            ]);

            if ($dupStmt->fetch()) {
                $errors[] = "このslugはすでに使用されています。";
            }
        }

        if (!$errors) {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE services
                    SET
                        title = :title,
                        slug = :slug,
                        label = :label,
                        summary = :summary,
                        service_phase = :service_phase,
                        has_detail_page = :has_detail_page,
                        detail_url = :detail_url,
                        status = :status,
                        sort_order = :sort_order,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    ":title" => $title,
                    ":slug" => $slug,
                    ":label" => $label !== "" ? $label : null,
                    ":summary" => $summary,
                    ":service_phase" => $servicePhase,
                    ":has_detail_page" => $hasDetailPage ? 1 : 0,
                    ":detail_url" => $hasDetailPage ? $detailUrl : null,
                    ":status" => $status,
                    ":sort_order" => $sortOrder,
                    ":id" => $serviceId,
                ]);

                $messages[] = "事業情報を更新しました。";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO services (
                        title,
                        slug,
                        label,
                        summary,
                        service_phase,
                        has_detail_page,
                        detail_url,
                        status,
                        sort_order
                    ) VALUES (
                        :title,
                        :slug,
                        :label,
                        :summary,
                        :service_phase,
                        :has_detail_page,
                        :detail_url,
                        :status,
                        :sort_order
                    )
                ");

                $stmt->execute([
                    ":title" => $title,
                    ":slug" => $slug,
                    ":label" => $label !== "" ? $label : null,
                    ":summary" => $summary,
                    ":service_phase" => $servicePhase,
                    ":has_detail_page" => $hasDetailPage ? 1 : 0,
                    ":detail_url" => $hasDetailPage ? $detailUrl : null,
                    ":status" => $status,
                    ":sort_order" => $sortOrder,
                ]);

                $serviceId = (int)$pdo->lastInsertId();
                $isEdit = true;

                $messages[] = "事業情報を作成しました。";
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = "事業情報の処理中にエラーが発生しました。";
}

require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="service-edit-page">

    <section class="service-edit-hero">
        <div class="container service-edit-hero-grid">

            <div class="service-edit-copy reveal">
                <p class="eyebrow">Admin / Services</p>
                <h1><?php echo $isEdit ? "事業編集" : "事業追加"; ?></h1>
                <p>
                    事業一覧に表示するカード情報を管理します。
                    詳細ページがまだ無い場合は「詳細ページあり」のチェックを外してください。
                </p>
            </div>

            <aside class="service-edit-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section service-edit-section">
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
                            <h2><?php echo $isEdit ? "事業情報を編集" : "新しい事業を追加"; ?></h2>
                        </div>

                        <a href="/admin/services/" class="back-button">一覧へ戻る</a>
                    </div>

                    <form
                        action="<?php echo $isEdit ? "/admin/services/edit/?id=" . h((string)$serviceId) : "/admin/services/edit/"; ?>"
                        method="post"
                        class="service-edit-form"
                    >
                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="title">事業名</label>
                                <input
                                    id="title"
                                    type="text"
                                    name="title"
                                    value="<?php echo h($title); ?>"
                                    maxlength="160"
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
                                    maxlength="120"
                                    placeholder="game-server"
                                    required
                                >
                                <small>半角英数字とハイフンのみ。管理用の識別子です。</small>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="label">英語ラベル</label>
                                <input
                                    id="label"
                                    type="text"
                                    name="label"
                                    value="<?php echo h($label); ?>"
                                    maxlength="100"
                                    placeholder="Game Server"
                                >
                            </div>

                            <div class="form-group">
                                <label for="sort_order">表示順</label>
                                <input
                                    id="sort_order"
                                    type="number"
                                    name="sort_order"
                                    value="<?php echo h((string)$sortOrder); ?>"
                                    step="1"
                                >
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="service_phase">提供ステータス</label>
                                <select id="service_phase" name="service_phase">
                                    <?php foreach ($phaseLabels as $value => $text): ?>
                                        <option value="<?php echo h($value); ?>" <?php echo $servicePhase === $value ? "selected" : ""; ?>>
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
                            <label for="summary">概要</label>
                            <textarea
                                id="summary"
                                name="summary"
                                rows="6"
                                required
                            ><?php echo h($summary); ?></textarea>
                            <small>一覧カードに表示される説明文です。</small>
                        </div>

                        <div class="detail-toggle-box">
                            <label class="checkbox-label">
                                <input
                                    type="checkbox"
                                    name="has_detail_page"
                                    value="1"
                                    id="has_detail_page"
                                    <?php echo $hasDetailPage ? "checked" : ""; ?>
                                >
                                <span>詳細ページあり</span>
                            </label>

                            <p>
                                チェックを外すと、公開側では「詳細準備中」と表示されます。
                            </p>
                        </div>

                        <div class="form-group detail-url-group">
                            <label for="detail_url">詳細ページURL</label>
                            <input
                                id="detail_url"
                                type="text"
                                name="detail_url"
                                value="<?php echo h($detailUrl); ?>"
                                maxlength="255"
                                placeholder="/services/example/"
                            >
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
                            <p>公開にすると一般ページに表示されます。下書き・非公開は管理側のみです。</p>
                        </div>

                        <div>
                            <span>提供ステータス</span>
                            <p>提供中・開発中・計画中として、ユーザー側の一覧にバッジ表示されます。</p>
                        </div>

                        <div>
                            <span>詳細ページ</span>
                            <p>まだ詳細ページが無い場合はチェックを外して保存してください。</p>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                        <div class="side-actions">
                            <a href="/services/" class="button ghost">公開一覧を見る</a>
                            <?php if ($hasDetailPage && $detailUrl !== ""): ?>
                                <a href="<?php echo h($detailUrl); ?>" class="button primary" target="_blank" rel="noopener">
                                    詳細ページを開く
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
    const checkbox = document.getElementById("has_detail_page");
    const detailGroup = document.querySelector(".detail-url-group");
    const detailInput = document.getElementById("detail_url");

    if (!checkbox || !detailGroup || !detailInput) return;

    const syncDetailUrl = () => {
        if (checkbox.checked) {
            detailGroup.classList.remove("is-hidden");
            detailInput.disabled = false;
        } else {
            detailGroup.classList.add("is-hidden");
            detailInput.disabled = true;
        }
    };

    checkbox.addEventListener("change", syncDetailUrl);
    syncDetailUrl();
});
</script>
</body>
</html>
