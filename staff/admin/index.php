<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/administration.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

if (!staff_can_access_admin($staffContext)) {
    http_response_code(403);
    exit('このページは上位管理者のみ利用できます。');
}

$sections = staff_administration_sections();
$section = trim((string) ($_GET['section'] ?? 'all'));
if ($section !== 'all' && !isset($sections[$section])) {
    $section = 'all';
}
$query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100, 'UTF-8');
$allModules = staff_administration_catalog();
$modules = staff_administration_filter_modules($allModules, $section, $query);
$groupedModules = staff_administration_group_modules($modules);
$overview = staff_administration_overview();
$staffModuleCount = count(array_filter($allModules, static fn(array $module): bool => $module['surface'] === 'staff'));
$legacyModuleCount = count($allModules) - $staffModuleCount;

staff_layout_start([
    'title' => '上位管理センター',
    'heading' => '上位管理センター',
    'eyebrow' => 'HC PLATFORM CONTROL CENTER',
    'description' => 'Webサイト全体、各サービス、顧客、契約、決済、スタッフとシステムを一か所から管理します。',
]);
?>
<div class="ops-page admin-hub">
    <section class="admin-hub__notice">
        <span class="admin-hub__notice-icon"><?= staff_icon('verified_user', '', 24) ?></span>
        <div>
            <strong>上位管理者専用ワークスペース</strong>
            <p>既存の管理機能はすべてスタッフコンソールへ統合済みです。旧URLからも対応するスタッフ版へ自動で移動し、今後追加された管理機能も自動検出します。</p>
        </div>
    </section>

    <section class="ops-summary">
        <article class="ops-card"><span class="ops-card__label">管理機能</span><strong><?= number_format(count($allModules)) ?></strong><small>追加機能も自動検出</small></article>
        <article class="ops-card"><span class="ops-card__label">スタッフ版へ統合</span><strong><?= number_format($staffModuleCount) ?></strong><small>共通UIで利用可能</small></article>
        <article class="ops-card"><span class="ops-card__label">公開サービス</span><strong><?= number_format($overview['services']) ?></strong><small>現在公開中</small></article>
        <article class="ops-card ops-card--attention"><span class="ops-card__label">要確認</span><strong><?= number_format($overview['attention']) ?></strong><small>契約処理・問い合わせ</small></article>
    </section>

    <section class="admin-hub__metrics" aria-label="運用状況">
        <span><?= staff_icon('group', '', 17) ?><strong><?= number_format($overview['customers']) ?></strong> HCアカウント</span>
        <span><?= staff_icon('dns', '', 17) ?><strong><?= number_format($overview['active_orders']) ?></strong> 稼働契約</span>
        <span><?= staff_icon('check_circle', '', 17) ?><strong><?= number_format($legacyModuleCount) ?></strong> 旧UI残り</span>
    </section>

    <form class="ops-toolbar admin-hub__toolbar" method="get" action="/staff/admin/">
        <label class="ops-toolbar__field">
            <span class="ops-label">管理機能を検索</span>
            <input class="ops-input" type="search" name="q" value="<?= staff_ui_escape($query) ?>" placeholder="例：通知、請求、サーバー、スタッフ">
        </label>
        <label class="ops-toolbar__field admin-hub__section-field">
            <span class="ops-label">管理範囲</span>
            <select class="ops-select" name="section">
                <option value="all">すべての管理機能</option>
                <?php foreach ($sections as $key => $sectionDefinition): ?>
                    <option value="<?= staff_ui_escape($key) ?>" <?= $section === $key ? 'selected' : '' ?>><?= staff_ui_escape($sectionDefinition['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="ops-button" type="submit"><?= staff_icon('search', '', 17) ?>表示</button>
        <?php if ($query !== '' || $section !== 'all'): ?><a class="ops-button ops-button--secondary" href="/staff/admin/">リセット</a><?php endif; ?>
    </form>

    <nav class="admin-hub__sections" aria-label="管理範囲">
        <a href="/staff/admin/" class="<?= $section === 'all' ? 'is-active' : '' ?>">すべて <small><?= count($allModules) ?></small></a>
        <?php foreach ($sections as $key => $sectionDefinition): ?>
            <?php $count = count(array_filter($allModules, static fn(array $module): bool => $module['section'] === $key)); ?>
            <a href="/staff/admin/?section=<?= staff_ui_escape($key) ?>" class="<?= $section === $key ? 'is-active' : '' ?>"><?= staff_ui_escape($sectionDefinition['label']) ?> <small><?= $count ?></small></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($modules === []): ?>
        <div class="ops-empty">条件に一致する管理機能はありません。検索語または管理範囲を変更してください。</div>
    <?php endif; ?>

    <?php foreach ($sections as $sectionKey => $sectionDefinition): ?>
        <?php $sectionModules = $groupedModules[$sectionKey] ?? []; ?>
        <?php if ($sectionModules === []): continue; endif; ?>
        <section class="admin-hub__group">
            <header class="admin-hub__group-header">
                <span class="admin-hub__group-icon"><?= staff_icon((string) $sectionDefinition['icon'], '', 22) ?></span>
                <div><h3><?= staff_ui_escape($sectionDefinition['label']) ?></h3><p><?= staff_ui_escape($sectionDefinition['description']) ?></p></div>
                <strong><?= number_format(count($sectionModules)) ?>機能</strong>
            </header>
            <div class="admin-hub__grid">
                <?php foreach ($sectionModules as $module): ?>
                    <a class="admin-hub-card" href="<?= staff_ui_escape($module['destination']) ?>">
                        <span class="admin-hub-card__icon"><?= staff_icon((string) $module['icon'], '', 21) ?></span>
                        <span class="admin-hub-card__content">
                            <span class="admin-hub-card__badges">
                                <small class="admin-hub-badge <?= $module['surface'] === 'staff' ? 'is-staff' : 'is-legacy' ?>"><?= $module['surface'] === 'staff' ? 'スタッフ版' : '段階移行中' ?></small>
                                <?php if (!empty($module['auto_detected'])): ?><small class="admin-hub-badge is-auto">自動検出</small><?php endif; ?>
                            </span>
                            <strong><?= staff_ui_escape($module['title']) ?></strong>
                            <span><?= staff_ui_escape($module['description']) ?></span>
                        </span>
                        <span class="admin-hub-card__arrow"><?= staff_icon('arrow_forward', '', 18) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php staff_layout_end();
