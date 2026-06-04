<?php
session_start();

require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/permissions.php";

$user = require_role("developer");

$pageTitle = "開発者ページ | HC Platform";
$pageDescription = "HC Platformの開発者専用ページです。";
$pageCss = "/dev/dev.css";

$dbStatus = [
    "ok" => false,
    "message" => "未確認",
    "database" => "-",
    "user" => "-",
];

$appConfig = [];
$mailConfig = [];
$securityConfig = [];

try {
    $pdo = db();

    $stmt = $pdo->query("
        SELECT
            current_database() AS db_name,
            current_user AS db_user
    ");

    $row = $stmt->fetch();

    $dbStatus = [
        "ok" => true,
        "message" => "DB接続OK",
        "database" => $row["db_name"] ?? "-",
        "user" => $row["db_user"] ?? "-",
    ];
} catch (Throwable $e) {
    $dbStatus = [
        "ok" => false,
        "message" => "DB接続エラー",
        "database" => "-",
        "user" => "-",
    ];
}

try {
    $appConfig = require __DIR__ . "/../config/app.php";
} catch (Throwable $e) {
    $appConfig = [];
}

try {
    $mailConfig = require __DIR__ . "/../config/mail.php";
} catch (Throwable $e) {
    $mailConfig = [];
}

try {
    $securityConfig = require __DIR__ . "/../config/security.php";
} catch (Throwable $e) {
    $securityConfig = [];
}

$extensions = [
    "pdo_pgsql",
    "pgsql",
    "mbstring",
    "openssl",
    "curl",
];

function dev_mask_status($value): string
{
    return !empty($value) ? "SET" : "EMPTY";
}

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="dev-page">

    <section class="dev-hero">
        <div class="container dev-hero-grid">
            <div class="dev-copy reveal">
                <p class="eyebrow">Developer</p>
                <h1>開発者ページ</h1>
                <p>
                    開発・保守向けの専用ページです。
                    DB接続、PHP拡張、環境設定の読み込み状態を確認できます。
                </p>
            </div>

            <aside class="dev-status-card reveal">
                <span>Developer Access</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>
        </div>
    </section>

    <section class="section dev-section">
        <div class="container">
            <div class="section-heading reveal">
                <p class="eyebrow">System Check</p>
                <h2>システム状態</h2>
                <p>
                    本番反映前や不具合確認時に、アプリの基本状態を確認できます。
                </p>
            </div>

            <div class="dev-check-grid">

                <article class="dev-check-card reveal">
                    <div class="check-head">
                        <span>01</span>
                        <h3>DB接続状態</h3>
                    </div>

                    <?php if ($dbStatus["ok"]): ?>
                        <strong class="check-badge ok">OK</strong>
                    <?php else: ?>
                        <strong class="check-badge ng">NG</strong>
                    <?php endif; ?>

                    <div class="check-list">
                        <div>
                            <span>状態</span>
                            <strong><?php echo h($dbStatus["message"]); ?></strong>
                        </div>
                        <div>
                            <span>Database</span>
                            <strong><?php echo h($dbStatus["database"]); ?></strong>
                        </div>
                        <div>
                            <span>User</span>
                            <strong><?php echo h($dbStatus["user"]); ?></strong>
                        </div>
                    </div>
                </article>

                <article class="dev-check-card reveal">
                    <div class="check-head">
                        <span>02</span>
                        <h3>PHP環境</h3>
                    </div>

                    <strong class="check-badge ok">INFO</strong>

                    <div class="check-list">
                        <div>
                            <span>PHP Version</span>
                            <strong><?php echo h(PHP_VERSION); ?></strong>
                        </div>
                        <div>
                            <span>Server Software</span>
                            <strong><?php echo h($_SERVER["SERVER_SOFTWARE"] ?? "-"); ?></strong>
                        </div>
                    </div>
                </article>

                <article class="dev-check-card reveal">
                    <div class="check-head">
                        <span>03</span>
                        <h3>PHP拡張</h3>
                    </div>

                    <div class="check-list">
                        <?php foreach ($extensions as $extension): ?>
                            <div>
                                <span><?php echo h($extension); ?></span>
                                <?php if (extension_loaded($extension)): ?>
                                    <strong class="mini-ok">有効</strong>
                                <?php else: ?>
                                    <strong class="mini-ng">無効</strong>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="dev-check-card reveal">
                    <div class="check-head">
                        <span>04</span>
                        <h3>アプリ設定</h3>
                    </div>

                    <div class="check-list">
                        <div>
                            <span>APP_URL</span>
                            <strong><?php echo h($appConfig["app_url"] ?? "-"); ?></strong>
                        </div>
                    </div>
                </article>

                <article class="dev-check-card reveal">
                    <div class="check-head">
                        <span>05</span>
                        <h3>メール設定</h3>
                    </div>

                    <div class="check-list">
                        <div>
                            <span>MAIL_MODE</span>
                            <strong><?php echo h($mailConfig["mode"] ?? "-"); ?></strong>
                        </div>
                        <div>
                            <span>SMTP_HOST</span>
                            <strong><?php echo h($mailConfig["smtp_host"] ?? "-"); ?></strong>
                        </div>
                        <div>
                            <span>SMTP_PORT</span>
                            <strong><?php echo h((string)($mailConfig["smtp_port"] ?? "-")); ?></strong>
                        </div>
                        <div>
                            <span>SMTP_USER</span>
                            <strong><?php echo h(dev_mask_status($mailConfig["smtp_user"] ?? "")); ?></strong>
                        </div>
                        <div>
                            <span>SMTP_PASSWORD</span>
                            <strong><?php echo h(dev_mask_status($mailConfig["smtp_password"] ?? "")); ?></strong>
                        </div>
                        <div>
                            <span>FROM</span>
                            <strong><?php echo h($mailConfig["from_email"] ?? "-"); ?></strong>
                        </div>
                    </div>
                </article>

                <article class="dev-check-card reveal">
                    <div class="check-head">
                        <span>06</span>
                        <h3>Turnstile設定</h3>
                    </div>

                    <div class="check-list">
                        <div>
                            <span>SITE_KEY</span>
                            <strong><?php echo h(dev_mask_status($securityConfig["turnstile_site_key"] ?? "")); ?></strong>
                        </div>
                        <div>
                            <span>SECRET_KEY</span>
                            <strong><?php echo h(dev_mask_status($securityConfig["turnstile_secret_key"] ?? "")); ?></strong>
                        </div>
                        <div>
                            <span>認証コード有効期限</span>
                            <strong><?php echo h((string)($securityConfig["verification_code_minutes"] ?? "-")); ?>分</strong>
                        </div>
                        <div>
                            <span>ログイン失敗ロック</span>
                            <strong><?php echo h((string)($securityConfig["login_lock_minutes"] ?? "-")); ?>分</strong>
                        </div>
                    </div>
                </article>

            </div>

            <div class="dev-actions reveal">
                <a href="/dev/logs/" class="button primary">ログ確認ページへ</a>
                <a href="/dashboard/" class="button ghost">ダッシュボードへ戻る</a>
                <a href="/admin/" class="button primary">管理者ページへ</a>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>