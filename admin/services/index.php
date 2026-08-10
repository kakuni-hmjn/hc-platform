<?php
session_start();

require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/admin/site/services/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "事業管理 | HC Platform";
$pageDescription = "HC Platformの管理者向け事業管理ページです。";
$pageCss = "/admin/services/services.css";

$errors = [];
$services = [];

$keyword = trim($_GET["q"] ?? "");
$status = $_GET["status"] ?? "";
$phase = $_GET["phase"] ?? "";

$statusLabels = [
    "" => "すべて",
    "published" => "公開",
    "draft" => "下書き",
    "hidden" => "非公開",
];

$phaseLabels = [
    "" => "すべて",
    "available" => "提供中",
    "developing" => "開発中",
    "planned" => "計画中",
];

if (!array_key_exists($status, $statusLabels)) {
    $status = "";
}

if (!array_key_exists($phase, $phaseLabels)) {
    $phase = "";
}

function admin_service_status_label(string $status): string
{
    $labels = [
        "published" => "公開",
        "draft" => "下書き",
        "hidden" => "非公開",
    ];

    return $labels[$status] ?? $status;
}

function admin_service_phase_label(string $phase): string
{
    $labels = [
        "available" => "提供中",
        "developing" => "開発中",
        "planned" => "計画中",
    ];

    return $labels[$phase] ?? "未設定";
}

try {
    $pdo = db();

    $sql = "
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
        WHERE 1 = 1
    ";

    $params = [];

    if ($keyword !== "") {
        $sql .= "
            AND (
                    title ILIKE :keyword
                 OR slug ILIKE :keyword
                 OR label ILIKE :keyword
                 OR summary ILIKE :keyword
            )
        ";

        $params[":keyword"] = "%" . $keyword . "%";
    }

    if ($status !== "") {
        $sql .= " AND status = :status";
        $params[":status"] = $status;
    }

    if ($phase !== "") {
        $sql .= " AND service_phase = :phase";
        $params[":phase"] = $phase;
    }

    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $services = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "事業情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="admin-services-page">

    <section class="admin-services-hero">
        <div class="container admin-services-hero-grid">

            <div class="admin-services-copy reveal">
                <p class="eyebrow">Admin / Services</p>
                <h1>事業管理</h1>
                <p>
                    HC Platformに表示する事業情報を管理できます。
                    事業名、概要、提供ステータス、公開状態、詳細ページ有無を確認できます。
                </p>
            </div>

            <aside class="admin-services-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section admin-services-section">
        <div class="container">

            <div class="services-panel reveal">

                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Services</p>
                        <h2>事業一覧</h2>
                    </div>

                    <div class="panel-actions">
                        <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                        <a href="/admin/services/edit/" class="create-button">新規追加</a>
                    </div>
                </div>

                <form action="/admin/services/" method="get" class="service-search-form">
                    <input
                        type="text"
                        name="q"
                        value="<?php echo h($keyword); ?>"
                        placeholder="事業名・slug・ラベル・概要で検索"
                    >

                    <select name="phase">
                        <?php foreach ($phaseLabels as $value => $label): ?>
                            <option value="<?php echo h($value); ?>" <?php echo $phase === $value ? "selected" : ""; ?>>
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

                    <?php if ($keyword !== "" || $phase !== "" || $status !== ""): ?>
                        <a href="/admin/services/" class="clear-button">クリア</a>
                    <?php endif; ?>
                </form>

                <?php if ($errors): ?>
                    <div class="admin-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="services-table-wrap">
                    <table class="services-table">
                        <thead>
                            <tr>
                                <th>表示順</th>
                                <th>事業</th>
                                <th>提供ステータス</th>
                                <th>詳細</th>
                                <th>公開状態</th>
                                <th>更新日</th>
                                <th>操作</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!$services): ?>
                                <tr>
                                    <td colspan="7" class="empty-cell">
                                        事業情報が見つかりません。
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h((string)$service["sort_order"]); ?></strong>
                                    </td>

                                    <td>
                                        <div class="service-cell">
                                            <strong><?php echo h($service["title"]); ?></strong>
                                            <span>
                                                <?php echo h($service["label"] ?? ""); ?>
                                                /
                                                <?php echo h($service["slug"]); ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="phase-badge phase-<?php echo h($service["service_phase"]); ?>">
                                            <?php echo h(admin_service_phase_label($service["service_phase"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if (!empty($service["has_detail_page"]) && !empty($service["detail_url"])): ?>
                                            <a class="detail-url-badge" href="<?php echo h($service["detail_url"]); ?>" target="_blank" rel="noopener">
                                                あり
                                            </a>
                                        <?php else: ?>
                                            <span class="detail-none-badge">なし</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?php echo h($service["status"]); ?>">
                                            <?php echo h(admin_service_status_label($service["status"])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo !empty($service["updated_at"])
                                            ? h(date("Y/m/d H:i", strtotime($service["updated_at"])))
                                            : h(date("Y/m/d H:i", strtotime($service["created_at"])));
                                        ?>
                                    </td>

                                    <td>
                                        <a class="edit-button" href="/admin/services/edit/?id=<?php echo h((string)$service["id"]); ?>">
                                            編集
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="table-note">
                    一般公開されるのは公開状態が「公開」の事業のみです。
                    詳細ページがない事業は、公開側では「詳細準備中」と表示されます。
                </p>

            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
