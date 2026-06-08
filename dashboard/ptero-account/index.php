<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/pterodactyl.php";

$currentUser = require_login();

$pageTitle = "ゲームサーバーパネルアカウント | HC Platform";
$pageDescription = "ゲームサーバーパネルのログイン情報を確認できます。";
$pageCss = "/dashboard/ptero-account/ptero-account.css";

$pdo = db();

$pteroLink = null;
$servers = [];
$panelUrl = "";

$flash = $_SESSION["ptero_account_flash"] ?? null;
unset($_SESSION["ptero_account_flash"]);

if (empty($_SESSION["ptero_account_token"])) {
    $_SESSION["ptero_account_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["ptero_account_token"];

try {
    $panelUrl = hc_ptero_panel_url();
} catch (Throwable $e) {
    $panelUrl = "";
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            ptero_user_id,
            ptero_external_id,
            ptero_uuid,
            username,
            email,
            status,
            initial_password,
            initial_password_created_at,
            initial_password_viewed_at,
            password_setup_completed_at,
            created_at,
            updated_at,
            last_synced_at
        FROM ptero_user_links
        WHERE user_id = :user_id
          AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $pteroLink = $stmt->fetch() ?: null;
} catch (Throwable $e) {
    $pteroLink = null;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            gso.id AS order_id,
            gso.server_name,
            gso.status AS order_status,

            ps.ptero_server_id,
            ps.ptero_identifier,
            ps.ptero_uuid,
            ps.ptero_allocation_id,
            ps.name AS ptero_name,
            ps.status AS ptero_status,
            ps.created_at
        FROM game_server_orders gso
        JOIN ptero_servers ps ON ps.order_id = gso.id
        WHERE gso.user_id = :user_id
          AND ps.deleted_at IS NULL
        ORDER BY ps.created_at DESC, ps.id DESC
    ");

    $stmt->execute([
        "user_id" => (int)$currentUser["id"],
    ]);

    $servers = $stmt->fetchAll();
} catch (Throwable $e) {
    $servers = [];
}

function ptero_account_datetime(?string $value): string
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

$hasInitialPassword = $pteroLink && !empty($pteroLink["initial_password"]);
$passwordCompleted = $pteroLink && !empty($pteroLink["password_setup_completed_at"]);

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="ptero-account-page">
    <section class="ptero-account-hero">
        <div class="container ptero-account-hero-grid">
            <div class="ptero-account-copy reveal">
                <p class="eyebrow">Dashboard / ゲームサーバーパネル</p>
                <h1>ゲームサーバーパネルアカウント</h1>
                <p>
                    Minecraftサーバー管理パネルへログインするための情報です。
                    初回作成時はHC側でランダムな初回パスワードを発行します。
                </p>
            </div>

            <aside class="ptero-account-status-card reveal">
                <span>Game Game Server Panel</span>
                <h2><?php echo $pteroLink ? "連携済み" : "未作成"; ?></h2>
                <p>
                    <?php if ($pteroLink): ?>
                        パネルユーザーID: <?php echo h((string)$pteroLink["ptero_user_id"]); ?>
                    <?php else: ?>
                        サーバー作成後に自動連携されます。
                    <?php endif; ?>
                </p>
            </aside>
        </div>
    </section>

    <section class="section ptero-account-section">
        <div class="container">
            <div class="toolbar">
                <a href="/dashboard/" class="back-button">マイページへ戻る</a>
                <a href="/dashboard/servers/" class="sub-button">契約中サーバーへ</a>

                <?php if ($panelUrl !== ""): ?>
                    <a href="<?php echo h($panelUrl); ?>" class="sub-button" target="_blank" rel="noopener">
                        ゲームサーバーパネルを開く
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="ptero-flash ptero-flash-<?php echo h((string)$flash["type"]); ?>">
                    <?php echo h((string)$flash["message"]); ?>
                </div>
            <?php endif; ?>

            <div class="ptero-account-grid reveal">
                <section class="ptero-panel">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Login</p>
                            <h2>ログイン情報</h2>
                        </div>
                    </div>

                    <?php if (!$pteroLink): ?>
                        <div class="empty-box">
                            <h3>ゲームサーバーパネルアカウントはまだ作成されていません。</h3>
                            <p>
                                支払い完了後、管理者がサーバー作成を実行すると、
                                HCアカウントに紐付いたゲームサーバーパネルアカウントが自動作成されます。
                            </p>
                        </div>
                    <?php else: ?>
                        <dl class="account-detail-list">
                            <div>
                                <dt>ログインメール</dt>
                                <dd><?php echo h((string)($pteroLink["email"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>ユーザー名</dt>
                                <dd><?php echo h((string)($pteroLink["username"] ?: "-")); ?></dd>
                            </div>

                            <div>
                                <dt>パネルユーザーID</dt>
                                <dd>#<?php echo h((string)$pteroLink["ptero_user_id"]); ?></dd>
                            </div>

                            <div>
                                <dt>External ID</dt>
                                <dd><?php echo h((string)$pteroLink["ptero_external_id"]); ?></dd>
                            </div>

                            <div>
                                <dt>パネルURL</dt>
                                <dd>
                                    <?php if ($panelUrl !== ""): ?>
                                        <a href="<?php echo h($panelUrl); ?>" target="_blank" rel="noopener">
                                            <?php echo h($panelUrl); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </dd>
                            </div>

                            <div>
                                <dt>初回パスワード状態</dt>
                                <dd>
                                    <?php if ($hasInitialPassword): ?>
                                        未設定
                                    <?php elseif ($passwordCompleted): ?>
                                        設定済み
                                    <?php else: ?>
                                        表示できません
                                    <?php endif; ?>
                                </dd>
                            </div>

                            <div>
                                <dt>最終同期</dt>
                                <dd><?php echo h(ptero_account_datetime((string)($pteroLink["last_synced_at"] ?? ""))); ?></dd>
                            </div>
                        </dl>

                        <?php if ($hasInitialPassword): ?>
                            <div class="initial-password-box">
                                <div class="initial-password-head">
                                    <div>
                                        <strong>初回パスワードの設定</strong>
                                        <p>
                                            ゲームサーバーパネルへ初回ログインするための一時パスワードです。
                                            ログイン後、ゲームサーバーパネル側で新しいパスワードへ変更してください。
                                        </p>
                                    </div>
                                    <span>初回のみ</span>
                                </div>

                                <label class="initial-password-field">
                                    <span>初回パスワード</span>
                                    <div>
                                        <input id="initialパネルPassword" type="password" value="<?php echo h((string)$pteroLink["initial_password"]); ?>" readonly>
                                        <button type="button" data-reveal-password>表示</button>
                                        <button type="button" data-copy-password>コピー</button>
                                    </div>
                                </label>

                                <ol class="initial-password-steps">
                                    <li>ゲームサーバーパネルを開く</li>
                                    <li>ログインメールと初回パスワードでログイン</li>
                                    <li>ゲームサーバーパネル側で自分のパスワードに変更</li>
                                    <li>完了後、このページで「設定済みにする」を押す</li>
                                </ol>

                                <form method="post" action="/dashboard/ptero-account/complete-password.php" class="password-complete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                    <button type="submit">
                                        初回パスワードを設定済みにする
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="password-notice">
                                <strong>パスワードについて</strong>
                                <p>
                                    初回パスワードは表示済み、または既存ゲームサーバーパネルアカウントのためHC側では表示できません。
                                    ログインできない場合は、ゲームサーバーパネル側の「パスワードを忘れた場合」から再設定してください。
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <section class="ptero-panel">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Servers</p>
                            <h2>紐付け済みサーバー</h2>
                        </div>
                    </div>

                    <?php if (!$servers): ?>
                        <div class="empty-box">
                            <h3>紐付け済みサーバーはありません。</h3>
                            <p>サーバー作成が完了すると、ここにゲームサーバーが表示されます。</p>
                        </div>
                    <?php else: ?>
                        <div class="server-link-list">
                            <?php foreach ($servers as $server): ?>
                                <article class="server-link-card">
                                    <div>
                                        <span>契約 #<?php echo h((string)$server["order_id"]); ?></span>
                                        <h3><?php echo h((string)($server["ptero_name"] ?: $server["server_name"] ?: "名称未設定")); ?></h3>
                                        <p>
                                            Identifier:
                                            <?php echo h((string)($server["ptero_identifier"] ?: "-")); ?>
                                            /
                                            Allocation ID:
                                            <?php echo h((string)($server["ptero_allocation_id"] ?: "-")); ?>
                                        </p>
                                    </div>

                                    <div class="server-link-actions">
                                        <a href="/dashboard/servers/detail/?id=<?php echo h((string)$server["order_id"]); ?>" class="secondary-action">
                                            契約詳細
                                        </a>

                                        <a href="/dashboard/servers/panel/?id=<?php echo h((string)$server["order_id"]); ?>" class="primary-action">
                                            サーバーパネルへ
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script>
(() => {
    const input = document.getElementById("initialパネルPassword");
    const revealButton = document.querySelector("[data-reveal-password]");
    const copyButton = document.querySelector("[data-copy-password]");

    if (revealButton && input) {
        revealButton.addEventListener("click", () => {
            const isHidden = input.type === "password";
            input.type = isHidden ? "text" : "password";
            revealButton.textContent = isHidden ? "隠す" : "表示";
        });
    }

    if (copyButton && input) {
        copyButton.addEventListener("click", async () => {
            try {
                await navigator.clipboard.writeText(input.value);
                copyButton.textContent = "コピー済み";
                setTimeout(() => {
                    copyButton.textContent = "コピー";
                }, 1800);
            } catch (error) {
                input.select();
                document.execCommand("copy");
                copyButton.textContent = "コピー済み";
                setTimeout(() => {
                    copyButton.textContent = "コピー";
                }, 1800);
            }
        });
    }
})();
</script>
</body>
</html>
