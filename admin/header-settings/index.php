<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/permissions.php";

$user = require_role("admin");

header('Location: /staff/admin/site/header/', true, 302);
exit;

$pageTitle = "ヘッダー表示設定 | HC Platform";
$pageDescription = "ヘッダーのOperationメニュー表示を管理します。";
$pageCss = "/admin/header-settings/header-settings.css";

$pdo = db();

$errors = [];
$successMessage = "";
$links = [];

$roleOptions = [
    "staff" => "スタッフ以上",
    "developer" => "開発者以上",
    "admin" => "管理者以上",
    "owner" => "オーナーのみ",
];

if (empty($_SESSION["header_settings_token"])) {
    $_SESSION["header_settings_token"] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION["header_settings_token"];

function header_settings_default_links(): array
{
    return [
        ["staff", "スタッフページ", "/staff/", "staff", true, 10],
        ["admin", "管理者ページ", "/admin/", "admin", true, 20],
        ["server_orders", "ゲームサーバー申込管理", "/admin/server-orders/", "admin", true, 30],
        ["plan_change_requests", "プラン変更申請管理", "/admin/plan-change-requests/", "admin", true, 40],
        ["game_plans", "ゲームサーバープラン管理", "/admin/game-plans/", "admin", true, 50],
        ["services", "事業管理", "/admin/services/", "admin", true, 60],
        ["news", "お知らせ管理", "/admin/news/", "admin", true, 70],
        ["ptero", "ゲームサーバーパネル連携", "/admin/ptero/", "admin", true, 80],
        ["dev", "開発者ページ", "/admin/dev/", "developer", true, 90],
        ["header_settings", "ヘッダー表示設定", "/admin/header-settings/", "admin", true, 100],
    ];
}

function header_settings_default_keys(): array
{
    return array_map(
        fn($link) => $link[0],
        header_settings_default_links()
    );
}

function header_settings_ensure_table(PDO $pdo): void
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
        ON CONFLICT (item_key) DO NOTHING
    ");

    foreach (header_settings_default_links() as $link) {
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

function header_settings_reset_defaults(PDO $pdo): void
{
    $defaultKeys = header_settings_default_keys();

    $placeholders = [];
    $params = [];

    foreach ($defaultKeys as $index => $key) {
        $placeholder = ":key_" . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $key;
    }

    if ($placeholders) {
        $deleteSql = "
            DELETE FROM header_operation_links
            WHERE item_key NOT IN (" . implode(", ", $placeholders) . ")
        ";

        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->execute($params);
    }

    $upsert = $pdo->prepare("
        INSERT INTO header_operation_links
        (item_key, label, url, required_role, is_visible, sort_order, updated_at)
        VALUES
        (:item_key, :label, :url, :required_role, :is_visible, :sort_order, NOW())
        ON CONFLICT (item_key) DO UPDATE SET
            label = EXCLUDED.label,
            url = EXCLUDED.url,
            required_role = EXCLUDED.required_role,
            is_visible = EXCLUDED.is_visible,
            sort_order = EXCLUDED.sort_order,
            updated_at = NOW()
    ");

    foreach (header_settings_default_links() as $link) {
        [$itemKey, $label, $url, $requiredRole, $isVisible, $sortOrder] = $link;

        $upsert->execute([
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

function header_settings_fetch_links(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT *
        FROM header_operation_links
        ORDER BY sort_order ASC, id ASC
    ");

    return $stmt->fetchAll();
}

function header_settings_validate_url(string $url): void
{
    if ($url === "" || !str_starts_with($url, "/")) {
        throw new RuntimeException("URLは / から始まる内部URLで入力してください。");
    }

    if (str_contains($url, "://")) {
        throw new RuntimeException("外部URLは登録できません。内部URLのみ登録してください。");
    }
}

try {
    header_settings_ensure_table($pdo);
} catch (Throwable $e) {
    $errors[] = "ヘッダー設定テーブルの準備に失敗しました。";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$errors) {
    $token = (string)($_POST["csrf_token"] ?? "");
    $action = trim((string)($_POST["action"] ?? "save"));

    if (!hash_equals($csrfToken, $token)) {
        $errors[] = "不正な操作です。もう一度やり直してください。";
    } else {
        try {
            if ($action === "save") {
                $postedLinks = $_POST["links"] ?? [];

                if (!is_array($postedLinks)) {
                    throw new RuntimeException("送信内容が不正です。");
                }

                $pdo->beginTransaction();

                $update = $pdo->prepare("
                    UPDATE header_operation_links
                    SET
                        label = :label,
                        url = :url,
                        required_role = :required_role,
                        is_visible = :is_visible,
                        sort_order = :sort_order,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                foreach ($postedLinks as $id => $link) {
                    $id = (int)$id;
                    $label = trim((string)($link["label"] ?? ""));
                    $url = trim((string)($link["url"] ?? ""));
                    $requiredRole = trim((string)($link["required_role"] ?? "staff"));
                    $sortOrder = (int)($link["sort_order"] ?? 0);
                    $isVisible = isset($link["is_visible"]);

                    if ($id <= 0) {
                        continue;
                    }

                    if ($label === "") {
                        throw new RuntimeException("ラベルが空の項目があります。");
                    }

                    header_settings_validate_url($url);

                    if (!array_key_exists($requiredRole, $roleOptions)) {
                        throw new RuntimeException("必要権限が不正です。");
                    }

                    $itemKeyStmt = $pdo->prepare("
                        SELECT item_key
                        FROM header_operation_links
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $itemKeyStmt->execute(["id" => $id]);
                    $current = $itemKeyStmt->fetch();

                    if ($current && (string)$current["item_key"] === "admin") {
                        $isVisible = true;
                        $requiredRole = "admin";
                    }

                    $update->execute([
                        "label" => $label,
                        "url" => $url,
                        "required_role" => $requiredRole,
                        "is_visible" => $isVisible ? "true" : "false",
                        "sort_order" => $sortOrder,
                        "id" => $id,
                    ]);
                }

                $pdo->commit();
                $successMessage = "ヘッダー表示設定を保存しました。";
            } elseif ($action === "add") {
                $label = trim((string)($_POST["new_label"] ?? ""));
                $url = trim((string)($_POST["new_url"] ?? ""));
                $requiredRole = trim((string)($_POST["new_required_role"] ?? "admin"));
                $sortOrder = (int)($_POST["new_sort_order"] ?? 999);
                $isVisible = isset($_POST["new_is_visible"]);

                if ($label === "") {
                    throw new RuntimeException("追加する項目のラベルを入力してください。");
                }

                header_settings_validate_url($url);

                if (!array_key_exists($requiredRole, $roleOptions)) {
                    throw new RuntimeException("追加する項目の必要権限が不正です。");
                }

                $itemKey = "custom_" . bin2hex(random_bytes(5));

                $stmt = $pdo->prepare("
                    INSERT INTO header_operation_links
                    (
                        item_key,
                        label,
                        url,
                        required_role,
                        is_visible,
                        sort_order,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        :item_key,
                        :label,
                        :url,
                        :required_role,
                        :is_visible,
                        :sort_order,
                        NOW(),
                        NOW()
                    )
                ");

                $stmt->execute([
                    "item_key" => $itemKey,
                    "label" => $label,
                    "url" => $url,
                    "required_role" => $requiredRole,
                    "is_visible" => $isVisible ? "true" : "false",
                    "sort_order" => $sortOrder,
                ]);

                $successMessage = "ヘッダーメニュー項目を追加しました。";
            } elseif ($action === "reset") {
                $pdo->beginTransaction();
                header_settings_reset_defaults($pdo);
                $pdo->commit();

                $successMessage = "ヘッダーメニューを初期状態にリセットしました。";
            } else {
                throw new RuntimeException("不明な操作です。");
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e->getMessage();
        }
    }
}

try {
    $links = header_settings_fetch_links($pdo);
} catch (Throwable $e) {
    $errors[] = "ヘッダー設定の取得に失敗しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="header-settings-page">
    <section class="header-settings-hero">
        <div class="container header-settings-hero-grid">
            <div class="header-settings-copy reveal">
                <p class="eyebrow">Admin / Header Settings</p>
                <h1>ヘッダー表示設定</h1>
                <p>
                    ヘッダーのOperation欄に表示する項目を管理します。
                    管理者ページは必須項目として常に表示されます。
                </p>
            </div>

            <aside class="header-settings-status-card reveal">
                <span>管理者</span>
                <h2><?php echo h((string)$user["username"]); ?></h2>
                <p><?php echo h(role_label((string)$user["role"])); ?></p>
            </aside>
        </div>
    </section>

    <section class="section header-settings-section">
        <div class="container">
            <div class="toolbar">
                <a href="/admin/" class="back-button">管理者ページへ戻る</a>
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

            <form method="post" action="/admin/header-settings/" class="settings-panel reveal">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="save">

                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Operation Menu</p>
                        <h2>表示項目</h2>
                    </div>

                    <div class="panel-actions">
                        <button type="submit" class="save-button">保存する</button>
                    </div>
                </div>

                <div class="settings-list">
                    <?php foreach ($links as $link): ?>
                        <?php
                        $id = (int)$link["id"];
                        $isRequired = (string)$link["item_key"] === "admin";
                        $isCustom = str_starts_with((string)$link["item_key"], "custom_");
                        $isChecked = !empty($link["is_visible"]) || $isRequired;
                        ?>

                        <article class="setting-card <?php echo $isRequired ? 'is-required' : ''; ?>">
                            <label class="visible-toggle">
                                <input
                                    type="checkbox"
                                    name="links[<?php echo h((string)$id); ?>][is_visible]"
                                    value="1"
                                    <?php echo $isChecked ? "checked" : ""; ?>
                                    <?php echo $isRequired ? "disabled" : ""; ?>
                                >
                                <span>
                                    表示
                                    <?php if ($isRequired): ?>
                                        <em>必須</em>
                                    <?php elseif ($isCustom): ?>
                                        <em>追加</em>
                                    <?php endif; ?>
                                </span>
                            </label>

                            <div class="setting-fields">
                                <div>
                                    <label>ラベル</label>
                                    <input
                                        type="text"
                                        name="links[<?php echo h((string)$id); ?>][label]"
                                        value="<?php echo h((string)$link["label"]); ?>"
                                        required
                                    >
                                </div>

                                <div>
                                    <label>URL</label>
                                    <input
                                        type="text"
                                        name="links[<?php echo h((string)$id); ?>][url]"
                                        value="<?php echo h((string)$link["url"]); ?>"
                                        required
                                    >
                                </div>

                                <div>
                                    <label>必要権限</label>
                                    <select name="links[<?php echo h((string)$id); ?>][required_role]" <?php echo $isRequired ? "disabled" : ""; ?>>
                                        <?php foreach ($roleOptions as $role => $label): ?>
                                            <option value="<?php echo h($role); ?>" <?php echo (string)$link["required_role"] === $role ? "selected" : ""; ?>>
                                                <?php echo h($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php if ($isRequired): ?>
                                        <input type="hidden" name="links[<?php echo h((string)$id); ?>][required_role]" value="admin">
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label>順番</label>
                                    <input
                                        type="number"
                                        name="links[<?php echo h((string)$id); ?>][sort_order]"
                                        value="<?php echo h((string)$link["sort_order"]); ?>"
                                    >
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </form>

            <section class="add-reset-grid reveal">
                <form method="post" action="/admin/header-settings/" class="add-panel">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="panel-head compact-head">
                        <div>
                            <p class="eyebrow">Add</p>
                            <h2>項目を追加</h2>
                        </div>
                    </div>

                    <div class="add-form-grid">
                        <div>
                            <label>ラベル</label>
                            <input type="text" name="new_label" placeholder="例: 請求管理" required>
                        </div>

                        <div>
                            <label>URL</label>
                            <input type="text" name="new_url" placeholder="例: /billing/" required>
                        </div>

                        <div>
                            <label>必要権限</label>
                            <select name="new_required_role">
                                <?php foreach ($roleOptions as $role => $label): ?>
                                    <option value="<?php echo h($role); ?>" <?php echo $role === "admin" ? "selected" : ""; ?>>
                                        <?php echo h($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>順番</label>
                            <input type="number" name="new_sort_order" value="999">
                        </div>
                    </div>

                    <label class="new-visible-check">
                        <input type="checkbox" name="new_is_visible" value="1" checked>
                        <span>追加後すぐ表示する</span>
                    </label>

                    <button type="submit" class="add-button">追加する</button>
                </form>

                <form method="post" action="/admin/header-settings/" class="reset-panel">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="action" value="reset">

                    <div class="panel-head compact-head">
                        <div>
                            <p class="eyebrow">Reset</p>
                            <h2>初期状態に戻す</h2>
                        </div>
                    </div>

                    <p>
                        ヘッダーメニューを初期状態に戻します。
                        追加したカスタム項目は削除されます。
                        管理者ページは必須項目として残ります。
                    </p>

                    <button type="submit" class="reset-button" onclick="return confirm('ヘッダーメニューを初期状態にリセットします。追加した項目は削除されます。よろしいですか？');">
                        リセットする
                    </button>
                </form>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
