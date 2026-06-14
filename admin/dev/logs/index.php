<?php
session_start();

require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("developer");

$pageTitle = "ログ確認 | HC Platform";
$pageDescription = "HC Platformの開発者向けログ確認ページです。";
$pageCss = "/dev/logs/logs.css";

$allowedLogs = [
    "mail" => [
        "label" => "認証メールログ",
        "path" => __DIR__ . "/../../storage/logs/mail.log",
        "description" => "開発環境でMAIL_MODE=logの場合、認証コードやパスワードリセットURLが記録されます。",
    ],
    "mail-error" => [
        "label" => "メールエラーログ",
        "path" => __DIR__ . "/../../storage/logs/mail-error.log",
        "description" => "SMTP送信失敗など、メール送信系のエラーが記録されます。",
    ],
];

$selected = $_GET["log"] ?? "mail-error";

if (!array_key_exists($selected, $allowedLogs)) {
    $selected = "mail-error";
}

$currentLog = $allowedLogs[$selected];
$logContent = "";
$logExists = file_exists($currentLog["path"]);

if ($logExists) {
    $lines = file($currentLog["path"], FILE_IGNORE_NEW_LINES);

    if ($lines !== false) {
        $lines = array_slice($lines, -200);
        $logContent = implode(PHP_EOL, $lines);
    }
}

require_once __DIR__ . "/..//parts/head.php";
?>
<body>
<?php include __DIR__ . "/..//parts/header/header.php"; ?>

<main class="dev-logs-page">

    <section class="dev-logs-hero">
        <div class="container dev-logs-hero-grid">

            <div class="dev-logs-copy reveal">
                <p class="eyebrow">Developer / Logs</p>
                <h1>ログ確認</h1>
                <p>
                    開発・保守向けのログ確認ページです。
                    直近200行まで表示します。秘密情報を扱う可能性があるため、developer以上のみアクセスできます。
                </p>
            </div>

            <aside class="dev-logs-status-card reveal">
                <span>Developer Access</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section dev-logs-section">
        <div class="container">

            <div class="logs-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Logs</p>
                        <h2><?php echo h($currentLog["label"]); ?></h2>
                    </div>
                    <a href="/dev/" class="back-button">開発者ページへ戻る</a>
                </div>

                <p class="logs-description">
                    <?php echo h($currentLog["description"]); ?>
                </p>

                <div class="log-tabs">
                    <?php foreach ($allowedLogs as $key => $log): ?>
                        <a
                            href="/dev/logs/?log=<?php echo h($key); ?>"
                            class="<?php echo $selected === $key ? "active" : ""; ?>"
                        >
                            <?php echo h($log["label"]); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="log-meta">
                    <div>
                        <span>ファイル</span>
                        <strong><?php echo h("storage/logs/" . basename($currentLog["path"])); ?></strong>
                    </div>
                    <div>
                        <span>状態</span>
                        <strong><?php echo $logExists ? "存在します" : "まだ作成されていません"; ?></strong>
                    </div>
                    <div>
                        <span>表示</span>
                        <strong>直近200行</strong>
                    </div>
                </div>

                <div class="log-viewer">
                    <?php if (!$logExists): ?>
                        <p class="empty-log">ログファイルはまだありません。</p>
                    <?php elseif ($logContent === ""): ?>
                        <p class="empty-log">ログ内容は空です。</p>
                    <?php else: ?>
                        <pre><?php echo h($logContent); ?></pre>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/..//parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>