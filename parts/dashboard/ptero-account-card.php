<?php

$dashboardUser = $currentUser ?? $user ?? null;

$pteroDashboardLink = null;
$pteroDashboardServerCount = 0;
$pteroDashboardFirstServerOrderId = null;
$pteroDashboardPanelUrl = "";

if ($dashboardUser && !empty($dashboardUser["id"])) {
    try {
        $pteroDashboardPanelUrl = function_exists("hc_ptero_panel_url") ? hc_ptero_panel_url() : "";
    } catch (Throwable $e) {
        $pteroDashboardPanelUrl = "";
    }

    try {
        $pdoForパネルCard = db();

        $stmt = $pdoForパネルCard->prepare("
            SELECT
                id,
                user_id,
                ptero_user_id,
                ptero_external_id,
                username,
                email,
                initial_password,
                initial_password_created_at,
                password_setup_completed_at,
                last_synced_at
            FROM ptero_user_links
            WHERE user_id = :user_id
              AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            "user_id" => (int)$dashboardUser["id"],
        ]);

        $pteroDashboardLink = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $pteroDashboardLink = null;
    }

    try {
        $stmt = $pdoForパネルCard->prepare("
            SELECT
                COUNT(*) AS count,
                MIN(gso.id) AS first_order_id
            FROM game_server_orders gso
            JOIN ptero_servers ps ON ps.order_id = gso.id
            WHERE gso.user_id = :user_id
              AND ps.deleted_at IS NULL
        ");

        $stmt->execute([
            "user_id" => (int)$dashboardUser["id"],
        ]);

        $row = $stmt->fetch();

        $pteroDashboardServerCount = (int)($row["count"] ?? 0);
        $pteroDashboardFirstServerOrderId = !empty($row["first_order_id"]) ? (int)$row["first_order_id"] : null;
    } catch (Throwable $e) {
        $pteroDashboardServerCount = 0;
        $pteroDashboardFirstServerOrderId = null;
    }
}

$pteroDashboardHasInitialPassword = $pteroDashboardLink && !empty($pteroDashboardLink["initial_password"]);
$pteroDashboardIsLinked = $pteroDashboardLink && !empty($pteroDashboardLink["ptero_user_id"]);

$pteroDashboardStatusLabel = "未作成";
$pteroDashboardStatusClass = "not-ready";

if ($pteroDashboardHasInitialPassword) {
    $pteroDashboardStatusLabel = "初回設定待ち";
    $pteroDashboardStatusClass = "needs-setup";
} elseif ($pteroDashboardIsLinked) {
    $pteroDashboardStatusLabel = "連携済み";
    $pteroDashboardStatusClass = "linked";
}
?>

<section class="section dashboard-ptero-section">
    <div class="container">
        <article class="dashboard-ptero-card dashboard-ptero-card-<?php echo h($pteroDashboardStatusClass); ?> reveal">
            <div class="dashboard-ptero-main">
                <div class="dashboard-ptero-icon">dns</div>

                <div>
                    <div class="dashboard-ptero-title-row">
                        <p class="eyebrow">ゲームサーバーパネル</p>
                        <span class="dashboard-ptero-status">
                            <?php echo h($pteroDashboardStatusLabel); ?>
                        </span>
                    </div>

                    <h2>ゲームサーバーパネルアカウント</h2>

                    <?php if ($pteroDashboardHasInitialPassword): ?>
                        <p>
                            初回パスワードが発行されています。
                            ログイン情報ページからパネルログイン情報を確認してください。
                        </p>
                    <?php elseif ($pteroDashboardIsLinked): ?>
                        <p>
                            ゲームサーバーパネルアカウントはHCアカウントに連携済みです。
                            サーバーパネルやログイン情報を確認できます。
                        </p>
                    <?php else: ?>
                        <p>
                            サーバー作成が完了すると、HCアカウントに紐付いたゲームサーバーパネルアカウントが自動作成されます。
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-ptero-meta">
                <div>
                    <span>パネルユーザー</span>
                    <strong>
                        <?php if ($pteroDashboardIsLinked): ?>
                            #<?php echo h((string)$pteroDashboardLink["ptero_user_id"]); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </strong>
                </div>

                <div>
                    <span>サーバー</span>
                    <strong><?php echo h((string)$pteroDashboardServerCount); ?> 件</strong>
                </div>

                <div>
                    <span>ログインID</span>
                    <strong>
                        <?php echo h((string)($pteroDashboardLink["email"] ?? "-")); ?>
                    </strong>
                </div>
            </div>

            <div class="dashboard-ptero-actions">
                <a href="/dashboard/ptero-account/" class="dashboard-ptero-primary">
                    ログイン情報を見る
                </a>

                <?php if ($pteroDashboardFirstServerOrderId): ?>
                    <a href="/dashboard/servers/panel/?id=<?php echo h((string)$pteroDashboardFirstServerOrderId); ?>" class="dashboard-ptero-secondary">
                        サーバーパネルへ
                    </a>
                <?php elseif ($pteroDashboardPanelUrl !== ""): ?>
                    <a href="<?php echo h($pteroDashboardPanelUrl); ?>" class="dashboard-ptero-secondary" target="_blank" rel="noopener">
                        ゲームサーバーパネルを開く
                    </a>
                <?php else: ?>
                    <a href="/dashboard/servers/" class="dashboard-ptero-secondary">
                        契約中サーバーへ
                    </a>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>
