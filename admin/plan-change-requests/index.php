<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/approvals/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "プラン変更申請管理 | HC Platform";
$pageDescription = "HC Platformの管理者向けプラン変更申請一覧ページです。";
$pageCss = "/admin/plan-change-requests/plan-change-requests.css";

$pdo = db();

$errors = [];
$requests = [];

$statusFilter = trim((string)($_GET["status"] ?? ""));
$keyword = trim((string)($_GET["q"] ?? ""));

$statuses = [
    "pending",
    "approved",
    "processed",
    "rejected",
    "cancelled",
];

function admin_plan_change_status_label(string $status): string
{
    return match ($status) {
        "pending" => "申請中",
        "approved" => "承認済み",
        "processed" => "反映済み",
        "rejected" => "却下",
        "cancelled" => "キャンセル",
        default => $status,
    };
}

function admin_plan_change_type_label(string $type): string
{
    return match ($type) {
        "next_renewal" => "次回更新時",
        "immediate" => "今すぐ変更",
        default => $type,
    };
}

function admin_price(?int $amount): string
{
    return "¥" . number_format((int)($amount ?? 0));
}

function admin_datetime(?string $value): string
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

$where = [];
$params = [];

if ($statusFilter !== "" && in_array($statusFilter, $statuses, true)) {
    $where[] = "r.status = :status";
    $params["status"] = $statusFilter;
}

if ($keyword !== "") {
    $where[] = "(
        CAST(r.id AS TEXT) ILIKE :keyword
        OR CAST(r.order_id AS TEXT) ILIKE :keyword
        OR u.username ILIKE :keyword
        OR u.email ILIKE :keyword
        OR gso.server_name ILIKE :keyword
        OR current_plan.name ILIKE :keyword
        OR requested_plan.name ILIKE :keyword
    )";
    $params["keyword"] = "%" . $keyword . "%";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

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

            gso.server_name,
            gso.status AS order_status,
            gso.payment_status,
            gso.amount AS order_amount,

            current_plan.name AS current_plan_name,
            current_plan.price_monthly AS current_price_monthly,
            current_plan.memory_mb AS current_memory_mb,
            current_plan.cpu_limit AS current_cpu_limit,

            requested_plan.name AS requested_plan_name,
            requested_plan.price_monthly AS requested_price_monthly,
            requested_plan.memory_mb AS requested_memory_mb,
            requested_plan.cpu_limit AS requested_cpu_limit
        FROM server_order_plan_change_requests r
        JOIN users u ON u.id = r.user_id
        JOIN game_server_orders gso ON gso.id = r.order_id
        JOIN game_server_plans current_plan ON current_plan.id = r.current_plan_id
        JOIN game_server_plans requested_plan ON requested_plan.id = r.requested_plan_id
        {$whereSql}
        ORDER BY r.created_at DESC, r.id DESC
    ");
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "プラン変更申請の取得中にエラーが発生しました。";
}

$summary = [
    "all" => count($requests),
    "pending" => 0,
    "approved" => 0,
    "processed" => 0,
    "rejected" => 0,
    "immediate" => 0,
];

foreach ($requests as $request) {
    $status = (string)($request["status"] ?? "");
    $changeType = (string)($request["change_type"] ?? "");

    if (isset($summary[$status])) {
        $summary[$status]++;
    }

    if ($changeType === "immediate") {
        $summary["immediate"]++;
    }
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="plan-requests-page">
    <section class="plan-requests-hero">
        <div class="container plan-requests-hero-grid">
            <div class="plan-requests-copy reveal">
                <p class="eyebrow">Admin / Plan Change Requests</p>
                <h1>プラン変更申請管理</h1>
                <p>
                    ユーザーから送信されたプラン変更申請を確認し、契約へ反映または却下します。
                    契約詳細へも直接移動できます。
                </p>
            </div>

            <aside class="plan-requests-status-card reveal">
                <span>表示中の申請</span>
                <h2><?php echo h((string)$summary["all"]); ?> 件</h2>
                <p>
                    申請中 <?php echo h((string)$summary["pending"]); ?> 件 /
                    承認済み <?php echo h((string)$summary["approved"]); ?> 件 /
                    今すぐ変更 <?php echo h((string)$summary["immediate"]); ?> 件
                </p>
            </aside>
        </div>
    </section>

    <section class="section plan-requests-section">
        <div class="container">
            <?php if ($errors): ?>
                <div class="admin-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="requests-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Requests</p>
                        <h2>申請一覧</h2>
                    </div>

                    <div class="panel-actions">
                        <a href="/admin/server-orders/" class="sub-button">契約管理へ</a>
                        <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                    </div>
                </div>

                <form method="get" action="/admin/plan-change-requests/" class="filter-bar">
                    <div class="filter-search">
                        <label for="q">検索</label>
                        <input
                            type="search"
                            id="q"
                            name="q"
                            value="<?php echo h($keyword); ?>"
                            placeholder="申請ID / 契約ID / ユーザー / メール / サーバー名 / プラン名"
                        >
                    </div>

                    <div>
                        <label for="status">状態</label>
                        <select id="status" name="status">
                            <option value="">すべて</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo h($status); ?>" <?php echo $statusFilter === $status ? "selected" : ""; ?>>
                                    <?php echo h(admin_plan_change_status_label($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit">絞り込み</button>
                    <a href="/admin/plan-change-requests/">リセット</a>
                </form>

                <?php if (!$requests): ?>
                    <div class="empty-box">
                        <h3>プラン変更申請はありません。</h3>
                        <p>ユーザーが契約詳細ページからプラン変更を申請すると、ここに表示されます。</p>
                    </div>
                <?php else: ?>
                    <div class="request-card-list">
                        <?php foreach ($requests as $request): ?>
                            <?php
                            $status = (string)$request["status"];
                            $changeType = (string)$request["change_type"];
                            $priceDiff = (int)$request["requested_price_monthly"] - (int)$request["current_price_monthly"];
                            ?>
                            <article class="request-card status-<?php echo h($status); ?> type-<?php echo h($changeType); ?>">
                                <div class="request-main">
                                    <div class="request-badges">
                                        <span class="request-id">#<?php echo h((string)$request["id"]); ?></span>
                                        <span class="status-badge status-<?php echo h($status); ?>">
                                            <?php echo h(admin_plan_change_status_label($status)); ?>
                                        </span>
                                        <span class="type-badge type-<?php echo h($changeType); ?>">
                                            <?php echo h(admin_plan_change_type_label($changeType)); ?>
                                        </span>
                                    </div>

                                    <h3><?php echo h((string)$request["server_name"]); ?></h3>
                                    <p>
                                        契約 #<?php echo h((string)$request["order_id"]); ?>
                                        /
                                        <?php echo h((string)$request["username"]); ?>
                                        /
                                        <?php echo h((string)$request["email"]); ?>
                                    </p>
                                </div>

                                <div class="request-plan">
                                    <span>プラン変更</span>
                                    <strong>
                                        <?php echo h((string)$request["current_plan_name"]); ?>
                                        →
                                        <?php echo h((string)$request["requested_plan_name"]); ?>
                                    </strong>
                                    <small>
                                        <?php echo h(admin_price((int)$request["current_price_monthly"])); ?>
                                        →
                                        <?php echo h(admin_price((int)$request["requested_price_monthly"])); ?>
                                        <?php if ($priceDiff !== 0): ?>
                                            / 差額 <?php echo h(($priceDiff > 0 ? "+" : "-") . admin_price(abs($priceDiff))); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <div class="request-date">
                                    <span>申請日時</span>
                                    <strong><?php echo h(admin_datetime((string)$request["created_at"])); ?></strong>
                                    <small><?php echo h(admin_plan_change_type_label($changeType)); ?></small>
                                </div>

                                <div class="request-action request-action-stack">
                                    <a href="/admin/plan-change-requests/detail/?id=<?php echo h((string)$request["id"]); ?>" class="detail-button">
                                        申請詳細
                                    </a>
                                    <a href="/admin/server-orders/detail/?id=<?php echo h((string)$request["order_id"]); ?>" class="secondary-detail-button">
                                        契約詳細
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
