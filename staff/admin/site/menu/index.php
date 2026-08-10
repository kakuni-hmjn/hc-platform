<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$config = admin_menu_load();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) { throw new RuntimeException('操作の有効期限が切れました。'); }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_pages') {
            $routes = $_POST['routes'] ?? []; $categories = $_POST['category_keys'] ?? []; $descriptions = $_POST['descriptions'] ?? [];
            if (!is_array($routes) || !is_array($categories) || !is_array($descriptions)) { throw new RuntimeException('送信内容が不正です。'); }
            $count = 0;
            foreach ($routes as $index => $route) {
                $route = (string) $route; $category = (string) ($categories[$index] ?? '');
                if (!str_starts_with($route, '/admin/') || !isset($config['categories'][$category])) { continue; }
                $config['assignments'][$route] = $category;
                $config['descriptions'][$route] = mb_substr(trim((string) ($descriptions[$index] ?? '')), 0, 300);
                $count++;
            }
            if (!admin_menu_save($config)) { throw new RuntimeException('管理ページ設定を保存できませんでした。'); }
            staff_administration_flash('success', $count . '件のページ設定を保存しました。');
        } elseif ($action === 'add_category') {
            $name = trim((string) ($_POST['name'] ?? '')); $description = trim((string) ($_POST['description'] ?? ''));
            if ($name === '') { throw new RuntimeException('カテゴリ名を入力してください。'); }
            $base = admin_menu_slug($name); $key = $base; $number = 2;
            while (isset($config['categories'][$key])) { $key = $base . '-' . $number++; }
            $max = 0; foreach ($config['categories'] as $category) { $max = max($max, (int) ($category['order'] ?? 0)); }
            $config['categories'][$key] = ['name' => $name, 'description' => $description, 'order' => $max + 10];
            if (!admin_menu_save($config)) { throw new RuntimeException('カテゴリを保存できませんでした。'); }
            staff_administration_flash('success', 'カテゴリを追加しました。');
        } elseif ($action === 'update_category') {
            $key = (string) ($_POST['key'] ?? ''); $name = trim((string) ($_POST['name'] ?? '')); $description = trim((string) ($_POST['description'] ?? ''));
            if (!isset($config['categories'][$key]) || $name === '') { throw new RuntimeException('カテゴリ情報が不正です。'); }
            $config['categories'][$key]['name'] = $name; $config['categories'][$key]['description'] = $description;
            if (!admin_menu_save($config)) { throw new RuntimeException('カテゴリを保存できませんでした。'); }
            staff_administration_flash('success', 'カテゴリを更新しました。');
        } elseif ($action === 'move_category') {
            $key = (string) ($_POST['key'] ?? ''); $direction = (string) ($_POST['direction'] ?? '');
            if (!isset($config['categories'][$key]) || !in_array($direction, ['up','down'], true)) { throw new RuntimeException('移動内容が不正です。'); }
            $config['categories'] = admin_menu_move_category($config['categories'], $key, $direction);
            if (!admin_menu_save($config)) { throw new RuntimeException('表示順を保存できませんでした。'); }
            staff_administration_flash('success', 'カテゴリの表示順を変更しました。');
        } elseif ($action === 'delete_category') {
            $key = (string) ($_POST['key'] ?? '');
            if ($key === 'uncategorized' || !isset($config['categories'][$key])) { throw new RuntimeException('このカテゴリは削除できません。'); }
            unset($config['categories'][$key]); foreach ($config['assignments'] as $route => $assigned) { if ($assigned === $key) { $config['assignments'][$route] = 'uncategorized'; } }
            if (!admin_menu_save($config)) { throw new RuntimeException('カテゴリを削除できませんでした。'); }
            staff_administration_flash('success', 'カテゴリを削除しました。');
        } else { throw new RuntimeException('不明な操作です。'); }
    } catch (Throwable $exception) { staff_administration_flash('error', $exception->getMessage()); }
    staff_administration_redirect('/staff/admin/site/menu/');
}

$config = admin_menu_load(); $pages = admin_menu_detect_pages($config); $flash = staff_administration_take_flash();
staff_layout_start(['title'=>'管理メニュー設定','heading'=>'管理メニュー設定','eyebrow'=>'WEBSITE / ADMIN CATALOG','description'=>'自動検出される管理機能の説明、旧分類とカテゴリ表示順をスタッフ画面から管理します。']);
?>
<div class="ops-page admin-native-page"><?php if($flash):?><div class="ops-alert <?=$flash['type']==='success'?'ops-alert--success':''?>"><?=staff_ui_escape($flash['message'])?></div><?php endif;?>
<section class="ops-panel"><header class="ops-panel__header"><div><h3>ページ分類</h3><p>検出済み<?=count($pages)?>件。新しい旧管理ページも自動で一覧へ追加されます。</p></div></header><div class="ops-panel__body"><form method="post"><input type="hidden" name="csrf_token" value="<?=staff_ui_escape(csrf_token())?>"><input type="hidden" name="action" value="save_pages"><div class="admin-native-catalog-list"><?php foreach($pages as $page):?><article class="admin-native-catalog-row"><input type="hidden" name="routes[]" value="<?=staff_ui_escape($page['route'])?>"><div><strong><?=staff_ui_escape($page['title'])?></strong><span><?=staff_ui_escape($page['route'])?></span></div><label><span class="ops-label">カテゴリ</span><select class="ops-select" name="category_keys[]"><?php foreach($config['categories'] as $key=>$category):?><option value="<?=staff_ui_escape($key)?>" <?=$page['category']===$key?'selected':''?>><?=staff_ui_escape($category['name'])?></option><?php endforeach;?></select></label><label><span class="ops-label">説明</span><input class="ops-input" name="descriptions[]" maxlength="300" value="<?=staff_ui_escape($page['description'])?>"></label></article><?php endforeach;?></div><div class="ops-form-actions"><button class="ops-button" type="submit">ページ設定を保存</button></div></form></div></section>
<section class="ops-panel"><header class="ops-panel__header"><div><h3>カテゴリ管理</h3><p><?=count($config['categories'])?>カテゴリ</p></div></header><div class="ops-panel__body"><div class="admin-native-category-grid"><?php foreach($config['categories'] as $key=>$category):?><article class="admin-native-category-card"><form method="post"><input type="hidden" name="csrf_token" value="<?=staff_ui_escape(csrf_token())?>"><input type="hidden" name="action" value="update_category"><input type="hidden" name="key" value="<?=staff_ui_escape($key)?>"><span class="ops-muted"><?=staff_ui_escape($key)?></span><label><span class="ops-label">カテゴリ名</span><input class="ops-input" name="name" value="<?=staff_ui_escape($category['name'])?>" required></label><label><span class="ops-label">説明</span><textarea class="ops-textarea" name="description" rows="3"><?=staff_ui_escape($category['description']??'')?></textarea></label><div class="ops-form-actions"><button class="ops-button ops-button--secondary" type="submit">保存</button></div></form><div class="admin-native-category-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?=staff_ui_escape(csrf_token())?>"><input type="hidden" name="action" value="move_category"><input type="hidden" name="key" value="<?=staff_ui_escape($key)?>"><button class="ops-button ops-button--compact" name="direction" value="up">上へ</button><button class="ops-button ops-button--compact" name="direction" value="down">下へ</button></form><?php if($key!=='uncategorized'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=staff_ui_escape(csrf_token())?>"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="key" value="<?=staff_ui_escape($key)?>"><button class="ops-button ops-button--compact ops-button--danger" type="submit">削除</button></form><?php endif;?></div></article><?php endforeach;?></div></div></section>
<section class="ops-panel"><header class="ops-panel__header"><div><h3>カテゴリを追加</h3><p>新しい管理分野を登録します。</p></div></header><div class="ops-panel__body"><form method="post"><input type="hidden" name="csrf_token" value="<?=staff_ui_escape(csrf_token())?>"><input type="hidden" name="action" value="add_category"><div class="ops-form-grid"><label><span class="ops-label">カテゴリ名</span><input class="ops-input" name="name" required></label><label><span class="ops-label">説明</span><input class="ops-input" name="description"></label></div><div class="ops-form-actions"><button class="ops-button" type="submit">カテゴリを追加</button></div></form></div></section></div>
<?php staff_layout_end();
