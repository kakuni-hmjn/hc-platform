<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/operations.php';
require_once dirname(__DIR__, 2) . '/lib/csrf.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'staff.dashboard.view');

$staffUserId = (int) ($staffUser['id'] ?? 0);
$preferences = staff_workspace_normalize($staffWorkspacePreferences);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = '確認情報の有効期限が切れました。再読み込みしてお試しください。';
    } else {
        $displayName = mb_substr(trim((string) ($_POST['display_name'] ?? '')), 0, 80, 'UTF-8');
        $profileBio = mb_substr(trim((string) ($_POST['profile_bio'] ?? '')), 0, 500, 'UTF-8');
        $workStatus = trim((string) ($_POST['work_status'] ?? 'online'));
        $validWorkStatuses = ['offline', 'online', 'working', 'busy', 'away', 'break'];
        if ($displayName === '' || !in_array($workStatus, $validWorkStatuses, true)) {
            $error = '表示名と勤務状態を確認してください。';
        } else {
            try {
                $avatarPath = staff_workspace_store_image((array) ($_FILES['avatar_image'] ?? []), $staffUserId, 'avatar');
                if ($avatarPath !== null) {
                    $preferences['avatar_image_path'] = $avatarPath;
                } elseif (isset($_POST['remove_avatar'])) {
                    $preferences['avatar_image_path'] = null;
                }
                $preferences['profile_bio'] = $profileBio;
                $pdo = staff_db();
                $pdo->beginTransaction();
                $statement = $pdo->prepare(
                    'UPDATE staff_users SET display_name = :display_name,
                        work_status = :work_status, updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );
                $statement->execute(['display_name' => $displayName, 'work_status' => $workStatus, 'id' => $staffUserId]);
                staff_workspace_preferences_save($staffUserId, $preferences);
                $pdo->commit();
                staff_ops_audit($staffUserId, 'staff.account.self_update', 'staff_user', $staffUserId, '自分のスタッフプロフィールを更新しました。', null, ['display_name' => $displayName, 'work_status' => $workStatus]);
                $staffUser['display_name'] = $displayName;
                $staffUser['work_status'] = $workStatus;
                $staffContext['user'] = $staffUser;
                $staffDisplayName = $displayName;
                $staffWorkspacePreferences = $preferences;
                $message = 'スタッフプロフィールを保存しました。';
            } catch (RuntimeException $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'プロフィールを保存できませんでした。';
            }
        }
    }
}

$avatarUrl = staff_workspace_asset_url($preferences['avatar_image_path']);
$initial = mb_strtoupper(mb_substr($staffDisplayName, 0, 1, 'UTF-8'), 'UTF-8');
$workStatusLabels = ['online' => 'オンライン', 'working' => '作業中', 'busy' => '対応中', 'away' => '離席中', 'break' => '休憩中', 'offline' => 'オフライン'];

staff_layout_start([
    'title' => 'スタッフアカウント', 'heading' => 'スタッフアカウント', 'eyebrow' => 'MY STAFF ACCOUNT',
    'description' => 'スタッフとして使う表示名、プロフィール画像、勤務状態、ワークスペースを管理します。',
]);
?>
<div class="ops-page">
    <?php if ($message !== ''): ?><div class="ops-alert ops-alert--success"><?= staff_ui_escape($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>

    <section class="ops-panel"><div class="ops-panel__body"><div class="workspace-account-hero"><div class="workspace-account-avatar" style="background:<?= staff_ui_escape($preferences['accent_color']) ?>"><?php if ($avatarUrl !== null): ?><img src="<?= staff_ui_escape($avatarUrl) ?>" alt=""><?php else: ?><?= staff_ui_escape($initial) ?><?php endif; ?></div><div class="workspace-account-copy"><h3><?= staff_ui_escape($staffDisplayName) ?></h3><p><?= staff_ui_escape($preferences['profile_bio'] ?: 'プロフィールメッセージはまだありません。') ?><br><?= staff_ui_escape($staffAccount['email'] ?? '-') ?> · <?= staff_ui_escape($staffRoleName) ?></p></div><a class="ops-button" href="/staff/account/customize/"><span class="material-icons">palette</span>ダッシュボードをカスタム</a></div></div></section>

    <section class="ops-summary"><article class="ops-card"><span class="ops-card__label">スタッフID</span><strong>#<?= $staffUserId ?></strong><small><?= staff_ui_escape($staffUser['employee_code'] ?: '社員コード未設定') ?></small></article><article class="ops-card"><span class="ops-card__label">ロール</span><strong class="ops-card__text"><?= staff_ui_escape($staffRoleName) ?></strong><small><?= staff_ui_escape($staffRoleSlug) ?></small></article><article class="ops-card"><span class="ops-card__label">権限</span><strong><?= number_format(count((array) ($staffContext['permissions'] ?? []))) ?></strong><small>現在有効</small></article><article class="ops-card"><span class="ops-card__label">勤務状態</span><strong class="ops-card__text"><?= staff_ui_escape($workStatusLabels[(string) ($staffUser['work_status'] ?? 'offline')] ?? '未設定') ?></strong><small>対応状況</small></article></section>

    <div class="ops-split">
        <section class="ops-detail"><header class="ops-panel__header"><div><h3>プロフィール編集</h3><p>ここで変更した内容はスタッフコンソール内だけに表示されます。</p></div></header><div class="ops-panel__body"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><div class="ops-form-grid"><label><span class="ops-label">スタッフ表示名</span><input class="ops-input" name="display_name" maxlength="80" required value="<?= staff_ui_escape($staffDisplayName) ?>"></label><label><span class="ops-label">勤務状態</span><select class="ops-select" name="work_status"><?php foreach ($workStatusLabels as $value => $label): ?><option value="<?= $value ?>" <?= ($staffUser['work_status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label></div><label><span class="ops-label">プロフィールメッセージ</span><textarea class="ops-textarea" name="profile_bio" rows="4" maxlength="500" placeholder="担当業務やひとことを書けます"><?= staff_ui_escape($preferences['profile_bio']) ?></textarea></label><div class="ops-section"><label><span class="ops-label">プロフィール画像</span><input class="ops-input" type="file" name="avatar_image" accept="image/jpeg,image/png,image/webp"></label><p class="ops-muted">JPEG・PNG・WebP、3MB以内。正方形に近い画像がおすすめです。</p><?php if ($avatarUrl !== null): ?><label class="ops-check"><input type="checkbox" name="remove_avatar" value="1"><span>現在のプロフィール画像を外す</span></label><?php endif; ?></div><div class="ops-form-actions"><button class="ops-button" type="submit"><span class="material-icons">save</span>プロフィールを保存</button></div></form></div></section>
        <section class="ops-list"><header class="ops-panel__header"><div><h3>アカウント情報</h3><p>HCアカウントとスタッフ権限の接続状況</p></div></header><div class="ops-panel__body"><dl class="ops-kv"><div><dt>HCアカウント</dt><dd><?= staff_ui_escape($staffAccount['username'] ?? '-') ?> (#<?= (int) $staffAccountId ?>)</dd></div><div><dt>メール</dt><dd><?= staff_ui_escape($staffAccount['email'] ?? '-') ?></dd></div><div><dt>スタッフ状態</dt><dd><?= staff_ui_escape(staff_ops_user_status_label((string) ($staffUser['status'] ?? 'active'))) ?></dd></div><div><dt>背景</dt><dd><?= staff_ui_escape(match ($preferences['background_mode']) { 'image' => 'マイ画像', 'preset' => 'プリセット', default => '標準' }) ?></dd></div><div><dt>部署</dt><dd><?= staff_ui_escape(implode(' / ', array_column((array) ($staffContext['departments'] ?? []), 'name')) ?: '未設定') ?></dd></div><div><dt>担当カテゴリ</dt><dd><?= staff_ui_escape(implode(' / ', array_column((array) ($staffContext['categories'] ?? []), 'name')) ?: '未設定') ?></dd></div></dl><div class="ops-section"><div class="ops-component-grid"><a class="ops-component" href="/staff/account/permissions/"><span class="material-icons">key</span><span><strong>有効権限</strong><small>ロール・所属・操作権限</small></span></a><a class="ops-component" href="/dashboard/"><span class="material-icons">account_circle</span><span><strong>HCアカウント</strong><small>一般アカウント情報</small></span></a></div></div></div></section>
    </div>
</div>
<?php staff_layout_end();

