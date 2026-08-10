<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";
require_once __DIR__ . "/../../../lib/game_server_approval.php";

$adminUser = require_role("admin");

header('Location: /staff/rental-server/game-server/approvals/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;
$pdo = db();

hc_server_approval_ensure_schema($pdo);

$pageTitle = "サーバー承認待ち | HC Platform";
$pageDescription = "自動作成済みゲームサーバーの利用開始を承認します。";
$pageCss = "/admin/server-orders/pending/pending.css";

$flash = $_SESSION["server_approval_flash"] ?? null;
unset($_SESSION["server_approval_flash"]);

if (empty($_SESSION["server_approval_token"])) {
    $_SESSION["server_approval_token"] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION["server_approval_token"];

$stmt = $pdo->query("
    SELECT
        gso.id,
        gso.user_id,
        gso.server_name,
        gso.status,
        gso.payment_status,
        gso.amount,
        gso.currency,
        gso.paid_at,
        gso.provisioned_at,
        gso.approval_requested_at,

        gsp.name AS plan_name,
        gsp.memory_mb,
        gsp.cpu_limit,
        gsp.disk_mb,

        u.username,
        u.email,

        ps.ptero_server_id,
        ps.ptero_identifier,
        ps.status AS ptero_server_status,

        pn.name AS node_name,
        pn.ptero_node_id
    FROM game_server_orders gso
    JOIN game_server_plans gsp
        ON gsp.id = gso.plan_id
    JOIN users u
        ON u.id = gso.user_id
    LEFT JOIN ptero_servers ps
        ON ps.order_id = gso.id
    LEFT JOIN ptero_nodes pn
        ON pn.id = gso.selected_node_id
    WHERE gso.status IN (
        'pending_approval',
        'approval_failed'
    )
      AND gso.payment_status = 'paid'
    ORDER BY
        gso.approval_requested_at ASC NULLS LAST,
        gso.id ASC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function pending_datetime(?string $value): string
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

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="server-approval-page">
    <div class="container">
        <header class="server-approval-header">
            <div class="server-approval-header-copy">
                <p class="eyebrow">Admin / Server Approval</p>
                <h1>サーバー承認待ち</h1>
                <p>
                    支払い後に自動作成されたゲームサーバーを確認し、
                    問題がなければ利用開始を承認します。
                </p>
            </div>

            <aside class="server-approval-summary">
                <span>Pending Approval</span>
                <strong><?php echo h((string)count($orders)); ?> 件</strong>
            </aside>
        </header>

        <div class="server-approval-toolbar">
            <a href="/admin/server-orders/" class="sub-button">
                注文一覧へ戻る
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                <?php echo h((string)$flash["message"]); ?>
            </div>
        <?php endif; ?>

        <?php if (!$orders): ?>
            <section class="server-approval-empty">
                <h2>承認待ちはありません</h2>
                <p>現在、利用開始確認が必要なサーバーはありません。</p>
            </section>
        <?php else: ?>
            <div class="server-approval-list">
                <?php foreach ($orders as $order): ?>
                    <article class="server-approval-card <?php echo $order["status"] === "approval_failed" ? "is-failed" : ""; ?>">
                        <div class="server-approval-card-head">
                            <div>
                                <div class="server-approval-order-meta">
                                    <span class="server-approval-order-number">
                                        Order #<?php echo h((string)$order["id"]); ?>
                                    </span>

                                    <span class="server-approval-status <?php echo $order["status"] === "approval_failed" ? "is-failed" : ""; ?>">
                                        <?php echo $order["status"] === "approval_failed" ? "承認失敗" : "承認待ち"; ?>
                                    </span>
                                </div>

                                <h2>
                                    <?php echo h((string)$order["server_name"]); ?>
                                </h2>

                                <p class="server-approval-user">
                                    <?php echo h((string)$order["username"]); ?>
                                    /
                                    <?php echo h((string)$order["email"]); ?>
                                </p>
                            </div>

                            <div class="server-approval-plan">
                                <strong>
                                    <?php echo h((string)$order["plan_name"]); ?>
                                </strong>

                                <p>
                                    <?php echo h(number_format((int)$order["amount"])); ?>
                                    円
                                </p>
                            </div>
                        </div>

                        <dl class="server-approval-detail-grid">
                            <div class="server-approval-detail">
                                <dt>注文状態</dt>
                                <dd><?php echo h((string)$order["status"]); ?></dd>
                            </div>

                            <div class="server-approval-detail">
                                <dt>支払い状態</dt>
                                <dd><?php echo h((string)$order["payment_status"]); ?></dd>
                            </div>

                            <div class="server-approval-detail">
                                <dt>メモリ</dt>
                                <dd>
                                    <?php echo h(number_format((int)$order["memory_mb"])); ?>
                                    MB
                                </dd>
                            </div>

                            <div class="server-approval-detail">
                                <dt>CPU</dt>
                                <dd>
                                    <?php echo h((string)$order["cpu_limit"]); ?>%
                                </dd>
                            </div>

                            <div class="server-approval-detail">
                                <dt>ディスク</dt>
                                <dd>
                                    <?php echo h(number_format((int)$order["disk_mb"])); ?>
                                    MB
                                </dd>
                            </div>

                            <div class="server-approval-detail">
                                <dt>Node</dt>
                                <dd>
                                    <?php echo h((string)($order["node_name"] ?: "-")); ?>
                                </dd>
                            </div>

                            <div class="server-approval-detail">
                                <dt>Pterodactyl Server ID</dt>
                                <dd>
                                    <?php echo h((string)($order["ptero_server_id"] ?: "-")); ?>
                                </dd>
                            </div>

                            <div class="server-approval-detail">
                                <dt>作成日時</dt>
                                <dd>
                                    <?php echo h(
                                        pending_datetime(
                                            (string)$order["provisioned_at"]
                                        )
                                    ); ?>
                                </dd>
                            </div>
                        </dl>

                        <?php if ($order["status"] === "approval_failed"): ?>
                            <div class="server-approval-error">
                                前回の承認処理に失敗しています。
                                再度承認を実行できます。
                            </div>
                        <?php endif; ?>

                        <form
                            method="post"
                            action="/admin/server-orders/approve"
                            class="server-approval-actions"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo h($csrfToken); ?>"
                            >

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?php echo h((string)$order["id"]); ?>"
                            >

                            <button type="submit" class="primary-action">
                                承認して利用開始
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
