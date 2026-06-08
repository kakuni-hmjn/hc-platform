<?php

session_start();

require_once __DIR__ . "/../../../../lib/helpers.php";
require_once __DIR__ . "/../../../../lib/auth.php";
require_once __DIR__ . "/../../../../lib/db.php";
require_once __DIR__ . "/../../../../lib/permissions.php";

$adminUser = require_role("admin");

$pageTitle = "ゲームサーバー作成失敗 | HC Platform";
$pageDescription = "ゲームサーバー作成に失敗した契約の一覧です。";
$pageCss = "/admin/server-orders/provision/failed/failed.css";

$pdo = db();

$orders = [];
$errors = [];

$flash = $_SESSION["provision_failed_flash"] ?? null;
unset($_SESSION["provision_failed_flash"]);

if (empty($_SESSION["provision_failed_token"])) {
    $_SESSION["provision_failed_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["provision_failed_token"];

function failed_datetime(?string $value): string
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

function failed_memory_label(?int $memoryMb): string
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

function failed_cpu_label(?int $cpuLimit): string
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

try {
    $pdo->exec("
        ALTER TABLE game_server_orders
        ADD COLUMN IF NOT EXISTS provisioning_started_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS provisioned_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS failed_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS provision_error TEXT NULL,
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL
    ");

    $stmt = $pdo->query("
        SELECT
            gso.id,
            gso.user_id,
            gso.selected_node_id,
            gso.server_name,
            gso.status,
            gso.payment_status,
            gso.amount,
            gso.currency,
            gso.created_at,
            gso.failed_at,
            gso.provision_error,

            gsp.name AS plan_name,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,
            gsp.ptero_egg_id,
            gsp.ptero_docker_image,

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
        WHERE gso.status = 'provision_failed'
          AND gso.payment_status = 'paid'
        ORDER BY gso.failed_at DESC NULLS LAST, gso.created_at DESC, gso.id DESC
    ");

    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "作成失敗一覧の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../../parts/header/header.php"; ?>

<main class="failed-provision-page">
    <section class="failed-hero">
        <div class="container failed-hero-grid">
            <div class="failed-copy reveal">
                <p class="eyebrow">Admin / Provision Failed</p>
                <h1>作成失敗した契約</h1>
                <p>
                    ゲームサーバー作成に失敗した契約を確認します。
                    原因を修正したあと、契約状態を「作成中」に戻して再実行できます。
                </p>
            </div>

            <aside class="failed-status-card reveal">
                <span>Failed</span>
                <h2><?php echo h((string)count($orders)); ?> 件</h2>
                <p>再実行が必要な契約です。</p>
            </aside>
        </div>
    </section>

    <section class="section failed-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/server-orders/provision/" class="back-button">ゲームサーバーパネル作成へ戻る</a>
                <a href="/admin/server-orders/ready/" class="sub-button">作成待ちへ</a>
                <a href="/admin/server-orders/" class="sub-button">申込管理へ</a>
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

            <section class="failed-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Retry Queue</p>
                        <h2>再実行待ち一覧</h2>
                    </div>
                </div>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>作成失敗した契約はありません。</h3>
                        <p>ゲームサーバーパネル作成でエラーになった契約がここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="failed-card-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="failed-card">
                                <div class="failed-card-head">
                                    <div>
                                        <span class="order-id">契約 #<?php echo h((string)$order["id"]); ?></span>
                                        <strong class="status-badge">作成失敗</strong>
                                    </div>

                                    <small>
                                        失敗日時:
                                        <?php echo h(failed_datetime((string)($order["failed_at"] ?? ""))); ?>
                                    </small>
                                </div>

                                <div class="failed-card-main">
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
                                            <?php echo h(failed_memory_label((int)$order["memory_mb"])); ?>
                                            /
                                            <?php echo h(failed_cpu_label((int)$order["cpu_limit"])); ?>
                                            /
                                            Disk <?php echo h((string)((int)$order["disk_mb"])); ?>MB
                                        </p>
                                    </div>

                                    <div class="ptero-info">
                                        <span>Node / Egg</span>
                                        <strong>
                                            <?php echo h((string)($order["node_name"] ?: "Node未選択")); ?>
                                            /
                                            Node ID:
                                            <?php echo h((string)($order["ptero_node_id"] ?: "-")); ?>
                                        </strong>
                                        <p>
                                            Egg:
                                            <?php echo h((string)($order["ptero_egg_id"] ?: "-")); ?>
                                            /
                                            <?php echo h((string)($order["ptero_docker_image"] ?: "Docker Image未設定")); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="error-box">
                                    <strong>失敗理由</strong>
                                    <p><?php echo nl2br(h((string)($order["provision_error"] ?: "エラー内容が記録されていません。"))); ?></p>
                                </div>

                                <div class="failed-actions">
                                    <a href="/admin/server-orders/detail/?id=<?php echo h((string)$order["id"]); ?>" class="secondary-action">
                                        契約詳細
                                    </a>

                                    <form method="post" action="/admin/server-orders/provision/failed/retry.php">
                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                        <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">

                                        <button type="submit" class="primary-action">
                                            再作成待ちへ戻す
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
