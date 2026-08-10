<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();

$statuses = ['draft' => '下書き', 'published' => '公開', 'hidden' => '非公開'];
$mode = (string) ($_GET['mode'] ?? 'list');
$planId = max(0, (int) ($_GET['id'] ?? 0));
$editing = $mode === 'edit';
$errors = [];
$plan = [
    'name' => '', 'slug' => '', 'description' => '', 'price_monthly' => 0,
    'memory_mb' => 2048, 'cpu_limit' => 100, 'disk_mb' => 10240,
    'backup_limit' => 1, 'database_limit' => 0, 'allocation_limit' => 1,
    'server_software_note' => '', 'ptero_nest_id' => '', 'ptero_egg_id' => '',
    'ptero_docker_image' => '', 'ptero_startup_command' => '',
    'status' => 'draft', 'sort_order' => 100,
];
$selectedNodeIds = [];

function staff_game_plan_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = (string) preg_replace('/[^a-z0-9\-]+/', '-', $value);
    $value = (string) preg_replace('/-+/', '-', $value);
    return trim($value, '-');
}

function staff_game_plan_nullable_int(mixed $value): ?int
{
    $value = trim((string) $value);
    return $value === '' ? null : (int) $value;
}

function staff_game_plan_nullable_string(mixed $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

try {
    $nodeStatement = $pdo->query("
        SELECT id, ptero_node_id, name, label, cpu_type, is_high_performance, status
        FROM ptero_nodes
        WHERE status IN ('active', 'maintenance')
        ORDER BY sort_order ASC, id ASC
    ");
    $nodes = $nodeStatement->fetchAll();
} catch (Throwable $exception) {
    $nodes = [];
    $errors[] = 'Node情報を取得できませんでした。';
}

if ($editing && $planId > 0) {
    try {
        $statement = $pdo->prepare('SELECT * FROM game_server_plans WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $planId]);
        $loadedPlan = $statement->fetch();
        if (!$loadedPlan) {
            throw new RuntimeException('指定されたプランが見つかりません。');
        }
        $plan = array_merge($plan, $loadedPlan);
        $statement = $pdo->prepare('SELECT node_id FROM game_server_plan_nodes WHERE plan_id = :plan_id ORDER BY is_primary DESC, id ASC');
        $statement->execute(['plan_id' => $planId]);
        $selectedNodeIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editing = true;
    $planId = max(0, (int) ($_POST['plan_id'] ?? 0));
    $plan = array_merge($plan, [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'slug' => staff_game_plan_slug((string) ($_POST['slug'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'price_monthly' => (int) ($_POST['price_monthly'] ?? 0),
        'memory_mb' => (int) ($_POST['memory_mb'] ?? 0),
        'cpu_limit' => (int) ($_POST['cpu_limit'] ?? 0),
        'disk_mb' => (int) ($_POST['disk_mb'] ?? 0),
        'backup_limit' => (int) ($_POST['backup_limit'] ?? 0),
        'database_limit' => (int) ($_POST['database_limit'] ?? 0),
        'allocation_limit' => (int) ($_POST['allocation_limit'] ?? 1),
        'server_software_note' => trim((string) ($_POST['server_software_note'] ?? '')),
        'ptero_nest_id' => trim((string) ($_POST['ptero_nest_id'] ?? '')),
        'ptero_egg_id' => trim((string) ($_POST['ptero_egg_id'] ?? '')),
        'ptero_docker_image' => trim((string) ($_POST['ptero_docker_image'] ?? '')),
        'ptero_startup_command' => trim((string) ($_POST['ptero_startup_command'] ?? '')),
        'status' => (string) ($_POST['status'] ?? 'draft'),
        'sort_order' => (int) ($_POST['sort_order'] ?? 100),
    ]);
    $selectedNodeIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['node_ids'] ?? [])))));

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = '操作の有効期限が切れました。もう一度お試しください。';
    }
    if ($plan['name'] === '') $errors[] = 'プラン名を入力してください。';
    if ($plan['slug'] === '') $errors[] = 'スラッグを入力してください。';
    if ($plan['description'] === '') $errors[] = '説明を入力してください。';
    if ($plan['price_monthly'] < 0) $errors[] = '月額料金が不正です。';
    if ($plan['memory_mb'] <= 0) $errors[] = 'メモリ容量を入力してください。';
    if ($plan['cpu_limit'] <= 0) $errors[] = 'CPU制限を入力してください。';
    if ($plan['disk_mb'] <= 0) $errors[] = 'ディスク容量を入力してください。';
    if (!isset($statuses[$plan['status']])) $errors[] = '公開状態が不正です。';
    if ($selectedNodeIds === []) $errors[] = '利用可能Nodeを1つ以上選択してください。';

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $values = [
                'name' => $plan['name'], 'slug' => $plan['slug'], 'description' => $plan['description'],
                'price_monthly' => $plan['price_monthly'], 'memory_mb' => $plan['memory_mb'],
                'cpu_limit' => $plan['cpu_limit'], 'disk_mb' => $plan['disk_mb'],
                'backup_limit' => $plan['backup_limit'], 'database_limit' => $plan['database_limit'],
                'allocation_limit' => $plan['allocation_limit'],
                'server_software_note' => staff_game_plan_nullable_string($plan['server_software_note']),
                'ptero_nest_id' => staff_game_plan_nullable_int($plan['ptero_nest_id']),
                'ptero_egg_id' => staff_game_plan_nullable_int($plan['ptero_egg_id']),
                'ptero_docker_image' => staff_game_plan_nullable_string($plan['ptero_docker_image']),
                'ptero_startup_command' => staff_game_plan_nullable_string($plan['ptero_startup_command']),
                'status' => $plan['status'], 'sort_order' => $plan['sort_order'],
            ];
            if ($planId > 0) {
                $values['id'] = $planId;
                $statement = $pdo->prepare("
                    UPDATE game_server_plans SET name=:name, slug=:slug, description=:description,
                        price_monthly=:price_monthly, memory_mb=:memory_mb, cpu_limit=:cpu_limit,
                        disk_mb=:disk_mb, backup_limit=:backup_limit, database_limit=:database_limit,
                        allocation_limit=:allocation_limit, server_software_note=:server_software_note,
                        ptero_nest_id=:ptero_nest_id, ptero_egg_id=:ptero_egg_id,
                        ptero_docker_image=:ptero_docker_image, ptero_startup_command=:ptero_startup_command,
                        status=:status, sort_order=:sort_order, updated_at=NOW() WHERE id=:id
                ");
                $statement->execute($values);
                $savedPlanId = $planId;
            } else {
                $statement = $pdo->prepare("
                    INSERT INTO game_server_plans (name, slug, description, price_monthly, memory_mb,
                        cpu_limit, disk_mb, backup_limit, database_limit, allocation_limit,
                        server_software_note, ptero_nest_id, ptero_egg_id, ptero_docker_image,
                        ptero_startup_command, status, sort_order, created_at)
                    VALUES (:name, :slug, :description, :price_monthly, :memory_mb, :cpu_limit,
                        :disk_mb, :backup_limit, :database_limit, :allocation_limit,
                        :server_software_note, :ptero_nest_id, :ptero_egg_id, :ptero_docker_image,
                        :ptero_startup_command, :status, :sort_order, NOW())
                ");
                $statement->execute($values);
                $savedPlanId = (int) $pdo->lastInsertId();
            }
            $statement = $pdo->prepare('DELETE FROM game_server_plan_nodes WHERE plan_id = :plan_id');
            $statement->execute(['plan_id' => $savedPlanId]);
            $statement = $pdo->prepare('INSERT INTO game_server_plan_nodes (plan_id, node_id, is_primary) VALUES (:plan_id, :node_id, CAST(:is_primary AS boolean))');
            foreach ($selectedNodeIds as $index => $nodeId) {
                $statement->execute(['plan_id' => $savedPlanId, 'node_id' => $nodeId, 'is_primary' => $index === 0 ? 'true' : 'false']);
            }
            $pdo->commit();
            staff_administration_flash('success', 'ゲームサーバープランを保存しました。');
            staff_administration_redirect('/staff/admin/services/game-plans/');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $exception instanceof PDOException && $exception->getCode() === '23505'
                ? '同じスラッグのプランがすでに存在します。' : '保存中にエラーが発生しました。';
        }
    }
}

try {
    $plans = $pdo->query("
        SELECT p.*,
            COUNT(DISTINCT o.id) FILTER (WHERE o.status NOT IN ('cancelled', 'expired')) AS active_contracts,
            COALESCE(STRING_AGG(DISTINCT COALESCE(n.label, n.name), ' / '), '未設定') AS node_labels
        FROM game_server_plans p
        LEFT JOIN game_server_orders o ON o.plan_id = p.id
        LEFT JOIN game_server_plan_nodes pn ON pn.plan_id = p.id
        LEFT JOIN ptero_nodes n ON n.id = pn.node_id
        GROUP BY p.id ORDER BY p.sort_order ASC, p.id ASC
    ")->fetchAll();
} catch (Throwable $exception) {
    $plans = [];
    $errors[] = 'プラン一覧を取得できませんでした。';
}
$flash = staff_administration_take_flash();

staff_layout_start([
    'title' => 'ゲームサーバープラン管理', 'heading' => 'ゲームサーバープラン管理',
    'eyebrow' => 'SERVICES / GAME SERVER PLANS',
    'description' => '価格、リソース、対応Node、パネル連携設定と公開状態をスタッフコンソールで管理します。',
]);
?>
<div class="ops-page admin-native-page">
    <?php if ($flash): ?><div class="ops-alert <?= $flash['type'] === 'success' ? 'ops-alert--success' : '' ?>"><?= staff_ui_escape($flash['message']) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endforeach; ?>

    <?php if ($editing): ?>
        <form method="post" class="ops-panel admin-native-form">
            <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
            <input type="hidden" name="plan_id" value="<?= $planId ?>">
            <header class="ops-panel__header"><div><h3><?= $planId > 0 ? 'プランを編集' : 'プランを追加' ?></h3><p>販売内容とサーバー作成時の設定を入力します。</p></div><a class="ops-button ops-button--secondary" href="/staff/admin/services/game-plans/">一覧へ戻る</a></header>
            <div class="ops-panel__body">
                <div class="ops-form-grid">
                    <label><span class="ops-label">プラン名</span><input class="ops-input" name="name" required value="<?= staff_ui_escape($plan['name']) ?>"></label>
                    <label><span class="ops-label">スラッグ</span><input class="ops-input" name="slug" required value="<?= staff_ui_escape($plan['slug']) ?>" placeholder="standard"></label>
                    <label><span class="ops-label">月額料金（円）</span><input class="ops-input" type="number" min="0" name="price_monthly" required value="<?= staff_ui_escape($plan['price_monthly']) ?>"></label>
                    <label><span class="ops-label">公開状態</span><select class="ops-select" name="status"><?php foreach ($statuses as $value => $label): ?><option value="<?= $value ?>" <?= $plan['status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
                    <label><span class="ops-label">メモリ（MB）</span><input class="ops-input" type="number" min="1" name="memory_mb" required value="<?= staff_ui_escape($plan['memory_mb']) ?>"></label>
                    <label><span class="ops-label">CPU（100 = 1vCPU）</span><input class="ops-input" type="number" min="1" name="cpu_limit" required value="<?= staff_ui_escape($plan['cpu_limit']) ?>"></label>
                    <label><span class="ops-label">ディスク（MB）</span><input class="ops-input" type="number" min="1" name="disk_mb" required value="<?= staff_ui_escape($plan['disk_mb']) ?>"></label>
                    <label><span class="ops-label">表示順</span><input class="ops-input" type="number" name="sort_order" value="<?= staff_ui_escape($plan['sort_order']) ?>"></label>
                    <label><span class="ops-label">バックアップ数</span><input class="ops-input" type="number" min="0" name="backup_limit" required value="<?= staff_ui_escape($plan['backup_limit']) ?>"></label>
                    <label><span class="ops-label">DB数</span><input class="ops-input" type="number" min="0" name="database_limit" required value="<?= staff_ui_escape($plan['database_limit']) ?>"></label>
                    <label><span class="ops-label">Allocation数</span><input class="ops-input" type="number" min="1" name="allocation_limit" required value="<?= staff_ui_escape($plan['allocation_limit']) ?>"></label>
                </div>
                <label class="admin-native-field"><span class="ops-label">説明</span><textarea class="ops-textarea" name="description" rows="4" required><?= staff_ui_escape($plan['description']) ?></textarea></label>
                <label class="admin-native-field"><span class="ops-label">対応ソフト・備考</span><textarea class="ops-textarea" name="server_software_note" rows="3"><?= staff_ui_escape($plan['server_software_note']) ?></textarea></label>

                <div class="ops-section"><h4>利用可能Node</h4><p class="ops-muted">先頭のNodeが優先Nodeとして保存されます。</p><div class="admin-plan-node-grid">
                    <?php foreach ($nodes as $node): ?><label class="ops-check admin-plan-node"><input type="checkbox" name="node_ids[]" value="<?= (int) $node['id'] ?>" <?= in_array((int) $node['id'], $selectedNodeIds, true) ? 'checked' : '' ?>><span><strong><?= staff_ui_escape($node['label'] ?: $node['name']) ?></strong><small><?= staff_ui_escape($node['cpu_type'] ?? '-') ?> · パネルNode #<?= (int) $node['ptero_node_id'] ?></small></span></label><?php endforeach; ?>
                    <?php if ($nodes === []): ?><div class="ops-empty">利用可能なNodeがありません。</div><?php endif; ?>
                </div></div>

                <div class="ops-section"><h4>ゲームサーバーパネル設定</h4><div class="ops-form-grid">
                    <label><span class="ops-label">Nest ID</span><input class="ops-input" type="number" min="1" name="ptero_nest_id" value="<?= staff_ui_escape($plan['ptero_nest_id']) ?>"></label>
                    <label><span class="ops-label">Egg ID</span><input class="ops-input" type="number" min="1" name="ptero_egg_id" value="<?= staff_ui_escape($plan['ptero_egg_id']) ?>"></label>
                    <label><span class="ops-label">Docker Image</span><input class="ops-input" name="ptero_docker_image" value="<?= staff_ui_escape($plan['ptero_docker_image']) ?>"></label>
                    <label><span class="ops-label">Startup Command</span><textarea class="ops-textarea" name="ptero_startup_command" rows="3"><?= staff_ui_escape($plan['ptero_startup_command']) ?></textarea></label>
                </div></div>
                <div class="ops-form-actions"><a class="ops-button ops-button--secondary" href="/staff/admin/services/game-plans/">キャンセル</a><button class="ops-button" type="submit"><?= staff_icon('save', '', 17) ?>保存する</button></div>
            </div>
        </form>
    <?php else: ?>
        <div class="ops-form-actions"><a class="ops-button" href="/staff/admin/services/game-plans/?mode=edit"><?= staff_icon('add_box', '', 17) ?>プランを追加</a><a class="ops-button ops-button--secondary" href="/staff/rental-server/game-server/contracts/">契約を確認</a></div>
        <section class="ops-panel"><header class="ops-panel__header"><div><h3>販売プラン</h3><p><?= number_format(count($plans)) ?>件</p></div></header><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>プラン</th><th>月額</th><th>リソース</th><th>Node</th><th>稼働契約</th><th>状態</th><th></th></tr></thead><tbody>
            <?php foreach ($plans as $row): ?><tr><td><strong><?= staff_ui_escape($row['name']) ?></strong><br><span class="ops-muted"><?= staff_ui_escape($row['slug']) ?></span></td><td>¥<?= number_format((int) $row['price_monthly']) ?></td><td><?= number_format((int) $row['memory_mb']) ?>MB / <?= number_format((int) $row['cpu_limit']) ?>%<br><span class="ops-muted"><?= number_format((int) $row['disk_mb']) ?>MB disk</span></td><td><?= staff_ui_escape($row['node_labels']) ?></td><td><?= number_format((int) $row['active_contracts']) ?>件</td><td><span class="ops-status ops-status--<?= staff_ui_escape($row['status']) ?>"><?= staff_ui_escape($statuses[$row['status']] ?? $row['status']) ?></span></td><td><a class="ops-button ops-button--secondary ops-button--compact" href="?mode=edit&amp;id=<?= (int) $row['id'] ?>"><?= staff_icon('edit_note', '', 16) ?>編集</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php if ($plans === []): ?><div class="ops-empty">プランはまだ登録されていません。</div><?php endif; ?></section>
    <?php endif; ?>
</div>
<?php staff_layout_end();
