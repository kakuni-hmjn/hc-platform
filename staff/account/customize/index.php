<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/operations.php';
require_once dirname(__DIR__, 3) . '/lib/csrf.php';
require_once dirname(__DIR__, 2) . '/components/layout.php';
require_once dirname(__DIR__, 2) . '/components/ui.php';

staff_require_permission($staffContext, 'staff.dashboard.view');

$staffUserId = (int) ($staffUser['id'] ?? 0);
$preferences = staff_workspace_normalize($staffWorkspacePreferences);
$catalog = staff_workspace_widget_catalog();
$iconCatalog = staff_workspace_icon_catalog();
$presets = staff_workspace_background_presets();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'save'));
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = '確認情報の有効期限が切れました。再読み込みしてお試しください。';
    } else {
        try {
            if ($action === 'reset') {
                $defaults = staff_workspace_defaults();
                $defaults['profile_bio'] = $preferences['profile_bio'];
                $defaults['avatar_image_path'] = $preferences['avatar_image_path'];
                $preferences = $defaults;
                staff_workspace_preferences_save($staffUserId, $preferences);
                staff_ops_audit($staffUserId, 'staff.workspace.reset', 'staff_user', $staffUserId, 'マイワークスペース設定を初期化しました。');
                $message = 'ダッシュボードを標準設定に戻しました。';
            } else {
                $preferences = staff_workspace_preferences_from_input($_POST, $preferences);
                $backgroundPath = staff_workspace_store_image((array) ($_FILES['background_image'] ?? []), $staffUserId, 'background');
                if ($backgroundPath !== null) {
                    $preferences['background_image_path'] = $backgroundPath;
                    $preferences['background_mode'] = 'image';
                } elseif (isset($_POST['remove_background'])) {
                    $preferences['background_image_path'] = null;
                    if ($preferences['background_mode'] === 'image') {
                        $preferences['background_mode'] = 'plain';
                    }
                }
                $preferences = staff_workspace_normalize($preferences);
                staff_workspace_preferences_save($staffUserId, $preferences);
                staff_ops_audit($staffUserId, 'staff.workspace.update', 'staff_user', $staffUserId, 'マイワークスペース設定を更新しました。', null, ['background_mode' => $preferences['background_mode'], 'background_scale' => $preferences['background_scale'], 'layout' => $preferences['dashboard_layout'], 'widgets' => $preferences['widgets'], 'custom_links_count' => count($preferences['custom_links'])]);
                $message = 'マイワークスペースを保存しました。';
            }
            $staffWorkspacePreferences = $preferences;
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            $error = 'ワークスペース設定を保存できませんでした。';
        }
    }
}

$backgroundUrl = staff_workspace_asset_url($preferences['background_image_path']);
$orderedWidgets = [];
foreach ($preferences['widgets'] as $widget) {
    if (isset($catalog[$widget])) {
        $orderedWidgets[$widget] = $catalog[$widget];
    }
}
foreach ($catalog as $widget => $definition) {
    if (!isset($orderedWidgets[$widget])) {
        $orderedWidgets[$widget] = $definition;
    }
}

staff_layout_start([
    'title' => 'ダッシュボードカスタマイズ', 'heading' => 'ダッシュボードカスタマイズ', 'eyebrow' => 'MY WORKSPACE',
    'description' => '背景、色、レイアウト、表示項目を自分専用に組み替えられます。設定は他のスタッフには影響しません。',
]);
?>
<div class="ops-page workspace-customizer" data-workspace-customizer>
    <?php if ($message !== ''): ?><div class="ops-alert ops-alert--success"><?= staff_ui_escape($message) ?><br><a class="ops-inline-link" href="/staff/">ダッシュボードで確認する</a></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>

    <div class="ops-form-actions"><a class="ops-button ops-button--secondary" href="/staff/account/">アカウントへ戻る</a><a class="ops-button" href="/staff/">ダッシュボードを見る</a></div>

    <form method="post" enctype="multipart/form-data" data-workspace-form>
        <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">

        <section class="ops-panel"><header class="ops-panel__header"><div><h3>背景</h3><p>標準、カラープリセット、自分の画像から選べます。</p></div></header><div class="ops-panel__body">
            <div class="workspace-choice-grid"><?php foreach (['plain' => ['標準', '読みやすい白ベース'], 'preset' => ['カラープリセット', '色の組み合わせから選択'], 'image' => ['マイ画像', '好きな画像を背景に使用']] as $value => [$label, $description]): ?><label class="workspace-choice"><input type="radio" name="background_mode" value="<?= $value ?>" <?= $preferences['background_mode'] === $value ? 'checked' : '' ?>><strong><?= $label ?></strong><small><?= $description ?></small></label><?php endforeach; ?></div>
            <div class="ops-section" data-background-preset-section><h4>カラープリセット</h4><div class="workspace-preset-grid"><?php foreach ($presets as $value => $preset): ?><label class="workspace-preset"><input type="radio" name="background_preset" value="<?= staff_ui_escape($value) ?>" <?= $preferences['background_preset'] === $value ? 'checked' : '' ?>><span class="workspace-preset__swatch" style="background:<?= staff_ui_escape($preset['css']) ?>"></span><small><?= staff_ui_escape($preset['label']) ?></small></label><?php endforeach; ?></div></div>
            <div class="ops-section" data-background-image-section><h4>背景画像</h4><div class="workspace-upload-preview <?= $backgroundUrl !== null ? 'has-image' : '' ?>" data-background-preview style="--workspace-preview-scale:<?= (float) $preferences['background_scale'] / 100 ?>;--workspace-preview-position:<?= staff_ui_escape($preferences['background_position']) ?>;<?= $backgroundUrl !== null ? '--workspace-preview-image:url(&quot;' . staff_ui_escape($backgroundUrl) . '&quot;)' : '' ?>"><div class="workspace-upload-preview__empty"><span class="material-icons">add_photo_alternate</span><span>画像を選ぶとここにプレビューされます</span></div></div><label><span class="ops-label">画像ファイル</span><input class="ops-input" type="file" name="background_image" accept="image/jpeg,image/png,image/webp" data-background-file></label><p class="ops-muted">JPEG・PNG・WebP、6MB以内。横長の画像がおすすめです。</p><?php if ($backgroundUrl !== null): ?><label class="ops-check"><input type="checkbox" name="remove_background" value="1"><span>現在の背景画像を外す</span></label><?php endif; ?></div>
            <div class="ops-form-grid ops-section"><label><span class="ops-label">画像の位置</span><select class="ops-select" name="background_position" data-background-position><?php foreach (['center' => '中央', 'top' => '上', 'bottom' => '下', 'left' => '左', 'right' => '右'] as $value => $label): ?><option value="<?= $value ?>" <?= $preferences['background_position'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label><span class="ops-label">画像の拡大率</span><span class="workspace-range"><input type="range" name="background_scale" min="100" max="200" step="5" value="<?= (int) $preferences['background_scale'] ?>" data-background-scale><output data-background-scale-output><?= (int) $preferences['background_scale'] ?>%</output></span><small class="ops-muted">中央を基準に、画面全体を保ったまま拡大します。</small></label><label><span class="ops-label">背景を白くする強さ</span><span class="workspace-range"><input type="range" name="background_overlay" min="0" max="90" step="5" value="<?= (int) $preferences['background_overlay'] ?>" data-overlay-range><output data-overlay-output><?= (int) $preferences['background_overlay'] ?>%</output></span></label></div>
        </div></section>

        <section class="ops-panel"><header class="ops-panel__header"><div><h3>色と見た目</h3><p>操作色とカードの質感を選べます。</p></div></header><div class="ops-panel__body"><div class="ops-form-grid"><label><span class="ops-label">アクセント色</span><span class="workspace-color-field"><input type="color" name="accent_color" value="<?= staff_ui_escape($preferences['accent_color']) ?>"><span class="ops-muted">リンク・ボタン・アイコンに反映</span></span></label><label><span class="ops-label">表示密度</span><span class="ops-check"><input type="checkbox" name="compact_mode" value="1" <?= $preferences['compact_mode'] ? 'checked' : '' ?>><span>カードをコンパクトにする</span></span></label></div><div class="workspace-choice-grid ops-section"><?php foreach (['solid' => ['白いカード', '最も読みやすい標準表示'], 'glass' => ['ガラスカード', '背景が透ける柔らかな表示']] as $value => [$label, $description]): ?><label class="workspace-choice"><input type="radio" name="panel_style" value="<?= $value ?>" <?= $preferences['panel_style'] === $value ? 'checked' : '' ?>><strong><?= $label ?></strong><small><?= $description ?></small></label><?php endforeach; ?></div><label class="ops-section"><span class="ops-label">自分用のひとこと</span><input class="ops-input" name="custom_greeting" maxlength="160" value="<?= staff_ui_escape($preferences['custom_greeting']) ?>" placeholder="例: 今日も安全第一でいきましょう"></label></div></section>

        <section class="ops-panel"><header class="ops-panel__header"><div><h3>レイアウト</h3><p>画面幅に応じてモバイルでは自動的に1列になります。</p></div></header><div class="ops-panel__body"><div class="workspace-choice-grid"><?php foreach (['balanced' => ['2列バランス', 'カードを左右均等に配置'], 'wide' => ['メイン重視', 'タスクなどを広く表示'], 'stacked' => ['1列表示', '上から順番に大きく表示']] as $value => [$label, $description]): ?><label class="workspace-choice"><input type="radio" name="dashboard_layout" value="<?= $value ?>" <?= $preferences['dashboard_layout'] === $value ? 'checked' : '' ?>><strong><?= $label ?></strong><small><?= $description ?></small></label><?php endforeach; ?></div></div></section>

        <section class="ops-panel" id="custom-links"><header class="ops-panel__header"><div><h3>カスタムリンク</h3><p>よく使うページを自分専用のウィジェットとして最大12個まで追加できます。</p></div><button class="ops-button ops-button--secondary ops-button--compact" type="button" data-custom-link-add><span class="material-icons">add</span>リンクを追加</button></header><div class="ops-panel__body">
            <div class="workspace-custom-link-list" data-custom-link-list data-next-index="<?= count($preferences['custom_links']) ?>">
                <div class="workspace-custom-link-empty" data-custom-link-empty <?= $preferences['custom_links'] !== [] ? 'hidden' : '' ?>><span class="material-icons">add_link</span><strong>カスタムリンクはまだありません</strong><small>「リンクを追加」から、よく使うページを登録できます。</small></div>
                <?php foreach ($preferences['custom_links'] as $index => $customLink): ?>
                    <article class="workspace-custom-link" data-custom-link-row>
                        <input type="hidden" name="custom_links[<?= $index ?>][id]" value="<?= staff_ui_escape($customLink['id']) ?>">
                        <span class="workspace-custom-link__icon"><span class="material-icons" data-custom-link-icon-preview><?= staff_ui_escape($customLink['icon']) ?></span></span>
                        <div class="workspace-custom-link__fields">
                            <div class="ops-form-grid"><label><span class="ops-label">タイトル</span><input class="ops-input" name="custom_links[<?= $index ?>][title]" maxlength="60" required value="<?= staff_ui_escape($customLink['title']) ?>" placeholder="例: 社内Wiki"></label><label><span class="ops-label">リンク先</span><input class="ops-input" name="custom_links[<?= $index ?>][url]" maxlength="500" required value="<?= staff_ui_escape($customLink['url']) ?>" placeholder="/staff/... または https://..."></label><label><span class="ops-label">説明</span><input class="ops-input" name="custom_links[<?= $index ?>][description]" maxlength="140" value="<?= staff_ui_escape($customLink['description']) ?>" placeholder="このリンクでできること"></label><div class="workspace-icon-field"><span class="ops-label">アイコン</span><details class="workspace-icon-picker" data-custom-link-icon-picker><summary><span class="material-icons" data-custom-link-icon-summary><?= staff_ui_escape($customLink['icon']) ?></span><strong data-custom-link-icon-label><?= staff_ui_escape($iconCatalog[$customLink['icon']] ?? 'リンク') ?></strong><span class="material-icons workspace-icon-picker__chevron">expand_more</span></summary><div class="workspace-icon-picker__grid" role="listbox" aria-label="アイコンを選択"><?php foreach ($iconCatalog as $icon => $label): ?><label class="workspace-icon-option" title="<?= staff_ui_escape($label) ?>"><input type="radio" name="custom_links[<?= $index ?>][icon]" value="<?= staff_ui_escape($icon) ?>" data-custom-link-icon data-icon-label="<?= staff_ui_escape($label) ?>" <?= $customLink['icon'] === $icon ? 'checked' : '' ?>><span class="material-icons"><?= staff_ui_escape($icon) ?></span><small><?= staff_ui_escape($label) ?></small></label><?php endforeach; ?></div></details></div></div>
                            <label class="ops-check"><input type="checkbox" name="custom_links[<?= $index ?>][open_new_tab]" value="1" <?= $customLink['open_new_tab'] ? 'checked' : '' ?>><span>新しいタブで開く</span></label>
                        </div>
                        <span class="workspace-custom-link__actions"><button type="button" data-custom-link-up title="上へ"><span class="material-icons">arrow_upward</span></button><button type="button" data-custom-link-down title="下へ"><span class="material-icons">arrow_downward</span></button><button type="button" data-custom-link-remove title="削除"><span class="material-icons">delete</span></button></span>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="ops-muted">サイト内は <code>/staff/...</code>、外部サイトは <code>https://...</code> の形式で入力してください。アイコンは現在のスタッフ画面で使用しているアイコン一覧から選べます。</p>
            <template data-custom-link-template>
                <article class="workspace-custom-link" data-custom-link-row>
                    <input type="hidden" name="custom_links[__INDEX__][id]" value="">
                    <span class="workspace-custom-link__icon"><span class="material-icons" data-custom-link-icon-preview>link</span></span>
                    <div class="workspace-custom-link__fields">
                        <div class="ops-form-grid"><label><span class="ops-label">タイトル</span><input class="ops-input" name="custom_links[__INDEX__][title]" maxlength="60" required placeholder="例: 社内Wiki"></label><label><span class="ops-label">リンク先</span><input class="ops-input" name="custom_links[__INDEX__][url]" maxlength="500" required placeholder="/staff/... または https://..."></label><label><span class="ops-label">説明</span><input class="ops-input" name="custom_links[__INDEX__][description]" maxlength="140" placeholder="このリンクでできること"></label><div class="workspace-icon-field"><span class="ops-label">アイコン</span><details class="workspace-icon-picker" data-custom-link-icon-picker><summary><span class="material-icons" data-custom-link-icon-summary>link</span><strong data-custom-link-icon-label>リンク</strong><span class="material-icons workspace-icon-picker__chevron">expand_more</span></summary><div class="workspace-icon-picker__grid" role="listbox" aria-label="アイコンを選択"><?php foreach ($iconCatalog as $icon => $label): ?><label class="workspace-icon-option" title="<?= staff_ui_escape($label) ?>"><input type="radio" name="custom_links[__INDEX__][icon]" value="<?= staff_ui_escape($icon) ?>" data-custom-link-icon data-icon-label="<?= staff_ui_escape($label) ?>" <?= $icon === 'link' ? 'checked' : '' ?>><span class="material-icons"><?= staff_ui_escape($icon) ?></span><small><?= staff_ui_escape($label) ?></small></label><?php endforeach; ?></div></details></div></div>
                        <label class="ops-check"><input type="checkbox" name="custom_links[__INDEX__][open_new_tab]" value="1"><span>新しいタブで開く</span></label>
                    </div>
                    <span class="workspace-custom-link__actions"><button type="button" data-custom-link-up title="上へ"><span class="material-icons">arrow_upward</span></button><button type="button" data-custom-link-down title="下へ"><span class="material-icons">arrow_downward</span></button><button type="button" data-custom-link-remove title="削除"><span class="material-icons">delete</span></button></span>
                </article>
            </template>
        </div></section>

        <section class="ops-panel"><header class="ops-panel__header"><div><h3>表示するウィジェット</h3><p>チェックで表示・非表示、矢印で上からの順番を変更できます。</p></div><div class="ops-tabs"><button class="ops-button ops-button--secondary ops-button--compact" type="button" data-widget-preset="all">全部表示</button><button class="ops-button ops-button--secondary ops-button--compact" type="button" data-widget-preset="focus">集中モード</button></div></header><div class="ops-panel__body"><div class="workspace-widget-list" data-widget-list><?php foreach ($orderedWidgets as $widget => $definition): ?><label class="workspace-widget-item" data-widget-item="<?= staff_ui_escape($widget) ?>"><input type="checkbox" name="widgets[]" value="<?= staff_ui_escape($widget) ?>" <?= in_array($widget, $preferences['widgets'], true) ? 'checked' : '' ?>><span class="material-icons"><?= staff_ui_escape($definition['icon']) ?></span><span><strong><?= staff_ui_escape($definition['label']) ?></strong><small><?= staff_ui_escape($definition['description']) ?></small></span><span class="workspace-widget-actions"><button type="button" data-widget-up title="上へ"><span class="material-icons">arrow_upward</span></button><button type="button" data-widget-down title="下へ"><span class="material-icons">arrow_downward</span></button></span></label><?php endforeach; ?></div></div></section>

        <div class="ops-form-actions"><button class="ops-button" type="submit"><span class="material-icons">save</span>このデザインを保存</button></div>
    </form>

    <section class="ops-panel"><header class="ops-panel__header"><div><h3>標準設定に戻す</h3><p>プロフィール画像や自己紹介は残し、ダッシュボードだけを初期化します。</p></div></header><div class="ops-panel__body"><form method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="action" value="reset"><button class="ops-button ops-button--secondary" type="submit">ダッシュボード設定をリセット</button></form></div></section>
</div>
<script src="/staff/workspace-customizer.js?v=4" defer></script>
<?php staff_layout_end();
