<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/operations.php';
require_once dirname(__DIR__, 2) . '/lib/csrf.php';
require_once dirname(__DIR__) . '/components/layout.php';
require_once dirname(__DIR__) . '/components/ui.php';

staff_require_permission($staffContext, 'support.tickets.view');

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$selectedId = max(0, (int) ($_GET['id'] ?? 0));
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = '確認情報の有効期限が切れました。再読み込みしてお試しください。';
    } elseif (!staff_has_permission($staffContext, 'staff.users.edit') && !staff_can_access_admin($staffContext)) {
        $error = '顧客状態を変更する権限がありません。';
    } else {
        $targetId = max(0, (int) ($_POST['customer_id'] ?? 0));
        $newStatus = trim((string) ($_POST['customer_status'] ?? ''));
        if ($targetId <= 0 || !in_array($newStatus, ['active', 'inactive', 'suspended'], true)) {
            $error = '更新内容が正しくありません。';
        } elseif ($targetId === $staffAccountId && $newStatus !== 'active') {
            $error = '操作中の自分のアカウントは停止できません。';
        } else {
            try {
                $statement = staff_db()->prepare('SELECT status FROM users WHERE id = :id LIMIT 1');
                $statement->execute(['id' => $targetId]);
                $oldStatus = (string) ($statement->fetchColumn() ?: '');
                $statement = staff_db()->prepare('UPDATE users SET status = :status WHERE id = :id');
                $statement->execute(['status' => $newStatus, 'id' => $targetId]);
                staff_ops_audit(
                    (int) ($staffUser['id'] ?? 0),
                    'customer.status.update',
                    'user',
                    $targetId,
                    '顧客アカウント状態を更新しました。',
                    ['status' => $oldStatus],
                    ['status' => $newStatus]
                );
                $selectedId = $targetId;
                $message = '顧客アカウントの状態を更新しました。';
            } catch (Throwable $exception) {
                $error = '顧客状態を更新できませんでした。';
            }
        }
    }
}

$data = staff_customers_load($search, $status, $selectedId);
$selected = $data['selected'];
$baseParams = array_filter(['q' => $search, 'status' => $status], static fn ($value): bool => $value !== '');

staff_layout_start([
    'title' => '顧客管理',
    'heading' => '顧客管理',
    'eyebrow' => 'CUSTOMER MANAGEMENT',
    'description' => 'HCアカウント、契約、問い合わせ履歴を一つの画面で確認します。',
]);
?>
<div class="ops-page">
    <?php if ($message !== ''): ?><div class="ops-alert ops-alert--success"><?= staff_ui_escape($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="ops-alert"><?= staff_ui_escape($error) ?></div><?php endif; ?>
    <?php foreach ($data['errors'] as $loadError): ?><div class="ops-alert"><?= staff_ui_escape($loadError) ?></div><?php endforeach; ?>

    <section class="ops-summary" aria-label="顧客集計">
        <article class="ops-card"><span class="ops-card__label">登録アカウント</span><strong><?= number_format($data['counts']['total']) ?></strong><small>削除済みを含む登録数</small></article>
        <article class="ops-card"><span class="ops-card__label">有効アカウント</span><strong><?= number_format($data['counts']['active']) ?></strong><small>現在ログイン可能</small></article>
        <article class="ops-card"><span class="ops-card__label">契約あり</span><strong><?= number_format($data['counts']['with_contracts']) ?></strong><small>ゲームサーバー申込あり</small></article>
        <article class="ops-card"><span class="ops-card__label">停止中</span><strong><?= number_format($data['counts']['suspended']) ?></strong><small>確認が必要なアカウント</small></article>
    </section>

    <form class="ops-toolbar" method="get">
        <label class="ops-toolbar__field"><span class="ops-label">顧客を検索</span><input class="ops-input" type="search" name="q" value="<?= staff_ui_escape($search) ?>" placeholder="名前・メール・顧客ID"></label>
        <label class="ops-toolbar__field"><span class="ops-label">アカウント状態</span><select class="ops-select" name="status"><option value="">すべて</option><?php foreach (['active' => '有効', 'inactive' => '無効', 'suspended' => '停止中'] as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <button class="ops-button" type="submit"><span class="material-icons">search</span>検索</button>
        <a class="ops-button ops-button--secondary" href="/staff/customers/">クリア</a>
    </form>

    <div class="ops-split">
        <section class="ops-list">
            <header class="ops-panel__header"><div><h3>顧客一覧</h3><p><?= number_format(count($data['customers'])) ?>件を表示</p></div></header>
            <div class="ops-rows">
                <?php foreach ($data['customers'] as $customer):
                    $params = $baseParams + ['id' => (int) $customer['id']];
                    $isSelected = (int) ($selected['id'] ?? 0) === (int) $customer['id'];
                ?>
                    <a class="ops-row <?= $isSelected ? 'is-selected' : '' ?>" href="?<?= staff_ui_escape(http_build_query($params)) ?>">
                        <span><strong class="ops-row__title"><?= staff_ui_escape($customer['username']) ?></strong><span class="ops-row__meta"><?= staff_ui_escape($customer['email']) ?><br>契約 <?= number_format((int) $customer['contract_count']) ?>件・問い合わせ <?= number_format((int) $customer['contact_count']) ?>件</span></span>
                        <span class="ops-row__end"><span class="ops-status ops-status--<?= staff_ui_escape($customer['status']) ?>"><?= staff_ui_escape(staff_ops_user_status_label((string) $customer['status'])) ?></span><span class="ops-row__meta">#<?= (int) $customer['id'] ?></span></span>
                    </a>
                <?php endforeach; ?>
                <?php if ($data['customers'] === []): ?><div class="ops-empty">条件に一致する顧客はいません。</div><?php endif; ?>
            </div>
        </section>

        <section class="ops-detail">
            <?php if (is_array($selected)): ?>
                <header class="ops-panel__header"><div><h3><?= staff_ui_escape($selected['username']) ?></h3><p>顧客ID #<?= (int) $selected['id'] ?> のアカウントと利用状況</p></div><span class="ops-status ops-status--<?= staff_ui_escape($selected['status']) ?>"><?= staff_ui_escape(staff_ops_user_status_label((string) $selected['status'])) ?></span></header>
                <div class="ops-panel__body">
                    <dl class="ops-kv">
                        <div><dt>メールアドレス</dt><dd><?= staff_ui_escape($selected['email']) ?></dd></div>
                        <div><dt>メール認証</dt><dd><?= !empty($selected['email_verified']) ? '認証済み' : '未認証' ?></dd></div>
                        <div><dt>登録日時</dt><dd><?= staff_ui_escape(staff_ops_datetime($selected['created_at'] ?? null)) ?></dd></div>
                        <div><dt>最終ログイン</dt><dd><?= staff_ui_escape(staff_ops_datetime($selected['last_login'] ?? null)) ?></dd></div>
                    </dl>

                    <?php if (staff_has_permission($staffContext, 'staff.users.edit') || staff_can_access_admin($staffContext)): ?>
                    <div class="ops-section"><h4>アカウント状態</h4><form class="ops-form-grid" method="post"><input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>"><input type="hidden" name="customer_id" value="<?= (int) $selected['id'] ?>"><label><span class="ops-label">状態</span><select class="ops-select" name="customer_status"><?php foreach (['active' => '有効', 'inactive' => '無効', 'suspended' => '停止中'] as $value => $label): ?><option value="<?= $value ?>" <?= ($selected['status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><div class="ops-form-actions"><button class="ops-button" type="submit">状態を保存</button></div></form></div>
                    <?php endif; ?>

                    <div class="ops-section"><h4>契約・申込</h4><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>ID</th><th>サーバー</th><th>プラン</th><th>契約状態</th><th>決済</th><th>金額</th></tr></thead><tbody><?php foreach ($data['orders'] as $order): ?><tr><td><a href="/staff/rental-server/game-server/contracts/?id=<?= (int) $order['id'] ?>">#<?= (int) $order['id'] ?></a></td><td><?= staff_ui_escape($order['server_name']) ?></td><td><?= staff_ui_escape($order['plan_name'] ?? '-') ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($order['status']) ?>"><?= staff_ui_escape(staff_rental_order_status_label((string) $order['status'])) ?></span></td><td><?= staff_ui_escape(staff_rental_payment_status_label((string) $order['payment_status'])) ?></td><td><?= staff_ui_escape(staff_rental_price($order['amount'], $order['currency'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($data['orders'] === []): ?><div class="ops-empty">契約・申込はありません。</div><?php endif; ?></div>

                    <div class="ops-section"><h4>お問い合わせ履歴</h4><div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>ID</th><th>件名</th><th>状態</th><th>受付日時</th></tr></thead><tbody><?php foreach ($data['contacts'] as $contact): ?><tr><td><a href="/staff/support/?view=overview&amp;id=<?= (int) $contact['id'] ?>">#<?= (int) $contact['id'] ?></a></td><td><?= staff_ui_escape($contact['subject']) ?></td><td><span class="ops-status ops-status--<?= staff_ui_escape($contact['status']) ?>"><?= staff_ui_escape($contact['status']) ?></span></td><td><?= staff_ui_escape(staff_ops_datetime($contact['created_at'] ?? null)) ?></td></tr><?php endforeach; ?></tbody></table></div><?php if ($data['contacts'] === []): ?><div class="ops-empty">お問い合わせ履歴はありません。</div><?php endif; ?></div>
                </div>
            <?php else: ?><div class="ops-empty">左の一覧から顧客を選択してください。</div><?php endif; ?>
        </section>
    </div>
</div>
<?php staff_layout_end();
