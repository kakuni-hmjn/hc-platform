<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = require_login();

$pageTitle = "契約中サーバー | HC Platform";
$pageDescription = "HC Platformで契約中のゲームサーバーと申込状況を確認できます。";
$pageCss = "/dashboard/servers/servers.css";

$pdo = db();

$errors = [];
$servers = [];
$orders = [];

$cancelled = isset($_GET["cancelled"]);
$cancelError = isset($_GET["cancel_error"]);

function format_price_servers(?int $price, ?string $currency = "jpy"): string
{
    $price = $price ?? 0;
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function mb_to_gb_servers(?int $mb): string
{
    if ($mb === null || $mb <= 0) {
        return "-";
    }

    $gb = $mb / 1024;

    if (floor($gb) == $gb) {
        return (string)(int)$gb . "GB";
    }

    return number_format($gb, 1) . "GB";
}

function cpu_to_vcpu_servers(?int $cpuLimit): string
{
    if ($cpuLimit === null || $cpuLimit <= 0) {
        return "無制限";
    }

    $vcpu = $cpuLimit / 100;

    if (floor($vcpu) == $vcpu) {
        return (string)(int)$vcpu . "vCPU";
    }

    return number_format($vcpu, 1) . "vCPU";
}

function format_date_servers(?string $value): string
{
    if (!$value) {
        return "-";
    }

    try {
        $dt = new DateTime($value);
        return $dt->format("Y/m/d H:i");
    } catch (Throwable $e) {
        return $value;
    }
}

function billing_type_label_servers(string $billingType): string
{
    return match ($billingType) {
        "auto_subscription" => "毎月自動更新",
        "manual_renewal" => "1ヶ月ごとの手動更新",
        default => "不明",
    };
}

function server_status_label_servers(string $status): string
{
    return match ($status) {
        "active" => "稼働中",
        "suspended" => "停止中",
        "cancelled" => "キャンセル",
        "deleted" => "削除済み",
        "unknown" => "不明",
        default => $status,
    };
}

function order_status_label_servers(string $status): string
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

function payment_status_label_servers(string $status): string
{
    return match ($status) {
        "unpaid" => "未払い",
        "checkout_created" => "決済ページ作成済み",
        "paid" => "支払い済み",
        "failed" => "支払い失敗",
        "refunded" => "返金済み",
        "cancelled" => "支払いキャンセル",
        default => $status,
    };
}

function ptero_panel_link_servers(?string $identifier): ?string
{
    $identifier = trim((string)($identifier ?? ""));
    $panelUrl = trim((string)getenv("PTERO_PANEL_URL"));

    if ($identifier === "" || $panelUrl === "") {
        return null;
    }

    return rtrim($panelUrl, "/") . "/server/" . rawurlencode($identifier);
}

try {
    $serverStmt = $pdo->prepare("
        SELECT
            ps.id AS server_local_id,
            ps.order_id,
            ps.user_id,
            ps.plan_id,
            ps.node_id,
            ps.ptero_user_id,
            ps.ptero_server_id,
            ps.ptero_identifier,
            ps.ptero_uuid,
            ps.name,
            ps.status AS server_status,
            ps.created_at AS server_created_at,
            ps.updated_at AS server_updated_at,
            ps.deleted_at AS server_deleted_at,

            gso.billing_type,
            gso.payment_status,
            gso.status AS order_status,
            gso.amount,
            gso.currency,
            gso.expires_at,
            gso.next_payment_due_at,
            gso.cancel_requested_at,
            gso.cancel_effective_at,
            gso.cancel_reason,
            gso.auto_renew_cancelled,
            gso.created_at AS order_created_at,

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
            gso.id,
            gso.user_id,
            gso.plan_id,
            gso.server_name,
            gso.minecraft_type,
            gso.server_software,
            gso.minecraft_version,
            gso.billing_type,
            gso.status,
            gso.payment_status,
            gso.amount,
            gso.currency,
            gso.expires_at,
            gso.next_payment_due_at,
            gso.cancel_requested_at,
            gso.cancel_effective_at,
            gso.auto_renew_cancelled,
            gso.created_at,

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
                    契約中のゲームサーバー、処理中の申込、サーバーパネルへの移動、
                    契約詳細の確認ができます。
                </p>
            </div>

            <aside class="servers-status-card reveal">
                <span>HC Account</span>
                <h2><?php echo h((string)$currentUser["username"]); ?></h2>
                <p>契約中サーバーと申込状況を表示しています。</p>
            </aside>
        </div>
    </section>

    <section class="section servers-section">
        <div class="container">
            <?php if ($cancelled): ?>
                <div class="servers-success">
                    解約申請を受け付けました。現在の利用期間終了までは利用できます。返金は行われません。
                </div>
            <?php endif; ?>

            <?php if ($cancelError): ?>
                <div class="servers-alert">
                    解約処理に失敗しました。返金不可への同意が必要です。
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="servers-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
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
                        <a href="/order/game-server/" class="create-button">プランを見る</a>
                    </div>
                <?php else: ?>
                    <div class="server-grid">
                        <?php foreach ($servers as $server): ?>
                            <?php
                            $isCancelRequested = !empty($server["auto_renew_cancelled"])
                                || !empty($server["cancel_requested_at"])
                                || !empty($server["cancel_effective_at"]);

                            $cardClass = "server-card status-" . (string)$server["server_status"];

                            if ($isCancelRequested) {
                                $cardClass .= " is-cancelled";
                            }

                            $price = (int)($server["amount"] ?: $server["price_monthly"] ?: 0);
                            $pteroLink = ptero_panel_link_servers($server["ptero_identifier"] ?? null);
                            ?>
                            <article class="<?php echo h($cardClass); ?>">
                                <div class="server-card-head">
                                    <div>
                                        <span class="server-status">
                                            <?php echo h($isCancelRequested ? "解約申請済み" : server_status_label_servers((string)$server["server_status"])); ?>
                                        </span>

                                        <h3><?php echo h((string)$server["name"]); ?></h3>
                                        <p>
                                            Order #<?php echo h((string)$server["order_id"]); ?>
                                            /
                                            <?php echo h((string)$server["plan_name"]); ?>
                                        </p>
                                    </div>

                                    <strong class="server-price">
                                        <?php echo h(format_price_servers($price, (string)$server["currency"])); ?>
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
                                        <strong><?php echo h(mb_to_gb_servers((int)$server["memory_mb"])); ?></strong>
                                    </div>
                                    <div>
                                        <span>CPU</span>
                                        <strong><?php echo h(cpu_to_vcpu_servers((int)$server["cpu_limit"])); ?></strong>
                                    </div>
                                    <div>
                                        <span>Disk</span>
                                        <strong><?php echo h(mb_to_gb_servers((int)$server["disk_mb"])); ?></strong>
                                    </div>
                                    <div>
                                        <span>Payment</span>
                                        <strong><?php echo h(payment_status_label_servers((string)$server["payment_status"])); ?></strong>
                                    </div>
                                    <div>
                                        <span>Billing</span>
                                        <strong><?php echo h(billing_type_label_servers((string)$server["billing_type"])); ?></strong>
                                    </div>
                                </div>

                                <div class="ptero-info">
                                    <div>
                                        <span>Node</span>
                                        <strong><?php echo h((string)($server["node_label"] ?: $server["node_name"] ?: "-")); ?></strong>
                                    </div>
                                    <div>
                                        <span>パネル Identifier</span>
                                        <strong><?php echo h((string)($server["ptero_identifier"] ?: "-")); ?></strong>
                                    </div>
                                    <div>
                                        <span>パネルサーバーID</span>
                                        <strong><?php echo h((string)($server["ptero_server_id"] ?: "-")); ?></strong>
                                    </div>
                                    <div>
                                        <span>CPU Type</span>
                                        <strong><?php echo h((string)($server["node_cpu_type"] ?: "-")); ?></strong>
                                    </div>
                                </div>

                                <div class="server-meta">
                                    <p>期限: <?php echo h(format_date_servers((string)$server["expires_at"])); ?></p>
                                    <p>次回更新目安: <?php echo h(format_date_servers((string)$server["next_payment_due_at"])); ?></p>
                                    <p>作成日: <?php echo h(format_date_servers((string)$server["server_created_at"])); ?></p>
                                </div>

                                <?php if ($isCancelRequested): ?>
                                    <div class="cancelled-box">
                                        <strong>解約申請済み</strong>
                                        <p>
                                            解約予定日:
                                            <?php echo h(format_date_servers((string)$server["cancel_effective_at"])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="server-actions">
                                    <a class="create-button" href="/dashboard/servers/detail/?id=<?php echo h((string)$server["order_id"]); ?>">
                                        詳細を見る
                                    </a>

                                    <?php if ($pteroLink): ?>
                                        <a class="panel-button" href="<?php echo h($pteroLink); ?>" target="_blank" rel="noopener">
                                            サーバーパネルへ
                                        </a>
                                    <?php else: ?>
                                        <span class="disabled-panel-button">
                                            パネル未準備
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$isCancelRequested && in_array((string)$server["server_status"], ["active", "suspended"], true)): ?>
                                    <details class="cancel-box">
                                        <summary>契約をキャンセルする</summary>

                                        <form method="post" action="/dashboard/servers/cancel/">
                                            <input type="hidden" name="server_id" value="<?php echo h((string)$server["server_local_id"]); ?>">

                                            <div class="refund-warning">
                                                <strong>返金について</strong>
                                                <p>
                                                    契約をキャンセルした場合、利用開始から1ヶ月以内であっても返金は行われません。
                                                    キャンセル後は次回更新が停止され、現在の利用期間終了までは利用できます。
                                                </p>
                                            </div>

                                            <label class="refund-check">
                                                <input type="checkbox" name="refund_policy_agreed" value="1" required>
                                                <span>1ヶ月以内のキャンセルでも返金不可であることに同意します。</span>
                                            </label>

                                            <textarea name="cancel_reason" rows="3" placeholder="キャンセル理由 任意"></textarea>

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

            <div class="servers-panel orders-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Orders</p>
                        <h2>処理中の申込</h2>
                    </div>
                </div>

                <?php if (!$orders): ?>
                    <div class="empty-box">
                        <h3>処理中の申込はありません。</h3>
                        <p>新しく申し込んだサーバーは、決済や作成が完了するまでここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="order-list">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderPrice = (int)($order["amount"] ?: $order["price_monthly"] ?: 0);
                            ?>
                            <article class="order-card">
                                <div>
                                    <span><?php echo h(order_status_label_servers((string)$order["status"])); ?></span>
                                    <h3><?php echo h((string)$order["server_name"]); ?></h3>
                                    <p>
                                        <?php echo h((string)$order["plan_name"]); ?>
                                        /
                                        <?php echo h(mb_to_gb_servers((int)$order["memory_mb"])); ?>
                                        /
                                        <?php echo h(cpu_to_vcpu_servers((int)$order["cpu_limit"])); ?>
                                    </p>
                                </div>

                                <strong>
                                    <?php echo h(format_price_servers($orderPrice, (string)$order["currency"])); ?>
                                    /月
                                </strong>

                                <a class="create-button" href="/dashboard/servers/detail/?id=<?php echo h((string)$order["id"]); ?>">
                                    詳細を見る
                                </a>
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
