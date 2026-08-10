<?php
session_start();

require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/permissions.php";
require_once __DIR__ . "/../../lib/pterodactyl.php";

$user = require_role("admin");

header('Location: /staff/rental-server/game-server/pterodactyl/' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 302);
exit;

$pageTitle = "ゲームサーバーパネル連携確認 | HC Platform";
$pageDescription = "HC Platformの管理者向けゲームサーバーパネル API接続確認ページです。";
$pageCss = "/admin/ptero/ptero.css";

$config = ptero_config();

$panelUrl = $config["panel_url"] ?? "";
$apiKey = $config["api_key"] ?? "";
$defaultNodeId = (int)($config["default_node_id"] ?? 0);
$defaultNestId = (int)($config["default_nest_id"] ?? 0);
$defaultEggId = (int)($config["default_egg_id"] ?? 0);

$nodesResult = null;
$nestsResult = null;
$eggsResult = null;

$nodes = [];
$nests = [];
$eggs = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $testType = $_POST["test_type"] ?? "";

    if ($testType === "nodes" || $testType === "all") {
        $nodesResult = ptero_get_nodes();

        if (!empty($nodesResult["ok"]) && !empty($nodesResult["data"]["data"])) {
            $nodes = $nodesResult["data"]["data"];
        }
    }

    if ($testType === "nests" || $testType === "all") {
        $nestsResult = ptero_get_nests();

        if (!empty($nestsResult["ok"]) && !empty($nestsResult["data"]["data"])) {
            $nests = $nestsResult["data"]["data"];
        }
    }

    if ($testType === "eggs" || $testType === "all") {
        $eggsResult = ptero_get_eggs($defaultNestId > 0 ? $defaultNestId : 1);

        if (!empty($eggsResult["ok"]) && !empty($eggsResult["data"]["data"])) {
            $eggs = $eggsResult["data"]["data"];
        }
    }
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="ptero-page">

    <section class="ptero-hero">
        <div class="container ptero-hero-grid">

            <div class="ptero-copy reveal">
                <p class="eyebrow">Admin / ゲームサーバーパネル</p>
                <h1>ゲームサーバーパネル連携確認</h1>
                <p>
                    HC Platformからゲームサーバーパネル PanelのApplication APIへ接続できるか確認します。
                    開発環境ではMock Modeでダミーデータを表示します。
                </p>
            </div>

            <aside class="ptero-status-card reveal">
                <span>管理者アクセス</span>
                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h(role_label($user["role"])); ?></p>
            </aside>

        </div>
    </section>

    <section class="section ptero-section">
        <div class="container">

            <div class="ptero-layout">

                <section class="ptero-panel reveal">
                    <div class="panel-head">
                        <div>
                            <p class="eyebrow">Connection</p>
                            <h2>接続設定</h2>
                        </div>

                        <a href="/admin/" class="back-button">管理者ページへ戻る</a>
                <a href="/admin/ptero/allocations/" class="sub-button">Allocation確認へ</a>
                    </div>

                    <div class="config-grid">
                        <article>
                            <span>パネル Enabled</span>
                            <strong><?php echo !empty($config["enabled"]) ? "有効" : "無効"; ?></strong>
                        </article>

                        <article>
                            <span>Mock Mode</span>
                            <strong><?php echo !empty($config["mock"]) ? "有効" : "無効"; ?></strong>
                        </article>

                        <article>
                            <span>パネルURL</span>
                            <strong><?php echo h($panelUrl !== "" ? $panelUrl : "未設定"); ?></strong>
                        </article>

                        <article>
                            <span>API Key</span>
                            <strong><?php echo h(ptero_mask_api_key($apiKey)); ?></strong>
                        </article>

                        <article>
                            <span>Default Node ID</span>
                            <strong><?php echo h((string)$defaultNodeId); ?></strong>
                        </article>

                        <article>
                            <span>Default Nest ID</span>
                            <strong><?php echo h((string)$defaultNestId); ?></strong>
                        </article>

                        <article>
                            <span>Default Egg ID</span>
                            <strong><?php echo h((string)$defaultEggId); ?></strong>
                        </article>
                    </div>

                    <?php if (!empty($config["mock"])): ?>
                        <div class="mock-notice">
                            <h3>Mock Mode</h3>
                            <p>
                                現在はモックモードです。ゲームサーバーパネル Panelには接続せず、
                                開発用のダミーNode / Nest / Eggを表示します。
                            </p>
                        </div>
                    <?php endif; ?>

                    <form action="/admin/ptero/" method="post" class="test-actions">
                        <button type="submit" name="test_type" value="nodes">Node取得テスト</button>
                        <button type="submit" name="test_type" value="nests">Nest取得テスト</button>
                        <button type="submit" name="test_type" value="eggs">Egg取得テスト</button>
                        <button type="submit" name="test_type" value="all">まとめて確認</button>
                    </form>

                    <?php foreach ([
                        "Node取得結果" => $nodesResult,
                        "Nest取得結果" => $nestsResult,
                        "Egg取得結果" => $eggsResult,
                    ] as $label => $result): ?>
                        <?php if ($result): ?>
                            <div class="<?php echo !empty($result["ok"]) ? "result-success" : "result-error"; ?>">
                                <h3><?php echo h($label); ?></h3>
                                <p>Status: <?php echo h((string)$result["status"]); ?></p>
                                <p>Mode: <?php echo !empty($result["mock"]) ? "Mock" : "Real API"; ?></p>

                                <?php if (!empty($result["error"])): ?>
                                    <p><?php echo h($result["error"]); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>

                <aside class="ptero-side reveal">
                    <h3>確認ポイント</h3>

                    <div class="side-list">
                        <div>
                            <span>Application API Key</span>
                            <p>ゲームサーバーパネルのClient APIキーではなく、Application APIキーが必要です。</p>
                        </div>

                        <div>
                            <span>開発環境</span>
                            <p>開発環境ではMock Modeを使い、実Panelには接続しません。</p>
                        </div>

                        <div>
                            <span>本番環境</span>
                            <p>本番ではPTERO_ENABLED=true、PTERO_MOCK=falseにします。</p>
                        </div>

                        <div>
                            <span>秘密情報</span>
                            <p>APIキーは必ず.envに保存し、GitHubには上げないようにします。</p>
                        </div>
                    </div>
                </aside>

            </div>

            <?php if ($nodes): ?>
                <section class="data-panel reveal">
                    <h2>Node一覧</h2>

                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名前</th>
                                    <th>FQDN</th>
                                    <th>Memory</th>
                                    <th>Disk</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($nodes as $node): ?>
                                    <?php $attr = $node["attributes"] ?? []; ?>
                                    <tr>
                                        <td><?php echo h((string)($attr["id"] ?? "")); ?></td>
                                        <td><?php echo h((string)($attr["name"] ?? "")); ?></td>
                                        <td><?php echo h((string)($attr["fqdn"] ?? "")); ?></td>
                                        <td><?php echo h((string)($attr["memory"] ?? "")); ?> MB</td>
                                        <td><?php echo h((string)($attr["disk"] ?? "")); ?> MB</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($nests): ?>
                <section class="data-panel reveal">
                    <h2>Nest一覧</h2>

                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名前</th>
                                    <th>説明</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($nests as $nest): ?>
                                    <?php $attr = $nest["attributes"] ?? []; ?>
                                    <tr>
                                        <td><?php echo h((string)($attr["id"] ?? "")); ?></td>
                                        <td><?php echo h((string)($attr["name"] ?? "")); ?></td>
                                        <td><?php echo h((string)($attr["description"] ?? "")); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($eggs): ?>
                <section class="data-panel reveal">
                    <h2>Egg一覧</h2>

                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名前</th>
                                    <th>Nest ID</th>
                                    <th>説明</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($eggs as $egg): ?>
                                    <?php $attr = $egg["attributes"] ?? []; ?>
                                    <tr>
                                        <td><?php echo h((string)($attr["id"] ?? "")); ?></td>
                                        <td><?php echo h((string)($attr["name"] ?? "")); ?></td>
                                        <td><?php echo h((string)($attr["nest"] ?? "")); ?></td>
                                        <td><?php echo h((string)($attr["description"] ?? "")); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
