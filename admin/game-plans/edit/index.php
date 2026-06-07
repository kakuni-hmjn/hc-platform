<?php
session_start();

require_once __DIR__ . "/../../../lib/auth.php";
require_once __DIR__ . "/../../../lib/helpers.php";
require_once __DIR__ . "/../../../lib/db.php";
require_once __DIR__ . "/../../../lib/permissions.php";

$user = require_role("admin");

$pageTitle = "ゲームサーバープラン編集 | HC Platform";
$pageDescription = "HC Platformの管理者向けゲームサーバープラン編集ページです。";
$pageCss = "/admin/game-plans/game-plans.css";

$pdo = db();

$errors = [];
$success = "";

$planId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$isEdit = $planId > 0;

$statuses = [
    "draft" => "下書き",
    "published" => "公開",
    "hidden" => "非公開",
];

$plan = [
    "name" => "",
    "slug" => "",
    "description" => "",
    "price_monthly" => 0,
    "memory_mb" => 2048,
    "cpu_limit" => 100,
    "disk_mb" => 10240,
    "backup_limit" => 1,
    "database_limit" => 0,
    "allocation_limit" => 1,
    "server_software_note" => "",
    "ptero_nest_id" => "",
    "ptero_egg_id" => "",
    "ptero_docker_image" => "",
    "ptero_startup_command" => "",
    "status" => "draft",
    "sort_order" => 100,
];

$selectedNodeIds = [];

function normalize_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace("/[^a-z0-9\-]+/", "-", $value);
    $value = preg_replace("/-+/", "-", $value);
    return trim($value, "-");
}

function to_nullable_int(string $value): ?int
{
    $value = trim($value);

    if ($value === "") {
        return null;
    }

    return (int)$value;
}

function to_nullable_string(string $value): ?string
{
    $value = trim($value);

    if ($value === "") {
        return null;
    }

    return $value;
}

try {
    $nodeStmt = $pdo->query("
        SELECT
            id,
            ptero_node_id,
            name,
            label,
            cpu_type,
            is_high_performance,
            status,
            sort_order
        FROM ptero_nodes
        WHERE status IN ('active', 'maintenance')
        ORDER BY sort_order ASC, id ASC
    ");
    $nodes = $nodeStmt->fetchAll();
} catch (Throwable $e) {
    $nodes = [];
    $errors[] = "Node情報の取得中にエラーが発生しました。";
}

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM game_server_plans
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(["id" => $planId]);
        $loadedPlan = $stmt->fetch();

        if (!$loadedPlan) {
            http_response_code(404);
            exit("プランが見つかりません。");
        }

        $plan = array_merge($plan, $loadedPlan);

        $nodeStmt = $pdo->prepare("
            SELECT node_id
            FROM game_server_plan_nodes
            WHERE plan_id = :plan_id
            ORDER BY is_primary DESC, id ASC
        ");
        $nodeStmt->execute(["plan_id" => $planId]);
        $selectedNodeIds = array_map("intval", $nodeStmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        $errors[] = "プラン情報の取得中にエラーが発生しました。";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $plan["name"] = trim($_POST["name"] ?? "");
    $plan["slug"] = normalize_slug($_POST["slug"] ?? "");
    $plan["description"] = trim($_POST["description"] ?? "");
    $plan["price_monthly"] = (int)($_POST["price_monthly"] ?? 0);
    $plan["memory_mb"] = (int)($_POST["memory_mb"] ?? 0);
    $plan["cpu_limit"] = (int)($_POST["cpu_limit"] ?? 0);
    $plan["disk_mb"] = (int)($_POST["disk_mb"] ?? 0);
    $plan["backup_limit"] = (int)($_POST["backup_limit"] ?? 0);
    $plan["database_limit"] = (int)($_POST["database_limit"] ?? 0);
    $plan["allocation_limit"] = (int)($_POST["allocation_limit"] ?? 1);
    $plan["server_software_note"] = trim($_POST["server_software_note"] ?? "");
    $plan["ptero_nest_id"] = trim($_POST["ptero_nest_id"] ?? "");
    $plan["ptero_egg_id"] = trim($_POST["ptero_egg_id"] ?? "");
    $plan["ptero_docker_image"] = trim($_POST["ptero_docker_image"] ?? "");
    $plan["ptero_startup_command"] = trim($_POST["ptero_startup_command"] ?? "");
    $plan["status"] = $_POST["status"] ?? "draft";
    $plan["sort_order"] = (int)($_POST["sort_order"] ?? 100);

    $selectedNodeIds = array_map("intval", $_POST["node_ids"] ?? []);
    $selectedNodeIds = array_values(array_unique(array_filter($selectedNodeIds)));

    if ($plan["name"] === "") {
        $errors[] = "プラン名を入力してください。";
    }

    if ($plan["slug"] === "") {
        $errors[] = "スラッグを入力してください。";
    }

    if ($plan["description"] === "") {
        $errors[] = "説明を入力してください。";
    }

    if ($plan["price_monthly"] < 0) {
        $errors[] = "月額料金が不正です。";
    }

    if ($plan["memory_mb"] <= 0) {
        $errors[] = "メモリ容量を入力してください。";
    }

    if ($plan["cpu_limit"] <= 0) {
        $errors[] = "CPU制限を入力してください。";
    }

    if ($plan["disk_mb"] <= 0) {
        $errors[] = "ディスク容量を入力してください。";
    }

    if (!array_key_exists($plan["status"], $statuses)) {
        $errors[] = "公開状態が不正です。";
    }

    if (!$selectedNodeIds) {
        $errors[] = "利用可能Nodeを1つ以上選択してください。";
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE game_server_plans
                    SET
                        name = :name,
                        slug = :slug,
                        description = :description,
                        price_monthly = :price_monthly,
                        memory_mb = :memory_mb,
                        cpu_limit = :cpu_limit,
                        disk_mb = :disk_mb,
                        backup_limit = :backup_limit,
                        database_limit = :database_limit,
                        allocation_limit = :allocation_limit,
                        server_software_note = :server_software_note,
                        ptero_nest_id = :ptero_nest_id,
                        ptero_egg_id = :ptero_egg_id,
                        ptero_docker_image = :ptero_docker_image,
                        ptero_startup_command = :ptero_startup_command,
                        status = :status,
                        sort_order = :sort_order,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $stmt->execute([
                    "id" => $planId,
                    "name" => $plan["name"],
                    "slug" => $plan["slug"],
                    "description" => $plan["description"],
                    "price_monthly" => $plan["price_monthly"],
                    "memory_mb" => $plan["memory_mb"],
                    "cpu_limit" => $plan["cpu_limit"],
                    "disk_mb" => $plan["disk_mb"],
                    "backup_limit" => $plan["backup_limit"],
                    "database_limit" => $plan["database_limit"],
                    "allocation_limit" => $plan["allocation_limit"],
                    "server_software_note" => to_nullable_string($plan["server_software_note"]),
                    "ptero_nest_id" => to_nullable_int($plan["ptero_nest_id"]),
                    "ptero_egg_id" => to_nullable_int($plan["ptero_egg_id"]),
                    "ptero_docker_image" => to_nullable_string($plan["ptero_docker_image"]),
                    "ptero_startup_command" => to_nullable_string($plan["ptero_startup_command"]),
                    "status" => $plan["status"],
                    "sort_order" => $plan["sort_order"],
                ]);

                $savedPlanId = $planId;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO game_server_plans (
                        name,
                        slug,
                        description,
                        price_monthly,
                        memory_mb,
                        cpu_limit,
                        disk_mb,
                        backup_limit,
                        database_limit,
                        allocation_limit,
                        server_software_note,
                        ptero_nest_id,
                        ptero_egg_id,
                        ptero_docker_image,
                        ptero_startup_command,
                        status,
                        sort_order,
                        created_at
                    ) VALUES (
                        :name,
                        :slug,
                        :description,
                        :price_monthly,
                        :memory_mb,
                        :cpu_limit,
                        :disk_mb,
                        :backup_limit,
                        :database_limit,
                        :allocation_limit,
                        :server_software_note,
                        :ptero_nest_id,
                        :ptero_egg_id,
                        :ptero_docker_image,
                        :ptero_startup_command,
                        :status,
                        :sort_order,
                        NOW()
                    )
                ");

                $stmt->execute([
                    "name" => $plan["name"],
                    "slug" => $plan["slug"],
                    "description" => $plan["description"],
                    "price_monthly" => $plan["price_monthly"],
                    "memory_mb" => $plan["memory_mb"],
                    "cpu_limit" => $plan["cpu_limit"],
                    "disk_mb" => $plan["disk_mb"],
                    "backup_limit" => $plan["backup_limit"],
                    "database_limit" => $plan["database_limit"],
                    "allocation_limit" => $plan["allocation_limit"],
                    "server_software_note" => to_nullable_string($plan["server_software_note"]),
                    "ptero_nest_id" => to_nullable_int($plan["ptero_nest_id"]),
                    "ptero_egg_id" => to_nullable_int($plan["ptero_egg_id"]),
                    "ptero_docker_image" => to_nullable_string($plan["ptero_docker_image"]),
                    "ptero_startup_command" => to_nullable_string($plan["ptero_startup_command"]),
                    "status" => $plan["status"],
                    "sort_order" => $plan["sort_order"],
                ]);

                $savedPlanId = (int)$pdo->lastInsertId();
            }

            $deleteStmt = $pdo->prepare("
                DELETE FROM game_server_plan_nodes
                WHERE plan_id = :plan_id
            ");
            $deleteStmt->execute(["plan_id" => $savedPlanId]);

            $insertNodeStmt = $pdo->prepare("
                INSERT INTO game_server_plan_nodes (
                    plan_id,
                    node_id,
                    is_primary
                ) VALUES (
                    :plan_id,
                    :node_id,
                    :is_primary
                )
            ");

            foreach ($selectedNodeIds as $index => $nodeId) {
                $insertNodeStmt->execute([
                    "plan_id" => $savedPlanId,
                    "node_id" => $nodeId,
                    "is_primary" => $index === 0,
                ]);
            }

            $pdo->commit();

            header("Location: /admin/game-plans/?saved=1");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException && $e->getCode() === "23505") {
                $errors[] = "同じスラッグのプランがすでに存在します。";
            } else {
                $errors[] = "保存中にエラーが発生しました。";
            }
        }
    }
}

require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>

<main class="game-plans-page">

    <section class="game-plans-hero">
        <div class="container game-plans-hero-grid">

            <div class="game-plans-copy reveal">
                <p class="eyebrow">Admin / Game Plans</p>
                <h1><?php echo $isEdit ? "プラン編集" : "プラン新規追加"; ?></h1>
                <p>
                    ゲームサーバーレンタルのプラン内容、価格、スペック、対応Node、Pterodactyl連携情報を設定します。
                </p>
            </div>

            <aside class="game-plans-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section game-plans-section">
        <div class="container">

            <form action="" method="post" class="plans-panel reveal">

                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Edit</p>
                        <h2>プラン設定</h2>
                    </div>

                    <div class="panel-actions">
                        <a href="/admin/game-plans/" class="back-button">一覧へ戻る</a>
                        <button type="submit" class="create-button">保存する</button>
                    </div>
                </div>

                <?php if ($errors): ?>
                    <div class="plans-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="edit-form-grid">
                    <div class="form-field">
                        <label for="name">プラン名</label>
                        <input id="name" name="name" type="text" value="<?php echo h((string)$plan["name"]); ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="slug">スラッグ</label>
                        <input id="slug" name="slug" type="text" value="<?php echo h((string)$plan["slug"]); ?>" required>
                        <small>例: entry / standard / ryzen-performance-32gb</small>
                    </div>

                    <div class="form-field full">
                        <label for="description">説明</label>
                        <textarea id="description" name="description" rows="4" required><?php echo h((string)$plan["description"]); ?></textarea>
                    </div>

                    <div class="form-field">
                        <label for="price_monthly">月額料金</label>
                        <input id="price_monthly" name="price_monthly" type="number" min="0" value="<?php echo h((string)$plan["price_monthly"]); ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="memory_mb">メモリ MB</label>
                        <input id="memory_mb" name="memory_mb" type="number" min="1" value="<?php echo h((string)$plan["memory_mb"]); ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="cpu_limit">CPU %</label>
                        <input id="cpu_limit" name="cpu_limit" type="number" min="1" value="<?php echo h((string)$plan["cpu_limit"]); ?>" required>
                        <small>100 = 1vCPU、200 = 2vCPU、800 = 8vCPU</small>
                    </div>

                    <div class="form-field">
                        <label for="disk_mb">ディスク MB</label>
                        <input id="disk_mb" name="disk_mb" type="number" min="1" value="<?php echo h((string)$plan["disk_mb"]); ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="backup_limit">バックアップ数</label>
                        <input id="backup_limit" name="backup_limit" type="number" min="0" value="<?php echo h((string)$plan["backup_limit"]); ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="database_limit">DB数</label>
                        <input id="database_limit" name="database_limit" type="number" min="0" value="<?php echo h((string)$plan["database_limit"]); ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="allocation_limit">Allocation数</label>
                        <input id="allocation_limit" name="allocation_limit" type="number" min="1" value="<?php echo h((string)$plan["allocation_limit"]); ?>" required>
                    </div>

                    <div class="form-field">
                        <label for="status">公開状態</label>
                        <select id="status" name="status">
                            <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?php echo h($value); ?>" <?php echo $plan["status"] === $value ? "selected" : ""; ?>>
                                    <?php echo h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="sort_order">表示順</label>
                        <input id="sort_order" name="sort_order" type="number" value="<?php echo h((string)$plan["sort_order"]); ?>">
                    </div>

                    <div class="form-field full">
                        <label for="server_software_note">対応ソフト・備考</label>
                        <textarea id="server_software_note" name="server_software_note" rows="3"><?php echo h((string)$plan["server_software_note"]); ?></textarea>
                    </div>
                </div>

                <section class="form-section">
                    <div class="form-section-head">
                        <h3>利用可能Node</h3>
                        <p>このプランで作成できるPterodactyl Nodeを選択します。複数選択できます。</p>
                    </div>

                    <?php if (!$nodes): ?>
                        <div class="plans-alert">
                            <p>利用可能なNodeが登録されていません。</p>
                        </div>
                    <?php else: ?>
                        <div class="node-check-grid">
                            <?php foreach ($nodes as $node): ?>
                                <?php $checked = in_array((int)$node["id"], $selectedNodeIds, true); ?>
                                <label class="node-check-card <?php echo !empty($node["is_high_performance"]) ? "high-performance" : ""; ?>">
                                    <input
                                        type="checkbox"
                                        name="node_ids[]"
                                        value="<?php echo h((string)$node["id"]); ?>"
                                        <?php echo $checked ? "checked" : ""; ?>
                                    >

                                    <span class="node-check-main">
                                        <strong><?php echo h($node["name"]); ?></strong>
                                        <small><?php echo h($node["label"]); ?></small>
                                    </span>

                                    <span class="node-check-meta">
                                        <?php echo h((string)$node["cpu_type"]); ?>
                                        / Ptero Node ID: <?php echo h((string)$node["ptero_node_id"]); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="form-section">
                    <div class="form-section-head">
                        <h3>Pterodactyl設定</h3>
                        <p>サーバー作成時に使うNest / Egg / Docker Image / Startup Commandです。未確定なら空欄でOKです。</p>
                    </div>

                    <div class="edit-form-grid">
                        <div class="form-field">
                            <label for="ptero_nest_id">Nest ID</label>
                            <input id="ptero_nest_id" name="ptero_nest_id" type="number" min="1" value="<?php echo h((string)$plan["ptero_nest_id"]); ?>">
                        </div>

                        <div class="form-field">
                            <label for="ptero_egg_id">Egg ID</label>
                            <input id="ptero_egg_id" name="ptero_egg_id" type="number" min="1" value="<?php echo h((string)$plan["ptero_egg_id"]); ?>">
                        </div>

                        <div class="form-field full">
                            <label for="ptero_docker_image">Docker Image</label>
                            <input id="ptero_docker_image" name="ptero_docker_image" type="text" value="<?php echo h((string)$plan["ptero_docker_image"]); ?>">
                        </div>

                        <div class="form-field full">
                            <label for="ptero_startup_command">Startup Command</label>
                            <textarea id="ptero_startup_command" name="ptero_startup_command" rows="4"><?php echo h((string)$plan["ptero_startup_command"]); ?></textarea>
                        </div>
                    </div>
                </section>

                <div class="form-submit-row">
                    <a href="/admin/game-plans/" class="back-button">キャンセル</a>
                    <button type="submit" class="create-button">保存する</button>
                </div>

            </form>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>