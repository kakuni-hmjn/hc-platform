<?php

session_start();

require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/pterodactyl.php";

$currentUser = require_login();
$pdo = db();

$orderId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => [
        "min_range" => 1,
    ],
]);

$pageTitle = "サーバーパネルへ移動 | HC Platform";
$pageDescription = "ゲームサーバーパネルへ移動します。";
$pageCss = "/dashboard/servers/panel/panel.css";

$errors = [];
$server = null;

if (!$orderId) {
    $errors[] = "契約IDが指定されていません。";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT
                gso.id AS order_id,
                gso.user_id,
                gso.server_name,
                gso.status AS order_status,

                ps.ptero_server_id,
                ps.ptero_identifier,
                ps.ptero_uuid,
                ps.name AS ptero_name,
                ps.status AS ptero_status
            FROM game_server_orders gso
            JOIN ptero_servers ps ON ps.order_id = gso.id
            WHERE gso.id = :order_id
              AND gso.user_id = :user_id
              AND ps.deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            "order_id" => $orderId,
            "user_id" => (int)$currentUser["id"],
        ]);

        $server = $stmt->fetch();

        if (!$server) {
            $errors[] = "ゲームサーバー情報が見つかりません。";
        } elseif ((string)$server["order_status"] !== "active") {
            $errors[] = "このサーバーはまだ利用可能状態ではありません。";
        } elseif (empty($server["ptero_identifier"])) {
            $errors[] = "ゲームサーバーパネル Identifierが登録されていません。";
        } else {
            $panelUrl = hc_ptero_panel_url();
            $targetUrl = rtrim($panelUrl, "/") . "/server/" . rawurlencode((string)$server["ptero_identifier"]);

            header("Location: " . $targetUrl, true, 302);
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = "サーバーパネル情報の取得中にエラーが発生しました。";
    }
}

require_once __DIR__ . "/../../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="panel-redirect-page">
    <section class="panel-redirect-hero">
        <div class="container panel-redirect-card">
            <p class="eyebrow">Game Server Panel</p>
            <h1>サーバーパネルへ移動できません</h1>

            <?php if ($errors): ?>
                <div class="panel-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="panel-actions">
                <a href="/dashboard/servers/detail/?id=<?php echo h((string)($orderId ?: "")); ?>" class="primary-action">
                    契約詳細へ戻る
                </a>

                <a href="/dashboard/servers/" class="secondary-action">
                    サーバー一覧へ
                </a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
