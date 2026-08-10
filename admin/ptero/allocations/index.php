<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";
require_once __DIR__ . "/../../../lib/pterodactyl.php";

$adminUser = require_role("admin");

header('Location: /staff/rental-server/game-server/nodes/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "接続ポート枠確認 | HC Platform";
$pageDescription = "ゲームサーバーパネル Nodeごとの空きAllocationを確認します。";
$pageCss = "/admin/ptero/allocations/allocations.css";

$pdo = db();

$nodes = [];
$errors = [];
$pteroEnabled = false;
$pteroMock = false;

try {
    $pteroEnabled = hc_ptero_enabled();
    $pteroMock = hc_ptero_mock();
} catch (Throwable $e) {
    $pteroEnabled = false;
    $pteroMock = false;
}

function allocation_datetime(?string $value): string
{
    if (!$value) {
        return "-";
    }

    try {
        return (new DateTime($value))->format("Y/m/d H:i");
    } catch (Throwable $e) {
        return $value;
    }
}

function allocation_fetch_node_summary(int $pteroNodeId): array
{
    $page = 1;
    $maxPages = 20;

    $total = 0;
    $free = 0;
    $assigned = 0;
    $nextFree = null;
    $items = [];

    while ($page <= $maxPages) {
        $response = hc_ptero_list_node_allocations($pteroNodeId, $page);
        $allocations = $response["data"] ?? [];

        foreach ($allocations as $allocation) {
            $attributes = $allocation["attributes"] ?? $allocation;

            $allocationId = (int)($attributes["id"] ?? 0);
            $ip = (string)($attributes["ip"] ?? "");
            $alias = $attributes["alias"] ?? null;
            $port = (int)($attributes["port"] ?? 0);
            $isFree = hc_ptero_allocation_is_free($allocation);

            if ($allocationId <= 0) {
                continue;
            }

            $total++;

            if ($isFree) {
                $free++;

                if ($nextFree === null) {
                    $nextFree = [
                        "id" => $allocationId,
                        "ip" => $ip,
                        "alias" => $alias,
                        "port" => $port,
                    ];
                }
            } else {
                $assigned++;
            }

            if (count($items) < 8) {
                $items[] = [
                    "id" => $allocationId,
                    "ip" => $ip,
                    "alias" => $alias,
                    "port" => $port,
                    "is_free" => $isFree,
                ];
            }
        }

        $pagination = $response["meta"]["pagination"] ?? [];
        $totalPages = (int)($pagination["total_pages"] ?? $page);

        if ($page >= $totalPages) {
            break;
        }

        $page++;
    }

    return [
        "total" => $total,
        "free" => $free,
        "assigned" => $assigned,
        "next_free" => $nextFree,
        "items" => $items,
    ];
}

try {
    $stmt = $pdo->query("
        SELECT
            id,
            name,
            ptero_node_id,
            fqdn,
            scheme,
            daemon_port,
            memory_total_mb,
            disk_total_mb,
            is_active,
            created_at,
            updated_at
        FROM ptero_nodes
        ORDER BY id ASC
    ");

    $nodes = $stmt->fetchAll();
} catch (Throwable $e) {
    try {
        $stmt = $pdo->query("
            SELECT
                id,
                name,
                ptero_node_id,
                created_at,
                updated_at
            FROM ptero_nodes
            ORDER BY id ASC
        ");

        $nodes = $stmt->fetchAll();
    } catch (Throwable $inner) {
        $errors[] = "ptero_nodes テーブルからNode情報を取得できませんでした。先にゲームサーバーパネル Node連携を設定してください。";
        $nodes = [];
    }
}

foreach ($nodes as $index => $node) {
    $summary = null;
    $nodeErrors = [];

    $pteroNodeId = (int)($node["ptero_node_id"] ?? 0);

    if (!$pteroEnabled) {
        $nodeErrors[] = "ゲームサーバーパネル連携が無効です。PTERO_ENABLED=true にしてください。";
    } elseif ($pteroNodeId <= 0) {
        $nodeErrors[] = "ゲームサーバーパネル Node IDが未設定です。";
    } else {
        try {
            $summary = allocation_fetch_node_summary($pteroNodeId);
        } catch (Throwable $e) {
            $nodeErrors[] = $e->getMessage();
        }
    }

    $nodes[$index]["allocation_summary"] = $summary;
    $nodes[$index]["allocation_errors"] = $nodeErrors;
}

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="allocations-page">
    <section class="allocations-hero">
        <div class="container allocations-hero-grid">
            <div class="allocations-copy reveal">
                <p class="eyebrow">Admin / ゲームサーバーパネル Allocations</p>
                <h1>Allocation確認</h1>
                <p>
                    ゲームサーバーパネル Nodeごとの空きIP:Portを確認します。
                    サーバー作成時は、ここで空いているAllocationが自動的に使われます。
                </p>
            </div>

            <aside class="allocations-status-card reveal">
                <span><?php echo $pteroMock ? "Mock Mode" : "Live API"; ?></span>
                <h2><?php echo h((string)count($nodes)); ?> Node</h2>
                <p>
                    <?php echo $pteroEnabled ? "ゲームサーバーパネル連携は有効です。" : "ゲームサーバーパネル連携は無効です。"; ?>
                </p>
            </aside>
        </div>
    </section>

    <section class="section allocations-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/ptero/" class="back-button">ゲームサーバーパネル確認へ戻る</a>
                <a href="/admin/server-orders/provision/" class="sub-button">サーバー作成へ</a>
                <a href="/admin/" class="sub-button">管理者ページへ</a>
            </div>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="allocations-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Nodes</p>
                        <h2>Node別Allocation状況</h2>
                    </div>

                    <a href="/admin/ptero/allocations/" class="refresh-button">再読み込み</a>
                </div>

                <?php if (!$nodes): ?>
                    <div class="empty-box">
                        <h3>Nodeが登録されていません。</h3>
                        <p>ゲームサーバーパネル Node連携ページでNodeを登録してください。</p>
                    </div>
                <?php else: ?>
                    <div class="allocation-card-list">
                        <?php foreach ($nodes as $node): ?>
                            <?php
                            $summary = $node["allocation_summary"] ?? null;
                            $nodeErrors = $node["allocation_errors"] ?? [];
                            $nextFree = $summary["next_free"] ?? null;
                            ?>
                            <article class="allocation-card <?php echo $summary && (int)$summary["free"] > 0 ? "has-free" : "no-free"; ?>">
                                <div class="allocation-card-head">
                                    <div>
                                        <span class="node-id">Local Node #<?php echo h((string)$node["id"]); ?></span>
                                        <strong class="node-badge">
                                            パネルNode #<?php echo h((string)($node["ptero_node_id"] ?? "-")); ?>
                                        </strong>
                                    </div>

                                    <small>更新: <?php echo h(allocation_datetime((string)($node["updated_at"] ?? $node["created_at"] ?? ""))); ?></small>
                                </div>

                                <div class="allocation-card-main">
                                    <div class="node-info">
                                        <h3><?php echo h((string)($node["name"] ?? "名称未設定")); ?></h3>
                                        <p>
                                            <?php if (!empty($node["fqdn"])): ?>
                                                <?php echo h((string)($node["scheme"] ?? "https")); ?>://<?php echo h((string)$node["fqdn"]); ?>:<?php echo h((string)($node["daemon_port"] ?? "8080")); ?>
                                            <?php else: ?>
                                                ゲームサーバーパネル Node ID:
                                                <?php echo h((string)($node["ptero_node_id"] ?? "-")); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <div class="allocation-stats">
                                        <div>
                                            <span>合計</span>
                                            <strong><?php echo $summary ? h((string)$summary["total"]) : "-"; ?></strong>
                                        </div>

                                        <div class="<?php echo $summary && (int)$summary["free"] > 0 ? "good" : "bad"; ?>">
                                            <span>空き</span>
                                            <strong><?php echo $summary ? h((string)$summary["free"]) : "-"; ?></strong>
                                        </div>

                                        <div>
                                            <span>使用済み</span>
                                            <strong><?php echo $summary ? h((string)$summary["assigned"]) : "-"; ?></strong>
                                        </div>
                                    </div>

                                    <div class="next-free-box">
                                        <span>次に使う候補</span>

                                        <?php if ($nextFree): ?>
                                            <strong>
                                                #<?php echo h((string)$nextFree["id"]); ?>
                                                /
                                                <?php echo h((string)($nextFree["alias"] ?: $nextFree["ip"])); ?>:<?php echo h((string)$nextFree["port"]); ?>
                                            </strong>
                                        <?php else: ?>
                                            <strong>-</strong>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($nodeErrors): ?>
                                    <div class="node-error-box">
                                        <?php foreach ($nodeErrors as $nodeError): ?>
                                            <p><?php echo h((string)$nodeError); ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                <?php elseif ($summary): ?>
                                    <div class="allocation-mini-list">
                                        <?php foreach ($summary["items"] as $item): ?>
                                            <span class="<?php echo $item["is_free"] ? "is-free" : "is-used"; ?>">
                                                #<?php echo h((string)$item["id"]); ?>
                                                <?php echo h((string)($item["alias"] ?: $item["ip"])); ?>:<?php echo h((string)$item["port"]); ?>
                                                /
                                                <?php echo $item["is_free"] ? "空き" : "使用中"; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
