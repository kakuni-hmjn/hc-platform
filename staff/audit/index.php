<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/operations.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'audit.logs.view');

$search = trim((string) ($_GET['q'] ?? ''));
$resultFilter = trim((string) ($_GET['result'] ?? ''));
$sourceFilter = trim((string) ($_GET['source'] ?? ''));
$selectedId = max(0, (int) ($_GET['id'] ?? 0));
$validResults = ['success', 'failed', 'denied', 'pending'];
$validSources = ['web', 'api', 'discord', 'system'];
if (!in_array($resultFilter, $validResults, true)) {
    $resultFilter = '';
}
if (!in_array($sourceFilter, $validSources, true)) {
    $sourceFilter = '';
}

$counts = ['today' => 0, 'success' => 0, 'failed' => 0, 'denied' => 0];
$logs = [];
$selected = null;
$error = '';
try {
    $row = staff_db()->query(
        "SELECT COUNT(*) FILTER (WHERE created_at >= CURRENT_DATE) AS today,
                COUNT(*) FILTER (WHERE result = 'success' AND created_at >= CURRENT_DATE - INTERVAL '30 days') AS success,
                COUNT(*) FILTER (WHERE result = 'failed' AND created_at >= CURRENT_DATE - INTERVAL '30 days') AS failed,
                COUNT(*) FILTER (WHERE result = 'denied' AND created_at >= CURRENT_DATE - INTERVAL '30 days') AS denied
         FROM staff_audit_logs"
    )->fetch();
    if (is_array($row)) {
        foreach ($counts as $key => $value) {
            $counts[$key] = (int) ($row[$key] ?? 0);
        }
    }

    $where = ['1 = 1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(l.action ILIKE :search OR l.description ILIKE :search OR l.target_type ILIKE :search OR l.target_id ILIKE :search OR COALESCE(su.display_name, u.username, u.email) ILIKE :search)';
        $params['search'] = staff_ops_like($search);
    }
    if ($resultFilter !== '') {
        $where[] = 'l.result = :result';
        $params['result'] = $resultFilter;
    }
    if ($sourceFilter !== '') {
        $where[] = 'l.source = :source';
        $params['source'] = $sourceFilter;
    }
    $statement = staff_db()->prepare(
        'SELECT l.*, COALESCE(su.display_name, u.username, u.email, \'システム\') AS actor_name
         FROM staff_audit_logs l
         LEFT JOIN staff_users su ON su.id = l.actor_staff_id
         LEFT JOIN users u ON u.id = su.account_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY l.created_at DESC, l.id DESC LIMIT 200'
    );
    $statement->execute($params);
    $logs = $statement->fetchAll() ?: [];
    if ($selectedId <= 0 && $logs !== []) {
        $selectedId = (int) ($logs[0]['id'] ?? 0);
    }
    foreach ($logs as $log) {
        if ((int) ($log['id'] ?? 0) === $selectedId) {
            $selected = $log;
            break;
        }
    }
    if ($selected === null && $selectedId > 0) {
        $statement = staff_db()->prepare(
            'SELECT l.*, COALESCE(su.display_name, u.username, u.email, \'システム\') AS actor_name
             FROM staff_audit_logs l
             LEFT JOIN staff_users su ON su.id = l.actor_staff_id
             LEFT JOIN users u ON u.id = su.account_id WHERE l.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $selectedId]);
        $row = $statement->fetch();
        $selected = is_array($row) ? $row : null;
    }
} catch (Throwable $exception) {
    $error = '操作ログを取得できませんでした。';
}

function staff_audit_json(mixed $value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '記録なし';
    }
    $decoded = json_decode((string) $value, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return (string) $value;
    }
    return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

$resultLabels = ['success' => '成功', 'failed' => '失敗', 'denied' => '拒否', 'pending' => '保留'];
$sourceLabels = ['web' => 'Web', 'api' => 'API', 'discord' => 'Discord', 'system' => 'システム'];
$baseParams = array_filter(['q' => $search, 'result' => $resultFilter, 'source' => $sourceFilter], static fn ($value): bool => $value !== '');

staff_layout_start([
    'title' => '操作ログ', 'heading' => '操作ログ', 'eyebrow' => 'AUDIT LOG',
    'description' => 'スタッフ操作と自動処理の実行結果を追跡します。機密値は記録せず、変更の流れを確認できます。',
]);
?>
<div class="ops-page">
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">本日</span><strong><?= number_format($counts['today']) ?></strong><small>今日の操作</small></article><article class="ops-card"><span class="ops-card__label">成功</span><strong><?= number_format($counts['success']) ?></strong><small>直近30日</small></article><article class="ops-card"><span class="ops-card__label">失敗</span><strong><?= number_format($counts['failed']) ?></strong><small>直近30日</small></article><article class="ops-card"><span class="ops-card__label">拒否</span><strong><?= number_format($counts['denied']) ?></strong><small>権限等で拒否</small></article></section>
    <form class="ops-toolbar" method="get"><label class="ops-toolbar__field"><span class="ops-label">検索</span><input class="ops-input" type="search" name="q" value="<?= staff_ui_escape($search) ?>" placeholder="操作・対象・担当者"></label><label class="ops-toolbar__field"><span class="ops-label">結果</span><select class="ops-select" name="result"><option value="">すべて</option><?php foreach ($resultLabels as $value => $label): ?><option value="<?= $value ?>" <?= $resultFilter === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="ops-toolbar__field"><span class="ops-label">経路</span><select class="ops-select" name="source"><option value="">すべて</option><?php foreach ($sourceLabels as $value => $label): ?><option value="<?= $value ?>" <?= $sourceFilter === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="ops-button" type="submit">検索</button><a class="ops-button ops-button--secondary" href="/staff/audit/">クリア</a></form>
    <div class="ops-split">
        <section class="ops-list"><header class="ops-panel__header"><div><h3>ログ一覧</h3><p><?= number_format(count($logs)) ?>件を表示</p></div></header><div class="ops-rows"><?php foreach ($logs as $log): $params = $baseParams + ['id' => (int) $log['id']]; ?><a class="ops-row <?= (int) ($selected['id'] ?? 0) === (int) $log['id'] ? 'is-selected' : '' ?>" href="?<?= staff_ui_escape(http_build_query($params)) ?>"><span><strong class="ops-row__title"><?= staff_ui_escape($log['description'] ?: $log['action']) ?></strong><span class="ops-row__meta"><?= staff_ui_escape($log['actor_name']) ?> · <?= staff_ui_escape(staff_ops_datetime($log['created_at'] ?? null)) ?><br><?= staff_ui_escape($log['action']) ?></span></span><span class="ops-row__end"><span class="ops-status ops-status--<?= staff_ui_escape($log['result']) ?>"><?= staff_ui_escape($resultLabels[(string) $log['result']] ?? $log['result']) ?></span><span class="ops-row__meta"><?= staff_ui_escape($sourceLabels[(string) $log['source']] ?? $log['source']) ?></span></span></a><?php endforeach; ?><?php if ($logs === []): ?><div class="ops-empty">条件に一致する操作ログはありません。</div><?php endif; ?></div></section>
        <section class="ops-detail"><?php if (is_array($selected)): ?><header class="ops-panel__header"><div><h3>操作 #<?= (int) $selected['id'] ?></h3><p><?= staff_ui_escape(staff_ops_datetime($selected['created_at'] ?? null)) ?></p></div><span class="ops-status ops-status--<?= staff_ui_escape($selected['result']) ?>"><?= staff_ui_escape($resultLabels[(string) $selected['result']] ?? $selected['result']) ?></span></header><div class="ops-panel__body"><dl class="ops-kv"><div><dt>操作</dt><dd><?= staff_ui_escape($selected['action']) ?></dd></div><div><dt>実行者</dt><dd><?= staff_ui_escape($selected['actor_name']) ?></dd></div><div><dt>対象</dt><dd><?= staff_ui_escape(($selected['target_type'] ?: '-') . ($selected['target_id'] ? ' #' . $selected['target_id'] : '')) ?></dd></div><div><dt>経路</dt><dd><?= staff_ui_escape($sourceLabels[(string) $selected['source']] ?? $selected['source']) ?></dd></div><div><dt>IPアドレス</dt><dd><?= staff_ui_escape($selected['ip_address'] ?: '記録なし') ?></dd></div><div><dt>承認者ID</dt><dd><?= staff_ui_escape($selected['approved_by'] ?: 'なし') ?></dd></div></dl><div class="ops-section"><h4>説明</h4><div class="ops-prose"><?= nl2br(staff_ui_escape($selected['description'] ?: '説明はありません。')) ?></div></div><div class="ops-section"><h4>変更前</h4><pre class="ops-code"><?= staff_ui_escape(staff_audit_json($selected['old_data'] ?? null)) ?></pre></div><div class="ops-section"><h4>変更後</h4><pre class="ops-code"><?= staff_ui_escape(staff_audit_json($selected['new_data'] ?? null)) ?></pre></div></div><?php else: ?><div class="ops-empty">左の一覧から操作を選択してください。</div><?php endif; ?></section>
    </div>
</div>
<?php staff_layout_end();
