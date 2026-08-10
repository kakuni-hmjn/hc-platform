<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/operations.php';
require_once dirname(__DIR__, 3) . '/lib/csrf.php';
require_once dirname(__DIR__, 2) . '/components/layout.php';
require_once dirname(__DIR__, 2) . '/components/ui.php';

staff_require_permission($staffContext, 'staff.users.view');

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$selectedId = max(0, (int) ($_GET['id'] ?? 0));
$message = '';
$error = '';
$canEdit = staff_has_permission($staffContext, 'staff.users.edit') || staff_can_access_admin($staffContext);
$canAssignRoles = staff_has_permission($staffContext, 'staff.roles.assign') || staff_can_access_admin($staffContext);
$canManageRoles = staff_has_permission($staffContext, 'staff.roles.manage') || staff_can_access_admin($staffContext);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = '確認情報の有効期限が切れました。再読み込みしてお試しください。';
    } elseif ($action === 'update_profile' && $canEdit) {
        $targetId = max(0, (int) ($_POST['staff_id'] ?? 0));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $employeeCode = trim((string) ($_POST['employee_code'] ?? ''));
        $newStatus = trim((string) ($_POST['staff_status'] ?? ''));
        $workStatus = trim((string) ($_POST['work_status'] ?? ''));
        $validStatuses = ['active', 'inactive', 'suspended', 'temporary', 'external'];
        $validWorkStatuses = ['offline', 'online', 'working', 'busy', 'away', 'break'];
        if ($targetId <= 0 || !in_array($newStatus, $validStatuses, true) || !in_array($workStatus, $validWorkStatuses, true)) {
            $error = '更新内容が正しくありません。';
        } else {
            try {
                $statement = staff_db()->prepare('SELECT account_id, display_name, employee_code, status, work_status FROM staff_users WHERE id = :id LIMIT 1');
                $statement->execute(['id' => $targetId]);
                $oldData = $statement->fetch();
                if (!is_array($oldData)) {
                    throw new RuntimeException('staff not found');
                }
                if ((int) $oldData['account_id'] === $staffAccountId && $newStatus !== 'active') {
                    throw new DomainException('self suspension');
                }
                $statement = staff_db()->prepare(
                    'UPDATE staff_users SET display_name = :display_name,
                        employee_code = :employee_code, status = :status,
                        work_status = :work_status, updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );
                $newData = ['display_name' => $displayName, 'employee_code' => $employeeCode, 'status' => $newStatus, 'work_status' => $workStatus];
                $statement->execute($newData + ['id' => $targetId]);
                staff_ops_audit((int) ($staffUser['id'] ?? 0), 'staff.profile.update', 'staff_user', $targetId, 'スタッフプロフィールを更新しました。', $oldData, $newData);
                $selectedId = $targetId;
                $message = 'スタッフ情報を保存しました。';
            } catch (DomainException $exception) {
                $error = '操作中の自分のスタッフ権限は停止できません。';
            } catch (Throwable $exception) {
                $error = 'スタッフ情報を更新できませんでした。';
            }
        }
    } elseif ($action === 'update_roles' && $canAssignRoles) {
        $targetId = max(0, (int) ($_POST['staff_id'] ?? 0));
        $roleIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['role_ids'] ?? [])), static fn (int $id): bool => $id > 0)));
        try {
            $pdo = staff_db();
            $statement = $pdo->prepare('SELECT account_id FROM staff_users WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $targetId]);
            $targetAccountId = (int) ($statement->fetchColumn() ?: 0);
            if ($targetAccountId <= 0 || $targetAccountId === $staffAccountId) {
                throw new DomainException('self role edit');
            }
            if ($roleIds === []) {
                throw new InvalidArgumentException('role required');
            }
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $statement = $pdo->prepare("SELECT id FROM account_roles WHERE is_staff_role = TRUE AND id IN ($placeholders)");
            $statement->execute($roleIds);
            $validRoleIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            if (count($validRoleIds) !== count($roleIds)) {
                throw new InvalidArgumentException('invalid role');
            }
            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                'DELETE FROM user_roles ur USING account_roles ar
                 WHERE ur.role_id = ar.id AND ar.is_staff_role = TRUE AND ur.user_id = :user_id'
            );
            $statement->execute(['user_id' => $targetAccountId]);
            $statement = $pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (:user_id, :role_id, :assigned_by) ON CONFLICT (user_id, role_id) DO NOTHING');
            foreach ($validRoleIds as $roleId) {
                $statement->execute(['user_id' => $targetAccountId, 'role_id' => $roleId, 'assigned_by' => $staffAccountId]);
            }
            $pdo->commit();
            staff_ops_audit((int) ($staffUser['id'] ?? 0), 'staff.roles.update', 'staff_user', $targetId, 'スタッフロールを更新しました。', null, ['role_ids' => $validRoleIds]);
            $selectedId = $targetId;
            $message = 'スタッフロールを更新しました。';
        } catch (DomainException $exception) {
            $error = '操作中の自分のロールはこの画面から変更できません。';
        } catch (InvalidArgumentException $exception) {
            $error = 'スタッフには1つ以上の有効なロールが必要です。';
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'スタッフロールを更新できませんでした。';
        }
    } elseif ($action === 'add_staff' && $canAssignRoles) {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $roleId = max(0, (int) ($_POST['role_id'] ?? 0));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $roleId <= 0) {
            $error = '登録済みHCアカウントのメールとロールを指定してください。';
        } else {
            try {
                $pdo = staff_db();
                $statement = $pdo->prepare('SELECT id, username FROM users WHERE LOWER(email) = :email AND deleted_at IS NULL LIMIT 1');
                $statement->execute(['email' => $email]);
                $account = $statement->fetch();
                if (!is_array($account)) {
                    throw new RuntimeException('account not found');
                }
                $statement = $pdo->prepare('SELECT id FROM account_roles WHERE id = :id AND is_staff_role = TRUE LIMIT 1');
                $statement->execute(['id' => $roleId]);
                if ($statement->fetchColumn() === false) {
                    throw new InvalidArgumentException('role');
                }
                $pdo->beginTransaction();
                $statement = $pdo->prepare(
                    'INSERT INTO staff_users (account_id, display_name, status, work_status)
                     VALUES (:account_id, :display_name, \'active\', \'offline\')
                     ON CONFLICT (account_id) DO UPDATE SET updated_at = CURRENT_TIMESTAMP
                     RETURNING id'
                );
                $statement->execute(['account_id' => (int) $account['id'], 'display_name' => (string) $account['username']]);
                $newStaffId = (int) $statement->fetchColumn();
                $statement = $pdo->prepare('INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (:user_id, :role_id, :assigned_by) ON CONFLICT (user_id, role_id) DO NOTHING');
                $statement->execute(['user_id' => (int) $account['id'], 'role_id' => $roleId, 'assigned_by' => $staffAccountId]);
                $pdo->commit();
                staff_ops_audit((int) ($staffUser['id'] ?? 0), 'staff.create', 'staff_user', $newStaffId, '既存HCアカウントをスタッフに追加しました。', null, ['account_id' => (int) $account['id'], 'role_id' => $roleId]);
                $selectedId = $newStaffId;
                $message = 'HCアカウントをスタッフに追加しました。';
            } catch (RuntimeException $exception) {
                $error = 'そのメールアドレスのHCアカウントが見つかりません。';
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'スタッフを追加できませんでした。';
            }
        }
    } else {
        $error = 'この操作を行う権限がありません。';
    }
}

$data = staff_users_load($search, $status, $selectedId);
$selected = $data['selected'];
$selectedRoleIds = array_map(static fn (array $role): int => (int) $role['id'], $data['roles']);
$baseParams = array_filter(['q' => $search, 'status' => $status], static fn ($value): bool => $value !== '');

staff_layout_start([
    'title' => 'スタッフ管理',
    'heading' => 'スタッフ管理',
    'eyebrow' => 'STAFF MANAGEMENT',
    'description' => 'スタッフプロフィール、勤務状態、担当ロールを管理します。',
]);
?>
<div class="ops-page">
    <?php if ($message !== ''): ?><div class="ops-alert ops-alert--success"><?= staff_ui_escape($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>
    <?php foreach ($data['errors'] as $loadError): ?><div class="ops-alert"><?= staff_ui_escape($loadError) ?></div><?php endforeach; ?>

    <section class="ops-summary" aria-label="スタッフ集計"><article class="ops-card"><span class="ops-card__label">スタッフ総数</span><strong><?= number_format($data['counts']['total']) ?></strong><small>登録プロフィール</small></article><article class="ops-card"><span class="ops-card__label">有効</span><strong><?= number_format($data['counts']['active']) ?></strong><small>勤務可能なスタッフ</small></article><article class="ops-card"><span class="ops-card__label">オンライン・勤務中</span><strong><?= number_format($data['counts']['working']) ?></strong><small>現在の勤務状態</small></article><article class="ops-card"><span class="ops-card__label">停止中</span><strong><?= number_format($data['counts']['suspended']) ?></strong><small>アクセス停止プロフィール</small></article></section>

    <?php if ($canManageRoles): ?><div class="ops-form-actions staff-role-manager-link"><a class="ops-button ops-button--secondary" href="/staff/admin/roles/"><?= staff_icon('admin_panel_settings', '', 17) ?>ロールとページ権限を管理</a></div><?php endif; ?>

    <?php if ($canAssignRoles): ?><section class="ops-panel"><header class="ops-panel__header"><div><h3>スタッフを追加</h3><p>既に登録済みのHCアカウントへスタッフロールを付与します。</p></div></header><div class="ops-panel__body"><form class="ops-form-grid" method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="add_staff"><label><span class="ops-label">HCアカウントのメール</span><input class="ops-input" type="email" name="email" required placeholder="staff@example.com"></label><label><span class="ops-label">初期ロール</span><select class="ops-select" name="role_id" required><option value="">選択してください</option><?php foreach ($data['available_roles'] as $role): ?><option value="<?= (int) $role['id'] ?>"><?= staff_ui_escape($role['name']) ?></option><?php endforeach; ?></select></label><div class="ops-form-actions"><button class="ops-button" type="submit"><?= staff_icon('person_add', '', 17) ?>追加する</button></div></form></div></section><?php endif; ?>

    <form class="ops-toolbar" method="get"><label class="ops-toolbar__field"><span class="ops-label">スタッフを検索</span><input class="ops-input" type="search" name="q" value="<?= staff_ui_escape($search) ?>" placeholder="表示名・メール・社員コード"></label><label class="ops-toolbar__field"><span class="ops-label">状態</span><select class="ops-select" name="status"><option value="">すべて</option><?php foreach (['active' => '有効', 'inactive' => '無効', 'suspended' => '停止中', 'temporary' => '一時', 'external' => '外部'] as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="ops-button" type="submit"><?= staff_icon('search', '', 17) ?>検索</button><a class="ops-button ops-button--secondary" href="/staff/admin/users/">クリア</a></form>

    <div class="ops-split">
        <section class="ops-list"><header class="ops-panel__header"><div><h3>スタッフ一覧</h3><p><?= number_format(count($data['users'])) ?>名を表示</p></div></header><div class="ops-rows"><?php foreach ($data['users'] as $user): $params = $baseParams + ['id' => (int) $user['id']]; $isSelected = (int) ($selected['id'] ?? 0) === (int) $user['id']; ?><a class="ops-row <?= $isSelected ? 'is-selected' : '' ?>" href="?<?= staff_ui_escape(http_build_query($params)) ?>"><span><strong class="ops-row__title"><?= staff_ui_escape($user['display_name'] ?: $user['username']) ?></strong><span class="ops-row__meta"><?= staff_ui_escape($user['email']) ?><br><?= staff_ui_escape($user['role_names'] ?: 'ロール未設定') ?></span></span><span class="ops-row__end"><span class="ops-status ops-status--<?= staff_ui_escape($user['work_status']) ?>"><?= staff_ui_escape(staff_ops_work_status_label((string) $user['work_status'])) ?></span><span class="ops-row__meta"><?= staff_ui_escape($user['employee_code'] ?: '#'.$user['id']) ?></span></span></a><?php endforeach; ?><?php if ($data['users'] === []): ?><div class="ops-empty">条件に一致するスタッフはいません。</div><?php endif; ?></div></section>

        <section class="ops-detail"><?php if (is_array($selected)): ?><header class="ops-panel__header"><div><h3><?= staff_ui_escape($selected['display_name'] ?: $selected['username']) ?></h3><p><?= staff_ui_escape($selected['email']) ?> / スタッフID #<?= (int) $selected['id'] ?></p></div><span class="ops-status ops-status--<?= staff_ui_escape($selected['status']) ?>"><?= staff_ui_escape(staff_ops_user_status_label((string) $selected['status'])) ?></span></header><div class="ops-panel__body">
            <dl class="ops-kv"><div><dt>HCアカウント</dt><dd><?= staff_ui_escape($selected['username']) ?> (#<?= (int) $selected['account_id'] ?>)</dd></div><div><dt>最終ログイン</dt><dd><?= staff_ui_escape(staff_ops_datetime($selected['last_login'] ?? null)) ?></dd></div><div><dt>部署</dt><dd><?= staff_ui_escape(implode(' / ', array_column($data['departments'], 'name')) ?: '未設定') ?></dd></div><div><dt>担当カテゴリ</dt><dd><?= staff_ui_escape(implode(' / ', array_column($data['categories'], 'name')) ?: '未設定') ?></dd></div></dl>
            <div class="ops-section"><h4>プロフィール・勤務状態</h4><?php if ($canEdit): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="update_profile"><input type="hidden" name="staff_id" value="<?= (int) $selected['id'] ?>"><div class="ops-form-grid"><label><span class="ops-label">表示名</span><input class="ops-input" name="display_name" maxlength="150" value="<?= staff_ui_escape($selected['display_name'] ?? '') ?>"></label><label><span class="ops-label">社員コード</span><input class="ops-input" name="employee_code" maxlength="50" value="<?= staff_ui_escape($selected['employee_code'] ?? '') ?>"></label><label><span class="ops-label">スタッフ状態</span><select class="ops-select" name="staff_status"><?php foreach (['active' => '有効', 'inactive' => '無効', 'suspended' => '停止中', 'temporary' => '一時', 'external' => '外部'] as $value => $label): ?><option value="<?= $value ?>" <?= $selected['status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label><span class="ops-label">勤務状態</span><select class="ops-select" name="work_status"><?php foreach (['offline' => 'オフライン', 'online' => 'オンライン', 'working' => '勤務中', 'busy' => '取り込み中', 'away' => '離席中', 'break' => '休憩中'] as $value => $label): ?><option value="<?= $value ?>" <?= $selected['work_status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label></div><div class="ops-form-actions"><button class="ops-button" type="submit">プロフィールを保存</button></div></form><?php else: ?><div class="ops-empty">閲覧のみ可能です。</div><?php endif; ?></div>
            <div class="ops-section"><h4>スタッフロール</h4><?php if ($canAssignRoles && (int) $selected['account_id'] !== $staffAccountId): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="update_roles"><input type="hidden" name="staff_id" value="<?= (int) $selected['id'] ?>"><div class="ops-form-grid"><?php foreach ($data['available_roles'] as $role): ?><label class="ops-row staff-role-option" style="--role-color:<?= staff_ui_escape($role['color'] ?? '#475569') ?>"><span class="staff-role-option__icon"><?= staff_icon((string) ($role['icon'] ?? 'badge'), '', 18) ?></span><span><strong class="ops-row__title"><?= staff_ui_escape($role['name']) ?></strong><span class="ops-row__meta"><?= staff_ui_escape($role['description'] ?? '') ?></span></span><input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $selectedRoleIds, true) ? 'checked' : '' ?>></label><?php endforeach; ?></div><div class="ops-form-actions"><button class="ops-button" type="submit">ロールを保存</button></div></form><?php else: ?><div class="ops-empty"><?= (int) $selected['account_id'] === $staffAccountId ? '操作中の自分のロールは安全のため変更できません。' : 'ロールの閲覧のみ可能です。' ?><div class="staff-role-chips"><?php foreach ($data['roles'] as $role): ?><span class="role-preview" style="--role-color:<?= staff_ui_escape($role['color'] ?? '#475569') ?>"><?= staff_icon((string) ($role['icon'] ?? 'badge'), '', 15) ?><?= staff_ui_escape($role['name']) ?></span><?php endforeach; ?><?php if ($data['roles'] === []): ?><span>ロール未設定</span><?php endif; ?></div></div><?php endif; ?></div>
        </div><?php else: ?><div class="ops-empty">左の一覧からスタッフを選択してください。</div><?php endif; ?></section>
    </div>
</div>
<?php staff_layout_end();
