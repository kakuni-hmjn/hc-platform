<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$adminUser = require_role("admin");

$pageTitle = "ゲームサーバー作成 | HC Platform";
$pageDescription = "作成中の契約をゲームサーバーパネルへ実作成します。";
$pageCss = "/admin/server-orders/provision/provision.css";

$pdo = db();

$orders = [];
$errors = [];

$flash = $_SESSION["provision_orders_flash"] ?? null;
unset($_SESSION["provision_orders_flash"]);

if (empty($_SESSION["provision_orders_token"])) {
    $_SESSION["provision_orders_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["provision_orders_token"];

function provision_datetime(?string $value): string
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

function provision_memory_label(?int $memoryMb): string
{
    if (!$memoryMb || $memoryMb <= 0) {
        return "-";
    }

    $gb = $memoryMb / 1024;

    if (floor($gb) == $gb) {
        return (string)(int)$gb . "GB";
    }

    return number_format($gb, 1) . "GB";
}

function provision_cpu_label(?int $cpuLimit): string
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

function provision_default_environment(array $order): string
{
    $jarfile = "server.jar";
    $version = trim((string)($order["minecraft_version"] ?? ""));

    if ($version === "") {
        $version = "latest";
    }

    $env = [
        "SERVER_JARFILE" => $jarfile,
        "MINECRAFT_VERSION" => $version,
        "BUILD_NUMBER" => "latest",
    ];

    return json_encode($env, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ptero_servers (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL REFERENCES game_server_orders(id) ON DELETE CASCADE,
            node_id INTEGER NULL,
            ptero_user_id INTEGER NULL,
            ptero_server_id INTEGER NULL,
            ptero_identifier VARCHAR(80),
            ptero_uuid VARCHAR(120),
            name VARCHAR(160),
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        )
    ");

    $pdo->exec("
        ALTER TABLE ptero_servers
        ADD COLUMN IF NOT EXISTS ptero_allocation_id INTEGER NULL
    ");

    $stmt = $pdo->query("
        SELECT
            gso.id,
            gso.user_id,
            gso.selected_node_id,
            gso.server_name,
            gso.minecraft_type,
            gso.server_software,
            gso.minecraft_version,
            gso.status,
            gso.payment_status,
            gso.amount,
            gso.currency,
            gso.created_at,

            gsp.name AS plan_name,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,
            gsp.backup_limit,
            gsp.database_limit,
            gsp.allocation_limit,
            gsp.ptero_nest_id,
            gsp.ptero_egg_id,
            gsp.ptero_docker_image,
            gsp.ptero_startup_command,

            u.username,
            u.email,

            pn.name AS node_name,
            pn.ptero_node_id,

            ps.id AS ptero_server_local_id
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        LEFT JOIN users u ON u.id = gso.user_id
        LEFT JOIN ptero_nodes pn ON pn.id = gso.selected_node_id
        LEFT JOIN ptero_servers ps ON ps.order_id = gso.id AND ps.deleted_at IS NULL
        WHERE gso.status = 'creating'
          AND gso.payment_status = 'paid'
          AND ps.id IS NULL
        ORDER BY gso.created_at ASC, gso.id ASC
    ");

    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "ゲームサーバーパネル作成待ち一覧の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="provision-page">
    <section class="provision-hero">
        <div class="container provision-hero-grid">
            <div class="provision-copy reveal">
                <p class="eyebrow">Admin / ゲームサーバーパネル Provisioning</p>
                <h1>ゲームサーバー作成</h1>
                <p>
                    状態が「作成中」の契約をゲームサーバーパネルへ実作成します。
                    ゲームサーバーパネルアカウント、Node、AllocationはHC側で自動取得します。
                </p>
            </div>

            <aside class="provision-status-card reveal">
                <span>Creating Queue</span>
                <h2><?php echo h((string)count($orders)); ?> 件</h2>
                <p>ゲームサーバーパネル作成待ちの契約です。</p>
            </aside>
        </div>
    </section>

    <section class="section provision-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/server-orders/ready/" class="back-button">作成待ちへ戻る</a>
                <a href="/admin/server-orders/" class="sub-button">申込管理へ</a>
                <a href="/admin/server-orders/provision/failed/" class="sub-button">作成失敗一覧へ</a>
                <a href="/admin/ptero/" class="sub-button">ゲームサーバーパネル確認へ</a>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="provision-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Provision</p>
                        <h2>作成中契約一覧</h2>
                    </div>
                </div>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>ゲームサーバーパネル作成待ちの契約はありません。</h3>
                        <p>支払い完了済み契約を「作成開始へ進める」と、ここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="provision-card-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="provision-card">
                                <div class="provision-card-head">
                                    <div>
                                        <span class="order-id">契約 #<?php echo h((string)$order["id"]); ?></span>
                                        <strong class="status-badge">作成中</strong>
                                    </div>

                                    <small><?php echo h(provision_datetime((string)$order["created_at"])); ?></small>
                                </div>

                                <div class="provision-card-main">
                                    <div class="main-info">
                                        <h3><?php echo h((string)($order["server_name"] ?: "名称未設定")); ?></h3>
                                        <p>
                                            <?php echo h((string)($order["username"] ?: "不明なユーザー")); ?>
                                            /
                                            <?php echo h((string)($order["email"] ?: "-")); ?>
                                        </p>
                                    </div>

                                    <div class="plan-info">
                                        <span>プラン</span>
                                        <strong><?php echo h((string)$order["plan_name"]); ?></strong>
                                        <p>
                                            <?php echo h(provision_memory_label((int)$order["memory_mb"])); ?>
                                            /
                                            <?php echo h(provision_cpu_label((int)$order["cpu_limit"])); ?>
                                            /
                                            Disk <?php echo h((string)((int)$order["disk_mb"])); ?>MB
                                        </p>
                                    </div>

                                    <div class="ptero-info">
                                        <span>Node / Egg</span>
                                        <strong>
                                            <?php echo h((string)($order["node_name"] ?: "Node未選択")); ?>
                                            /
                                            Node ID: <?php echo h((string)($order["ptero_node_id"] ?: "-")); ?>
                                        </strong>
                                        <p>
                                            Egg: <?php echo h((string)($order["ptero_egg_id"] ?: "-")); ?>
                                            /
                                            <?php echo h((string)($order["ptero_docker_image"] ?: "Docker Image未設定")); ?>
                                        </p>
                                    </div>
                                </div>

                                <form method="post" action="/admin/server-orders/provision/create.php" class="provision-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                    <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">

                                    <div class="form-grid">
                                        <div class="auto-user-box">
                                            <span>パネルユーザー</span>
                                            <strong>HCアカウントから自動取得・自動作成</strong>
                                            <p>external_id = hc_user_<?php echo h((string)$order["user_id"]); ?> で紐付けます。</p>
                                        </div>

                                        <div class="auto-user-box">
                                            <span>Allocation</span>
                                            <strong>Nodeと空きIP:Portを自動取得</strong>
                                            <p>
                                                Node ID <?php echo h((string)($order["ptero_node_id"] ?: "-")); ?>
                                                が未選択の場合は、空きAllocationがあるNodeを自動選択します。
                                            </p>
                                        </div>

                                        <label>
                                            <span>Startup Command</span>
                                            <input
                                                type="text"
                                                name="startup_command"
                                                value="<?php echo h((string)($order["ptero_startup_command"] ?: "java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}")); ?>"
                                                required
                                            >
                                        </label>

                                        <label>
                                            <span>Docker Image</span>
                                            <input
                                                type="text"
                                                name="docker_image"
                                                value="<?php echo h((string)($order["ptero_docker_image"] ?: "ghcr.io/pterodactyl/yolks:java_21")); ?>"
                                                required
                                            >
                                        </label>
                                    </div>

                                    <label class="environment-label">
                                        <span>Environment JSON</span>
                                        <textarea name="environment_json" rows="7" required><?php echo h(provision_default_environment($order)); ?></textarea>
                                    </label>

                                    <div class="provision-actions">
                                        <a href="/admin/server-orders/detail/?id=<?php echo h((string)$order["id"]); ?>" class="secondary-action">
                                            契約詳細
                                        </a>

                                        <button type="submit" class="primary-action">
                                            ゲームサーバーを作成
                                        </button>
                                    </div>
                                </form>
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
