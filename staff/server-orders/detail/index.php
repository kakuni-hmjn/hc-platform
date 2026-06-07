<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("staff");

$pageTitle = "契約詳細確認 | HC Platform";
$pageDescription = "スタッフ向けのゲームサーバー契約詳細確認ページです。";
$pageCss = "/staff/server-orders/server-orders.css";

$pdo = db();

$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$errors = [];
$successMessage = "";
$order = null;
$events = [];
$notes = [];

if (empty($_SESSION["staff_order_note_token"])) {
    $_SESSION["staff_order_note_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["staff_order_note_token"];

function staff_detail_ensure_notes_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_order_notes (
            id SERIAL PRIMARY KEY,
            order_id INTEGER NOT NULL REFERENCES game_server_orders(id) ON DELETE CASCADE,
            staff_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            note TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_staff_order_notes_order_id
        ON staff_order_notes(order_id)
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_staff_order_notes_staff_user_id
        ON staff_order_notes(staff_user_id)
    ");
}

function staff_detail_status_label(string $status): string
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

function staff_detail_payment_label(string $status): string
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

function staff_detail_event_label(string $eventType): string
{
    return match ($eventType) {
        "order_created" => "申込作成",
        "payment_checkout_created" => "決済ページ作成",
        "payment_paid" => "決済完了",
        "payment_failed" => "決済失敗",
        "server_provision_started" => "サーバー作成開始",
        "server_provisioned" => "サーバー作成完了",
        "server_provision_failed" => "サーバー作成失敗",
        "cancel_requested" => "キャンセル申請",
        "admin_cancel_processed" => "キャンセル処理",
        "plan_change_requested" => "プラン変更申請",
        "admin_plan_change_note" => "管理者メモ",
        "admin_plan_change_approved" => "プラン変更承認",
        "admin_plan_change_rejected" => "プラン変更却下",
        "admin_plan_change_processed" => "プラン変更反映",
        "admin_plan_change_applied" => "承認済み変更反映",
        default => $eventType,
    };
}

function staff_detail_price(?int $amount, ?int $fallbackAmount = 0, ?string $currency = "jpy"): string
{
    $price = $amount ?: $fallbackAmount ?: 0;
    $currency = strtolower((string)($currency ?: "jpy"));

    if ($currency === "jpy") {
        return "¥" . number_format($price);
    }

    return strtoupper($currency) . " " . number_format($price);
}

function staff_detail_memory_label(?int $memoryMb): string
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

function staff_detail_cpu_label(?int $cpuLimit): string
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

function staff_detail_datetime(?string $value): string
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

try {
    staff_detail_ensure_notes_table($pdo);
} catch (Throwable $e) {
    $errors[] = "スタッフメモテーブルの準備に失敗しました。";
}

if (!$orderId) {
    $errors[] = "契約IDが指定されていません。";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                gso.*,

                gsp.name AS plan_name,
                gsp.slug AS plan_slug,
                gsp.price_monthly,
                gsp.memory_mb,
                gsp.cpu_limit,
                gsp.disk_mb,

                u.username,
                u.email,
                u.role,
                u.status AS user_status,
                u.email_verified,

                ps.ptero_identifier AS ptero_identifier,
                ps.ptero_server_id,
                ps.status AS ptero_status,
                ps.created_at AS ptero_created_at
            FROM game_server_orders gso
            JOIN game_server_plans gsp ON gsp.id = gso.plan_id
            LEFT JOIN users u ON u.id = gso.user_id
            LEFT JOIN ptero_servers ps ON ps.order_id = gso.id
            WHERE gso.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            "id" => $orderId,
        ]);

        $order = $stmt->fetch();

        if (!$order) {
            $errors[] = "契約が見つかりません。";
        }
    } catch (Throwable $e) {
        $errors[] = "契約詳細の取得中にエラーが発生しました。";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $order && $orderId) {
    $token = (string)($_POST["csrf_token"] ?? "");
    $action = trim((string)($_POST["action"] ?? ""));

    if (!hash_equals($csrfToken, $token)) {
        $errors[] = "不正な操作です。もう一度やり直してください。";
    } else {
        try {
            if ($action === "add_note") {
                $note = trim((string)($_POST["note"] ?? ""));

                if ($note === "") {
                    throw new RuntimeException("メモ内容を入力してください。");
                }

                if (mb_strlen($note) > 3000) {
                    throw new RuntimeException("メモは3000文字以内で入力してください。");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO staff_order_notes
                    (order_id, staff_user_id, note, created_at)
                    VALUES
                    (:order_id, :staff_user_id, :note, NOW())
                ");

                $stmt->execute([
                    "order_id" => $orderId,
                    "staff_user_id" => (int)$user["id"],
                    "note" => $note,
                ]);

                $successMessage = "スタッフメモを追加しました。";
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($orderId) {
    try {
        $eventStmt = $pdo->prepare("
            SELECT
                soe.id,
                soe.event_type,
                soe.title,
                soe.message,
                soe.old_status,
                soe.new_status,
                soe.old_payment_status,
                soe.new_payment_status,
                soe.created_at,
                actor.username AS actor_username
            FROM server_order_events soe
            LEFT JOIN users actor ON actor.id = soe.actor_user_id
            WHERE soe.order_id = :order_id
            ORDER BY soe.created_at DESC, soe.id DESC
            LIMIT 50
        ");

        $eventStmt->execute([
            "order_id" => $orderId,
        ]);

        $events = $eventStmt->fetchAll();
    } catch (Throwable $e) {
        $events = [];
    }

    try {
        $noteStmt = $pdo->prepare("
            SELECT
                son.id,
                son.note,
                son.created_at,
                u.username AS staff_username,
                u.role AS staff_role
            FROM staff_order_notes son
            LEFT JOIN users u ON u.id = son.staff_user_id
            WHERE son.order_id = :order_id
            ORDER BY son.created_at DESC, son.id DESC
            LIMIT 100
        ");

        $noteStmt->execute([
            "order_id" => $orderId,
        ]);

        $notes = $noteStmt->fetchAll();
    } catch (Throwable $e) {
        $notes = [];
    }
}

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="staff-orders-page">
    <section class="staff-orders-hero detail-hero">
        <div class="container staff-orders-hero-grid">
            <div class="staff-orders-copy reveal">
                <p class="eyebrow">Staff / Contract Detail</p>
                <h1>契約詳細確認</h1>
                <p>
                    スタッフ向けの閲覧専用詳細ページです。
                    契約状態の変更、決済状態の変更、サーバー作成などの操作はできません。
                </p>
            </div>

            <aside class="staff-orders-status-card reveal">
                <span>契約ID</span>
                <h2>#<?php echo h((string)($orderId ?: "-")); ?></h2>
                <p>
                    <?php if ($order): ?>
                        <?php echo h(staff_detail_status_label((string)$order["status"])); ?>
                        /
                        <?php echo h(staff_detail_payment_label((string)$order["payment_status"])); ?>
                    <?php else: ?>
                        詳細を表示できません。
                    <?php endif; ?>
                </p>
            </aside>
        </div>
    </section>

    <section class="section staff-orders-section">
        <div class="container">
            <div class="toolbar">
                <a href="/staff/server-orders/" class="back-button">申込一覧へ戻る</a>
                <a href="/staff/" class="sub-button">スタッフページへ戻る</a>
            </div>

            <?php if ($successMessage !== ""): ?>
                <div class="flash-message flash-success">
                    <?php echo h($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="flash-message flash-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="detail-grid reveal">
                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Contract</p>
                                <h2>契約情報</h2>
                            </div>
                        </div>

                        <dl class="detail-list">
                            <div>
                                <dt>サーバー名</dt>
                                <dd><?php echo h((string)($order["server_name"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>契約状態</dt>
                                <dd><?php echo h(staff_detail_status_label((string)$order["status"])); ?></dd>
                            </div>

                            <div>
                                <dt>決済状態</dt>
                                <dd><?php echo h(staff_detail_payment_label((string)$order["payment_status"])); ?></dd>
                            </div>

                            <div>
                                <dt>申込日時</dt>
                                <dd><?php echo h(staff_detail_datetime((string)$order["created_at"])); ?></dd>
                            </div>

                            <div>
                                <dt>自動更新停止</dt>
                                <dd><?php echo !empty($order["auto_renew_cancelled"]) ? "はい" : "いいえ"; ?></dd>
                            </div>

                            <div>
                                <dt>キャンセル申請日時</dt>
                                <dd><?php echo h(staff_detail_datetime((string)($order["cancel_requested_at"] ?? ""))); ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">User</p>
                                <h2>ユーザー情報</h2>
                            </div>
                        </div>

                        <dl class="detail-list">
                            <div>
                                <dt>ユーザー名</dt>
                                <dd><?php echo h((string)($order["username"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>メール</dt>
                                <dd><?php echo h((string)($order["email"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>権限</dt>
                                <dd><?php echo h((string)($order["role"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>状態</dt>
                                <dd><?php echo h((string)($order["user_status"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>メール認証</dt>
                                <dd><?php echo !empty($order["email_verified"]) ? "認証済み" : "未認証"; ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Plan</p>
                                <h2>プラン情報</h2>
                            </div>
                        </div>

                        <dl class="detail-list">
                            <div>
                                <dt>プラン</dt>
                                <dd><?php echo h((string)$order["plan_name"]); ?></dd>
                            </div>

                            <div>
                                <dt>料金</dt>
                                <dd><?php echo h(staff_detail_price((int)$order["amount"], (int)$order["price_monthly"], (string)$order["currency"])); ?></dd>
                            </div>

                            <div>
                                <dt>メモリ</dt>
                                <dd><?php echo h(staff_detail_memory_label((int)$order["memory_mb"])); ?></dd>
                            </div>

                            <div>
                                <dt>CPU</dt>
                                <dd><?php echo h(staff_detail_cpu_label((int)$order["cpu_limit"])); ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="detail-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Pterodactyl</p>
                                <h2>サーバー作成情報</h2>
                            </div>
                        </div>

                        <dl class="detail-list">
                            <div>
                                <dt>Pterodactyl ID</dt>
                                <dd><?php echo h((string)($order["ptero_server_id"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>識別子</dt>
                                <dd><?php echo h((string)($order["ptero_identifier"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>状態</dt>
                                <dd><?php echo h((string)($order["ptero_status"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>作成日時</dt>
                                <dd><?php echo h(staff_detail_datetime((string)($order["ptero_created_at"] ?? ""))); ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="detail-panel wide-panel staff-note-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Staff Notes</p>
                                <h2>スタッフメモ</h2>
                            </div>

                            <span class="event-count"><?php echo h((string)count($notes)); ?> 件</span>
                        </div>

                        <form method="post" action="/staff/server-orders/detail/?id=<?php echo h((string)$orderId); ?>" class="staff-note-form">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                            <input type="hidden" name="action" value="add_note">

                            <label>内部メモを追加</label>
                            <textarea name="note" rows="4" placeholder="例: 支払い方法について案内済み。ユーザー返信待ち。" required></textarea>

                            <button type="submit">メモを追加</button>
                        </form>

                        <?php if (!$notes): ?>
                            <div class="empty-box">
                                <h3>スタッフメモはまだありません。</h3>
                                <p>対応状況や確認内容を残しておくと、他のスタッフや管理者が把握しやすくなります。</p>
                            </div>
                        <?php else: ?>
                            <div class="staff-note-list">
                                <?php foreach ($notes as $note): ?>
                                    <article class="staff-note-card">
                                        <div class="staff-note-head">
                                            <strong><?php echo h((string)($note["staff_username"] ?: "unknown")); ?></strong>
                                            <span><?php echo h(staff_detail_datetime((string)$note["created_at"])); ?></span>
                                        </div>

                                        <p><?php echo nl2br(h((string)$note["note"])); ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="detail-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Timeline</p>
                                <h2>操作履歴</h2>
                            </div>

                            <span class="event-count"><?php echo h((string)count($events)); ?> 件</span>
                        </div>

                        <?php if (!$events): ?>
                            <div class="empty-box">
                                <h3>履歴はまだありません。</h3>
                                <p>契約イベントが作成されるとここに表示されます。</p>
                            </div>
                        <?php else: ?>
                            <div class="event-list">
                                <?php foreach ($events as $event): ?>
                                    <article class="event-card">
                                        <div>
                                            <span><?php echo h(staff_detail_datetime((string)$event["created_at"])); ?></span>
                                            <h3><?php echo h((string)($event["title"] ?: staff_detail_event_label((string)$event["event_type"]))); ?></h3>

                                            <?php if (!empty($event["message"])): ?>
                                                <p><?php echo nl2br(h((string)$event["message"])); ?></p>
                                            <?php endif; ?>

                                            <div class="event-meta">
                                                <em><?php echo h(staff_detail_event_label((string)$event["event_type"])); ?></em>
                                                <em>操作: <?php echo h((string)($event["actor_username"] ?: "system")); ?></em>
                                            </div>
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
</body>
</html>
