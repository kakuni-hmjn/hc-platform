<?php
session_start();

require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/db.php";

$currentUser = current_user();

$pageTitle = "事業一覧 | HC Platform";
$pageDescription = "HC Platformの事業一覧ページです。ゲームサーバー、Webサービス、クリエイター支援、コミュニティ運営、インフラ、開発などの取り組みを紹介します。";
$pageCss = "/services/services.css";

$errors = [];
$services = [];

$keyword = trim($_GET["q"] ?? "");
$phase = $_GET["phase"] ?? "";

$phaseLabels = [
    "" => "すべて",
    "available" => "提供中",
    "developing" => "開発中",
    "planned" => "計画中",
];

if (!array_key_exists($phase, $phaseLabels)) {
    $phase = "";
}

function service_phase_label(string $phase): string
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
        WHERE status = 'published'
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

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="services-page">

    <section class="services-hero">
        <div class="container services-hero-grid">

            <div class="services-copy reveal">
                <p class="eyebrow">Services</p>
                <h1>事業一覧</h1>
                <p>
                    HC Platformでは、ゲーム、Web、配信、コミュニティ、インフラ、開発を軸に、
                    遊びと創作を支えるためのサービスを展開していきます。
                </p>

                <div class="services-actions">
                    <a href="/contact/" class="button primary">お問い合わせ</a>

                    <?php if ($currentUser): ?>
                        <a href="/dashboard/" class="button ghost">ダッシュボードへ</a>
                    <?php else: ?>
                        <a href="/register/" class="button ghost">アカウント作成</a>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="services-status-card reveal">
                <span>HC Platform</span>
                <h2>複数の事業をひとつの基盤へ</h2>
                <p>
                    HC Accountを中心に、サービス利用・サポート・各種事業ページを順次整備しています。
                </p>
            </aside>

        </div>
    </section>

    <section class="section service-list-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Business</p>
                <h2>展開中・準備中の事業</h2>
                <p>
                    HC Platformで展開している事業・今後公開予定のサービス一覧です。
                    提供ステータスで絞り込みできます。
                </p>
            </div>

            <div class="services-panel reveal">

                <div class="services-panel-head">
                    <div>
                        <span>Search</span>
                        <h3>事業を探す</h3>
                    </div>

                    <a href="/contact/" class="panel-contact-button">相談する</a>
                </div>

                <form action="/services/" method="get" class="service-search-form">
                    <input
                        type="text"
                        name="q"
                        value="<?php echo h($keyword); ?>"
                        placeholder="キーワードで検索 例: サーバー / Web / 配信"
                    >

                    <select name="phase">
                        <?php foreach ($phaseLabels as $phaseValue => $phaseName): ?>
                            <option value="<?php echo h($phaseValue); ?>" <?php echo $phase === $phaseValue ? "selected" : ""; ?>>
                                <?php echo h($phaseName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">検索</button>

                    <?php if ($keyword !== "" || $phase !== ""): ?>
                        <a href="/services/" class="clear-button">クリア</a>
                    <?php endif; ?>
                </form>

                <?php if ($errors): ?>
                    <div class="services-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($keyword !== "" || $phase !== ""): ?>
                    <p class="search-result-text">
                        <?php if ($keyword !== ""): ?>
                            「<?php echo h($keyword); ?>」
                        <?php endif; ?>

                        <?php if ($phase !== ""): ?>
                            <?php echo $keyword !== "" ? " / " : ""; ?>
                            <?php echo h($phaseLabels[$phase]); ?>
                        <?php endif; ?>

                        の検索結果
                    </p>
                <?php endif; ?>

                <div class="services-grid">
                    <?php if (!$services && !$errors): ?>
                        <div class="empty-services">
                            <h3>事業が見つかりませんでした。</h3>
                            <p>
                                キーワードや提供ステータスを変えて検索するか、お問い合わせからご相談ください。
                            </p>
                            <a href="/contact/" class="button primary">お問い合わせ</a>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($services as $index => $service): ?>
                        <article class="service-card reveal">
                            <div class="service-head">
                                <span><?php echo h(str_pad((string)($index + 1), 2, "0", STR_PAD_LEFT)); ?></span>
                                <p><?php echo h($service["label"] ?? "Service"); ?></p>
                            </div>

                            <div class="service-phase-row">
                                <span class="service-phase-badge phase-<?php echo h($service["service_phase"]); ?>">
                                    <?php echo h(service_phase_label($service["service_phase"])); ?>
                                </span>
                            </div>

                            <div class="service-body">
                                <h3><?php echo h($service["title"]); ?></h3>
                                <p><?php echo h($service["summary"]); ?></p>
                            </div>

                            <div class="service-card-footer">
                                <?php if (!empty($service["has_detail_page"]) && !empty($service["detail_url"])): ?>
                                    <a href="<?php echo h($service["detail_url"]); ?>" class="service-detail-button">
                                        詳細を見る
                                        <span>→</span>
                                    </a>
                                <?php else: ?>
                                    <span class="service-coming-soon">
                                        詳細準備中
                                    </span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

            </div>

        </div>
    </section>

    <section class="section services-concept-section">
        <div class="container">
            <div class="concept-panel reveal">
                <p class="eyebrow">Concept</p>
                <h2>遊びと創作を支える場所へ</h2>
                <p>
                    HC Platformは、ひとつのサービスだけでなく、
                    ゲームサーバー、Webサービス、配信、コミュニティ、インフラ、開発を組み合わせて、
                    ユーザーの活動を支えるプラットフォームを目指しています。
                </p>

                <div class="concept-actions">
                    <a href="/company/" class="button ghost">運営情報を見る</a>
                    <a href="/contact/" class="button primary">相談する</a>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>