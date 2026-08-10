<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/admin/services/game-plans/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "ゲームサーバープラン管理 | HC Platform";
$pageDescription = "HC Platformの管理者向けゲームサーバープラン管理ページです。";
$pageCss = "/admin/game-plans/game-plans.css";

$errors = [];
$plans = [];

function gp_price(?int $amount): string
{
    return "¥" . number_format((int)($amount ?? 0));
}

function gp_mb_to_gb(?int $mb): string
{
    if (!$mb || $mb <= 0) {
        return "-";
    }

    $gb = $mb / 1024;

    if (floor($gb) == $gb) {
        return (string)(int)$gb . "GB";
    }

    return number_format($gb, 1) . "GB";
}

function gp_cpu(?int $cpuLimit): string
{
    if (!$cpuLimit || $cpuLimit <= 0) {
        return "無制限";
    }

    $vcpu = $cpuLimit / 100;

    if (floor($vcpu) == $vcpu) {
        return (string)(int)$vcpu . "vCPU";
    }

    return number_format($vcpu, 1) . "vCPU";
}

function gp_status_label(string $status): string
{
    return match ($status) {
        "draft" => "下書き",
        "published" => "公開",
        "hidden" => "非公開",
        default => $status,
    };
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

            COALESCE((
                SELECT COUNT(*)
                FROM game_server_orders o
                WHERE o.plan_id = gsp.id
                  AND o.status NOT IN ('cancelled', 'expired')
            ), 0) AS active_contract_count,

            COALESCE((
                SELECT COUNT(*)
                FROM game_server_orders o
                WHERE o.plan_id = gsp.id
            ), 0) AS total_contract_count,

            COALESCE((
                SELECT COUNT(*)
                FROM server_order_plan_change_requests r
                WHERE r.current_plan_id = gsp.id
                  AND r.status = 'pending'
            ), 0) AS outgoing_pending_change_count,

            COALESCE((
                SELECT COUNT(*)
                FROM server_order_plan_change_requests r
                WHERE r.requested_plan_id = gsp.id
                  AND r.status = 'pending'
            ), 0) AS incoming_pending_change_count,

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
                    各プランの契約中件数とプラン変更申請の流れも確認できます。
                </p>
            </div>

            <aside class="game-plans-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h((string)$user["username"]); ?></h2>
                <p><?php echo h(role_label((string)$user["role"])); ?></p>
            </aside>
        </div>
    </section>

    <section class="section game-plans-section">
        <div class="container">
            <?php if ($errors): ?>
                <div class="admin-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="plans-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Plans</p>
                        <h2>プラン一覧</h2>
                    </div>

                    <div class="panel-actions">
                        <a href="/admin/server-orders/" class="sub-button">契約管理</a>
                        <a href="/admin/plan-change-requests/" class="sub-button">プラン変更申請</a>
                        <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                    </div>
                </div>

                <?php if (!$plans): ?>
                    <div class="empty-box">
                        <h3>プランがまだ登録されていません。</h3>
                        <p>新規追加からゲームサーバープランを作成してください。</p>
                        <a href="/admin/game-plans/edit/" class="back-button">プランを追加する</a>
                    </div>
                <?php else: ?>
                    <div class="plan-card-grid">
                        <?php foreach ($plans as $plan): ?>
                            <?php
                            $nodes = [];
                            if (!empty($plan["nodes"])) {
                                $decoded = json_decode((string)$plan["nodes"], true);
                                $nodes = is_array($decoded) ? $decoded : [];
                            }

                            $activeContractCount = (int)($plan["active_contract_count"] ?? 0);
                            $totalContractCount = (int)($plan["total_contract_count"] ?? 0);
                            $incomingChangeCount = (int)($plan["incoming_pending_change_count"] ?? 0);
                            $outgoingChangeCount = (int)($plan["outgoing_pending_change_count"] ?? 0);
                            ?>
                            <article class="plan-card status-<?php echo h((string)$plan["status"]); ?>">
                                <div class="plan-card-head">
                                    <div>
                                        <span class="plan-status">
                                            <?php echo h(gp_status_label((string)$plan["status"])); ?>
                                        </span>
                                        <h3><?php echo h((string)$plan["name"]); ?></h3>
                                        <p><?php echo h((string)$plan["slug"]); ?></p>
                                    </div>

                                    <strong class="plan-price">
                                        <?php echo h(gp_price((int)$plan["price_monthly"])); ?>
                                        <small>/月</small>
                                    </strong>
                                </div>

                                <?php if ($activeContractCount > 0): ?>
                                    <div class="plan-contract-badge">
                                        契約中 <?php echo h((string)$activeContractCount); ?> 件
                                    </div>
                                <?php endif; ?>

                                <div class="plan-spec-grid">
                                    <div>
                                        <span>Memory</span>
                                        <strong><?php echo h(gp_mb_to_gb((int)$plan["memory_mb"])); ?></strong>
                                    </div>
                                    <div>
                                        <span>CPU</span>
                                        <strong><?php echo h(gp_cpu((int)$plan["cpu_limit"])); ?></strong>
                                    </div>
                                    <div>
                                        <span>Disk</span>
                                        <strong><?php echo h(gp_mb_to_gb((int)$plan["disk_mb"])); ?></strong>
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

                                <div class="plan-flow-grid">
                                    <a href="/admin/server-orders/?q=<?php echo h((string)$plan["name"]); ?>">
                                        <span>契約</span>
                                        <strong><?php echo h((string)$activeContractCount); ?> 件</strong>
                                        <small>全体 <?php echo h((string)$totalContractCount); ?> 件</small>
                                    </a>

                                    <a href="/admin/plan-change-requests/?q=<?php echo h((string)$plan["name"]); ?>">
                                        <span>変更申請</span>
                                        <strong>IN <?php echo h((string)$incomingChangeCount); ?> / OUT <?php echo h((string)$outgoingChangeCount); ?></strong>
                                        <small>申請一覧へ</small>
                                    </a>
                                </div>

                                <div class="plan-node-list">
                                    <span>対応Node</span>

                                    <?php if (!$nodes): ?>
                                        <p>対応Nodeが未設定です。</p>
                                    <?php else: ?>
                                        <?php foreach ($nodes as $node): ?>
                                            <div class="plan-node-item">
                                                <strong>
                                                    <?php echo h((string)($node["label"] ?: $node["name"] ?: "Node")); ?>
                                                </strong>
                                                <small>
                                                    Node #<?php echo h((string)($node["ptero_node_id"] ?? "-")); ?>
                                                    /
                                                    <?php echo h((string)($node["cpu_type"] ?? "-")); ?>
                                                    <?php if (!empty($node["is_primary"])): ?>
                                                        / 優先
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="plan-card-actions">
                                    <a href="/admin/game-plans/edit/?id=<?php echo h((string)$plan["id"]); ?>" class="detail-button">
                                        編集する
                                    </a>
                                    <a href="/admin/server-orders/?q=<?php echo h((string)$plan["name"]); ?>" class="secondary-detail-button">
                                        契約を見る
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
