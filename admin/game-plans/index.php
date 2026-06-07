<?php
session_start();

require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "ゲームサーバープラン管理 | HC Platform";
$pageDescription = "HC Platformの管理者向けゲームサーバープラン管理ページです。";
$pageCss = "/admin/game-plans/game-plans.css";

$errors = [];
$plans = [];

$statusLabels = [
    "draft" => "下書き",
    "published" => "公開",
    "hidden" => "非公開",
];

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

function plan_status_label(string $status): string
{
    $labels = [
        "draft" => "下書き",
        "published" => "公開",
        "hidden" => "非公開",
    ];

    return $labels[$status] ?? "不明";
}

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
            gsp.ptero_nest_id,
            gsp.ptero_egg_id,
            gsp.ptero_docker_image,
            gsp.ptero_startup_command,
            gsp.status,
            gsp.sort_order,
            gsp.created_at,
            gsp.updated_at,

            COALESCE(
                json_agg(
                    json_build_object(
                        'id', pn.id,
                        'ptero_node_id', pn.ptero_node_id,
                        'name', pn.name,
                        'label', pn.label,
                        'cpu_type', pn.cpu_type,
                        'is_high_performance', pn.is_high_performance,
                        'is_primary', gspn.is_primary
                    )
                    ORDER BY gspn.is_primary DESC, pn.sort_order ASC, pn.id ASC
                ) FILTER (WHERE pn.id IS NOT NULL),
                '[]'
            ) AS nodes
        FROM game_server_plans gsp
        LEFT JOIN game_server_plan_nodes gspn ON gspn.plan_id = gsp.id
        LEFT JOIN ptero_nodes pn ON pn.id = gspn.node_id
        GROUP BY gsp.id
        ORDER BY gsp.sort_order ASC, gsp.id ASC
    ");

    $plans = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "ゲームサーバープラン情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="game-plans-page">

    <section class="game-plans-hero">
        <div class="container game-plans-hero-grid">

            <div class="game-plans-copy reveal">
                <p class="eyebrow">Admin / Game Plans</p>
                <h1>ゲームサーバープラン管理</h1>
                <p>
                    ゲームサーバーレンタルで表示・申込に使うプランを管理します。
                    価格、メモリ、CPU、ディスク容量、Pterodactyl連携情報を確認できます。
                </p>
            </div>

            <aside class="game-plans-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section game-plans-section">
        <div class="container">

            <div class="plans-panel reveal">

                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Plans</p>
                        <h2>プラン一覧</h2>
                    </div>

                    <div class="panel-actions">
                        <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                        <a href="/admin/game-plans/edit/" class="create-button">新規追加</a>
                    </div>
                </div>

                <?php if ($errors): ?>
                    <div class="plans-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$plans && !$errors): ?>
                    <div class="empty-plans">
                        <h3>プランがまだ登録されていません。</h3>
                        <p>新規追加からゲームサーバープランを作成してください。</p>
                        <a href="/admin/game-plans/edit/" class="create-button">プランを追加する</a>
                    </div>
                <?php endif; ?>

                <?php if ($plans): ?>
                    <div class="plans-grid">
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

                            <article class="plan-card status-<?php echo h($plan["status"]); ?>">
                                <div class="plan-card-head">
                                    <div>
                                        <span class="plan-status">
                                            <?php echo h(plan_status_label($plan["status"])); ?>
                                        </span>
                                        <h3><?php echo h($plan["name"]); ?></h3>
                                        <p class="plan-slug">/<?php echo h($plan["slug"]); ?></p>
                                    </div>

                                    <strong class="plan-price">
                                        ¥<?php echo h(number_format((int)$plan["price_monthly"])); ?>
                                        <small>/月</small>
                                    </strong>
                                </div>

                                <p class="plan-description">
                                    <?php echo h($plan["description"]); ?>
                                </p>

                                <div class="spec-grid">
                                    <div>
                                        <span>Memory</span>
                                        <strong><?php echo h(format_mb_to_gb((int)$plan["memory_mb"])); ?></strong>
                                    </div>

                                    <div>
                                        <span>CPU</span>
                                        <strong><?php echo h(format_cpu_to_vcpu((int)$plan["cpu_limit"])); ?></strong>
                                        <small class="spec-sub">
                                            <?php echo h((string)$plan["cpu_limit"]); ?>%
                                        </small>
                                    </div>

                                    <div>
                                        <span>Disk</span>
                                        <strong><?php echo h(format_mb_to_gb((int)$plan["disk_mb"])); ?></strong>
                                    </div>

                                    <div>
                                        <span>Backup</span>
                                        <strong><?php echo h((string)$plan["backup_limit"]); ?></strong>
                                    </div>

                                    <div>
                                        <span>Database</span>
                                        <strong><?php echo h((string)$plan["database_limit"]); ?></strong>
                                    </div>

                                    <div>
                                        <span>Allocation</span>
                                        <strong><?php echo h((string)$plan["allocation_limit"]); ?></strong>
                                    </div>
                                </div>

                                <?php if (!empty($plan["server_software_note"])): ?>
                                    <div class="note-box">
                                        <span>Software</span>
                                        <p><?php echo h($plan["server_software_note"]); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="nodes-box">
                                    <span>対応Node</span>

                                    <?php if ($planNodes): ?>
                                        <div class="node-list">
                                            <?php foreach ($planNodes as $node): ?>
                                                <div class="node-chip <?php echo !empty($node["is_high_performance"]) ? "high-performance" : ""; ?>">
                                                    <strong>
                                                        <?php echo h((string)$node["name"]); ?>
                                                        <?php if (!empty($node["is_primary"])): ?>
                                                            <small>優先</small>
                                                        <?php endif; ?>
                                                    </strong>

                                                    <p>
                                                        <?php echo h((string)$node["label"]); ?>
                                                        <?php if (!empty($node["cpu_type"])): ?>
                                                            / <?php echo h((string)$node["cpu_type"]); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="node-empty">対応Nodeが未設定です。</p>
                                    <?php endif; ?>
                                </div>

                                <div class="ptero-box">
                                    <div>
                                        <span>Nest ID</span>
                                        <strong><?php echo h($plan["ptero_nest_id"] !== null ? (string)$plan["ptero_nest_id"] : "未設定"); ?></strong>
                                    </div>

                                    <div>
                                        <span>Egg ID</span>
                                        <strong><?php echo h($plan["ptero_egg_id"] !== null ? (string)$plan["ptero_egg_id"] : "未設定"); ?></strong>
                                    </div>
                                </div>

                                <div class="plan-card-footer">
                                    <span>表示順: <?php echo h((string)$plan["sort_order"]); ?></span>

                                    <a href="/admin/game-plans/edit/?id=<?php echo h((string)$plan["id"]); ?>">
                                        編集する
                                        <span>→</span>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>