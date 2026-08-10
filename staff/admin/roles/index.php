<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/operations.php';
require_once dirname(__DIR__, 3) . '/lib/csrf.php';
require_once dirname(__DIR__, 2) . '/components/layout.php';
require_once dirname(__DIR__, 2) . '/components/ui.php';

staff_require_permission($staffContext, 'staff.roles.manage');
$pdo = staff_db();
$selectedId = max(0, (int) ($_GET['id'] ?? 0));
$message = '';
$error = '';
$iconCatalog = [
    'badge' => 'スタッフ', 'verified_user' => '認証・所有者', 'admin_panel_settings' => '管理者',
    'manage_accounts' => 'マネージャー', 'support_agent' => 'サポート', 'receipt_long' => '注文',
    'approval' => '承認', 'code' => '開発', 'dns' => 'インフラ', 'visibility' => '閲覧',
    'schedule' => '臨時', 'groups' => 'チーム', 'payments' => '決済', 'inventory_2' => '物品',
];

function staff_role_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = (string) preg_replace('/[^a-z0-9_-]+/', '_', $value);
    $value = (string) preg_replace('/_+/', '_', $value);
    return trim($value, '_-');
}

function staff_role_redirect(int $roleId = 0): never
{
    header('Location: /staff/admin/roles/' . ($roleId > 0 ? '?id=' . $roleId : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = '操作の有効期限が切れました。もう一度お試しください。';
    } else {
        try {
            if ($action === 'create') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $slug = staff_role_slug((string) ($_POST['slug'] ?? $name));
                $description = trim((string) ($_POST['description'] ?? ''));
                $color = strtolower(trim((string) ($_POST['color'] ?? '#475569')));
                $icon = trim((string) ($_POST['icon'] ?? 'badge'));
                $priority = max(1, min(899, (int) ($_POST['priority'] ?? 500)));
                if ($name === '' || $slug === '') throw new InvalidArgumentException('ロール名と識別キーを入力してください。');
                if (!preg_match('/^#[0-9a-f]{6}$/', $color)) $color = '#475569';
                if (!isset($iconCatalog[$icon])) $icon = 'badge';
                $pdo->beginTransaction();
                $statement = $pdo->prepare(
                    "INSERT INTO account_roles (slug, name, role_type, description, priority, is_system, is_staff_role, color, icon, created_at, updated_at)
                     VALUES (:slug, :name, 'custom', :description, :priority, FALSE, TRUE, :color, :icon, NOW(), NOW()) RETURNING id"
                );
                $statement->execute(compact('slug', 'name', 'description', 'priority', 'color', 'icon'));
                $roleId = (int) $statement->fetchColumn();
                $mandatory = ['staff.access', 'staff.dashboard.view', 'page.staff.dashboard', 'page.staff.account'];
                $statement = $pdo->prepare(
                    'INSERT INTO role_permissions (role_id, permission_id)
                     SELECT :role_id, id FROM permissions WHERE permission_key = ANY(CAST(:keys AS TEXT[])) ON CONFLICT DO NOTHING'
                );
                $statement->execute(['role_id' => $roleId, 'keys' => '{' . implode(',', $mandatory) . '}']);
                $pdo->commit();
                staff_ops_audit((int) ($staffUser['id'] ?? 0), 'staff.role.create', 'account_role', $roleId, 'スタッフロールを作成しました。', null, ['slug' => $slug, 'name' => $name]);
                staff_role_redirect($roleId);
            }

            $roleId = max(0, (int) ($_POST['role_id'] ?? 0));
            $statement = $pdo->prepare('SELECT * FROM account_roles WHERE id = :id AND is_staff_role = TRUE LIMIT 1');
            $statement->execute(['id' => $roleId]);
            $targetRole = $statement->fetch();
            if (!is_array($targetRole)) throw new RuntimeException('ロールが見つかりません。');

            if ($action === 'delete') {
                if (in_array((string) $targetRole['slug'], ['owner', 'administrator'], true)) {
                    throw new DomainException('オーナーと管理者ロールは削除できません。');
                }
                $statement = $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = :id');
                $statement->execute(['id' => $roleId]);
                if ((int) $statement->fetchColumn() > 0) {
                    throw new DomainException('使用中のロールです。先にスタッフからロールを外してください。');
                }
                $pdo->prepare('DELETE FROM account_roles WHERE id = :id')->execute(['id' => $roleId]);
                staff_ops_audit((int) ($staffUser['id'] ?? 0), 'staff.role.delete', 'account_role', $roleId, 'スタッフロールを削除しました。', $targetRole, null);
                staff_role_redirect();
            }

            if ($action === 'update') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $color = strtolower(trim((string) ($_POST['color'] ?? '#475569')));
                $icon = trim((string) ($_POST['icon'] ?? 'badge'));
                $priority = max(1, min((string) $targetRole['slug'] === 'owner' ? 1000 : 899, (int) ($_POST['priority'] ?? 500)));
                if ($name === '') throw new InvalidArgumentException('ロール名を入力してください。');
                if (!preg_match('/^#[0-9a-f]{6}$/', $color)) $color = '#475569';
                if (!isset($iconCatalog[$icon])) $icon = 'badge';
                $permissionIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['permission_ids'] ?? [])))));
                if ($permissionIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
                    $statement = $pdo->prepare("SELECT id FROM permissions WHERE id IN ($placeholders)");
                    $statement->execute($permissionIds);
                    $permissionIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
                }
                $mandatoryKeys = ['staff.access', 'staff.dashboard.view', 'page.staff.dashboard', 'page.staff.account'];
                if (in_array((string) $targetRole['slug'], ['owner', 'administrator'], true)) {
                    $mandatoryKeys[] = 'staff.roles.manage';
                    $mandatoryKeys[] = 'staff.permissions.manage';
                    $mandatoryKeys[] = 'page.staff.admin.roles';
                    $mandatoryKeys[] = 'page.staff.admin.hub';
                }
                $statement = $pdo->prepare('SELECT id FROM permissions WHERE permission_key = ANY(CAST(:keys AS TEXT[]))');
                $statement->execute(['keys' => '{' . implode(',', $mandatoryKeys) . '}']);
                $permissionIds = array_values(array_unique(array_merge($permissionIds, array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN)))));

                $pdo->beginTransaction();
                $statement = $pdo->prepare('UPDATE account_roles SET name=:name, description=:description, priority=:priority, color=:color, icon=:icon, updated_at=NOW() WHERE id=:id');
                $statement->execute(compact('name', 'description', 'priority', 'color', 'icon') + ['id' => $roleId]);
                $pdo->prepare('DELETE FROM role_permissions WHERE role_id = :id')->execute(['id' => $roleId]);
                if ($permissionIds !== []) {
                    $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id) ON CONFLICT DO NOTHING');
                    foreach ($permissionIds as $permissionId) $insert->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
                $pdo->commit();
                staff_ops_audit((int) ($staffUser['id'] ?? 0), 'staff.role.update', 'account_role', $roleId, 'スタッフロールと権限を更新しました。', $targetRole, ['permission_ids' => $permissionIds]);
                staff_role_redirect($roleId);
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception->getCode() === '23505' ? '同じ識別キーのロールがすでにあります。' : 'ロールを保存できませんでした。';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception->getMessage();
        }
    }
}

$roles = $pdo->query(
    'SELECT r.*, COUNT(DISTINCT ur.user_id) AS member_count, COUNT(DISTINCT rp.permission_id) AS permission_count
     FROM account_roles r LEFT JOIN user_roles ur ON ur.role_id=r.id LEFT JOIN role_permissions rp ON rp.role_id=r.id
     WHERE r.is_staff_role=TRUE GROUP BY r.id ORDER BY r.priority DESC, r.id'
)->fetchAll() ?: [];
if ($selectedId <= 0 && $roles !== []) $selectedId = (int) $roles[0]['id'];
$selectedRole = null;
foreach ($roles as $role) if ((int) $role['id'] === $selectedId) $selectedRole = $role;
$permissions = $pdo->query('SELECT id, permission_key, name, description FROM permissions ORDER BY permission_key')->fetchAll() ?: [];
$selectedPermissionIds = [];
if (is_array($selectedRole)) {
    $statement = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id=:id');
    $statement->execute(['id' => $selectedId]);
    $selectedPermissionIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}
$pageRegistry = staff_page_access_registry();
$pagePermissionGroups = [];
$actionPermissions = [];
foreach ($permissions as $permission) {
    $key = (string) $permission['permission_key'];
    if (isset($pageRegistry[$key])) {
        $group = (string) $pageRegistry[$key]['group'];
        $pagePermissionGroups[$group][] = $permission;
    } else {
        $actionPermissions[] = $permission;
    }
}

staff_layout_start([
    'title' => 'ロール・権限管理', 'heading' => 'ロール・権限管理', 'eyebrow' => 'STAFF / ROLES & PERMISSIONS',
    'description' => '複数ロールを組み合わせ、表示できるページと実行できる操作を個別に設定します。',
]);
?>
<div class="ops-page staff-role-manager">
    <?php if ($message !== ''): ?><div class="ops-alert ops-alert--success"><?= staff_ui_escape($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>

    <section class="ops-panel"><header class="ops-panel__header"><div><h3>新しいロール</h3><p>色・アイコン・表示順を設定してロールを追加します。</p></div></header><div class="ops-panel__body"><form method="post" class="admin-native-form"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="create"><div class="ops-form-grid"><label><span class="ops-label">ロール名</span><input class="ops-input" name="name" required placeholder="例：夜間サポート"></label><label><span class="ops-label">識別キー</span><input class="ops-input" name="slug" pattern="[a-z0-9_-]+" placeholder="night_support"></label><label><span class="ops-label">ロールカラー</span><input class="ops-input role-color-input" type="color" name="color" value="#475569"></label><label><span class="ops-label">表示順</span><input class="ops-input" type="number" name="priority" min="1" max="899" value="500"></label><label><span class="ops-label">アイコン</span><select class="ops-select" name="icon"><?php foreach ($iconCatalog as $icon => $label): ?><option value="<?= staff_ui_escape($icon) ?>"><?= staff_ui_escape($label) ?></option><?php endforeach; ?></select></label><label><span class="ops-label">説明</span><input class="ops-input" name="description" maxlength="500" placeholder="担当範囲や用途"></label></div><div class="ops-form-actions"><button class="ops-button" type="submit"><?= staff_icon('add_box', '', 17) ?>ロールを作成</button></div></form></div></section>

    <div class="ops-split role-manager-split"><section class="ops-list"><header class="ops-panel__header"><div><h3>ロール一覧</h3><p><?= number_format(count($roles)) ?>ロール</p></div></header><div class="ops-rows"><?php foreach ($roles as $role): ?><a class="ops-row role-list-row <?= (int) $role['id'] === $selectedId ? 'is-selected' : '' ?>" href="?id=<?= (int) $role['id'] ?>"><span class="role-list-row__icon" style="--role-color:<?= staff_ui_escape($role['color'] ?? '#475569') ?>"><?= staff_icon((string) ($role['icon'] ?? 'badge'), '', 18) ?></span><span><strong class="ops-row__title"><?= staff_ui_escape($role['name']) ?></strong><span class="ops-row__meta">@<?= staff_ui_escape($role['slug']) ?><br><?= number_format((int) $role['member_count']) ?>名・<?= number_format((int) $role['permission_count']) ?>権限</span></span></a><?php endforeach; ?></div></section>

    <section class="ops-detail"><?php if (is_array($selectedRole)): ?><form method="post" class="admin-native-form"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="role_id" value="<?= (int) $selectedRole['id'] ?>"><header class="ops-panel__header"><div><h3><?= staff_ui_escape($selectedRole['name']) ?></h3><p>@<?= staff_ui_escape($selectedRole['slug']) ?>・<?= number_format((int) $selectedRole['member_count']) ?>名</p></div><span class="role-preview" style="--role-color:<?= staff_ui_escape($selectedRole['color'] ?? '#475569') ?>"><?= staff_icon((string) ($selectedRole['icon'] ?? 'badge'), '', 17) ?><?= staff_ui_escape($selectedRole['name']) ?></span></header><div class="ops-panel__body"><div class="ops-form-grid"><label><span class="ops-label">ロール名</span><input class="ops-input" name="name" required value="<?= staff_ui_escape($selectedRole['name']) ?>"></label><label><span class="ops-label">識別キー</span><input class="ops-input" disabled value="<?= staff_ui_escape($selectedRole['slug']) ?>"></label><label><span class="ops-label">ロールカラー</span><input class="ops-input role-color-input" type="color" name="color" value="<?= staff_ui_escape($selectedRole['color'] ?? '#475569') ?>"></label><label><span class="ops-label">表示順</span><input class="ops-input" type="number" name="priority" min="1" max="1000" value="<?= (int) $selectedRole['priority'] ?>"></label><label><span class="ops-label">アイコン</span><select class="ops-select" name="icon"><?php foreach ($iconCatalog as $icon => $label): ?><option value="<?= staff_ui_escape($icon) ?>" <?= ($selectedRole['icon'] ?? '') === $icon ? 'selected' : '' ?>><?= staff_ui_escape($label) ?></option><?php endforeach; ?></select></label><label><span class="ops-label">説明</span><textarea class="ops-textarea" name="description" rows="3"><?= staff_ui_escape($selectedRole['description'] ?? '') ?></textarea></label></div>

        <div class="role-permission-section"><h4>表示できるページ</h4><p>チェックしたページだけがサイドバーに表示され、URLへ直接アクセスした場合も制限されます。</p><?php foreach ($pagePermissionGroups as $group => $groupPermissions): ?><details class="role-permission-group" open><summary><strong><?= staff_ui_escape($group) ?></strong><small><?= number_format(count($groupPermissions)) ?>ページ</small></summary><div><?php foreach ($groupPermissions as $permission): ?><label class="role-permission-item"><input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id'] ?>" <?= in_array((int) $permission['id'], $selectedPermissionIds, true) ? 'checked' : '' ?>><span><?= staff_icon('description', '', 16) ?><span><strong><?= staff_ui_escape($pageRegistry[$permission['permission_key']]['label']) ?></strong><small><?= staff_ui_escape($permission['permission_key']) ?></small></span></span></label><?php endforeach; ?></div></details><?php endforeach; ?></div>

        <div class="role-permission-section"><h4>実行できる操作</h4><p>ページを表示できても、操作権限がなければ更新・承認・返信などは実行できません。</p><div class="role-permission-grid"><?php foreach ($actionPermissions as $permission): ?><label class="role-permission-item"><input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id'] ?>" <?= in_array((int) $permission['id'], $selectedPermissionIds, true) ? 'checked' : '' ?>><span><?= staff_icon('key', '', 16) ?><span><strong><?= staff_ui_escape($permission['name']) ?></strong><small><?= staff_ui_escape($permission['permission_key']) ?></small></span></span></label><?php endforeach; ?></div></div>
        <div class="ops-form-actions"><button class="ops-button" type="submit"><?= staff_icon('save', '', 17) ?>ロールと権限を保存</button></div></div></form>
        <?php if (!in_array((string) $selectedRole['slug'], ['owner', 'administrator'], true)): ?><form method="post" class="role-delete-form" onsubmit="return confirm('このロールを削除しますか？')"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="role_id" value="<?= (int) $selectedRole['id'] ?>"><button class="ops-button ops-button--danger" type="submit"><?= staff_icon('delete', '', 16) ?>ロールを削除</button></form><?php endif; ?>
        <?php else: ?><div class="ops-empty">左の一覧からロールを選択してください。</div><?php endif; ?></section></div>
</div>
<?php staff_layout_end();

