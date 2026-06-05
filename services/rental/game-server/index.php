<?php
session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";

$currentUser = current_user();

$pageTitle = "ゲームサーバーレンタル | HC Platform";
$pageDescription = "HC Platformのゲームサーバーレンタルページです。Minecraft Java / Bedrock Edition、Paper、Purpur、Fabric、Forge、NeoForgeなどに対応予定です。";
$pageCss = "/services/rental/game-server/game-server.css";

$supports = [
    [
        "label" => "Java",
        "title" => "Minecraft Java",
        "text" => "Paper、Purpur、Fabric、Forge、NeoForgeなど、Java版の主要なサーバー構成に対応予定です。",
    ],
    [
        "label" => "BE",
        "title" => "Minecraft Bedrock Edition",
        "text" => "統合版向けのサーバー構成にも対応できるように整備していきます。",
    ],
    [
        "label" => "Cross",
        "title" => "クロスプレイ構成",
        "text" => "Geyserなどを利用したJava版・統合版のクロスプレイ構成も検討しています。",
    ],
];

$softwares = [
    "Paper",
    "Purpur",
    "Spigot",
    "Fabric",
    "Forge",
    "NeoForge",
    "Vanilla",
    "Geyser",
];

$plans = [
    [
        "name" => "Entry",
        "price" => "¥300〜",
        "spec" => "2GB / 1vCPU",
        "text" => "少人数プレイや検証用に使いやすい最小構成です。",
    ],
    [
        "name" => "Standard",
        "price" => "¥500〜",
        "spec" => "4GB / 2vCPU",
        "text" => "友達同士のマルチプレイや軽めのプラグイン構成に向いた標準プランです。",
    ],
    [
        "name" => "Advanced",
        "price" => "¥800〜",
        "spec" => "8GB / 4vCPU",
        "text" => "プラグインや中規模ワールドを使うサーバー向けの構成です。",
    ],
    [
        "name" => "High Clock",
        "price" => "¥1,500〜",
        "spec" => "16GB / 6vCPU",
        "text" => "大きめのワールドや長期運用向けの高性能プランです。",
    ],
];

$features = [
    [
        "title" => "Pterodactyl連携",
        "text" => "Pterodactyl Panelと連携し、サーバー作成・管理をHC Platform側から扱えるようにします。",
    ],
    [
        "title" => "管理者承認式",
        "text" => "初期段階では申し込み後に管理者が内容を確認し、安全にサーバーを作成します。",
    ],
    [
        "title" => "プラン選択式",
        "text" => "メモリ、CPU、ディスク容量などに応じたプランを選べる形にします。",
    ],
    [
        "title" => "段階的な自動化",
        "text" => "決済確認後の自動作成、ノード自動選択、ダッシュボード表示を順次追加していきます。",
    ],
];

$flow = [
    [
        "title" => "アカウント作成",
        "text" => "HC Accountを作成して、サービス利用に必要な情報を登録します。",
    ],
    [
        "title" => "プラン選択",
        "text" => "用途に合わせてメモリ・CPU・容量などのプランを選びます。",
    ],
    [
        "title" => "申し込み",
        "text" => "サーバー名や利用用途を入力して申し込みます。",
    ],
    [
        "title" => "サーバー作成",
        "text" => "管理者確認後、Pterodactyl上にゲームサーバーを作成します。",
    ],
];

require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="game-rental-page">

    <section class="game-hero">
        <div class="game-hero-bg-blocks" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="container game-hero-grid">

            <div class="game-copy reveal">
                <p class="eyebrow">Game Server Rental</p>
                <h1>ゲームサーバーレンタル</h1>
                <p>
                    Minecraft Java / Bedrock Editionに対応したゲームサーバーレンタルを整備中です。
                    Paper、Purpur、Fabric、Forge、NeoForgeなど、用途に合わせたサーバー構成を選べる形を目指します。
                </p>

                <div class="hero-badges">
                    <span>Java対応予定</span>
                    <span>BE対応予定</span>
                    <span>MOD対応予定</span>
                    <span>国内サーバー運用</span>
                </div>

                <div class="game-actions">
                    <a href="/contact/" class="button primary">相談する</a>
                    <a href="/services/rental/" class="button ghost">レンタル事業へ戻る</a>
                </div>
            </div>

            <div class="game-hero-side reveal">
                <div class="game-hero-visual">
                    <img src="/assets/game-server-minecraft.png" alt="Minecraft server rental visual">
                </div>

                <aside class="game-status-card">
                    <span>Preparing</span>
                    <h2>Minecraft向けサーバーから開始予定</h2>
                    <p>
                        まずはMinecraft Javaを中心に、Pterodactyl連携による申し込み・承認・サーバー作成の流れを整備していきます。
                    </p>
                </aside>
            </div>

        </div>
    </section>

    <section class="section support-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Support</p>
                <h2>対応予定</h2>
                <p>
                    Java版・統合版・クロスプレイ構成まで、Minecraft向けのサーバー環境を段階的に整えていきます。
                </p>
            </div>

            <div class="support-grid">
                <?php foreach ($supports as $support): ?>
                    <article class="support-card reveal">
                        <span><?php echo h($support["label"]); ?></span>
                        <h3><?php echo h($support["title"]); ?></h3>
                        <p><?php echo h($support["text"]); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section software-section">
        <div class="container software-grid">

            <div class="software-copy reveal">
                <p class="eyebrow">Server Software</p>
                <h2>主要なサーバーソフトに対応</h2>
                <p>
                    軽量なPaper/Purpur、MOD向けのFabric/Forge/NeoForgeなど、
                    プレイスタイルに合わせた構成を選べるようにしていきます。
                </p>
            </div>

            <div class="software-list reveal">
                <?php foreach ($softwares as $software): ?>
                    <span><?php echo h($software); ?></span>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section feature-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Features</p>
                <h2>実装予定の機能</h2>
                <p>
                    最初は手動承認式で安全に運用し、その後に決済・自動作成・ダッシュボード連携を追加していきます。
                </p>
            </div>

            <div class="feature-grid">
                <?php foreach ($features as $index => $feature): ?>
                    <article class="feature-card reveal">
                        <span><?php echo h(str_pad((string)($index + 1), 2, "0", STR_PAD_LEFT)); ?></span>
                        <h3><?php echo h($feature["title"]); ?></h3>
                        <p><?php echo h($feature["text"]); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section plan-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Plans</p>
                <h2>プラン例</h2>
                <p>
                    正式な価格やスペックは調整中です。まずは小規模から使いやすい価格帯を想定しています。
                </p>
            </div>

            <div class="plan-grid">
                <?php foreach ($plans as $plan): ?>
                    <article class="plan-card reveal">
                        <span><?php echo h($plan["name"]); ?></span>
                        <h3><?php echo h($plan["price"]); ?></h3>
                        <strong><?php echo h($plan["spec"]); ?></strong>
                        <p><?php echo h($plan["text"]); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section flow-section">
        <div class="container flow-grid">

            <div class="flow-copy reveal">
                <p class="eyebrow">Flow</p>
                <h2>利用開始までの流れ</h2>
                <p>
                    初期段階では、申し込み後に管理者が内容を確認してからサーバーを作成します。
                </p>
            </div>

            <div class="flow-list reveal">
                <?php foreach ($flow as $index => $item): ?>
                    <article>
                        <span><?php echo h(str_pad((string)($index + 1), 2, "0", STR_PAD_LEFT)); ?></span>
                        <h3><?php echo h($item["title"]); ?></h3>
                        <p><?php echo h($item["text"]); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </section>

    <section class="section ptero-section">
        <div class="container ptero-grid">

            <div class="ptero-panel reveal">
                <span>Pterodactyl</span>
                <h2>Panel連携で管理しやすく</h2>
                <p>
                    Pterodactyl Panelを利用して、ゲームサーバーの作成・起動・停止・ファイル管理などを行える構成を目指します。
                    HC Platform側では、申し込みや注文管理、サーバー情報の表示を担当していきます。
                </p>
            </div>

            <div class="ptero-list reveal">
                <article>
                    <h3>サーバー作成</h3>
                    <p>管理者確認後、Pterodactyl APIでサーバーを作成します。</p>
                </article>

                <article>
                    <h3>利用状況の表示</h3>
                    <p>将来的に、HC Platformのマイページから契約中のサーバーを確認できるようにします。</p>
                </article>

                <article>
                    <h3>自動化への拡張</h3>
                    <p>決済や在庫管理、ノード選択と連携して自動作成へ拡張していきます。</p>
                </article>
            </div>

        </div>
    </section>

    <section class="section game-cta-section">
        <div class="container">
            <div class="game-cta reveal">
                <p class="eyebrow">Contact</p>
                <h2>ゲームサーバーレンタルについて相談したいですか？</h2>
                <p>
                    Minecraftサーバー、MOD構成、統合版対応、クロスプレイ構成など、
                    ゲームサーバーレンタルに関する相談はこちらから送信できます。
                </p>

                <div class="cta-badges">
                    <span>Java</span>
                    <span>Bedrock Edition</span>
                    <span>MOD</span>
                    <span>Pterodactyl</span>
                </div>

                <div class="game-cta-actions">
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

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>