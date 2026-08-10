<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/components/ui.php';

$staffUserId = (int) ($staffContext['user']['id'] ?? 0);
$staffDashboard = staff_dashboard_load($staffUserId);
$staffGreeting = staff_greeting();
$workspace = staff_workspace_normalize($staffWorkspacePreferences);
$widgetCatalog = staff_workspace_widget_catalog();
$workspaceGreeting = $workspace['custom_greeting'] !== ''
    ? $workspace['custom_greeting']
    : $staffGreeting['message'] . ' タスク、通知、社内連絡、担当業務をまとめて確認できます。';

$systemLinks = [
    ['HC物品管理センター', '商品、備品、IT資産、在庫、ロケーションを管理', '/staff/property/', 'inventory_2', 'HPMC'],
];
if (staff_has_permission($staffContext, 'support.tickets.view') || staff_can_access_admin($staffContext)) {
    $systemLinks[] = ['お問い合わせ', '概要・チャット・メールをまとめて対応', '/staff/support/', 'support_agent', 'SUPPORT'];
    $systemLinks[] = ['顧客管理', 'HCアカウント、契約、問い合わせを確認', '/staff/customers/', 'group', 'CUSTOMER'];
}
if (staff_has_permission($staffContext, 'orders.view') || staff_can_access_admin($staffContext)) {
    $systemLinks[] = ['レンタルサーバー', '申込、承認、作成、稼働状況を確認', '/staff/rental-server/', 'dns', 'SERVER'];
}

$categoryLinks = [
    'game-operations' => '/staff/rental-server/game-server/',
    'rental-server' => '/staff/rental-server/',
    'development' => '/staff/development/',
    'infrastructure' => '/staff/infrastructure/',
    'support' => '/staff/support/',
];

staff_layout_start([
    'title' => 'スタッフダッシュボード',
    'heading' => 'ダッシュボード',
    'eyebrow' => 'HC STAFF WORKSPACE',
    'description' => '担当業務、通知、システム状況を確認できます。',
    'active_navigation' => 'dashboard',
    'show_heading' => false,
    'workspace_background' => true,
]);
?>
<div
    class="staff-personal-dashboard staff-personal-dashboard--<?= staff_ui_escape($workspace['dashboard_layout']) ?> staff-personal-dashboard--panels-<?= staff_ui_escape($workspace['panel_style']) ?> <?= $workspace['compact_mode'] ? 'staff-personal-dashboard--compact' : '' ?>"
>
    <header class="staff-workspace-hero">
        <div class="staff-workspace-hero__copy">
            <p class="staff-page-heading__eyebrow">MY HC WORKSPACE</p>
            <h2><?= staff_ui_escape($staffGreeting['title']) ?>、<?= staff_ui_escape($staffDisplayName) ?>さん</h2>
            <p><?= staff_ui_escape($workspaceGreeting) ?></p>
        </div>
        <div class="staff-workspace-hero__actions">
            <span class="staff-page-heading__status"><span></span><?= staff_ui_escape($staffRoleName) ?></span>
            <a class="staff-workspace-customize" href="/staff/account/customize/"><span class="material-icons">palette</span>カスタマイズ</a>
        </div>
    </header>

    <div class="staff-widget-grid">
        <?php foreach ($workspace['widgets'] as $widget): ?>
            <?php if (!isset($widgetCatalog[$widget])) { continue; } ?>

            <?php if ($widget === 'summary'): ?>
                <section class="staff-dashboard-widget staff-dashboard-widget--summary" data-workspace-widget="summary">
                    <div class="staff-summary-grid">
                        <a href="/staff/tasks/?status=todo" class="staff-summary-card"><span>今日のタスク</span><strong><?= (int) $staffDashboard['counts']['todo'] ?></strong><small>未着手の担当業務</small></a>
                        <a href="/staff/tasks/?status=in_progress" class="staff-summary-card"><span>対応中</span><strong><?= (int) $staffDashboard['counts']['in_progress'] ?></strong><small>現在進行中の仕事</small></a>
                        <a href="/staff/tasks/?filter=overdue" class="staff-summary-card staff-summary-card--warning"><span>期限超過</span><strong><?= (int) $staffDashboard['counts']['overdue'] ?></strong><small>早めの確認が必要</small></a>
                        <a href="/staff/notifications/" class="staff-summary-card"><span>未読通知</span><strong><?= (int) $staffDashboard['counts']['notifications'] ?></strong><small>まだ確認していない通知</small></a>
                    </div>
                </section>

            <?php elseif ($widget === 'systems'): ?>
                <section class="staff-panel staff-dashboard-widget staff-dashboard-widget--systems" data-workspace-widget="systems">
                    <header class="staff-panel__header"><div><h3>業務システム</h3><p>担当業務に使用する管理センター</p></div></header>
                    <div class="staff-system-grid">
                        <?php foreach ($systemLinks as [$label, $description, $href, $icon, $badge]): ?>
                            <a class="staff-system-card" href="<?= staff_ui_escape($href) ?>"><span class="material-icons"><?= staff_ui_escape($icon) ?></span><span><strong><?= staff_ui_escape($label) ?></strong><small><?= staff_ui_escape($description) ?></small></span><em><?= staff_ui_escape($badge) ?></em></a>
                        <?php endforeach; ?>
                    </div>
                </section>

            <?php elseif ($widget === 'tasks'): ?>
                <section class="staff-panel staff-dashboard-widget staff-dashboard-widget--tasks" data-workspace-widget="tasks">
                    <header class="staff-panel__header"><div><h3>自分のタスク</h3><p>優先度と期限順に表示</p></div><a href="/staff/tasks/">すべて見る</a></header>
                    <div class="staff-list">
                        <?php if ($staffDashboard['tasks'] === []): ?>
                            <div class="staff-empty-state"><span class="material-icons">task_alt</span><strong>担当タスクはありません</strong><p>新しい仕事が割り当てられるとここに表示されます。</p></div>
                        <?php else: ?>
                            <?php foreach ($staffDashboard['tasks'] as $task): ?>
                                <a href="/staff/tasks/detail/?id=<?= (int) $task['id'] ?>" class="staff-list-row"><div><strong><?= staff_ui_escape($task['title']) ?></strong><p><?= staff_ui_escape($task['task_number']) ?> · <?= staff_ui_escape(staff_format_due_date($task['due_at'] ?? null)) ?></p></div><span class="staff-status staff-status--<?= staff_ui_escape($task['status']) ?>"><?= staff_ui_escape(staff_task_status_label((string) $task['status'])) ?></span></a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

            <?php elseif ($widget === 'announcements'): ?>
                <section class="staff-panel staff-dashboard-widget staff-dashboard-widget--announcements" data-workspace-widget="announcements">
                    <header class="staff-panel__header"><div><h3>社内連絡</h3><p>最新のお知らせ</p></div><a href="/staff/announcements/">一覧を見る</a></header>
                    <div class="staff-list">
                        <?php if ($staffDashboard['announcements'] === []): ?>
                            <div class="staff-empty-state"><span class="material-icons">campaign</span><strong>新しい社内連絡はありません</strong></div>
                        <?php else: ?>
                            <?php foreach ($staffDashboard['announcements'] as $announcement): ?>
                                <article class="staff-list-row"><div><strong><?= staff_ui_escape($announcement['title']) ?></strong><p><?= staff_ui_escape($announcement['body']) ?></p></div><?php if (!empty($announcement['requires_confirmation'])): ?><span class="staff-status staff-status--waiting">確認必須</span><?php endif; ?></article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

            <?php elseif ($widget === 'categories'): ?>
                <section class="staff-panel staff-dashboard-widget staff-dashboard-widget--categories" data-workspace-widget="categories">
                    <header class="staff-panel__header"><div><h3>担当業務</h3><p>カテゴリと権限から生成</p></div></header>
                    <div class="staff-category-grid">
                        <?php foreach ((array) ($staffContext['categories'] ?? []) as $category): $slug = (string) ($category['slug'] ?? ''); ?>
                            <a href="<?= staff_ui_escape($categoryLinks[$slug] ?? '/staff/') ?>" class="staff-category-card"><strong><?= staff_ui_escape($category['name']) ?></strong><small><?= staff_ui_escape($category['description'] ?? '') ?></small></a>
                        <?php endforeach; ?>
                        <?php if (staff_can_access_admin($staffContext)): ?><a href="/staff/admin/" class="staff-category-card"><strong>全体管理</strong><small>管理者向け業務機能</small></a><?php endif; ?>
                        <?php if (($staffContext['categories'] ?? []) === [] && !staff_can_access_admin($staffContext)): ?><div class="staff-empty-state staff-empty-state--small"><strong>担当カテゴリは未設定です</strong></div><?php endif; ?>
                    </div>
                </section>

            <?php elseif ($widget === 'custom_links' && $workspace['custom_links'] !== []): ?>
                <section class="staff-panel staff-dashboard-widget staff-dashboard-widget--custom-links" data-workspace-widget="custom_links">
                    <header class="staff-panel__header"><div><h3>マイリンク</h3><p>自分で追加したショートカット</p></div><a href="/staff/account/customize/#custom-links">編集</a></header>
                    <div class="staff-custom-link-grid">
                        <?php foreach ($workspace['custom_links'] as $customLink): ?>
                            <a class="staff-custom-link-card" href="<?= staff_ui_escape($customLink['url']) ?>"<?= $customLink['open_new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                                <span class="staff-custom-link-card__icon"><span class="material-icons"><?= staff_ui_escape($customLink['icon']) ?></span></span>
                                <span class="staff-custom-link-card__copy"><strong><?= staff_ui_escape($customLink['title']) ?></strong><small><?= staff_ui_escape($customLink['description'] !== '' ? $customLink['description'] : $customLink['url']) ?></small></span>
                                <span class="material-icons staff-custom-link-card__open"><?= $customLink['open_new_tab'] ? 'open_in_new' : 'arrow_forward' ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>

            <?php elseif ($widget === 'context'): ?>
                <section class="staff-panel staff-dashboard-widget staff-dashboard-widget--context" data-workspace-widget="context">
                    <header class="staff-panel__header"><div><h3>所属・権限</h3><p>現在有効なスタッフ情報</p></div><a href="/staff/account/permissions/">詳細</a></header>
                    <div class="staff-context-grid"><div><span>基本ロール</span><strong><?= staff_ui_escape($staffRoleName) ?></strong></div><div><span>カテゴリ</span><strong><?= count((array) ($staffContext['categories'] ?? [])) ?></strong></div><div><span>所属部署</span><strong><?= count((array) ($staffContext['departments'] ?? [])) ?></strong></div><div><span>有効権限</span><strong><?= count((array) ($staffContext['permissions'] ?? [])) ?></strong></div></div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($workspace['widgets'] === []): ?>
            <section class="staff-panel staff-dashboard-widget staff-dashboard-widget--empty"><div class="staff-empty-state"><span class="material-icons">dashboard_customize</span><strong>表示するウィジェットがありません</strong><p>カスタマイズ画面から好きな項目を追加できます。</p><a class="staff-workspace-customize" href="/staff/account/customize/">ウィジェットを追加</a></div></section>
        <?php endif; ?>
    </div>
</div>
<?php staff_layout_end();
