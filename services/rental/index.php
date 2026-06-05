<?php
session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";

$currentUser = current_user();

$pageTitle = "レンタル事業 | HC Platform";
$pageDescription = "HC Platformのレンタル事業ページです。ゲームサーバー、VPS、専用サーバーなど、用途に合わせたレンタルサービスを整備しています。";
$pageCss = "/services/rental/rental.css";

$rentalServices = [
    [
        "label" => "Game Server",
        "title" => "ゲームサーバーレンタル",
        "phase" => "開発中",
        "summary" => "Minecraft Java / Bedrock Editionに対応したゲームサーバーレンタルです。Purpur、Paper、Fabric、Forge、NeoForgeなど、用途に合わせた構成を選べる形で整備していきます。",
        "features" => [
            "Minecraft Java / BE 対応",
            "Paper / Purpur 対応",
            "Fabric / Forge / NeoForge 対応",
            "Pterodactyl連携予定",
        ],
        "url" => "/services/rental/game-server/",
    ],
    [
        "label" => "VPS",
        "title" => "VPSレンタル",
        "phase" => "計画中",
        "summary" => "Webサイト、Bot、検証環境、軽量サービス運用などに使える仮想サーバー提供を将来的に予定しています。",
        "features" => [
            "Linux VPS",
            "用途別プラン",
            "管理画面連携予定",
            "準備中",
        ],
        "url" => "",
    ],
    [
        "label" => "Dedicated",
        "title" => "専用サーバーレンタル",
        "phase" => "計画中",
        "summary" => "高負荷用途や長期運用向けに、物理サーバーを専用で利用できるサービスを検討しています。",
        "features" => [
            "物理サーバー提供",
            "高性能構成",
            "用途相談",
            "準備中",
        ],
        "url" => "",
    ],
];

$futureItems = [
    [
        "title" => "ゲームサーバー提供",
        "text" => "Minecraftを中心に、Pterodactylと連携したゲームサーバーレンタルを最初の対象として整備します。",
    ],
    [
        "title" => "VPS・専用サーバー展開",
        "text" => "将来的にはWebサービス、Bot、検証環境、専用サーバー利用などにも対応できる構成を目指します。",
    ],
    [
        "title" => "HC Accountとの連携",
        "text" => "アカウント、申込、注文管理、サーバー情報表示をHC Platform上で扱えるようにしていきます。",
    ],
];

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="rental-page">

    <section class="rental-hero">
        <div class="container rental-hero-grid">

            <div class="rental-copy reveal">
                <p class="eyebrow">Rental Service</p>
                <h1>レンタル事業</h1>
                <p>
                    ゲームサーバー、VPS、専用サーバーなど、
                    用途に合わせて使えるレンタルサービスをHC Platform上で整備していきます。
                </p>

                <div class="rental-actions">
                    <a href="/services/rental/game-server/" class="button primary">ゲームサーバーレンタルを見る</a>
                    <a href="/services/" class="button ghost">事業一覧へ戻る</a>
                </div>
            </div>

            <aside class="rental-status-card reveal">
                <span>Domestic Server</span>
                <h2>国内サーバー運用で低遅延を目指す</h2>
                <p>
                    国内環境でのサーバー運用を中心に、ゲームサーバーやVPSなどを
                    できるだけ低遅延で安定して利用できるレンタルサービスとして整備していきます。
                </p>
            </aside>

        </div>
    </section>

    <section class="section about-rental-section">
        <div class="container about-rental-grid">

            <div class="about-rental-copy reveal">
                <p class="eyebrow">About</p>
                <h2>レンタルサービスとは</h2>
                <p>
                    HC Platformのレンタル事業では、ユーザーが必要なサーバー環境を
                    用途に合わせて選び、申込・管理できる仕組みを整備していきます。
                </p>
                <p>
                    ゲームサーバーやVPS、専用サーバーなどを段階的に提供し、
                    個人利用からコミュニティ運営、サービス開発まで支えられる基盤を目指します。
                </p>
            </div>

            <aside class="about-rental-card reveal">
                <span>Concept</span>
                <h3>必要な環境を、必要な形で。</h3>
                <p>
                    サーバーを借りるだけではなく、HC Accountと連携して、
                    申込・管理・サポートまでまとめて扱える形を目指しています。
                </p>
            </aside>

        </div>
    </section>

    <section class="section rental-service-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Lineup</p>
                <h2>レンタルサービス</h2>
                <p>
                    現在はゲームサーバーレンタルを中心に準備中です。
                    VPSや専用サーバーは今後の展開として整備していきます。
                </p>
            </div>

            <div class="rental-service-grid">
                <?php foreach ($rentalServices as $index => $service): ?>
                    <article class="rental-service-card reveal">
                        <div class="rental-service-head">
                            <span><?php echo h(str_pad((string)($index + 1), 2, "0", STR_PAD_LEFT)); ?></span>
                            <p><?php echo h($service["label"]); ?></p>
                        </div>

                        <div class="phase-badge">
                            <?php echo h($service["phase"]); ?>
                        </div>

                        <h3><?php echo h($service["title"]); ?></h3>
                        <p><?php echo h($service["summary"]); ?></p>

                        <ul>
                            <?php foreach ($service["features"] as $feature): ?>
                                <li><?php echo h($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if (!empty($service["url"])): ?>
                            <a href="<?php echo h($service["url"]); ?>" class="service-button">
                                詳細を見る
                                <span>→</span>
                            </a>
                        <?php else: ?>
                            <span class="service-coming-soon">詳細準備中</span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section future-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Future</p>
                <h2>今後の展開</h2>
                <p>
                    レンタル事業は、まずゲームサーバーから始めて、
                    需要や運用状況に合わせてサービス範囲を広げていきます。
                </p>
            </div>

            <div class="future-grid">
                <?php foreach ($futureItems as $index => $item): ?>
                    <article class="future-card reveal">
                        <span><?php echo h(str_pad((string)($index + 1), 2, "0", STR_PAD_LEFT)); ?></span>
                        <h3><?php echo h($item["title"]); ?></h3>
                        <p><?php echo h($item["text"]); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section rental-cta-section">
        <div class="container">
            <div class="rental-cta reveal">
                <p class="eyebrow">Contact</p>
                <h2>レンタル事業について相談したいですか？</h2>
                <p>
                    ゲームサーバー、VPS、専用サーバーなど、
                    レンタル事業に関する相談や要望はこちらから送信できます。
                </p>

                <div class="rental-cta-actions">
                    <a href="/contact/" class="button primary">お問い合わせする</a>
                    <?php if ($currentUser): ?>
                        <a href="/dashboard/" class="button ghost">マイページへ</a>
                    <?php else: ?>
                        <a href="/register/" class="button ghost">アカウント作成</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>