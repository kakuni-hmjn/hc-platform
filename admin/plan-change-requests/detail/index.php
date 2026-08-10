<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/approvals/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "プラン変更申請詳細 | HC Platform";
$pageDescription = "HC Platformの管理者向けプラン変更申請詳細ページです。";
$pageCss = "/admin/plan-change-requests/plan-change-requests.css";

$pdo = db();

$errors = [];
$request = null;
$events = [];

$flash = $_SESSION["plan_change_admin_flash"] ?? null;
unset($_SESSION["plan_change_admin_flash"]);

if (empty($_SESSION["plan_change_admin_token"])) {
    $_SESSION["plan_change_admin_token"] = bin2hex(random_bytes(32));
}

$actionToken = (string)$_SESSION["plan_change_admin_token"];

$requestId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

function pc_status_label(string $status): string
{
    return match ($status) {
        "pending" => "申請中",
        "processed" => "反映済み",
        "rejected" => "却下",
        "approved" => "承認済み",
        "cancelled" => "キャンセル",
        default => $status,
    };
}

function pc_type_label(string $type): string
{
    return match ($type) {
        "next_renewal" => "次回更新時",
        "immediate" => "今すぐ変更",
        default => $type,
    };
}

function pc_order_status_label(string $status): string
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

function pc_payment_status_label(string $status): string
{
    return match ($status) {
        "unpaid" => "未払い",
        "checkout_created" => "Checkout作成済み",
        "paid" => "支払い済み",
        "failed" => "支払い失敗",
        "refunded" => "返金済み",
        "cancelled" => "支払いキャンセル",
        default => $status,
    };
}

function pc_price(?int $amount): string
{
    return "¥" . number_format((int)($amount ?? 0));
}

function pc_mb_to_gb(?int $mb): string
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

function pc_cpu(?int $cpuLimit): string
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

function pc_datetime(?string $value): string
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

function pc_text($value): string
{
    $value = trim((string)($value ?? ""));
    return $value === "" ? "-" : $value;
}

if (!$requestId) {
    $errors[] = "申請IDが指定されていません。";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                r.id,
                r.order_id,
                r.user_id,
                r.current_plan_id,
                r.requested_plan_id,
                r.change_type,
                r.status,
                r.user_note,
                r.admin_note,
                r.created_at,
                r.updated_at,
                r.approved_at,
                r.rejected_at,
                r.processed_at,

                u.username,
                u.email,
                u.role AS user_role,
                u.status AS user_status,

                gso.server_name,
                gso.status AS order_status,
                gso.payment_status,
                gso.amount AS order_amount,
                gso.currency,
                gso.billing_type,
                gso.next_payment_due_at,
                gso.expires_at,
                gso.stripe_customer_id,
                gso.stripe_subscription_id,
                gso.created_at AS order_created_at,

                current_plan.name AS current_plan_name,
                current_plan.price_monthly AS current_price_monthly,
                current_plan.memory_mb AS current_memory_mb,
                current_plan.cpu_limit AS current_cpu_limit,
                current_plan.disk_mb AS current_disk_mb,

                requested_plan.name AS requested_plan_name,
                requested_plan.price_monthly AS requested_price_monthly,
                requested_plan.memory_mb AS requested_memory_mb,
                requested_plan.cpu_limit AS requested_cpu_limit,
                requested_plan.disk_mb AS requested_disk_mb
            FROM server_order_plan_change_requests r
            JOIN users u ON u.id = r.user_id
            JOIN game_server_orders gso ON gso.id = r.order_id
            JOIN game_server_plans current_plan ON current_plan.id = r.current_plan_id
            JOIN game_server_plans requested_plan ON requested_plan.id = r.requested_plan_id
            WHERE r.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            "id" => $requestId,
        ]);

        $request = $stmt->fetch();

        if (!$request) {
            $errors[] = "指定されたプラン変更申請が見つかりません。";
        }
    } catch (Throwable $e) {
        $errors[] = "申請詳細の取得中にエラーが発生しました。";
    }
}

if ($request) {
    try {
        $eventStmt = $pdo->prepare("
            SELECT
                soe.*,
                u.username AS actor_username,
                u.role AS actor_role
            FROM server_order_events soe
            LEFT JOIN users u ON u.id = soe.actor_user_id
            WHERE soe.order_id = :order_id
            ORDER BY soe.created_at DESC, soe.id DESC
            LIMIT 20
        ");

        $eventStmt->execute([
            "order_id" => (int)$request["order_id"],
        ]);

        $events = $eventStmt->fetchAll();
    } catch (Throwable $e) {
        $events = [];
    }
}

$priceDiff = $request
    ? (int)$request["requested_price_monthly"] - (int)$request["current_price_monthly"]
    : 0;

$summaryClass = "request-detail-summary";
if ($request) {
    $summaryClass .= " status-" . (string)$request["status"];
    $summaryClass .= " type-" . (string)$request["change_type"];
}

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="plan-request-detail-page">
    <section class="plan-requests-hero">
        <div class="container plan-requests-hero-grid">
            <div class="plan-requests-copy reveal">
                <p class="eyebrow">Admin / Plan Change Detail</p>
                <h1>プラン変更申請詳細</h1>
                <p>
                    申請内容を確認し、契約へ反映するか却下します。
                    今すぐ変更の場合は、変更先プラン1ヶ月分の料金請求が必要です。
                </p>
            </div>

            <aside class="plan-requests-status-card reveal">
                <span>管理者</span>
                <h2><?php echo h((string)$user["username"]); ?></h2>
                <p><?php echo h(role_label((string)$user["role"])); ?></p>
            </aside>
        </div>
    </section>

    <section class="section plan-requests-section">
        <div class="container">
            <div class="detail-toolbar">
                <a href="/admin/plan-change-requests/" class="back-button">申請一覧へ戻る</a>
                <?php if ($request): ?>
                    <a href="/admin/server-orders/detail/?id=<?php echo h((string)$request["order_id"]); ?>" class="sub-button">
                        契約詳細を見る
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="admin-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($request): ?>
                <article class="<?php echo h($summaryClass); ?>">
                    <div class="summary-main">
                        <div class="request-badges">
                            <span class="request-id">#<?php echo h((string)$request["id"]); ?></span>
                            <span class="status-badge status-<?php echo h((string)$request["status"]); ?>">
                                <?php echo h(pc_status_label((string)$request["status"])); ?>
                            </span>
                            <span class="type-badge type-<?php echo h((string)$request["change_type"]); ?>">
                                <?php echo h(pc_type_label((string)$request["change_type"])); ?>
                            </span>
                        </div>

                        <h2><?php echo h((string)$request["server_name"]); ?></h2>
                        <p>
                            契約 #<?php echo h((string)$request["order_id"]); ?>
                            /
                            <?php echo h((string)$request["username"]); ?>
                            /
                            <?php echo h((string)$request["email"]); ?>
                        </p>
                    </div>

                    <div class="summary-price">
                        <span>料金変更</span>
                        <strong>
                            <?php echo h(pc_price((int)$request["current_price_monthly"])); ?>
                            →
                            <?php echo h(pc_price((int)$request["requested_price_monthly"])); ?>
                        </strong>
                        <small>
                            差額:
                            <?php echo h(($priceDiff > 0 ? "+" : ($priceDiff < 0 ? "-" : "")) . pc_price(abs($priceDiff))); ?>
                        </small>
                    </div>
                </article>

                <?php if ((string)$request["change_type"] === "immediate"): ?>
                    <div class="immediate-admin-warning">
                        <strong>今すぐ変更の申請です</strong>
                        <p>
                            この申請を反映する場合、変更先プラン
                            「<?php echo h((string)$request["requested_plan_name"]); ?>」
                            の1ヶ月分
                            <?php echo h(pc_price((int)$request["requested_price_monthly"])); ?>
                            の料金請求が必要です。
                            Stripe連携前の開発環境では、請求処理はMockまたは手動確認として扱ってください。
                        </p>
                    </div>
                <?php endif; ?>

                <section class="request-action-panel">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Actions</p>
                            <h2>管理操作</h2>
                        </div>
                    </div>

                    <?php if ((string)$request["status"] === "pending"): ?>
                        <div class="action-layout">
                            <form method="post" action="/admin/plan-change-requests/detail/action.php" class="action-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                                <input type="hidden" name="request_id" value="<?php echo h((string)$request["id"]); ?>">
                                <input type="hidden" name="action" value="process">

                                <label for="process_note">反映メモ</label>
                                <textarea id="process_note" name="admin_note" rows="3" placeholder="例: 管理者確認済み。開発環境のためMock反映。"></textarea>

                                <button type="submit" class="action-button action-success">
                                    契約へ反映する
                                </button>
                            </form>

                            <form method="post" action="/admin/plan-change-requests/detail/action.php" class="action-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                                <input type="hidden" name="request_id" value="<?php echo h((string)$request["id"]); ?>">
                                <input type="hidden" name="action" value="reject">

                                <label for="reject_note">却下理由</label>
                                <textarea id="reject_note" name="admin_note" rows="3" placeholder="例: 現在このプランへの変更は受け付けていません。"></textarea>

                                <button type="submit" class="action-button action-danger">
                                    申請を却下する
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="info-box">
                            <strong>この申請は処理済みです。</strong>
                            <p>状態: <?php echo h(pc_status_label((string)$request["status"])); ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="/admin/plan-change-requests/detail/action.php" class="note-form">
                        <input type="hidden" name="csrf_token" value="<?php echo h($actionToken); ?>">
                        <input type="hidden" name="request_id" value="<?php echo h((string)$request["id"]); ?>">
                        <input type="hidden" name="action" value="add_note">

                        <label for="admin_note">管理者メモ追加</label>
                        <textarea id="admin_note" name="admin_note" rows="3" placeholder="この申請に関する管理者メモを追加"></textarea>

                        <button type="submit" class="action-button action-primary">
                            メモを追加
                        </button>
                    </form>
                </section>

                <div class="request-detail-grid">
                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">User</p>
                                <h2>ユーザー情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>ユーザーID</span>
                                <strong><?php echo h((string)$request["user_id"]); ?></strong>
                            </div>
                            <div>
                                <span>ユーザー名</span>
                                <strong><?php echo h((string)$request["username"]); ?></strong>
                            </div>
                            <div>
                                <span>メール</span>
                                <strong><?php echo h((string)$request["email"]); ?></strong>
                            </div>
                            <div>
                                <span>状態</span>
                                <strong><?php echo h((string)$request["user_status"]); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Contract</p>
                                <h2>契約情報</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>契約ID</span>
                                <strong>#<?php echo h((string)$request["order_id"]); ?></strong>
                            </div>
                            <div>
                                <span>サーバー名</span>
                                <strong><?php echo h((string)$request["server_name"]); ?></strong>
                            </div>
                            <div>
                                <span>契約状態</span>
                                <strong><?php echo h(pc_order_status_label((string)$request["order_status"])); ?></strong>
                            </div>
                            <div>
                                <span>支払い状態</span>
                                <strong><?php echo h(pc_payment_status_label((string)$request["payment_status"])); ?></strong>
                            </div>
                            <div>
                                <span>次回支払い</span>
                                <strong><?php echo h(pc_datetime((string)$request["next_payment_due_at"])); ?></strong>
                            </div>
                            <div>
                                <span>期限</span>
                                <strong><?php echo h(pc_datetime((string)$request["expires_at"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Current Plan</p>
                                <h2>現在のプラン</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>プラン</span>
                                <strong><?php echo h((string)$request["current_plan_name"]); ?></strong>
                            </div>
                            <div>
                                <span>月額</span>
                                <strong><?php echo h(pc_price((int)$request["current_price_monthly"])); ?></strong>
                            </div>
                            <div>
                                <span>メモリ</span>
                                <strong><?php echo h(pc_mb_to_gb((int)$request["current_memory_mb"])); ?></strong>
                            </div>
                            <div>
                                <span>CPU</span>
                                <strong><?php echo h(pc_cpu((int)$request["current_cpu_limit"])); ?></strong>
                            </div>
                            <div>
                                <span>ディスク</span>
                                <strong><?php echo h(pc_mb_to_gb((int)$request["current_disk_mb"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Requested Plan</p>
                                <h2>変更先プラン</h2>
                            </div>
                        </div>

                        <div class="detail-list">
                            <div>
                                <span>プラン</span>
                                <strong><?php echo h((string)$request["requested_plan_name"]); ?></strong>
                            </div>
                            <div>
                                <span>月額</span>
                                <strong><?php echo h(pc_price((int)$request["requested_price_monthly"])); ?></strong>
                            </div>
                            <div>
                                <span>メモリ</span>
                                <strong><?php echo h(pc_mb_to_gb((int)$request["requested_memory_mb"])); ?></strong>
                            </div>
                            <div>
                                <span>CPU</span>
                                <strong><?php echo h(pc_cpu((int)$request["requested_cpu_limit"])); ?></strong>
                            </div>
                            <div>
                                <span>ディスク</span>
                                <strong><?php echo h(pc_mb_to_gb((int)$request["requested_disk_mb"])); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Notes</p>
                                <h2>申請メモ</h2>
                            </div>
                        </div>

                        <div class="notes-grid">
                            <div class="note-box">
                                <span>ユーザーメモ</span>
                                <p><?php echo nl2br(h(pc_text($request["user_note"]))); ?></p>
                            </div>

                            <div class="note-box">
                                <span>管理者メモ</span>
                                <p><?php echo nl2br(h(pc_text($request["admin_note"]))); ?></p>
                            </div>
                        </div>
                    </section>

                    <section class="detail-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">History</p>
                                <h2>契約操作履歴</h2>
                            </div>
                        </div>

                        <?php if (!$events): ?>
                            <div class="empty-box">
                                <p>操作履歴はまだありません。</p>
                            </div>
                        <?php else: ?>
                            <div class="event-list">
                                <?php foreach ($events as $event): ?>
                                    <article class="event-item">
                                        <div class="event-dot"></div>
                                        <div>
                                            <div class="event-title-line">
                                                <strong><?php echo h((string)$event["title"]); ?></strong>
                                                <span><?php echo h(pc_datetime((string)$event["created_at"])); ?></span>
                                            </div>
                                            <p>
                                                操作:
                                                <?php echo h((string)($event["actor_username"] ?: "system")); ?>
                                                <?php if (!empty($event["actor_role"])): ?>
                                                    / <?php echo h((string)$event["actor_role"]); ?>
                                                <?php endif; ?>
                                            </p>
                                            <?php if (!empty($event["message"])): ?>
                                                <div class="event-message">
                                                    <?php echo nl2br(h((string)$event["message"])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/admin/plan-change-requests/detail/plan-change-admin-ui.js"></script>
</body>
</html>
