<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/order_access.php";

$adminUser = require_role("admin");

$pageTitle = "申込受付設定 | HC Platform";
$pageDescription = "サービスごとの新規申込受付状態を管理します。";
$pageCss = "/admin/order-settings/order-settings.css";

$pdo = db();

$flash = $_SESSION["order_settings_flash"] ?? null;
unset($_SESSION["order_settings_flash"]);

if (empty($_SESSION["order_settings_token"])) {
    $_SESSION["order_settings_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["order_settings_token"];

hc_order_settings_ensure_schema($pdo);

$settings = [
    hc_order_get_setting($pdo, "game_server"),
];

function order_settings_datetime(?string $value): string
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

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="order-settings-page">
    <section class="order-settings-hero">
        <div class="container order-settings-hero-grid">
            <div class="order-settings-copy reveal">
                <p class="eyebrow">Admin / Order Settings</p>
                <h1>申込受付設定</h1>
                <p>
                    メンテナンスや受付停止時に、一般ユーザーの新規申込を停止できます。
                    管理者・スタッフは確認用に申込ページへアクセスできます。
                </p>
            </div>

            <aside class="order-settings-status-card reveal">
                <span>Order Control</span>
                <h2><?php echo h((string)count($settings)); ?> 件</h2>
                <p>サービスごとの申込受付状態です。</p>
            </aside>
        </div>
    </section>

    <section class="section order-settings-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                <a href="/order/game-server/" class="sub-button">申込ページ確認</a>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <section class="order-settings-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Services</p>
                        <h2>サービス別 受付状態</h2>
                    </div>
                </div>

                <div class="order-setting-card-list">
                    <?php foreach ($settings as $setting): ?>
                        <?php $isEnabled = hc_order_bool_value($setting["is_enabled"] ?? true); ?>

                        <article class="order-setting-card <?php echo $isEnabled ? "is-enabled" : "is-disabled"; ?>">
                            <div class="order-setting-head">
                                <div>
                                    <span class="service-key"><?php echo h((string)$setting["service_key"]); ?></span>
                                    <strong class="status-badge">
                                        <?php echo $isEnabled ? "受付中" : "受付停止中"; ?>
                                    </strong>
                                </div>

                                <small>
                                    更新:
                                    <?php echo h(order_settings_datetime((string)($setting["updated_at"] ?? ""))); ?>
                                </small>
                            </div>

                            <div class="order-setting-main">
                                <div>
                                    <h3><?php echo h((string)$setting["service_name"]); ?></h3>
                                    <p>
                                        一般ユーザーの新規申込受付を切り替えます。
                                        受付停止中でも管理者はページ確認・テストが可能です。
                                    </p>
                                </div>
                            </div>

                            <form method="post" action="/admin/order-settings/update.php" class="order-setting-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                <input type="hidden" name="service_key" value="<?php echo h((string)$setting["service_key"]); ?>">

                                <label class="toggle-row">
                                    <span>申込受付</span>
                                    <select name="is_enabled">
                                        <option value="1" <?php echo $isEnabled ? "selected" : ""; ?>>受付する</option>
                                        <option value="0" <?php echo !$isEnabled ? "selected" : ""; ?>>受付停止</option>
                                    </select>
                                </label>

                                <label>
                                    <span>受付停止中メッセージ</span>
                                    <textarea name="disabled_message" rows="4"><?php echo h((string)($setting["disabled_message"] ?? "")); ?></textarea>
                                </label>

                                <label>
                                    <span>管理メモ</span>
                                    <textarea name="admin_memo" rows="3"><?php echo h((string)($setting["admin_memo"] ?? "")); ?></textarea>
                                </label>

                                <div class="form-actions">
                                    <button type="submit" class="primary-action">保存</button>
                                </div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
