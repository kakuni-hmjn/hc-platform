<?php
session_start();

require_once __DIR__ . "/lib/helpers.php";
require_once __DIR__ . "/lib/auth.php";
require_once __DIR__ . "/lib/db.php";

$currentUser = current_user();

$pageTitle = "HC Platform | HCと共にある生活";
$pageDescription = "HC Platformは、ゲームサーバー、Webサービス、クリエイター支援、コミュニティ運営を通して、遊びと創作を支えるためのプラットフォームです。";
$pageCss = "/index.css";
$enableAdsense = true;

$heroDisplayName = "";
if ($currentUser) {
    $heroDisplayName = trim((string)($currentUser["username"] ?? $currentUser["name"] ?? $currentUser["email"] ?? ""));
}

$heroWelcomeTitle = $heroDisplayName !== ""
    ? "おかえりなさい、" . $heroDisplayName . "さん"
    : "おかえりなさい";

$heroPanel = $currentUser ? [
    "badge" => "HC Account",
    "status" => "Signed in",
    "title" => $heroWelcomeTitle,
    "description" => "契約中のサービス、サポート状況、アカウント情報をマイページから確認できます。",
    "primary" => [
        "text" => "マイページを開く",
        "url" => "/dashboard/",
    ],
    "items" => [
        [
            "label" => "Dashboard",
            "title" => "契約・アカウント",
            "text" => "契約中サービスとアカウント情報を確認",
            "url" => "/dashboard/",
        ],
        [
            "label" => "Services",
            "title" => "サービス一覧",
            "text" => "提供中のHCサービスを確認",
            "url" => "/services/",
        ],
        [
            "label" => "Support",
            "title" => "お問い合わせ",
            "text" => "相談・サポート依頼を送信",
            "url" => "/contact/",
        ],
    ],
] : [
    "badge" => "Start HC",
    "status" => "Ready",
    "title" => "サービスを始める",
    "description" => "HC Accountを作成すると、サービス申込・お問い合わせ・契約情報の確認ができます。",
    "primary" => [
        "text" => "無料でアカウント作成",
        "url" => "/register/",
    ],
    "items" => [
        [
            "label" => "Account",
            "title" => "アカウント作成",
            "text" => "HCサービスを使うためのアカウントを作成",
            "url" => "/register/",
        ],
        [
            "label" => "Login",
            "title" => "ログイン",
            "text" => "すでにアカウントを持っている方はこちら",
            "url" => "/login/",
        ],
        [
            "label" => "Services",
            "title" => "サービスを見る",
            "text" => "提供中のサービスを確認",
            "url" => "/services/",
        ],
    ],
];


$newsItems = [];
$newsErrors = [];

function top_news_category_label(string $category): string
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

    $stmt = $pdo->query("
        SELECT
            id,
            title,
            slug,
            category,
            summary,
            has_related_link,
            related_url,
            related_button_text,
            is_pinned,
            published_at
        FROM news
        WHERE status = 'published'
          AND published_at IS NOT NULL
          AND published_at <= NOW()
        ORDER BY
            is_pinned DESC,
            published_at DESC,
            id DESC
        LIMIT 3
    ");

    $newsItems = $stmt->fetchAll();
} catch (Throwable $e) {
    $newsErrors[] = "お知らせ情報の取得中にエラーが発生しました。";
}

$services = [];
$serviceErrors = [];

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

    $stmt = $pdo->query("
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
            sort_order
        FROM services
        WHERE status = 'published'
        ORDER BY sort_order ASC, id ASC
        LIMIT 6
    ");

    $services = $stmt->fetchAll();
} catch (Throwable $e) {
    $serviceErrors[] = "事業情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/parts/head.php";
?>
<body>
<?php include __DIR__ . "/parts/header/header.php"; ?>

<main class="home-page">

    <section class="home-hero">
                <div class="top-hero-bg-motion" aria-hidden="true">
                    <span class="top-hero-orb top-hero-orb-1"></span>
                    <span class="top-hero-orb top-hero-orb-2"></span>
                    <span class="top-hero-orb top-hero-orb-3"></span>
                    <span class="top-hero-grid"></span>
                </div>

                
        <div class="container hero-grid">

            <div class="hero-copy reveal">
                <p class="eyebrow">HC Platform</p>

                <h1 class="hero-title">
                    <span>遊ぶ、作る、配信する。その裏側も支える</span>
                </h1>

                <p class="hero-lead">
                    HC Platformは、ゲームサーバー、Webサービス、クリエイター支援、
                    コミュニティ運営を通して、遊びと創作を支えるためのプラットフォームです。
                </p>

                <div class="hero-actions">
                    <a href="#services" class="button primary">事業を見る</a>

                    <?php if ($currentUser): ?>
                        <a href="/dashboard/" class="button ghost">ダッシュボードへ</a>
                    <?php else: ?>
                        <a href="/register/" class="button ghost">アカウント作成</a>
                    <?php endif; ?>

                    <a href="/contact/" class="button ghost">お問い合わせ</a>
                </div>
            </div>

            <aside class="hero-panel hero-action-card reveal" aria-label="HCサービスメニュー">

                <div class="hero-action-card-bg" aria-hidden="true"></div>

                <div class="hero-action-head">
                    <div>
                        <div class="panel-badge"><?php echo h($heroPanel["badge"]); ?></div>
                        <h2><?php echo h($heroPanel["title"]); ?></h2>
                    </div>

                    <span class="hero-action-status">
                        <span></span>
                        <?php echo h($heroPanel["status"]); ?>
                    </span>
                </div>

                <p class="hero-action-description">
                    <?php echo h($heroPanel["description"]); ?>
                </p>

                <a class="hero-action-primary" href="<?php echo h($heroPanel["primary"]["url"]); ?>">
                    <span><?php echo h($heroPanel["primary"]["text"]); ?></span>
                    <strong>→</strong>
                </a>

                <div class="hero-action-list">
                    <?php foreach ($heroPanel["items"] as $item): ?>
                        <a class="hero-action-item" href="<?php echo h($item["url"]); ?>">
                            <span class="hero-action-item-label"><?php echo h($item["label"]); ?></span>

                            <span class="hero-action-item-main">
                                <strong><?php echo h($item["title"]); ?></strong>
                                <small><?php echo h($item["text"]); ?></small>
                            </span>

                            <span class="hero-action-item-arrow">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>

            </aside>

        </div>
    </section>

    <section class="section top-news-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">News</p>
                <h2>お知らせ</h2>
                <p>
                    HC Platformの更新情報、機能追加、メンテナンス、サービス情報などをお知らせします。
                </p>
            </div>

            <div class="top-news-panel reveal">

                <?php if ($newsErrors): ?>
                    <div class="top-news-error">
                        <?php foreach ($newsErrors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$newsItems && !$newsErrors): ?>
                    <div class="top-news-empty">
                        <p>現在表示できるお知らせはありません。</p>
                    </div>
                <?php endif; ?>

                <div class="top-news-list">
                    <?php foreach ($newsItems as $item): ?>
                        <article class="top-news-item">
                            <div class="top-news-meta">
                                <time datetime="<?php echo h(date("Y-m-d", strtotime($item["published_at"]))); ?>">
                                    <?php echo h(date("Y.m.d", strtotime($item["published_at"]))); ?>
                                </time>

                                <span class="top-news-category category-<?php echo h($item["category"]); ?>">
                                    <?php echo h(top_news_category_label($item["category"])); ?>
                                </span>

                                <?php if (!empty($item["is_pinned"])): ?>
                                    <span class="top-news-pin">重要</span>
                                <?php endif; ?>
                            </div>

                            <div class="top-news-content">
                                <h3>
                                    <a href="/news/detail/?slug=<?php echo h($item["slug"]); ?>">
                                        <?php echo h($item["title"]); ?>
                                    </a>
                                </h3>

                                <p><?php echo h($item["summary"]); ?></p>
                            </div>

                            <div class="top-news-actions">
                                <a href="/news/detail/?slug=<?php echo h($item["slug"]); ?>" class="top-news-detail">
                                    詳細を見る
                                    <span>→</span>
                                </a>

                                <?php if (!empty($item["has_related_link"]) && !empty($item["related_url"]) && !empty($item["related_button_text"])): ?>
                                    <a href="<?php echo h($item["related_url"]); ?>" class="top-news-related">
                                        <?php echo h($item["related_button_text"]); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="top-news-more">
                    <a href="/news/" class="button primary">お知らせ一覧を見る</a>
                </div>

            </div>

        </div>
    </section>

    <section id="services" class="section services-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Services</p>
                <h2>事業一覧</h2>
                <p>
                    HC Platformは、ゲーム、Web、配信、コミュニティ、インフラ、開発を軸に、
                    複数のサービス展開を目指しています。
                </p>
            </div>

        <div class="services-grid">
            <?php if ($serviceErrors): ?>
                <div class="service-error-card reveal">
                    <?php foreach ($serviceErrors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$services && !$serviceErrors): ?>
                <div class="service-error-card reveal">
                    <p>現在表示できる事業情報はありません。</p>
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

        <div class="services-more reveal">
            <a href="/services/" class="button ghost">事業一覧をもっと見る</a>
        </div>

        </div>
    </section>

    <section class="section concept-section">
        <div class="container concept-grid">

            <div class="concept-copy reveal">
                <p class="eyebrow">Concept</p>
                <h2>HC Platformが目指すこと</h2>
                <p>
                    遊ぶ人、作る人、配信する人、運営する人。
                    それぞれの活動を支えるために、アカウント基盤・サービス基盤・サポート導線を整備しています。
                </p>
            </div>

            <div class="concept-list reveal">
                <article>
                    <span>01</span>
                    <h3>アカウントから各サービスへつながる</h3>
                    <p>HC Accountを中心に、複数サービスを利用しやすい形へ整えていきます。</p>
                </article>

                <article>
                    <span>02</span>
                    <h3>遊びと創作をまとめて支える</h3>
                    <p>ゲームサーバー、配信、Webサービス、開発支援をひとつの流れで扱います。</p>
                </article>

                <article>
                    <span>03</span>
                    <h3>小さく始めて拡張する</h3>
                    <p>必要な機能から順番に整備し、運営しながら継続的に改善していきます。</p>
                </article>
            </div>

        </div>
    </section>

    <section class="section guide-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Guide</p>
                <h2>目的から探す</h2>
                <p>
                    はじめての方、サービスを使いたい方、相談したい方に向けた入口です。
                </p>
            </div>

            <div class="guide-grid">
                <a href="/company/" class="guide-card reveal">
                    <span>For First Visitors</span>
                    <h3>はじめての方</h3>
                    <p>HC Platformの運営情報やサービスの方向性を確認できます。</p>
                </a>

                <a href="/register/" class="guide-card reveal">
                    <span>For Users</span>
                    <h3>サービスを使いたい方</h3>
                    <p>アカウントを作成して、各サービスの利用準備を進められます。</p>
                </a>

                <a href="<?php echo $currentUser ? "/dashboard/" : "/login/"; ?>" class="guide-card reveal">
                    <span>Account</span>
                    <h3>アカウントをお持ちの方</h3>
                    <p>ログイン後、ダッシュボードからアカウント情報を確認できます。</p>
                </a>

                <a href="/contact/" class="guide-card reveal">
                    <span>Support</span>
                    <h3>相談したい方</h3>
                    <p>サービスやアカウントに関するお問い合わせを送信できます。</p>
                </a>
            </div>

        </div>
    </section>

    <section class="section trust-section">
        <div class="container trust-grid">

            <div class="trust-card reveal">
                <p class="eyebrow">Operation</p>
                <h2>運営について</h2>
                <p>
                    HC Platformは、HMJn companyが運営するWebサービス・コミュニティ関連プラットフォームです。
                    サービス公開と継続的な改善に向けて、必要な機能を順次整備しています。
                </p>
            </div>

            <div class="trust-links reveal">
                <a href="/company/">
                    <span>Company</span>
                    <strong>運営情報</strong>
                </a>
                <a href="/terms/">
                    <span>Terms</span>
                    <strong>利用規約</strong>
                </a>
                <a href="/privacy/">
                    <span>Privacy</span>
                    <strong>プライバシーポリシー</strong>
                </a>
                <a href="/contact/">
                    <span>Contact</span>
                    <strong>お問い合わせ</strong>
                </a>
            </div>

        </div>
    </section>

    <section class="section contact-cta-section">
        <div class="container">
            <div class="contact-cta reveal">
                <p class="eyebrow">Contact</p>
                <h2>サービスについて相談したいですか？</h2>
                <p>
                    ゲームサーバー、アカウント、Webサービス、開発相談など、
                    HC Platformに関するお問い合わせはこちらから送信できます。
                </p>
                <a href="/contact/" class="button primary">お問い合わせする</a>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . "/parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/index.js"></script>
</body>
</html>