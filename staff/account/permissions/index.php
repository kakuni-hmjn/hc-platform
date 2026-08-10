<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/components/layout.php';
require_once dirname(__DIR__, 2) . '/components/ui.php';

staff_require_permission($staffContext, 'staff.dashboard.view');

$permissionKeys = array_values(array_unique(array_map('strval', (array) ($staffContext['permissions'] ?? []))));
sort($permissionKeys);
$permissionDetails = [];
if ($permissionKeys !== []) {
    try {
        $placeholders = implode(',', array_fill(0, count($permissionKeys), '?'));
        $statement = staff_db()->prepare("SELECT permission_key, name FROM permissions WHERE permission_key IN ($placeholders)");
        $statement->execute($permissionKeys);
        foreach ($statement->fetchAll() ?: [] as $row) {
            $permissionDetails[(string) $row['permission_key']] = (string) $row['name'];
        }
    } catch (Throwable $exception) {
        $permissionDetails = [];
    }
}

$groups = [];
foreach ($permissionKeys as $permissionKey) {
    $prefix = explode('.', $permissionKey, 2)[0] ?: 'other';
    $groups[$prefix][] = $permissionKey;
}
$groupLabels = [
    'staff' => 'スタッフコンソール', 'tasks' => 'タスク', 'announcements' => '社内連絡',
    'orders' => '注文・契約', 'support' => 'お問い合わせ', 'billing' => '決済・請求',
    'development' => '開発・デプロイ', 'infrastructure' => 'インフラ', 'audit' => '操作ログ',
    'system' => 'システム', 'users' => '顧客',
];

staff_layout_start([
    'title' => '有効権限', 'heading' => '有効権限', 'eyebrow' => 'MY ACCESS',
    'description' => '現在のHCアカウントに付与されているロール、部署、担当カテゴリ、操作権限を確認します。',
]);
?>
<div class="ops-page">
    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">ロール</span><strong><?= number_format(count((array) ($staffContext['roles'] ?? []))) ?></strong><small>アカウントへ付与</small></article><article class="ops-card"><span class="ops-card__label">有効権限</span><strong><?= number_format(count($permissionKeys)) ?></strong><small>実行できる操作</small></article><article class="ops-card"><span class="ops-card__label">部署</span><strong><?= number_format(count((array) ($staffContext['departments'] ?? []))) ?></strong><small>所属部署</small></article><article class="ops-card"><span class="ops-card__label">担当カテゴリ</span><strong><?= number_format(count((array) ($staffContext['categories'] ?? []))) ?></strong><small>担当サービス範囲</small></article></section>
    <div class="ops-split">
        <section class="ops-list"><header class="ops-panel__header"><div><h3>ロールと所属</h3><p>権限の付与元を確認できます。</p></div></header><div class="ops-panel__body"><div class="ops-section"><h4>スタッフロール</h4><div class="ops-rows"><?php foreach ((array) ($staffContext['roles'] ?? []) as $role): ?><article class="ops-row"><span><strong class="ops-row__title"><?= staff_ui_escape($role['name'] ?? $role['slug'] ?? 'ロール') ?></strong><span class="ops-row__meta"><?= staff_ui_escape($role['description'] ?? '') ?><br><?= staff_ui_escape($role['slug'] ?? '') ?></span></span><?php if (!empty($role['is_system'])): ?><span class="ops-status ops-status--active">システム</span><?php endif; ?></article><?php endforeach; ?><?php if (($staffContext['roles'] ?? []) === []): ?><div class="ops-empty">ロールが付与されていません。</div><?php endif; ?></div></div><div class="ops-section"><h4>所属部署</h4><div class="ops-chip-list"><?php foreach ((array) ($staffContext['departments'] ?? []) as $department): ?><span class="ops-chip"><?= staff_ui_escape($department['name'] ?? $department['slug'] ?? '') ?><?= !empty($department['is_primary']) ? '（主所属）' : '' ?></span><?php endforeach; ?><?php if (($staffContext['departments'] ?? []) === []): ?><span class="ops-muted">未設定</span><?php endif; ?></div></div><div class="ops-section"><h4>担当カテゴリ</h4><div class="ops-chip-list"><?php foreach ((array) ($staffContext['categories'] ?? []) as $category): ?><span class="ops-chip"><?= staff_ui_escape($category['name'] ?? $category['slug'] ?? '') ?></span><?php endforeach; ?><?php if (($staffContext['categories'] ?? []) === []): ?><span class="ops-muted">未設定</span><?php endif; ?></div></div></div></section>
        <section class="ops-detail"><header class="ops-panel__header"><div><h3>操作権限</h3><p><?= number_format(count($permissionKeys)) ?>件の権限が有効です。</p></div></header><div class="ops-panel__body"><?php foreach ($groups as $prefix => $keys): ?><div class="ops-section"><h4><?= staff_ui_escape($groupLabels[$prefix] ?? strtoupper($prefix)) ?> <span class="ops-muted">(<?= number_format(count($keys)) ?>)</span></h4><div class="ops-permission-grid"><?php foreach ($keys as $key): ?><div class="ops-permission"><span class="material-icons">check_circle</span><span><strong><?= staff_ui_escape($permissionDetails[$key] ?? $key) ?></strong><small><?= staff_ui_escape($key) ?></small></span></div><?php endforeach; ?></div></div><?php endforeach; ?><?php if ($permissionKeys === []): ?><div class="ops-empty">有効な操作権限がありません。管理者へ確認してください。</div><?php endif; ?></div></section>
    </div>
    <?php if (staff_has_permission($staffContext, 'staff.users.view') || staff_can_access_admin($staffContext)): ?><div class="ops-form-actions"><a class="ops-button ops-button--secondary" href="/staff/admin/users/">スタッフ管理でロールを確認</a></div><?php endif; ?>
</div>
<?php staff_layout_end();
