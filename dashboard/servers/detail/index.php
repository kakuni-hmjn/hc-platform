<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";

$currentUser = require_login();

$pageTitle = "サーバー契約詳細 | HC Platform";
$pageDescription = "HC Platformのゲームサーバー契約詳細ページです。";
$pageCss = "/dashboard/servers/detail/detail.css";

$pdo = db();

$errors = [];
$order = null;
$availablePlans = [];
$planRequests = [];

$flash = $_SESSION["server_detail_flash"] ?? null;
unset($_SESSION["server_detail_flash"]);

if (empty($_SESSION["server_detail_action_token"])) {
    $_SESSION["server_detail_action_token"] = bin2hex(random_bytes(32));
}
$actionToken = (string)$_SESSION["server_detail_action_token"];

$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

function user_order_status_label(string $status): string
{
    return match ($status) {
        "pending_payment" => "決済待ち",
        "paid" => "決済済み",
        "creating" => "サーバー作成中",
        "pending_approval" => "管理者確認待ち",
        "activating" => "利用開始処理中",
        "active" => "稼働中",
        "provision_failed" => "サーバー作成失敗",
        "approval_failed" => "利用開始処理失敗",
        "suspended" => "停止中",
        "cancelled" => "キャンセル",
        "expired" => "期限切れ",
        default => $status,
    };
}

function user_payment_status_label(string $status): string
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

function user_billing_type_label(?string $billingType): string
{
    return match ((string)$billingType) {
        "auto_subscription" => "毎月自動更新",
        "manual_renewal" => "1ヶ月ごとの手動更新",
        default => (string)($billingType ?: "-"),
    };
}

function user_price(?int $amount, ?string $currency = "jpy"): string
{
    $amount = $amount ?? 0;
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($amount);
    }

    return strtoupper($currency) . " " . number_format($amount);
}

function user_mb_to_gb(?int $mb): string
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

function user_cpu_to_vcpu(?int $cpuLimit): string
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

function user_datetime(?string $value): string
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

function user_text($value): string
{
    $value = trim((string)($value ?? ""));
    return $value === "" ? "-" : $value;
}

function plan_change_status_label(string $status): string
{
    return match ($status) {
        "pending" => "申請中",
        "approved" => "承認済み",
        "rejected" => "却下",
        "processed" => "反映済み",
        "cancelled" => "キャンセル",
        default => $status,
    };
}

function plan_change_type_label(string $type): string
{
    return match ($type) {
        "next_renewal" => "次回更新時に変更",
        "immediate" => "今すぐ変更",
        default => $type,
    };
}

if (!$orderId) {
    $errors[] = "契約IDが指定されていません。";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                gso.id,
                gso.user_id,
                gso.plan_id,
                gso.selected_node_id,
                gso.server_name,
                gso.minecraft_type,
                gso.server_software,
                gso.minecraft_version,
                gso.player_count_estimate,
                gso.note,
                gso.billing_type,
                gso.billing_period,
                gso.status,
                gso.payment_status,
                gso.amount,
                gso.currency,
                gso.stripe_customer_id,
                gso.stripe_subscription_id,
                gso.paid_at,
                gso.expires_at,
                gso.next_payment_due_at,
                gso.provisioning_started_at,
                gso.provisioned_at,
                gso.failed_at,
                gso.provision_error,
                gso.cancel_requested_at,
                gso.cancel_effective_at,
                gso.cancel_reason,
                gso.auto_renew_cancelled,
                gso.created_at,
                gso.updated_at,

                gsp.name AS plan_name,
                gsp.slug AS plan_slug,
                gsp.description AS plan_description,
                gsp.price_monthly,
                gsp.memory_mb,
                gsp.cpu_limit,
                gsp.disk_mb,
                gsp.backup_limit,
                gsp.database_limit,
                gsp.allocation_limit,

                ps.id AS ptero_server_local_id,
                ps.ptero_server_id,
                ps.ptero_identifier,
                ps.ptero_uuid,
                ps.status AS ptero_server_status,
                ps.created_at AS ptero_created_at,

                pn.name AS node_name,
                pn.label AS node_label,
                pn.cpu_type AS node_cpu_type
            FROM game_server_orders gso
            JOIN game_server_plans gsp ON gsp.id = gso.plan_id
            LEFT JOIN ptero_servers ps ON ps.order_id = gso.id
            LEFT JOIN ptero_nodes pn ON pn.id = COALESCE(gso.selected_node_id, ps.node_id)
            WHERE gso.id = :id
              AND gso.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            "id" => $orderId,
            "user_id" => (int)$currentUser["id"],
        ]);

        $order = $stmt->fetch();

        if (!$order) {
            $errors[] = "指定された契約が見つかりません。";
        }
    } catch (Throwable $e) {
        $errors[] = "契約情報の取得中にエラーが発生しました。";
    }
}

if ($order) {
    try {
        $planStmt = $pdo->prepare("
            SELECT
                id,
                name,
                slug,
                description,
                price_monthly,
                memory_mb,
                cpu_limit,
                disk_mb
            FROM game_server_plans
            WHERE status = 'published'
              AND id != :current_plan_id
            ORDER BY sort_order ASC, id ASC
        ");
        $planStmt->execute([
            "current_plan_id" => (int)$order["plan_id"],
        ]);
        $availablePlans = $planStmt->fetchAll();
    } catch (Throwable $e) {
        $availablePlans = [];
    }

    try {
        $requestStmt = $pdo->prepare("
            SELECT
                r.*,
                current_plan.name AS current_plan_name,
                requested_plan.name AS requested_plan_name,
                requested_plan.price_monthly AS requested_price_monthly,
                requested_plan.memory_mb AS requested_memory_mb,
                requested_plan.cpu_limit AS requested_cpu_limit
            FROM server_order_plan_change_requests r
            JOIN game_server_plans current_plan ON current_plan.id = r.current_plan_id
            JOIN game_server_plans requested_plan ON requested_plan.id = r.requested_plan_id
            WHERE r.order_id = :order_id
              AND r.user_id = :user_id
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT 10
        ");
        $requestStmt->execute([
            "order_id" => (int)$order["id"],
            "user_id" => (int)$currentUser["id"],
        ]);
        $planRequests = $requestStmt->fetchAll();
    } catch (Throwable $e) {
        $planRequests = [];
    }
}

$status = $order ? (string)$order["status"] : "";
$paymentStatus = $order ? (string)$order["payment_status"] : "";

$canPayOrder = $order
    && (
        $status === "pending_payment"
        || in_array($paymentStatus, ["unpaid", "checkout_created", "failed"], true)
    )
    && !in_array($status, [
        "paid",
        "creating",
        "pending_approval",
        "activating",
        "active",
        "provision_failed",
        "approval_failed",
        "cancelled",
        "expired",
        "suspended"
    ], true);

$pteroServer = null;

if ($order) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                ptero_user_id,
                ptero_server_id,
                ptero_identifier,
                ptero_uuid,
                ptero_allocation_id,
                name,
                status,
                created_at
            FROM ptero_servers
            WHERE order_id = :order_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            "order_id" => (int)$order["id"],
        ]);

        $pteroServer = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $pteroServer = null;
    }
}

$hasCancel = $order && (
    !empty($order["auto_renew_cancelled"])
    || !empty($order["cancel_requested_at"])
    || !empty($order["cancel_effective_at"])
    || $status === "cancelled"
);

$panelUrl = trim((string)getenv("PTERO_PANEL_URL"));
$pteroLink = null;

if ($order && $panelUrl !== "" && !empty($order["ptero_identifier"])) {
    $pteroLink = rtrim($panelUrl, "/") . "/server/" . rawurlencode((string)$order["ptero_identifier"]);
}

$summaryClass = "server-detail-summary";
if ($order) {
    $summaryClass .= " status-" . $status;

    if ($hasCancel) {
        $summaryClass .= " is-cancel-related";
    }
}

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="server-detail-page">
    <section class="server-detail-hero">
        <div class="container server-detail-hero-grid">
            <div class="server-detail-copy reveal">
                <p class="eyebrow">Dashboard / Server Detail</p>
                <h1>サーバー契約詳細</h1>
                <p>
                    契約内容、支払い状態、更新予定、プラン変更申請を確認できます。
                </p>
            </div>

            <aside class="server-detail-status-card reveal">
                <span>HC Account</span>
                <h2><?php echo h((string)$currentUser["username"]); ?></h2>
                <p>ログイン中のアカウントで契約情報を表示しています。</p>
            </aside>
        </div>
    </section>

    <section class="section server-detail-section">
        <div class="container">
            <div class="server-detail-toolbar">
                <a href="/dashboard/servers/" class="back-button">サーバー一覧へ戻る</a>
                <a href="/dashboard/" class="sub-button">ダッシュボードへ戻る</a>

                <?php if ($canPayOrder): ?>
                    <a href="/billing/checkout/?order_id=<?php echo h((string)$order["id"]); ?>&auto=1" class="pay-button">
                        決済へ進む
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="servers-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <article class="<?php echo h($summaryClass); ?>">
                    <div class="summary-main">
                        <div class="summary-badges">
                            <span class="contract-id">#<?php echo h((string)$order["id"]); ?></span>
                            <span class="status-pill status-<?php echo h($status); ?>">
                                <?php echo h(user_order_status_label($status)); ?>
                            </span>
                            <span class="payment-pill payment-<?php echo h($paymentStatus); ?>">
                                <?php echo h(user_payment_status_label($paymentStatus)); ?>
                            </span>
                            <?php if ($hasCancel): ?>
                                <span class="cancel-pill">解約関連</span>
                            <?php endif; ?>
                        </div>

                        <h2><?php echo h(user_text($order["server_name"])); ?></h2>
                        <p>
                            <?php echo h(user_text($order["plan_name"])); ?>
                            /
                            <?php echo h(user_mb_to_gb((int)$order["memory_mb"])); ?>
                            /
                            <?php echo h(user_cpu_to_vcpu((int)$order["cpu_limit"])); ?>
                        </p>
                    </div>

                    <div class="summary-price">
                        <span>月額料金</span>
                        <strong><?php echo h(user_price((int)$order["amount"], (string)$order["currency"])); ?></strong>
                        <small><?php echo h(user_billing_type_label($order["billing_type"])); ?></small>
                    </div>
                </article>

                <?php if ($canPayOrder): ?>
            <div class="payment-required-box">
                <div>
                    <strong>この契約は決済待ちです。</strong>
                    <p>
                        決済が完了すると、契約状態が更新され、サーバー作成処理へ進めるようになります。
                    </p>
                </div>

                <a href="/billing/checkout/?order_id=<?php echo h((string)$order["id"]); ?>&auto=1">
                    決済へ進む
                </a>
            </div>
        <?php endif; ?>

        <?php if ($pteroServer): ?>
            <div class="ptero-server-box">
                <div class="ptero-server-main">
                    <div>
                        <span>ゲームサーバーパネル Server</span>
                        <strong><?php echo h((string)($pteroServer["name"] ?: $order["server_name"] ?: "名称未設定")); ?></strong>
                        <p>
                            Identifier:
                            <?php echo h((string)($pteroServer["ptero_identifier"] ?: "-")); ?>
                            /
                            Allocation ID:
                            <?php echo h((string)($pteroServer["ptero_allocation_id"] ?: "-")); ?>
                        </p>
                    </div>

                    <div class="ptero-card-actions">
                        <a href="/dashboard/ptero-account/" class="ptero-account-button">
                            ログイン情報
                        </a>

                        <a href="/dashboard/servers/panel/?id=<?php echo h((string)$order["id"]); ?>" class="ptero-panel-button">
                            サーバーパネルへ
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="server-detail-grid">
                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Contract</p>
                                <h2>契約情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>サーバー名</span>
                                <strong><?php echo h(user_text($order["server_name"])); ?></strong>
                            </div>
                            <div>
                                <span>契約状態</span>
                                <strong><?php echo h(user_order_status_label($status)); ?></strong>
                            </div>
                            <div>
                                <span>支払い状態</span>
                                <strong><?php echo h(user_payment_status_label($paymentStatus)); ?></strong>
                            </div>
                            <div>
                                <span>契約タイプ</span>
                                <strong><?php echo h(user_billing_type_label($order["billing_type"])); ?></strong>
                            </div>
                            <div>
                                <span>次回支払い目安</span>
                                <strong><?php echo h(user_datetime((string)$order["next_payment_due_at"])); ?></strong>
                            </div>
                            <div>
                                <span>利用期限</span>
                                <strong><?php echo h(user_datetime((string)$order["expires_at"])); ?></strong>
                            </div>
                            <div>
                                <span>契約作成日</span>
                                <strong><?php echo h(user_datetime((string)$order["created_at"])); ?></strong>
                            </div>
                            <div>
                                <span>解約予定日</span>
                                <strong><?php echo h(user_datetime((string)$order["cancel_effective_at"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Plan</p>
                                <h2>現在のプラン</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>プラン名</span>
                                <strong><?php echo h(user_text($order["plan_name"])); ?></strong>
                            </div>
                            <div>
                                <span>月額</span>
                                <strong><?php echo h(user_price((int)$order["price_monthly"], "jpy")); ?></strong>
                            </div>
                            <div>
                                <span>メモリ</span>
                                <strong><?php echo h(user_mb_to_gb((int)$order["memory_mb"])); ?></strong>
                            </div>
                            <div>
                                <span>CPU</span>
                                <strong><?php echo h(user_cpu_to_vcpu((int)$order["cpu_limit"])); ?></strong>
                            </div>
                            <div>
                                <span>ディスク</span>
                                <strong><?php echo h(user_mb_to_gb((int)$order["disk_mb"])); ?></strong>
                            </div>
                            <div>
                                <span>バックアップ</span>
                                <strong><?php echo h((string)$order["backup_limit"]); ?></strong>
                            </div>
                            <div>
                                <span>データベース</span>
                                <strong><?php echo h((string)$order["database_limit"]); ?></strong>
                            </div>
                            <div>
                                <span>追加ポート</span>
                                <strong><?php echo h((string)$order["allocation_limit"]); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Server</p>
                                <h2>サーバー情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>ゲームサーバーパネル ID</span>
                                <strong><?php echo h(user_text($order["ptero_identifier"])); ?></strong>
                            </div>
                            <div>
                                <span>サーバー状態</span>
                                <strong><?php echo h(user_text($order["ptero_server_status"])); ?></strong>
                            </div>
                            <div>
                                <span>Node</span>
                                <strong><?php echo h(user_text($order["node_label"] ?: $order["node_name"])); ?></strong>
                            </div>
                            <div>
                                <span>CPU種別</span>
                                <strong><?php echo h(user_text($order["node_cpu_type"])); ?></strong>
                            </div>
                            <div>
                                <span>作成開始</span>
                                <strong><?php echo h(user_datetime((string)$order["provisioning_started_at"])); ?></strong>
                            </div>
                            <div>
                                <span>作成完了</span>
                                <strong><?php echo h(user_datetime((string)$order["provisioned_at"])); ?></strong>
                            </div>
                        </div>

                        <?php if ($pteroLink): ?>
                            <a class="main-action-link" href="<?php echo h($pteroLink); ?>" target="_blank" rel="noopener">
                                サーバー管理画面を開く
                            </a>
                        <?php else: ?>
                            <div class="info-box">
                                <strong>サーバー管理画面リンクはまだ利用できません。</strong>
                                <p>サーバー作成完了後、またはゲームサーバーパネル連携設定後に表示されます。</p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Billing</p>
                                <h2>支払い方法</h2>
                            </div>
                        </div>

                        <div class="billing-box">

                        <?php if ($canPayOrder): ?>
                            <div class="billing-pay-box">
                                <strong>決済待ちです</strong>
                                <p>
                                    この契約はまだ決済が完了していません。
                                    Stripe Checkoutで支払いを完了してください。
                                </p>

                                <a href="/billing/checkout/?order_id=<?php echo h((string)$order["id"]); ?>&auto=1" class="main-action-link payment-action-link">
                                    決済へ進む
                                </a>
                            </div>
                        <?php else: ?>
                            <p>
                                支払い方法の変更は、Stripeの請求管理ページから行います。
                                開発環境ではMock処理として完了メッセージのみ表示します。
                            </p>

                            <form method="post" action="/dashboard/servers/detail/billing-portal.php">
                                <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                                <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                                <button type="submit" class="main-action-button">
                                    支払い方法を変更
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </section>

                    <section class="detail-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Plan Change</p>
                                <h2>プラン変更申請</h2>
                            </div>
                        </div>

                        <div class="plan-change-layout">
                            <form method="post" action="/dashboard/servers/detail/plan-change.php" class="plan-change-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                                <input type="hidden" name="order_id" value="<?php echo h((string)$order["id"]); ?>">
                                <input type="hidden" name="current_plan_id" value="<?php echo h((string)$order["plan_id"]); ?>">

                                <div class="info-box">
                                    <strong>現在のプラン</strong>
                                    <p>
                                        <?php echo h((string)$order["plan_name"]); ?>
                                        /
                                        <?php echo h(user_price((int)$order["price_monthly"], "jpy")); ?>
                                        /
                                        <?php echo h(user_mb_to_gb((int)$order["memory_mb"])); ?>
                                        /
                                        <?php echo h(user_cpu_to_vcpu((int)$order["cpu_limit"])); ?>
                                    </p>
                                </div>

                                <label for="requested_plan_id">変更先プラン</label>

                                <?php if (!$availablePlans): ?>
                                    <div class="info-box">
                                        <strong>変更可能なプランがありません。</strong>
                                        <p>現在のプラン以外に公開中のプランがないため、変更申請はできません。</p>
                                    </div>
                                <?php else: ?>
                                    <select id="requested_plan_id" name="requested_plan_id" required>
                                        <option value="">選択してください</option>
                                        <?php foreach ($availablePlans as $plan): ?>
                                            <option value="<?php echo h((string)$plan["id"]); ?>">
                                                <?php echo h((string)$plan["name"]); ?>
                                                /
                                                <?php echo h(user_price((int)$plan["price_monthly"], "jpy")); ?>
                                                /
                                                <?php echo h(user_mb_to_gb((int)$plan["memory_mb"])); ?>
                                                /
                                                <?php echo h(user_cpu_to_vcpu((int)$plan["cpu_limit"])); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label for="change_type">変更タイミング</label>
                                    <select id="change_type" name="change_type">
                                        <option value="next_renewal">次回更新時に変更</option>
                                        <option value="immediate">今すぐ変更する（1ヶ月分の料金が発生）</option>
                                    </select>

                                    <div class="immediate-charge-warning" hidden>
                                        <strong>今すぐ変更する場合の注意</strong>
                                        <p>
                                            今すぐプランを変更する場合、変更先プランの1ヶ月分の料金が発生します。
                                            申請後、管理者確認のうえで決済・プラン変更処理を行います。
                                        </p>
                                        <label class="immediate-charge-check">
                                            <input type="checkbox" name="immediate_charge_agreed" value="1">
                                            <span>今すぐ変更する場合、変更先プランの1ヶ月分の料金が発生することに同意します。</span>
                                        </label>
                                    </div>

                                    <label for="user_note">メモ</label>
                                    <textarea id="user_note" name="user_note" rows="4" placeholder="例: 次回更新時に上位プランへ変更したいです。"></textarea>

                                    <button type="submit" class="main-action-button">
                                        プラン変更を申請
                                    </button>
                                <?php endif; ?>
                            </form>

                            <div class="plan-request-list">
                                <h3>申請履歴</h3>

                                <?php if (!$planRequests): ?>
                                    <p class="empty-text">プラン変更申請はまだありません。</p>
                                <?php else: ?>
                                    <?php foreach ($planRequests as $request): ?>
                                        <article class="plan-request-card">
                                            <div>
                                                <span><?php echo h(plan_change_status_label((string)$request["status"])); ?></span>
                                                <strong>
                                                    <?php echo h((string)$request["current_plan_name"]); ?>
                                                    →
                                                    <?php echo h((string)$request["requested_plan_name"]); ?>
                                                </strong>
                                                <small>
                                                    <?php echo h(plan_change_type_label((string)$request["change_type"])); ?>
                                                    /
                                                    <?php echo h(user_datetime((string)$request["created_at"])); ?>
                                                </small>
                                            </div>

                                            <?php if (!empty($request["user_note"])): ?>
                                                <p><?php echo nl2br(h((string)$request["user_note"])); ?></p>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <?php if (!empty($order["provision_error"]) || !empty($order["cancel_reason"])): ?>
                        <section class="detail-panel wide-panel">
                            <div class="panel-head">
                                <div>
                                    <p class="eyebrow">Messages</p>
                                    <h2>契約メッセージ</h2>
                                </div>
                            </div>

                            <?php if (!empty($order["provision_error"])): ?>
                                <div class="error-box">
                                    <span>作成エラー</span>
                                    <p><?php echo nl2br(h((string)$order["provision_error"])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($order["cancel_reason"])): ?>
                                <div class="cancel-box">
                                    <span>解約理由</span>
                                    <p><?php echo nl2br(h((string)$order["cancel_reason"])); ?></p>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/dashboard/servers/detail/plan-change-ui.js"></script>
</body>
</html>
