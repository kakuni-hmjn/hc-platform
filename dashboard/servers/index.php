<?php
session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = current_user();

if (!$currentUser) {
    header("Location: /login/?redirect=/dashboard/servers/");
    exit;
}

$pageTitle = "契約中サーバー | HC Platform";
$pageDescription = "HC Platformで契約中のゲームサーバー一覧ページです。";
$pageCss = "/dashboard/servers/servers.css";

$pdo = db();
$servers = [];
$orders = [];
$errors = [];

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

function billing_type_label(string $billingType): string
{
    return match ($billingType) {
        "auto_subscription" => "毎月自動更新",
        "manual_renewal" => "1ヶ月ごとの手動更新",
        default => "不明",
    };
}

function server_status_label(string $status): string
{
    return match ($status) {
        "active" => "稼働中",
        "suspended" => "停止中",
        "deleted" => "削除済み",
        "unknown" => "不明",
        default => $status,
    };
}

function order_status_label(string $status): string
{
    return match ($status) {
        "pending_payment" => "決済待ち",
        "paid" => "決済済み",
        "creating" => "作成中",
        "active" => "稼働中",
        "provision_failed" => "作成失敗",
        "suspended" => "停止中",
        "cancelled" => "キャンセル",
        "expired" => "期限切れ",
        default => $status,
    };
}

try {
    $serverStmt = $pdo->prepare("
        SELECT
            ps.*,
            gso.billing_type,
            gso.payment_status,
            gso.expires_at,
            gso.next_payment_due_at,
            gso.cancel_requested_at,
            gso.cancel_effective_at,
            gso.auto_renew_cancelled,
            gsp.name AS plan_name,
            gsp.name AS plan_name,
            gsp.price_monthly,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,
            pn.name AS node_name,
            pn.label AS node_label,
            pn.cpu_type AS node_cpu_type
        FROM ptero_servers ps
        JOIN game_server_orders gso ON gso.id = ps.order_id
        JOIN game_server_plans gsp ON gsp.id = ps.plan_id
        LEFT JOIN ptero_nodes pn ON pn.id = ps.node_id
        WHERE ps.user_id = :user_id
        AND ps.status != 'deleted'
        ORDER BY ps.created_at DESC
    ");
    $serverStmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);
    $servers = $serverStmt->fetchAll();

    $orderStmt = $pdo->prepare("
        SELECT
            gso.*,
            gsp.name AS plan_name,
            gsp.price_monthly,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb
        FROM game_server_orders gso
        JOIN game_server_plans gsp ON gsp.id = gso.plan_id
        WHERE gso.user_id = :user_id
        AND gso.status IN ('pending_payment', 'paid', 'creating', 'provision_failed')
        AND NOT EXISTS (
            SELECT 1
            FROM ptero_servers ps
            WHERE ps.order_id = gso.id
        )
        ORDER BY gso.created_at DESC
    ");
    $orderStmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);
    $orders = $orderStmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "サーバー情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="servers-page">

    <section class="servers-hero">
        <div class="container servers-hero-grid">

            <div class="servers-copy reveal">
                <p class="eyebrow">Dashboard / Servers</p>
                <h1>契約中サーバー</h1>
                <p>
                    ゲームサーバーレンタルで作成されたサーバーと、現在処理中の申込を確認できます。
                </p>
            </div>

            <aside class="servers-status-card reveal">
                <span>HC Account</span>
                <h2><?php echo h($currentUser["username"]); ?></h2>
                <p>契約中サーバーと申込状況を表示しています。</p>
            </aside>

        </div>
    </section>

    <section class="section servers-section">
        <div class="container">

            <?php if ($errors): ?>
                <div class="servers-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET["cancelled"])): ?>
                <div class="servers-success">
                    <p>解約申請を受け付けました。現在の利用期間終了までは利用できます。返金は行われません。</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET["cancel_error"])): ?>
                <div class="servers-alert">
                    <p>解約処理に失敗しました。返金不可への同意が必要です。</p>
                </div>
            <?php endif; ?>

            <div class="servers-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Active Servers</p>
                        <h2>サーバー一覧</h2>
                    </div>

                    <a href="/order/game-server/" class="create-button">新しく申し込む</a>
                </div>

                <?php if (!$servers): ?>
                    <div class="empty-box">
                        <h3>契約中のゲームサーバーはありません。</h3>
                        <p>ゲームサーバーレンタルページからプランを選んで申し込みできます。</p>
                        <a href="/services/rental/game-server/" class="create-button">プランを見る</a>
                    </div>
                <?php else: ?>
                    <div class="server-grid">
                        <?php foreach ($servers as $server): ?>
                            <article class="server-card status-<?php echo h((string)$server["status"]); ?> <?php echo !empty($server["auto_renew_cancelled"]) ? "is-cancelled" : ""; ?>">
                                <div class="server-card-head">
                                    <div>
                                        <span class="server-status">
                                            <?php echo !empty($server["auto_renew_cancelled"]) ? "解約申請済み" : h(server_status_label((string)$server["status"])); ?>
                                        </span>
                                        <h3><?php echo h((string)$server["name"]); ?></h3>
                                        <p>Order #<?php echo h((string)$server["order_id"]); ?></p>
                                    </div>

                                    <strong class="server-price">
                                        ¥<?php echo h(number_format((int)$server["price_monthly"])); ?>
                                        <small>/月</small>
                                    </strong>
                                </div>

                                <div class="spec-grid">
                                    <div>
                                        <span>Plan</span>
                                        <strong><?php echo h((string)$server["plan_name"]); ?></strong>
                                    </div>

                                    <div>
                                        <span>Memory</span>
                                        <strong><?php echo h(format_mb_to_gb((int)$server["memory_mb"])); ?></strong>
                                    </div>

                                    <div>
                                        <span>CPU</span>
                                        <strong><?php echo h(format_cpu_to_vcpu((int)$server["cpu_limit"])); ?></strong>
                                    </div>

                                    <div>
                                        <span>Disk</span>
                                        <strong><?php echo h(format_mb_to_gb((int)$server["disk_mb"])); ?></strong>
                                    </div>

                                    <div>
                                        <span>Payment</span>
                                        <strong><?php echo h(billing_type_label((string)$server["billing_type"])); ?></strong>
                                    </div>

                                    <div>
                                        <span>Node</span>
                                        <strong><?php echo h((string)($server["node_label"] ?? "未設定")); ?></strong>
                                    </div>
                                </div>

                                <div class="ptero-info">
                                    <div>
                                        <span>Ptero Identifier</span>
                                        <strong><?php echo h((string)($server["ptero_identifier"] ?? "未設定")); ?></strong>
                                    </div>

                                    <div>
                                        <span>Ptero Server ID</span>
                                        <strong><?php echo h((string)($server["ptero_server_id"] ?? "未設定")); ?></strong>
                                    </div>
                                </div>

                                <div class="server-meta">
                                    <?php if (!empty($server["expires_at"])): ?>
                                        <span>期限: <?php echo h((string)$server["expires_at"]); ?></span>
                                    <?php elseif (!empty($server["next_payment_due_at"])): ?>
                                        <span>次回更新目安: <?php echo h((string)$server["next_payment_due_at"]); ?></span>
                                    <?php else: ?>
                                        <span>作成日: <?php echo h((string)$server["created_at"]); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($server["auto_renew_cancelled"])): ?>
                                    <div class="server-meta">
                                        <?php if (!empty($server["auto_renew_cancelled"])): ?>
                                            <span>
                                                解約申請済み:
                                                <?php echo !empty($server["cancel_effective_at"]) ? h((string)$server["cancel_effective_at"]) . " まで利用可能" : "利用期間終了まで利用可能"; ?>
                                            </span>
                                        <?php elseif (!empty($server["expires_at"])): ?>
                                            <span>期限: <?php echo h((string)$server["expires_at"]); ?></span>
                                        <?php elseif (!empty($server["next_payment_due_at"])): ?>
                                            <span>次回更新目安: <?php echo h((string)$server["next_payment_due_at"]); ?></span>
                                        <?php else: ?>
                                            <span>作成日: <?php echo h((string)$server["created_at"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <details class="cancel-box">
                                        <summary>契約をキャンセルする</summary>

                                        <form action="/dashboard/servers/cancel/" method="post">
                                            <input type="hidden" name="server_id" value="<?php echo h((string)$server["id"]); ?>">

                                            <div class="refund-warning">
                                                <strong>返金について</strong>
                                                <p>
                                                    契約をキャンセルした場合、利用開始から1ヶ月以内であっても返金は行われません。
                                                    キャンセル後は次回更新が停止され、現在の利用期間終了までは利用できます。
                                                </p>
                                            </div>

                                            <label class="refund-check">
                                                <input type="checkbox" name="agree_refund_policy" value="1" required>
                                                <span>1ヶ月以内のキャンセルでも返金不可であることに同意します。</span>
                                            </label>

                                            <textarea
                                                name="cancel_reason"
                                                rows="3"
                                                placeholder="キャンセル理由 任意"
                                            ></textarea>

                                            <button type="submit" class="cancel-button">
                                                返金不可に同意してキャンセルする
                                            </button>
                                        </form>
                                    </details>
                                <?php endif; ?>

                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($orders): ?>
                <div class="servers-panel orders-panel reveal">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Orders</p>
                            <h2>処理中の申込</h2>
                        </div>
                    </div>

                    <div class="order-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="order-card">
                                <div>
                                    <span><?php echo h(order_status_label((string)$order["status"])); ?></span>
                                    <h3><?php echo h((string)$order["server_name"]); ?></h3>
                                    <p>
                                        <?php echo h((string)$order["plan_name"]); ?>
                                        /
                                        <?php echo h(billing_type_label((string)$order["billing_type"])); ?>
                                    </p>
                                </div>

                                <strong>
                                    <?php echo h((string)$order["payment_status"]); ?>
                                </strong>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>