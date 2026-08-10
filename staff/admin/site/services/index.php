<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/lib/administration.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';
require_once dirname(__DIR__, 3) . '/components/ui.php';

staff_administration_require_admin($staffContext);
$pdo = staff_db();
$statuses = ['draft' => '下書き', 'published' => '公開', 'hidden' => '非公開'];
$phases = ['available' => '提供中', 'developing' => '開発中', 'planned' => '計画中'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) { throw new RuntimeException('操作の有効期限が切れました。'); }
        $title = trim((string) ($_POST['title'] ?? '')); $slug = trim((string) ($_POST['slug'] ?? '')); $label = trim((string) ($_POST['label'] ?? '')); $summary = trim((string) ($_POST['summary'] ?? ''));
        $phase = (string) ($_POST['service_phase'] ?? 'planned'); $status = (string) ($_POST['status'] ?? 'draft'); $sort = (int) ($_POST['sort_order'] ?? 0); $hasDetail = isset($_POST['has_detail_page']); $detailUrl = trim((string) ($_POST['detail_url'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) { throw new RuntimeException('事業名を160文字以内で入力してください。'); }
        if (!preg_match('/^[a-z0-9-]+$/', $slug) || mb_strlen($slug) > 120) { throw new RuntimeException('slugは半角英数字とハイフンで入力してください。'); }
        if ($summary === '') { throw new RuntimeException('概要を入力してください。'); }
        if (!isset($phases[$phase]) || !isset($statuses[$status])) { throw new RuntimeException('状態の指定が不正です。'); }
        if ($hasDetail && ($detailUrl === '' || (!str_starts_with($detailUrl, '/') && !filter_var($detailUrl, FILTER_VALIDATE_URL)))) { throw new RuntimeException('詳細ページURLを正しく入力してください。'); }
        $dup = $pdo->prepare('SELECT id FROM services WHERE slug = :slug AND id <> :id'); $dup->execute(['slug' => $slug, 'id' => $id]);
        if ($dup->fetch()) { throw new RuntimeException('このslugはすでに使用されています。'); }
        $params = ['title' => $title, 'slug' => $slug, 'label' => $label !== '' ? $label : null, 'summary' => $summary, 'phase' => $phase, 'detail' => $hasDetail ? 'true' : 'false', 'url' => $hasDetail ? $detailUrl : null, 'status' => $status, 'sort' => $sort];
        if ($id > 0) {
            $params['id'] = $id;
            $stmt = $pdo->prepare('UPDATE services SET title=:title, slug=:slug, label=:label, summary=:summary, service_phase=:phase, has_detail_page=CAST(:detail AS BOOLEAN), detail_url=:url, status=:status, sort_order=:sort, updated_at=NOW() WHERE id=:id');
            $stmt->execute($params);
            staff_administration_flash('success', '事業・サービス情報を更新しました。');
        } else {
            $stmt = $pdo->prepare('INSERT INTO services (title,slug,label,summary,service_phase,has_detail_page,detail_url,status,sort_order,created_at,updated_at) VALUES (:title,:slug,:label,:summary,:phase,CAST(:detail AS BOOLEAN),:url,:status,:sort,NOW(),NOW()) RETURNING id');
            $stmt->execute($params); $id = (int) $stmt->fetchColumn();
            staff_administration_flash('success', '事業・サービス情報を追加しました。');
        }
    } catch (Throwable $exception) { staff_administration_flash('error', $exception->getMessage()); }
    staff_administration_redirect('/staff/admin/site/services/?id=' . max(0, $id));
}

$query = trim((string) ($_GET['q'] ?? '')); $filterStatus = (string) ($_GET['status'] ?? ''); $filterPhase = (string) ($_GET['phase'] ?? '');
$where = ['1=1']; $params = [];
if ($query !== '') { $where[] = '(title ILIKE :q OR slug ILIKE :q OR label ILIKE :q OR summary ILIKE :q)'; $params['q'] = '%' . $query . '%'; }
if (isset($statuses[$filterStatus])) { $where[] = 'status=:status'; $params['status'] = $filterStatus; } else { $filterStatus = ''; }
if (isset($phases[$filterPhase])) { $where[] = 'service_phase=:phase'; $params['phase'] = $filterPhase; } else { $filterPhase = ''; }
$stmt = $pdo->prepare('SELECT * FROM services WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order,id'); $stmt->execute($params); $services = $stmt->fetchAll() ?: [];
$selectedId = (int) ($_GET['id'] ?? 0); $selected = null;
if ($selectedId > 0) { $stmt = $pdo->prepare('SELECT * FROM services WHERE id=:id'); $stmt->execute(['id' => $selectedId]); $selected = $stmt->fetch() ?: null; }
$form = $selected ?: ['id'=>0,'title'=>'','slug'=>'','label'=>'','summary'=>'','service_phase'=>'planned','has_detail_page'=>false,'detail_url'=>'','status'=>'draft','sort_order'=>0];
$flash = staff_administration_take_flash();

staff_layout_start(['title'=>'事業・サービス掲載','heading'=>'事業・サービス掲載','eyebrow'=>'WEBSITE / SERVICES','description'=>'公開サイトに掲載する事業カード、提供状況、公開状態と詳細ページを管理します。']);
?>
<div class="ops-page admin-native-page">
    <?php if ($flash): ?><div class="ops-alert <?= $flash['type']==='success'?'ops-alert--success':'' ?>"><?= staff_ui_escape($flash['message']) ?></div><?php endif; ?>
    <form class="ops-toolbar" method="get"><label class="ops-toolbar__field"><span class="ops-label">検索</span><input class="ops-input" name="q" value="<?= staff_ui_escape($query) ?>" placeholder="事業名・slug・概要"></label><label><span class="ops-label">提供状況</span><select class="ops-select" name="phase"><option value="">すべて</option><?php foreach($phases as $value=>$label):?><option value="<?=$value?>" <?=$filterPhase===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><label><span class="ops-label">公開状態</span><select class="ops-select" name="status"><option value="">すべて</option><?php foreach($statuses as $value=>$label):?><option value="<?=$value?>" <?=$filterStatus===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><button class="ops-button" type="submit">検索</button><a class="ops-button ops-button--secondary" href="/staff/admin/site/services/">新規追加</a></form>
    <div class="ops-split"><section class="ops-list"><header class="ops-panel__header"><div><h3>掲載サービス</h3><p><?=count($services)?>件</p></div></header><div class="ops-rows"><?php foreach($services as $service):?><a class="ops-row <?= (int)$service['id']===$selectedId?'is-selected':'' ?>" href="?id=<?=(int)$service['id']?>"><span><strong class="ops-row__title"><?=staff_ui_escape($service['title'])?></strong><span class="ops-row__meta"><?=staff_ui_escape($service['slug'])?> / <?=staff_ui_escape($service['summary'])?></span></span><span class="ops-row__end"><span class="ops-status ops-status--<?=staff_ui_escape($service['status'])?>"><?=staff_ui_escape($statuses[$service['status']]??$service['status'])?></span><span class="ops-row__meta"><?=staff_ui_escape($phases[$service['service_phase']]??$service['service_phase'])?></span></span></a><?php endforeach;?></div></section>
        <section class="ops-detail"><header class="ops-panel__header"><div><h3><?= $selected?'掲載内容を編集':'新しいサービスを追加' ?></h3><p><?= $selected?'サービス #'.(int)$selected['id']:'公開前は下書きで保存できます。' ?></p></div></header><div class="ops-panel__body"><form method="post" class="admin-native-form"><input type="hidden" name="csrf_token" value="<?=staff_ui_escape(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$form['id']?>"><div class="ops-form-grid"><label><span class="ops-label">事業名</span><input class="ops-input" name="title" maxlength="160" value="<?=staff_ui_escape($form['title'])?>" required></label><label><span class="ops-label">slug</span><input class="ops-input" name="slug" maxlength="120" value="<?=staff_ui_escape($form['slug'])?>" required></label><label><span class="ops-label">英語ラベル</span><input class="ops-input" name="label" maxlength="100" value="<?=staff_ui_escape($form['label'])?>"></label><label><span class="ops-label">表示順</span><input class="ops-input" type="number" name="sort_order" value="<?=(int)$form['sort_order']?>"></label><label><span class="ops-label">提供状況</span><select class="ops-select" name="service_phase"><?php foreach($phases as $value=>$label):?><option value="<?=$value?>" <?=$form['service_phase']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><label><span class="ops-label">公開状態</span><select class="ops-select" name="status"><?php foreach($statuses as $value=>$label):?><option value="<?=$value?>" <?=$form['status']===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label></div><label class="admin-native-field"><span class="ops-label">概要</span><textarea class="ops-textarea" name="summary" rows="5" required><?=staff_ui_escape($form['summary'])?></textarea></label><label class="ops-check"><input type="checkbox" name="has_detail_page" value="1" <?=!empty($form['has_detail_page'])?'checked':''?>>詳細ページあり</label><label class="admin-native-field"><span class="ops-label">詳細ページURL</span><input class="ops-input" name="detail_url" value="<?=staff_ui_escape($form['detail_url'])?>" placeholder="/services/example/"></label><div class="ops-form-actions"><button class="ops-button" type="submit"><?= $selected?'変更を保存':'サービスを追加' ?></button></div></form></div></section>
    </div>
</div>
<?php staff_layout_end();
