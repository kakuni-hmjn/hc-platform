<?php
session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";

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

$plans = [];

try {
    $pdo = db();

    $stmt = $pdo->query("
        SELECT
            gsp.id,
            gsp.name,
            gsp.slug,
            gsp.description,
            gsp.price_monthly,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,
            gsp.backup_limit,
            gsp.database_limit,
            gsp.allocation_limit,
            gsp.server_software_note,
            gsp.status,
            gsp.sort_order,

            COALESCE(
                json_agg(
                    json_build_object(
                        'name', pn.name,
                        'label', pn.label,
                        'cpu_type', pn.cpu_type,
                        'is_high_performance', pn.is_high_performance
                    )
                    ORDER BY pn.sort_order ASC, pn.id ASC
                ) FILTER (WHERE pn.id IS NOT NULL),
                '[]'
            ) AS nodes
        FROM game_server_plans gsp
        LEFT JOIN game_server_plan_nodes gspn ON gspn.plan_id = gsp.id
        LEFT JOIN ptero_nodes pn ON pn.id = gspn.node_id
        WHERE gsp.status = 'published'
        GROUP BY gsp.id
        ORDER BY gsp.sort_order ASC, gsp.id ASC
    ");

    $plans = $stmt->fetchAll();
} catch (Throwable $e) {
    $plans = [];
}

function format_mb_to_gb(int $mb): string
{
    if ($mb <= 0) {
        return "0GB";
    }

    $gb = $mb / 1024;

    if (floor($gb) == $gb) {
        return (string)(int)$gb . "GB";
    }

    return number_format($gb, 1) . "GB";
}

function format_cpu_to_vcpu(int $cpuLimit): string
{
    if ($cpuLimit <= 0) {
        return "無制限";
    }

    $vcpu = $cpuLimit / 100;

    if (floor($vcpu) == $vcpu) {
        return (string)(int)$vcpu . "vCPU";
    }

    return number_format($vcpu, 1) . "vCPU";
}

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
                    <a href="/order/game-server/" class="button primary">今すぐサーバーを入手</a>
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
                <h2>プラン</h2>
                <p>
                    用途に合わせて、低価格な通常プランから高クロックCPUを使った高性能プランまで整備していきます。
                </p>
            </div>

            <?php if ($plans): ?>
                <div class="plan-grid">
                    <?php foreach ($plans as $plan): ?>
                        <?php
                            $planNodes = [];

                            if (!empty($plan["nodes"])) {
                                $decodedNodes = json_decode($plan["nodes"], true);
                                if (is_array($decodedNodes)) {
                                    $planNodes = $decodedNodes;
                                }
                            }
                        ?>

                        <article class="plan-card reveal">
                            <span><?php echo h($plan["name"]); ?></span>

                            <h3>
                                ¥<?php echo h(number_format((int)$plan["price_monthly"])); ?>〜
                            </h3>

                            <strong>
                                <?php echo h(format_mb_to_gb((int)$plan["memory_mb"])); ?>
                                /
                                <?php echo h(format_cpu_to_vcpu((int)$plan["cpu_limit"])); ?>
                            </strong>

                            <p><?php echo h($plan["description"]); ?></p>

                            <div class="public-plan-specs">
                                <div>
                                    <small>Disk</small>
                                    <b><?php echo h(format_mb_to_gb((int)$plan["disk_mb"])); ?></b>
                                </div>

                                <div>
                                    <small>Backup</small>
                                    <b><?php echo h((string)$plan["backup_limit"]); ?></b>
                                </div>

                                <div>
                                    <small>DB</small>
                                    <b><?php echo h((string)$plan["database_limit"]); ?></b>
                                </div>
                            </div>

                            <?php if (!empty($plan["server_software_note"])): ?>
                                <div class="public-plan-note">
                                    <?php echo h($plan["server_software_note"]); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($planNodes): ?>
                                <div class="public-plan-nodes">
                                    <?php foreach ($planNodes as $node): ?>
                                        <span class="<?php echo !empty($node["is_high_performance"]) ? "high-performance" : ""; ?>">
                                            <?php echo h((string)$node["label"]); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="plans-empty reveal">
                    <h3>現在表示できるプランは準備中です。</h3>
                    <p>
                        ゲームサーバーレンタルのプランは、公開準備が整い次第掲載します。
                    </p>
                </div>
            <?php endif; ?>

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