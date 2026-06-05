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

$newsItems = [
    [
        "date" => "2026.06",
        "title" => "お問い合わせ受付を開始しました",
        "text" => "HC Platformへのご相談・ご質問をお問い合わせフォームから送信できるようになりました。",
        "href" => "/contact/",
    ],
    [
        "date" => "2026.06",
        "title" => "HC Account機能を整備しました",
        "text" => "アカウント作成、ログイン、メール認証、ダッシュボード機能を整備しました。",
        "href" => "/register/",
    ],
    [
        "date" => "2026.06",
        "title" => "HC Platformサイトを更新しました",
        "text" => "公開に向けて、トップページと各サービス導線の整備を進めています。",
        "href" => "/company/",
    ],
];

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
        <div class="container hero-grid">

            <div class="hero-copy reveal">
                <p class="eyebrow">HC Platform</p>

                <h1 class="hero-title">
                    <span>HCと共にある生活。</span>
                    <span>遊ぶ、作る、配信する。</span>
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

            <aside class="hero-panel reveal">
                <div class="panel-badge">Now Building</div>
                <h2>公開に向けて整備中</h2>
                <p>
                    HC Account、お問い合わせ、スタッフ管理機能など、
                    サービス公開に必要な基盤を順次整備しています。
                </p>

                <div class="hero-mini-list">
                    <div>
                        <span>Account</span>
                        <strong>HC Account</strong>
                    </div>
                    <div>
                        <span>Support</span>
                        <strong>Contact</strong>
                    </div>
                    <div>
                        <span>Service</span>
                        <strong>Coming Next</strong>
                    </div>
                </div>
            </aside>

        </div>
    </section>

    <section class="section news-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">News</p>
                <h2>お知らせ</h2>
                <p>
                    HC Platformの更新情報や公開に向けたお知らせを掲載します。
                </p>
            </div>

            <div class="news-panel reveal">
                <div class="news-panel-head">
                    <div>
                        <span>Latest News</span>
                        <h3>最新のお知らせ</h3>
                    </div>

                    <a href="/news/" class="news-list-button">お知らせ一覧へ</a>
                </div>

                <div class="news-list">
                    <?php foreach ($newsItems as $item): ?>
                        <a href="<?php echo h($item["href"]); ?>" class="news-row">
                            <div class="news-date">
                                <?php echo h($item["date"]); ?>
                            </div>

                            <div class="news-content">
                                <h4><?php echo h($item["title"]); ?></h4>
                                <p><?php echo h($item["text"]); ?></p>
                            </div>

                            <div class="news-arrow">
                                →
                            </div>
                        </a>
                    <?php endforeach; ?>
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