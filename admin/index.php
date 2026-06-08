<?php

session_start();

require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "管理者ページ | HC Platform";
$pageDescription = "HC Platformの管理者向けページです。";
$pageCss = "/admin/admin.css";

$pdo = db();

$pendingPlanChangeCount = 0;
$pendingServerOrderCount = 0;
$billingNeedsPaymentCount = 0;
$headerLinks = [];

$headerMenuFlash = $_SESSION["admin_header_menu_flash"] ?? null;
unset($_SESSION["admin_header_menu_flash"]);

if (empty($_SESSION["admin_header_menu_token"])) {
    $_SESSION["admin_header_menu_token"] = bin2hex(random_bytes(32));
}
$headerMenuToken = (string)$_SESSION["admin_header_menu_token"];

function admin_default_header_operation_links(): array
{
    return [
        ["staff", "スタッフページ", "/staff/", "staff", true, 10],
        ["admin", "管理者ページ", "/admin/", "admin", true, 20],
        ["users", "ユーザー管理", "/admin/users/", "admin", true, 25],
        ["server_orders", "ゲームサーバー申込管理", "/admin/server-orders/", "admin", true, 30],
        ["plan_change_requests", "プラン変更申請管理", "/admin/plan-change-requests/", "admin", true, 40],
        ["game_plans", "ゲームサーバープラン管理", "/admin/game-plans/", "admin", true, 50],
        ["admin_billing", "請求・支払い管理", "/admin/billing/", "admin", true, 55],
        ["services", "事業管理", "/admin/services/", "admin", true, 60],
        ["news", "お知らせ管理", "/admin/news/", "admin", true, 70],
        ["site_notifications", "全体通知管理", "/admin/site-notifications/", "admin", true, 75],
        ["user_notifications", "個別通知管理", "/admin/user-notifications/", "admin", true, 76],
        ["ptero", "ゲームサーバーパネル連携", "/admin/ptero/", "admin", true, 80],
        ["dev", "開発者ページ", "/admin/dev/", "developer", true, 90],
        ["header_settings", "ヘッダー表示設定", "/admin/header-settings/", "admin", true, 100],
    ];
}

function admin_ensure_header_operation_links(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS header_operation_links (
            id SERIAL PRIMARY KEY,
            item_key VARCHAR(80) NOT NULL UNIQUE,
            label VARCHAR(120) NOT NULL,
            url VARCHAR(255) NOT NULL,
            required_role VARCHAR(40) NOT NULL DEFAULT 'staff',
            is_visible BOOLEAN NOT NULL DEFAULT true,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )
    ");

    $stmt = $pdo->prepare("
        INSERT INTO header_operation_links
        (item_key, label, url, required_role, is_visible, sort_order)
        VALUES
        (:item_key, :label, :url, :required_role, :is_visible, :sort_order)
        ON CONFLICT (item_key) DO UPDATE SET
            label = EXCLUDED.label,
            url = EXCLUDED.url,
            required_role = EXCLUDED.required_role,
            sort_order = EXCLUDED.sort_order,
            updated_at = NOW()
    ");

    foreach (admin_default_header_operation_links() as $link) {
        [$itemKey, $label, $url, $requiredRole, $isVisible, $sortOrder] = $link;

        $stmt->execute([
            "item_key" => $itemKey,
            "label" => $label,
            "url" => $url,
            "required_role" => $requiredRole,
            "is_visible" => $isVisible ? "true" : "false",
            "sort_order" => $sortOrder,
        ]);
    }

    $pdo->exec("
        UPDATE header_operation_links
        SET is_visible = true, updated_at = NOW()
        WHERE item_key = 'admin'
    ");
}

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM server_order_plan_change_requests
        WHERE status = 'pending'
    ");
    $row = $stmt->fetch();
    $pendingPlanChangeCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $pendingPlanChangeCount = 0;
}

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM game_server_orders
        WHERE status IN ('pending_payment', 'paid', 'creating', 'provision_failed')
    ");
    $row = $stmt->fetch();
    $pendingServerOrderCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $pendingServerOrderCount = 0;
}

try {
    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM game_server_orders
        WHERE payment_status IN ('unpaid', 'checkout_created', 'failed')
    ");
    $row = $stmt->fetch();
    $billingNeedsPaymentCount = (int)($row["count"] ?? 0);
} catch (Throwable $e) {
    $billingNeedsPaymentCount = 0;
}

try {
    admin_ensure_header_operation_links($pdo);

    $stmt = $pdo->query("
        SELECT
            id,
            item_key,
            label,
            url,
            required_role,
            is_visible,
            sort_order
        FROM header_operation_links
        ORDER BY sort_order ASC, id ASC
    ");

    $headerLinks = $stmt->fetchAll();
} catch (Throwable $e) {
    $headerLinks = [];
}

require_once __DIR__ . "/../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="admin-page">
    <section class="admin-hero admin-hero-improved">
        <div class="container admin-hero-grid">
            <div class="admin-copy reveal">
                <p class="eyebrow">Admin Console</p>
                <h1>管理者ページ</h1>
                <p>
                    ユーザー、契約、請求、プラン変更、通知、サイト情報、ゲームサーバーパネル連携を管理します。
                    よく使う管理機能をカテゴリごとに整理しています。
                </p>

                <div class="admin-quick-links">
                    <a href="/admin/users/">ユーザー管理</a>
                    <a href="/admin/server-orders/">申込確認</a>
                    <a href="/admin/billing/">請求・支払い</a>
                    <a href="/admin/plan-change-requests/">プラン変更申請</a>
                    <a href="/admin/site-notifications/">全体通知作成</a>
                    <a href="/admin/user-notifications/">個別通知送信</a>
                </div>
            </div>

            <aside class="admin-status-card reveal">
                <span>ログイン中</span>
                <h2><?php echo h((string)$user["username"]); ?></h2>
                <p><?php echo h(role_label((string)$user["role"])); ?></p>

                <div class="admin-mini-stats">
                    <div class="<?php echo $pendingServerOrderCount > 0 ? 'has-alert' : ''; ?>">
                        <strong><?php echo h((string)$pendingServerOrderCount); ?></strong>
                        <small>処理中申込</small>
                    </div>

                    <div class="<?php echo $pendingPlanChangeCount > 0 ? 'has-alert' : ''; ?>">
                        <strong><?php echo h((string)$pendingPlanChangeCount); ?></strong>
                        <small>プラン変更申請</small>
                    </div>

                    <div class="<?php echo $billingNeedsPaymentCount > 0 ? 'has-alert' : ''; ?>">
                        <strong><?php echo h((string)$billingNeedsPaymentCount); ?></strong>
                        <small>支払い確認</small>
                    </div>
                </div>

                <?php if ($headerMenuFlash): ?>
                    <div class="admin-header-menu-flash admin-header-menu-flash-<?php echo h((string)$headerMenuFlash["type"]); ?>">
                        <?php echo h((string)$headerMenuFlash["message"]); ?>
                    </div>
                <?php endif; ?>

                <details class="admin-header-menu-editor">
                    <summary>
                        <span>ヘッダーメニューを編集</span>
                        <strong>選択</strong>
                    </summary>

                    <form method="post" action="/admin/header-settings/quick-update.php" class="admin-header-menu-form">
                        <input type="hidden" name="csrf_token" value="<?php echo h($headerMenuToken); ?>">

                        <?php if (!$headerLinks): ?>
                            <div class="admin-header-menu-empty">
                                ヘッダー項目を取得できませんでした。
                            </div>
                        <?php else: ?>
                            <div class="admin-header-menu-list">
                                <?php foreach ($headerLinks as $link): ?>
                                    <?php
                                    $isRequired = (string)$link["item_key"] === "admin";
                                    $isChecked = !empty($link["is_visible"]) || $isRequired;
                                    ?>
                                    <label class="admin-header-menu-item <?php echo $isRequired ? 'is-required' : ''; ?>">
                                        <input
                                            type="checkbox"
                                            name="visible_links[]"
                                            value="<?php echo h((string)$link["id"]); ?>"
                                            <?php echo $isChecked ? "checked" : ""; ?>
                                            <?php echo $isRequired ? "disabled" : ""; ?>
                                        >

                                        <span>
                                            <?php echo h((string)$link["label"]); ?>
                                            <?php if ($isRequired): ?>
                                                <em>必須</em>
                                            <?php endif; ?>
                                        </span>

                                        <small><?php echo h((string)$link["url"]); ?></small>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="admin-header-menu-actions">
                                <button type="submit">保存</button>
                                <a href="/admin/header-settings/">詳細設定</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </details>
            </aside>
        </div>
    </section>

    <section class="section admin-section">
        <div class="container">
            <div class="section-head reveal">
                <p class="eyebrow">Management</p>
                <h2>管理メニュー</h2>
            </div>

            <div class="admin-menu-groups reveal">
                <section class="admin-menu-group">
                    <div class="admin-group-head">
                        <span class="admin-group-icon" data-icon="manage_accounts"></span>
                        <div>
                            <h3>ユーザー・権限管理</h3>
                            <p>ユーザー情報、権限、スタッフ向けページを管理します。</p>
                        </div>
                    </div>

                    <div class="admin-menu-grid admin-menu-grid-compact">
                        <a href="/admin/users/" class="admin-menu-card admin-menu-card-users">
                            <span class="admin-card-mark" data-icon="group"></span>
                            <h3>ユーザー管理</h3>
                            <p>登録ユーザー、権限、アカウント状態を確認・管理します。</p>
                        </a>

                        <a href="/staff/" class="admin-menu-card">
                            <span class="admin-card-mark" data-icon="support_agent"></span>
                            <h3>スタッフページ</h3>
                            <p>運営・サポート向けの作業ページへ移動します。</p>
                        </a>
                    </div>
                </section>

                <section class="admin-menu-group">
                    <div class="admin-group-head">
                        <span class="admin-group-icon" data-icon="assignment"></span>
                        <div>
                            <h3>契約・請求管理</h3>
                            <p>申込、契約、請求、プラン変更、ゲームサーバープランを管理します。</p>
                        </div>
                    </div>

                    <div class="admin-menu-grid admin-menu-grid-compact">
                        <a href="/admin/server-orders/" class="admin-menu-card admin-menu-card-important">
                            <span class="admin-card-mark" data-icon="dns"></span>
                            <h3>ゲームサーバー申込管理</h3>
                            <p>申込、決済状態、作成状態、キャンセル申請を確認します。</p>

                            <?php if ($pendingServerOrderCount > 0): ?>
                                <strong class="admin-card-badge">
                                    処理中 <?php echo h((string)$pendingServerOrderCount); ?> 件
                                </strong>
                            <?php endif; ?>
                        </a>

                        <a href="/admin/billing/" class="admin-menu-card admin-menu-card-billing <?php echo $billingNeedsPaymentCount > 0 ? 'admin-menu-card-alert' : ''; ?>">
                            <span class="admin-card-mark" data-icon="payments"></span>

                            <?php if ($billingNeedsPaymentCount > 0): ?>
                                <strong class="admin-alert-corner-badge">
                                    支払い確認 <?php echo h((string)$billingNeedsPaymentCount); ?> 件
                                </strong>
                            <?php endif; ?>

                            <h3>請求・支払い管理</h3>
                            <p>契約ごとの支払い状態、金額、請求詳細を確認します。</p>
                        </a>

                        <a href="/admin/plan-change-requests/" class="admin-menu-card admin-menu-card-important <?php echo $pendingPlanChangeCount > 0 ? 'admin-menu-card-alert' : ''; ?>">
                            <span class="admin-card-mark" data-icon="sync_alt"></span>

                            <?php if ($pendingPlanChangeCount > 0): ?>
                                <strong class="admin-alert-corner-badge">
                                    申請あり <?php echo h((string)$pendingPlanChangeCount); ?> 件
                                </strong>
                            <?php endif; ?>

                            <h3>プラン変更申請管理</h3>
                            <p>ユーザーから送信されたプラン変更申請を確認します。</p>
                        </a>

                        <a href="/admin/game-plans/" class="admin-menu-card">
                            <span class="admin-card-mark" data-icon="view_list"></span>
                            <h3>ゲームサーバープラン管理</h3>
                            <p>料金、スペック、ゲームサーバーパネル連携情報を管理します。</p>
                        </a>
                    </div>
                </section>

                <section class="admin-menu-group">
                    <div class="admin-group-head">
                        <span class="admin-group-icon" data-icon="notifications"></span>
                        <div>
                            <h3>通知管理</h3>
                            <p>ユーザーに表示する通知を作成します。送信済み一覧は表示しません。</p>
                        </div>
                    </div>

                    <div class="admin-menu-grid admin-menu-grid-compact">
                        <a href="/admin/site-notifications/" class="admin-menu-card admin-menu-card-notice">
                            <span class="admin-card-mark" data-icon="campaign"></span>
                            <h3>全体通知管理</h3>
                            <p>全ユーザー向けの通知を作成します。</p>
                        </a>

                        <a href="/admin/user-notifications/" class="admin-menu-card admin-menu-card-direct">
                            <span class="admin-card-mark" data-icon="person"></span>
                            <h3>個別通知管理</h3>
                            <p>特定ユーザーへ「あなた宛」の通知を送信します。</p>
                        </a>
                    </div>
                </section>

                <section class="admin-menu-group">
                    <div class="admin-group-head">
                        <span class="admin-group-icon" data-icon="web"></span>
                        <div>
                            <h3>サイト管理</h3>
                            <p>トップページや公開ページに表示する情報を管理します。</p>
                        </div>
                    </div>

                    <div class="admin-menu-grid admin-menu-grid-compact">
                        <a href="/admin/news/" class="admin-menu-card">
                            <span class="admin-card-mark" data-icon="article"></span>
                            <h3>お知らせ管理</h3>
                            <p>トップページやお知らせ一覧に表示する情報を管理します。</p>
                        </a>

                        <a href="/admin/services/" class="admin-menu-card">
                            <span class="admin-card-mark" data-icon="business_center"></span>
                            <h3>事業管理</h3>
                            <p>トップページや事業一覧に表示する事業情報を管理します。</p>
                        </a>

                        <a href="/admin/header-settings/" class="admin-menu-card admin-menu-card-header-settings">
                            <span class="admin-card-mark" data-icon="menu"></span>
                            <h3>ヘッダー表示設定</h3>
                            <p>ヘッダーのOperationメニューを編集します。</p>
                        </a>
                    </div>
                </section>

                <section class="admin-menu-group">
                    <div class="admin-group-head">
                        <span class="admin-group-icon" data-icon="hub"></span>
                        <div>
                            <h3>開発・連携</h3>
                            <p>外部連携、開発者用ページ、通常ダッシュボードへ移動します。</p>
                        </div>
                    </div>

                    <div class="admin-menu-grid admin-menu-grid-compact">
                        <a href="/admin/ptero/" class="admin-menu-card">
                            <span class="admin-card-mark" data-icon="device_hub"></span>
                            <h3>ゲームサーバーパネル連携</h3>
                            <p>API接続確認、Node / Nest / Eggの取得テストを行います。</p>
                        </a>

                        <a href="/admin/dev/" class="admin-menu-card">
                            <span class="admin-card-mark" data-icon="code"></span>
                            <h3>開発者ページ</h3>
                            <p>開発・保守向けの確認ページへ移動します。</p>
                        </a>

                        <a href="/dashboard/" class="admin-menu-card">
                            <span class="admin-card-mark" data-icon="home"></span>
                            <h3>ダッシュボードへ戻る</h3>
                            <p>通常のアカウントダッシュボードへ戻ります。</p>
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
