<?php
session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/stripe.php";

$currentUser = current_user();

if (!$currentUser) {
    header("Location: /login/?redirect=/order/game-server/");
    exit;
}

$pageTitle = "ゲームサーバー申込 | HC Platform";
$pageDescription = "HC Platformのゲームサーバーレンタル申込ページです。";
$pageCss = "/order/game-server/order.css";

$pdo = db();

$errors = [];
$plans = [];

$form = [
    "plan_id" => isset($_GET["plan_id"]) ? (int)$_GET["plan_id"] : 0,
    "server_name" => "",
    "minecraft_type" => "java",
    "server_software" => "paper",
    "minecraft_version" => "latest",
    "player_count_estimate" => "",
    "billing_type" => "auto_subscription",
    "note" => "",
];

function format_mb_to_gb(int $mb): string
{
    if ($mb <= 0) {
        return "0GB";
    }

    $gb = $mb / 1024;

    if (floor($gb) == $gb) {
        return (string)(int)$gb . "GB";
    }

    return number_format($gb, 1) . "GB";
}

function format_cpu_to_vcpu(int $cpuLimit): string
{
    if ($cpuLimit <= 0) {
        return "無制限";
    }

    $vcpu = $cpuLimit / 100;

    if (floor($vcpu) == $vcpu) {
        return (string)(int)$vcpu . "vCPU";
    }

    return number_format($vcpu, 1) . "vCPU";
}

function get_current_user_id(array $user): int
{
    return (int)($user["id"] ?? 0);
}

try {
    $stmt = $pdo->query("
        SELECT
            gsp.id,
            gsp.name,
            gsp.slug,
            gsp.description,
            gsp.price_monthly,
            gsp.memory_mb,
            gsp.cpu_limit,
            gsp.disk_mb,
            gsp.backup_limit,
            gsp.database_limit,
            gsp.allocation_limit,
            gsp.server_software_note,
            gsp.sort_order,

            COALESCE(
                json_agg(
                    json_build_object(
                        'id', pn.id,
                        'name', pn.name,
                        'label', pn.label,
                        'cpu_type', pn.cpu_type,
                        'is_high_performance', pn.is_high_performance,
                        'is_primary', gspn.is_primary
                    )
                    ORDER BY gspn.is_primary DESC, pn.sort_order ASC, pn.id ASC
                ) FILTER (WHERE pn.id IS NOT NULL),
                '[]'
            ) AS nodes
        FROM game_server_plans gsp
        LEFT JOIN game_server_plan_nodes gspn ON gspn.plan_id = gsp.id
        LEFT JOIN ptero_nodes pn ON pn.id = gspn.node_id
        WHERE gsp.status = 'published'
        GROUP BY gsp.id
        ORDER BY gsp.sort_order ASC, gsp.id ASC
    ");

    $plans = $stmt->fetchAll();
} catch (Throwable $e) {
    $errors[] = "プラン情報の取得中にエラーが発生しました。";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $form["plan_id"] = (int)($_POST["plan_id"] ?? 0);
    $form["server_name"] = trim($_POST["server_name"] ?? "");
    $form["minecraft_type"] = $_POST["minecraft_type"] ?? "java";
    $form["server_software"] = $_POST["server_software"] ?? "paper";
    $form["minecraft_version"] = trim($_POST["minecraft_version"] ?? "latest");
    $form["player_count_estimate"] = trim($_POST["player_count_estimate"] ?? "");
    $form["billing_type"] = $_POST["billing_type"] ?? "auto_subscription";
    $form["note"] = trim($_POST["note"] ?? "");

    $selectedPlan = null;

    foreach ($plans as $plan) {
        if ((int)$plan["id"] === $form["plan_id"]) {
            $selectedPlan = $plan;
            break;
        }
    }

    if (!$selectedPlan) {
        $errors[] = "プランを選択してください。";
    }

    if ($form["server_name"] === "") {
        $errors[] = "サーバー名を入力してください。";
    } elseif (mb_strlen($form["server_name"]) > 100) {
        $errors[] = "サーバー名は100文字以内で入力してください。";
    }

    if (!in_array($form["minecraft_type"], ["java", "bedrock", "crossplay", "other"], true)) {
        $errors[] = "Minecraft種別が不正です。";
    }

    if (!in_array($form["server_software"], ["paper", "purpur", "spigot", "fabric", "forge", "neoforge", "vanilla", "geyser", "other"], true)) {
        $errors[] = "サーバーソフトが不正です。";
    }

    if (!in_array($form["billing_type"], ["auto_subscription", "manual_renewal"], true)) {
        $errors[] = "支払いタイプが不正です。";
    }

    $playerCount = null;

    if ($form["player_count_estimate"] !== "") {
        $playerCount = (int)$form["player_count_estimate"];

        if ($playerCount < 1) {
            $errors[] = "想定人数は1以上で入力してください。";
        }
    }

    if (!$errors && $selectedPlan) {
        try {
            $pdo->beginTransaction();

            $nodeStmt = $pdo->prepare("
                SELECT
                    pn.id
                FROM game_server_plan_nodes gspn
                JOIN ptero_nodes pn ON pn.id = gspn.node_id
                WHERE gspn.plan_id = :plan_id
                AND pn.status = 'active'
                ORDER BY gspn.is_primary DESC, pn.sort_order ASC, pn.id ASC
                LIMIT 1
            ");
            $nodeStmt->execute([
                "plan_id" => $form["plan_id"],
            ]);

            $selectedNodeId = $nodeStmt->fetchColumn();

            if (!$selectedNodeId) {
                throw new RuntimeException("このプランで利用可能なNodeが設定されていません。");
            }

            $insertStmt = $pdo->prepare("
                INSERT INTO game_server_orders (
                    user_id,
                    plan_id,
                    server_name,
                    minecraft_type,
                    server_software,
                    minecraft_version,
                    player_count_estimate,
                    note,
                    selected_node_id,
                    billing_type,
                    billing_period,
                    status,
                    payment_status,
                    amount,
                    currency,
                    created_at
                ) VALUES (
                    :user_id,
                    :plan_id,
                    :server_name,
                    :minecraft_type,
                    :server_software,
                    :minecraft_version,
                    :player_count_estimate,
                    :note,
                    :selected_node_id,
                    :billing_type,
                    'monthly',
                    'pending_payment',
                    'unpaid',
                    :amount,
                    'jpy',
                    NOW()
                )
            ");

            $insertStmt->execute([
                "user_id" => get_current_user_id($currentUser),
                "plan_id" => $form["plan_id"],
                "server_name" => $form["server_name"],
                "minecraft_type" => $form["minecraft_type"],
                "server_software" => $form["server_software"],
                "minecraft_version" => $form["minecraft_version"] !== "" ? $form["minecraft_version"] : null,
                "player_count_estimate" => $playerCount,
                "note" => $form["note"] !== "" ? $form["note"] : null,
                "selected_node_id" => (int)$selectedNodeId,
                "billing_type" => $form["billing_type"],
                "amount" => (int)$selectedPlan["price_monthly"],
            ]);

            $orderId = (int)$pdo->lastInsertId();

            $order = [
                "id" => $orderId,
                "user_id" => get_current_user_id($currentUser),
                "plan_id" => (int)$form["plan_id"],
            ];

            $checkout = stripe_create_checkout_session($order, $selectedPlan, $form["billing_type"]);

            if (empty($checkout["ok"])) {
                throw new RuntimeException($checkout["error"] ?? "Stripe Checkoutの作成に失敗しました。");
            }

            $updateStmt = $pdo->prepare("
                UPDATE game_server_orders
                SET
                    stripe_checkout_session_id = :stripe_checkout_session_id,
                    payment_status = 'checkout_created',
                    updated_at = NOW()
                WHERE id = :id
            ");

            $updateStmt->execute([
                "id" => $orderId,
                "stripe_checkout_session_id" => $checkout["checkout_session_id"],
            ]);

            $pdo->commit();

            header("Location: " . $checkout["url"]);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="order-page">

    <section class="order-hero">
        <div class="container order-hero-grid">

            <div class="order-copy reveal">
                <p class="eyebrow">Order / Game Server</p>
                <h1>ゲームサーバー申込</h1>
                <p>
                    利用したいプラン、サーバー構成、支払いタイプを選択して申し込みます。
                    決済完了後、Pterodactyl Panel上にサーバーを自動作成する流れを整備しています。
                </p>
            </div>

            <aside class="order-status-card reveal">
                <span>Stripe Checkout</span>
                <h2>自動更新・手動更新に対応</h2>
                <p>
                    毎月自動更新、または1ヶ月ごとの手動更新を選択できます。
                </p>
            </aside>

        </div>
    </section>

    <section class="section order-section">
        <div class="container">

            <form action="/order/game-server/" method="post" class="order-panel reveal">

                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Server Order</p>
                        <h2>申込内容</h2>
                    </div>

                    <a href="/services/rental/game-server/" class="back-button">プランページへ戻る</a>
                </div>

                <?php if ($errors): ?>
                    <div class="order-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <section class="form-section">
                    <div class="form-section-head">
                        <h3>プラン選択</h3>
                        <p>公開中のゲームサーバープランから選択してください。</p>
                    </div>

                    <?php if (!$plans): ?>
                        <div class="order-alert">
                            <p>現在選択できるプランがありません。</p>
                        </div>
                    <?php else: ?>
                        <div class="plan-select-grid">
                            <?php foreach ($plans as $plan): ?>
                                <?php
                                    $checked = (int)$form["plan_id"] === (int)$plan["id"];
                                    $planNodes = [];

                                    if (!empty($plan["nodes"])) {
                                        $decodedNodes = json_decode($plan["nodes"], true);
                                        if (is_array($decodedNodes)) {
                                            $planNodes = $decodedNodes;
                                        }
                                    }
                                ?>

                                <label class="plan-select-card">
                                    <input
                                        type="radio"
                                        name="plan_id"
                                        value="<?php echo h((string)$plan["id"]); ?>"
                                        <?php echo $checked ? "checked" : ""; ?>
                                        required
                                    >

                                    <span class="plan-name"><?php echo h($plan["name"]); ?></span>

                                    <strong>
                                        ¥<?php echo h(number_format((int)$plan["price_monthly"])); ?> / 月
                                    </strong>

                                    <small>
                                        <?php echo h(format_mb_to_gb((int)$plan["memory_mb"])); ?>
                                        /
                                        <?php echo h(format_cpu_to_vcpu((int)$plan["cpu_limit"])); ?>
                                        /
                                        Disk <?php echo h(format_mb_to_gb((int)$plan["disk_mb"])); ?>
                                    </small>

                                    <?php if ($planNodes): ?>
                                        <span class="node-labels">
                                            <?php foreach ($planNodes as $node): ?>
                                                <b class="<?php echo !empty($node["is_high_performance"]) ? "high-performance" : ""; ?>">
                                                    <?php echo h((string)$node["label"]); ?>
                                                </b>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="form-section">
                    <div class="form-section-head">
                        <h3>サーバー情報</h3>
                        <p>Minecraftサーバーの基本情報を入力してください。</p>
                    </div>

                    <div class="order-form-grid">
                        <div class="form-field">
                            <label for="server_name">サーバー名</label>
                            <input id="server_name" name="server_name" type="text" value="<?php echo h($form["server_name"]); ?>" required>
                        </div>

                        <div class="form-field">
                            <label for="minecraft_type">Minecraft種別</label>
                            <select id="minecraft_type" name="minecraft_type">
                                <option value="java" <?php echo $form["minecraft_type"] === "java" ? "selected" : ""; ?>>Java Edition</option>
                                <option value="bedrock" <?php echo $form["minecraft_type"] === "bedrock" ? "selected" : ""; ?>>Bedrock Edition</option>
                                <option value="crossplay" <?php echo $form["minecraft_type"] === "crossplay" ? "selected" : ""; ?>>Java + BE クロスプレイ</option>
                                <option value="other" <?php echo $form["minecraft_type"] === "other" ? "selected" : ""; ?>>その他相談</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="server_software">サーバーソフト</label>
                            <select id="server_software" name="server_software">
                                <option value="paper" <?php echo $form["server_software"] === "paper" ? "selected" : ""; ?>>Paper</option>
                                <option value="purpur" <?php echo $form["server_software"] === "purpur" ? "selected" : ""; ?>>Purpur</option>
                                <option value="spigot" <?php echo $form["server_software"] === "spigot" ? "selected" : ""; ?>>Spigot</option>
                                <option value="fabric" <?php echo $form["server_software"] === "fabric" ? "selected" : ""; ?>>Fabric</option>
                                <option value="forge" <?php echo $form["server_software"] === "forge" ? "selected" : ""; ?>>Forge</option>
                                <option value="neoforge" <?php echo $form["server_software"] === "neoforge" ? "selected" : ""; ?>>NeoForge</option>
                                <option value="vanilla" <?php echo $form["server_software"] === "vanilla" ? "selected" : ""; ?>>Vanilla</option>
                                <option value="geyser" <?php echo $form["server_software"] === "geyser" ? "selected" : ""; ?>>Geyser構成</option>
                                <option value="other" <?php echo $form["server_software"] === "other" ? "selected" : ""; ?>>その他相談</option>
                            </select>
                        </div>

                        <div class="form-field">
                            <label for="minecraft_version">バージョン</label>
                            <input id="minecraft_version" name="minecraft_version" type="text" value="<?php echo h($form["minecraft_version"]); ?>" placeholder="latest / 1.21.5 など">
                        </div>

                        <div class="form-field">
                            <label for="player_count_estimate">想定人数</label>
                            <input id="player_count_estimate" name="player_count_estimate" type="number" min="1" value="<?php echo h((string)$form["player_count_estimate"]); ?>">
                        </div>

                        <div class="form-field full">
                            <label for="note">備考</label>
                            <textarea id="note" name="note" rows="4" placeholder="MOD構成、プラグイン、希望内容など"><?php echo h($form["note"]); ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <div class="form-section-head">
                        <h3>支払いタイプ</h3>
                        <p>毎月自動更新か、1ヶ月ごとの手動更新を選択できます。</p>
                    </div>

                    <div class="billing-grid">
                        <label class="billing-card recommended">
                            <input
                                type="radio"
                                name="billing_type"
                                value="auto_subscription"
                                <?php echo $form["billing_type"] === "auto_subscription" ? "checked" : ""; ?>
                            >

                            <span>おすすめ</span>
                            <strong>毎月自動更新</strong>
                            <p>
                                カード、Apple Pay、Google Payなどで毎月自動更新します。
                                支払い忘れを防ぎたい人向けです。
                            </p>
                        </label>

                        <label class="billing-card">
                            <input
                                type="radio"
                                name="billing_type"
                                value="manual_renewal"
                                <?php echo $form["billing_type"] === "manual_renewal" ? "checked" : ""; ?>
                            >

                            <span>PayPay向け</span>
                            <strong>1ヶ月ごとの手動更新</strong>
                            <p>
                                1ヶ月分をその都度支払う方式です。
                                PayPayなどの単発決済に対応しやすい方式です。
                            </p>
                        </label>
                    </div>
                </section>

                <div class="submit-row">
                    <a href="/services/rental/game-server/" class="back-button">キャンセル</a>
                    <button type="submit" class="submit-button">決済へ進む</button>
                </div>

            </form>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>