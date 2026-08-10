<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../components/layout.php';
require_once __DIR__ . '/../components/ui.php';
require_once __DIR__ . '/../../lib/csrf.php';

staff_require_permission(
    $staffContext,
    'support.tickets.view'
);

$pdo = staff_db();
$schemaReady = staff_support_schema_ready($pdo);
$canReply = staff_has_permission(
    $staffContext,
    'support.tickets.reply'
) || staff_can_access_admin($staffContext);
$view = trim((string) ($_GET['view'] ?? $_POST['return_view'] ?? 'overview'));

if (!in_array($view, ['overview', 'chat', 'email'], true)) {
    $view = 'overview';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactId = filter_input(
        INPUT_POST,
        'contact_id',
        FILTER_VALIDATE_INT
    );
    $contactId = is_int($contactId) ? $contactId : 0;
    $action = trim((string) ($_POST['action'] ?? ''));

    try {
        if (!$canReply) {
            throw new RuntimeException(
                'この操作を行う権限がありません。'
            );
        }

        if (!csrf_check($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException(
                'セッションを確認できませんでした。もう一度お試しください。'
            );
        }

        $ticket = staff_support_find($pdo, $contactId);

        if ($ticket === null) {
            throw new RuntimeException(
                'お問い合わせが見つかりません。'
            );
        }

        $auditAction = '';
        $auditDescription = '';
        $auditData = [];
        $flashMessage = '更新しました。';
        $flashType = 'success';

        if ($action === 'status') {
            $status = trim((string) ($_POST['status'] ?? ''));

            staff_support_update_status(
                $pdo,
                $contactId,
                $status
            );

            $auditAction = 'support.contact.status_updated';
            $auditDescription = 'お問い合わせの対応状況を変更';
            $auditData = ['status' => $status];
            $flashMessage = '対応状況を「'
                . staff_support_status_label($status)
                . '」に変更しました。';
        } elseif ($action === 'assign') {
            $assignedValue = trim(
                (string) ($_POST['assigned_to'] ?? '')
            );
            $assignedTo = $assignedValue === ''
                ? null
                : filter_var(
                    $assignedValue,
                    FILTER_VALIDATE_INT
                );

            if (
                $assignedValue !== ''
                && !is_int($assignedTo)
            ) {
                throw new InvalidArgumentException(
                    '担当者の値が正しくありません。'
                );
            }

            staff_support_assign(
                $pdo,
                $contactId,
                $assignedTo
            );

            $auditAction = 'support.contact.assigned';
            $auditDescription = 'お問い合わせの担当者を変更';
            $auditData = ['assigned_to' => $assignedTo];
            $flashMessage = '担当者を変更しました。';
        } elseif ($action === 'message') {
            $mode = trim((string) ($_POST['mode'] ?? 'reply'));
            $deliveryChannel = trim(
                (string) ($_POST['delivery_channel'] ?? 'chat')
            );
            $body = (string) ($_POST['body'] ?? '');

            $result = staff_support_add_message(
                $pdo,
                $ticket,
                $staffAccountId,
                $body,
                $mode,
                $deliveryChannel
            );

            $auditAction = $mode === 'note'
                ? 'support.contact.note_added'
                : 'support.contact.replied';
            $auditDescription = $mode === 'note'
                ? 'お問い合わせへ社内メモを追加'
                : 'お問い合わせへ返信';
            $auditData = [
                'message_id' => $result['message_id'] ?? null,
                'delivery_status' => $result['delivery_status'] ?? null,
                'delivery_channel' => $result['delivery_channel'] ?? null,
            ];

            if ($mode === 'note') {
                $flashMessage = '社内メモを追加しました。';
            } elseif (
                ($result['delivery_status'] ?? '') === 'failed'
            ) {
                $flashType = 'error';
                $flashMessage = ($result['delivery_channel'] ?? '') === 'chat'
                    ? 'チャット履歴は保存しましたが、HCアカウントへの通知に失敗しました。'
                    : '返信履歴は保存しましたが、メール送信に失敗しました。';
            } elseif (
                ($result['delivery_status'] ?? '') === 'logged'
            ) {
                $flashMessage = '返信を保存しました。開発環境のためメールログへ出力しています。';
            } else {
                $flashMessage = ($result['delivery_channel'] ?? '') === 'chat'
                    ? 'HCアカウントの個別チャットへ送信しました。'
                    : 'お問い合わせメールアドレスへ返信を送信しました。';
            }
        } else {
            throw new InvalidArgumentException(
                '操作内容が正しくありません。'
            );
        }

        staff_support_audit(
            $pdo,
            (int) ($staffContext['user']['id'] ?? 0),
            $auditAction,
            $contactId,
            $auditDescription,
            $auditData
        );

        $_SESSION['staff_support_flash'] = [
            'type' => $flashType,
            'message' => $flashMessage,
        ];
    } catch (Throwable $error) {
        $safeMessage = $error instanceof InvalidArgumentException
            || (
                $error instanceof RuntimeException
                && !$error instanceof PDOException
            )
            ? $error->getMessage()
            : '更新中にエラーが発生しました。もう一度お試しください。';

        if ($safeMessage !== $error->getMessage()) {
            error_log(
                '[staff/support] ' . $error->getMessage()
            );
        }

        $_SESSION['staff_support_flash'] = [
            'type' => 'error',
            'message' => $safeMessage,
        ];
    }

    $returnPath = match ($view) {
        'chat' => '/staff/support/chat/',
        'email' => '/staff/support/email/',
        default => '/staff/support/',
    };

    header(
        'Location: ' . $returnPath
            . '?id=' . max(0, $contactId)
    );
    exit;
}

$allowedFilters = [
    'all',
    ...array_keys(staff_support_statuses()),
];
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

$query = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($query) > 120) {
    $query = mb_substr($query, 0, 120);
}

$supportChannel = match ($view) {
    'chat' => 'chat',
    'email' => 'email',
    default => 'all',
};
$counts = staff_support_counts($pdo, $supportChannel);
$tickets = staff_support_list(
    $pdo,
    $statusFilter,
    $query,
    $schemaReady,
    $supportChannel
);
$staffOptions = staff_support_staff_options($pdo);

$requestedId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);
$selectedId = is_int($requestedId)
    ? $requestedId
    : 0;
$selected = staff_support_find($pdo, $selectedId);

if (
    $view === 'chat'
    && $selected !== null
    && empty($selected['user_id'])
) {
    $selected = null;
}
$messages = $selected !== null
    ? staff_support_messages(
        $pdo,
        $selected,
        $schemaReady,
        $supportChannel
    )
    : [];
$emailMessages = array_values(array_filter(
    $messages,
    static fn (array $message): bool => (
        ($message['visibility'] ?? 'public') !== 'internal'
        && in_array(
            (string) ($message['delivery_channel'] ?? 'imported'),
            ['email', 'imported'],
            true
        )
    )
));

$flash = $_SESSION['staff_support_flash'] ?? null;
unset($_SESSION['staff_support_flash']);

$statusTabs = [
    'all' => 'すべて',
    ...staff_support_statuses(),
];
$priorityLabels = [
    'high' => '優先',
    'normal' => '通常',
    'low' => '低',
];

$isEmailView = $view === 'email';
$isChatView = $view === 'chat';
$supportPageTitle = $isEmailView
    ? 'サポートメール'
    : ($isChatView ? 'サポートチャット' : 'お問い合わせ');
$supportPagePath = $isEmailView
    ? '/staff/support/email/'
    : '/staff/support/chat/';

staff_layout_start([
    'title' => $supportPageTitle,
    'heading' => $supportPageTitle,
    'show_heading' => false,
]);

?>
<?php if ($view === 'overview'): ?>
<section
    class="staff-support support-overview"
    data-support-root
    data-support-view="overview"
    data-support-ticket-id="<?= (int) $selectedId ?>"
    data-support-selected="<?= $selected !== null ? '1' : '0' ?>"
>
    <header class="support-page-header support-overview-header">
        <div>
            <p>CONTACT OVERVIEW</p>
            <h2>お問い合わせ</h2>
            <span>
                まずお問い合わせ内容と顧客情報を確認し、チャットまたはメールで対応します。
            </span>
        </div>

        <div class="support-metrics" aria-label="お問い合わせ対応状況">
            <div><span>未対応</span><strong><?= (int) $counts['open'] ?></strong></div>
            <div><span>対応中</span><strong><?= (int) $counts['in_progress'] ?></strong></div>
            <div><span>返信待ち</span><strong><?= (int) $counts['waiting'] ?></strong></div>
            <div><span>本日の解決</span><strong><?= (int) $counts['resolved_today'] ?></strong></div>
        </div>
    </header>

    <?php if (is_array($flash)): ?>
        <div class="support-flash support-flash--<?= staff_ui_escape(
            ($flash['type'] ?? '') === 'error' ? 'error' : 'success'
        ) ?>" role="status">
            <?= staff_icon(
                ($flash['type'] ?? '') === 'error' ? 'error_outline' : 'check_circle',
                '',
                19
            ) ?>
            <span><?= staff_ui_escape($flash['message'] ?? '') ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="support-flash support-flash--warning" role="status">
            <?= staff_icon('database', '', 19) ?>
            <span>返信機能を使うには、最新のDBマイグレーションを適用してください。</span>
        </div>
    <?php endif; ?>

    <div class="support-toolbar">
        <nav class="support-tabs" aria-label="対応状況で絞り込み">
            <?php foreach ($statusTabs as $value => $label): ?>
                <?php
                $overviewTabQuery = ['view' => 'overview', 'status' => $value];
                if ($query !== '') {
                    $overviewTabQuery['q'] = $query;
                }
                ?>
                <a
                    href="/staff/support/?<?= staff_ui_escape(http_build_query($overviewTabQuery)) ?>"
                    class="<?= $statusFilter === $value ? 'is-active' : '' ?>"
                    <?= $statusFilter === $value ? 'aria-current="page"' : '' ?>
                >
                    <?= staff_ui_escape($label) ?>
                    <span><?= (int) ($counts[$value] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <form class="support-search" method="get" action="/staff/support/">
            <input type="hidden" name="view" value="overview">
            <input type="hidden" name="status" value="<?= staff_ui_escape($statusFilter) ?>">
            <?= staff_icon('search', '', 19) ?>
            <input
                type="search"
                name="q"
                value="<?= staff_ui_escape($query) ?>"
                placeholder="名前・内容・IDで検索"
                aria-label="お問い合わせを検索"
            >
        </form>
    </div>

    <div class="support-overview-shell">
        <aside class="support-overview-list" aria-label="お問い合わせ一覧">
            <header class="support-pane-heading">
                <div><h3>お問い合わせ一覧</h3><span><?= count($tickets) ?>件</span></div>
                <?= staff_icon('inbox', '', 20) ?>
            </header>

            <div
                class="support-ticket-list"
                data-staff-preserve-scroll="support-ticket-list-overview"
            >
                <?php if ($tickets === []): ?>
                    <div class="support-empty">
                        <?= staff_icon('inbox', '', 34) ?>
                        <strong>対象のお問い合わせはありません</strong>
                    </div>
                <?php endif; ?>

                <?php foreach ($tickets as $ticket): ?>
                    <?php
                    $overviewTicketId = (int) ($ticket['id'] ?? 0);
                    $overviewTicketQuery = [
                        'view' => 'overview',
                        'id' => $overviewTicketId,
                    ];
                    if ($statusFilter !== 'all') {
                        $overviewTicketQuery['status'] = $statusFilter;
                    }
                    if ($query !== '') {
                        $overviewTicketQuery['q'] = $query;
                    }
                    ?>
                    <a
                        href="/staff/support/?<?= staff_ui_escape(http_build_query($overviewTicketQuery)) ?>"
                        class="support-ticket<?= $overviewTicketId === $selectedId ? ' is-selected' : '' ?>"
                    >
                        <span class="support-avatar support-avatar--<?= staff_ui_escape(
                            (string) ($ticket['priority'] ?? 'normal')
                        ) ?>">
                            <?= staff_ui_escape(mb_strtoupper(mb_substr(
                                (string) ($ticket['name'] ?? 'C'),
                                0,
                                1,
                                'UTF-8'
                            ), 'UTF-8')) ?>
                        </span>
                        <span class="support-ticket__body">
                            <span class="support-ticket__topline">
                                <strong><?= staff_ui_escape($ticket['name'] ?? 'お客さま') ?></strong>
                                <time><?= staff_ui_escape(staff_support_time_label(
                                    $ticket['updated_at'] ?? $ticket['created_at'] ?? null
                                )) ?></time>
                            </span>
                            <small><?= staff_ui_escape(staff_support_ticket_number($overviewTicketId)) ?></small>
                            <b><?= staff_ui_escape($ticket['subject'] ?? '件名なし') ?></b>
                            <p><?= staff_ui_escape($ticket['latest_message'] ?? '') ?></p>
                            <span class="support-ticket__meta">
                                <i class="support-status support-status--<?= staff_ui_escape(
                                    (string) ($ticket['status'] ?? 'open')
                                ) ?>">
                                    <?= staff_ui_escape(staff_support_status_label(
                                        (string) ($ticket['status'] ?? 'open')
                                    )) ?>
                                </i>
                                <em class="support-account-mini<?= !empty($ticket['user_id'])
                                    ? ' is-connected'
                                    : '' ?>">
                                    <?= !empty($ticket['user_id'])
                                        ? 'HCアカウントあり'
                                        : 'アカウントなし' ?>
                                </em>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <main
            class="support-overview-detail"
            data-support-detail-scroll
        >
            <?php if ($selected === null): ?>
                <div class="support-empty support-empty--conversation">
                    <?= staff_icon('description', '', 42) ?>
                    <strong>お問い合わせを選択してください</strong>
                    <p>最初に内容と顧客情報を確認します。</p>
                </div>
            <?php else: ?>
                <?php
                $selectedStatus = (string) $selected['status'];
                $selectedPriority = (string) $selected['priority'];
                $isAccountChat = !empty($selected['user_id']);
                ?>
                <header class="support-overview-detail__header">
                    <a
                        href="/staff/support/?status=<?= staff_ui_escape($statusFilter) ?>"
                        class="support-mobile-back"
                        aria-label="お問い合わせ一覧へ戻る"
                    >
                        <?= staff_icon('arrow_back', '', 20) ?>
                    </a>
                    <div>
                        <p>
                            <span class="support-status support-status--<?= staff_ui_escape($selectedStatus) ?>">
                                <?= staff_ui_escape(staff_support_status_label($selectedStatus)) ?>
                            </span>
                            <small><?= staff_ui_escape(staff_support_ticket_number((int) $selected['id'])) ?></small>
                        </p>
                        <h3><?= staff_ui_escape($selected['subject'] ?? '件名なし') ?></h3>
                    </div>

                    <?php if ($canReply): ?>
                        <form method="post" action="/staff/support/">
                            <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                            <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="return_view" value="overview">
                            <input type="hidden" name="status" value="<?= $selectedStatus === 'resolved'
                                ? 'in_progress'
                                : 'resolved' ?>">
                            <button type="submit" class="support-resolve-button">
                                <?= staff_icon($selectedStatus === 'resolved' ? 'restart_alt' : 'check', '', 18) ?>
                                <?= $selectedStatus === 'resolved' ? '再開する' : '解決にする' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </header>

                <div class="support-overview-content">
                    <section class="support-inquiry-card">
                        <header>
                            <span>お問い合わせ内容</span>
                            <time><?= staff_ui_escape(staff_support_date_label(
                                $selected['created_at'] ?? null
                            )) ?></time>
                        </header>
                        <p><?= nl2br(staff_ui_escape($selected['message'] ?? '')) ?></p>
                    </section>

                    <div class="support-overview-columns">
                        <section class="support-overview-card">
                            <header><h3>顧客・受付情報</h3></header>
                            <dl>
                                <div><dt>お名前</dt><dd><?= staff_ui_escape($selected['name'] ?? '') ?></dd></div>
                                <div><dt>メール</dt><dd><?= staff_ui_escape($selected['email'] ?? '') ?></dd></div>
                                <div><dt>カテゴリ</dt><dd><?= staff_ui_escape(staff_support_category_label(
                                    (string) ($selected['category'] ?? '')
                                )) ?></dd></div>
                                <div><dt>送信元IP</dt><dd><?= staff_ui_escape($selected['ip_address'] ?? '未記録') ?></dd></div>
                                <div><dt>最終更新</dt><dd><?= staff_ui_escape(staff_support_date_label(
                                    $selected['updated_at'] ?? $selected['created_at'] ?? null
                                )) ?></dd></div>
                            </dl>
                        </section>

                        <section class="support-overview-card support-account-state support-account-state--<?= $isAccountChat
                            ? 'connected'
                            : 'missing' ?>">
                            <header><h3>HCアカウント</h3></header>
                            <span class="support-account-state__badge">
                                <?= staff_icon($isAccountChat ? 'verified_user' : 'person_off', '', 18) ?>
                                <?= $isAccountChat ? 'アカウントあり' : 'アカウントなし' ?>
                            </span>
                            <strong><?= staff_ui_escape(
                                $isAccountChat
                                    ? ($selected['account_name'] ?? 'HC Account')
                                    : 'チャットは利用できません'
                            ) ?></strong>
                            <p>
                                <?= $isAccountChat
                                    ? 'HCアカウントの個別チャットとメール返信を選べます。'
                                    : 'このお問い合わせはメールで対応してください。' ?>
                            </p>
                            <?php if ($isAccountChat): ?>
                                <a href="/staff/support/chat/?id=<?= (int) $selected['id'] ?>">
                                    <?= staff_icon('forum', '', 18) ?>
                                    サポートチャットを開く
                                </a>
                            <?php else: ?>
                                <button type="button" disabled>
                                    <?= staff_icon('forum', '', 18) ?>
                                    チャット無効
                                </button>
                            <?php endif; ?>
                        </section>
                    </div>

                    <section class="support-overview-card support-overview-actions">
                        <header><h3>対応設定</h3></header>
                        <div>
                            <form method="post" action="/staff/support/" class="support-detail-form">
                                <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                                <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="return_view" value="overview">
                                <label><span>ステータス</span>
                                    <select name="status" data-support-auto-submit <?= !$canReply ? 'disabled' : '' ?>>
                                        <?php foreach (staff_support_statuses() as $value => $label): ?>
                                            <option value="<?= staff_ui_escape($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>>
                                                <?= staff_ui_escape($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit">更新</button>
                            </form>

                            <form method="post" action="/staff/support/" class="support-detail-form">
                                <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                                <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                                <input type="hidden" name="action" value="assign">
                                <input type="hidden" name="return_view" value="overview">
                                <label><span>担当者</span>
                                    <select name="assigned_to" data-support-auto-submit <?= !$canReply ? 'disabled' : '' ?>>
                                        <option value="">未割り当て</option>
                                        <?php foreach ($staffOptions as $staffOption): ?>
                                            <option value="<?= (int) $staffOption['id'] ?>" <?= (int) ($selected['assigned_to'] ?? 0) === (int) $staffOption['id'] ? 'selected' : '' ?>>
                                                <?= staff_ui_escape($staffOption['name'] ?? 'Staff') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit">更新</button>
                            </form>
                        </div>
                    </section>

                    <section class="support-overview-card support-email-history">
                        <header>
                            <h3>メール履歴</h3>
                            <a href="/staff/support/email/?id=<?= (int) $selected['id'] ?>">
                                <?= staff_icon('mail', '', 16) ?>
                                サポートメールを開く
                            </a>
                        </header>

                        <div class="support-email-history__list">
                            <?php foreach ($emailMessages as $emailMessage): ?>
                                <?php
                                $isCustomerEmail = (
                                    ($emailMessage['author_type'] ?? '')
                                    === 'customer'
                                );
                                $emailDeliveryStatus = (string) (
                                    $emailMessage['delivery_status'] ?? ''
                                );
                                ?>
                                <article class="support-email-history__item support-email-history__item--<?= $isCustomerEmail
                                    ? 'received'
                                    : 'sent' ?>">
                                    <header>
                                        <div>
                                            <?= staff_icon(
                                                $isCustomerEmail
                                                    ? 'mark_email_unread'
                                                    : 'outgoing_mail',
                                                '',
                                                18
                                            ) ?>
                                            <strong><?= staff_ui_escape(
                                                $emailMessage['author_name']
                                                    ?? ($isCustomerEmail
                                                        ? 'お客さま'
                                                        : 'Staff')
                                            ) ?></strong>
                                            <span>
                                                <?= $isCustomerEmail
                                                    ? '受信'
                                                    : '返信' ?>
                                            </span>
                                        </div>
                                        <time><?= staff_ui_escape(
                                            staff_support_date_label(
                                                $emailMessage['created_at']
                                                    ?? null
                                            )
                                        ) ?></time>
                                    </header>

                                    <p><?= nl2br(staff_ui_escape(
                                        $emailMessage['body'] ?? ''
                                    )) ?></p>

                                    <?php if (!$isCustomerEmail): ?>
                                        <footer class="support-email-history__status support-email-history__status--<?= staff_ui_escape(
                                            $emailDeliveryStatus
                                        ) ?>">
                                            <?= staff_icon(
                                                $emailDeliveryStatus === 'failed'
                                                    ? 'error_outline'
                                                    : 'done_all',
                                                '',
                                                15
                                            ) ?>
                                            <?= match ($emailDeliveryStatus) {
                                                'failed' => '送信失敗',
                                                'logged' => '開発メールログへ保存済み',
                                                'sent' => '送信済み',
                                                default => '履歴へ保存済み',
                                            } ?>
                                        </footer>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <form
                        method="post"
                        action="/staff/support/"
                        class="support-composer support-overview-email-composer"
                        data-support-composer
                    >
                        <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                        <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                        <input type="hidden" name="action" value="message">
                        <input type="hidden" name="mode" value="reply" data-support-mode>
                        <input type="hidden" name="delivery_channel" value="email">
                        <input type="hidden" name="return_view" value="overview">
                        <header>
                            <?= staff_icon('mail', '', 20) ?>
                            <div>
                                <strong>メールで返信</strong>
                                <span><?= staff_ui_escape($selected['email'] ?? '') ?> へ送信します</span>
                            </div>
                        </header>
                        <textarea
                            name="body"
                            maxlength="5000"
                            placeholder="<?= staff_ui_escape((string) $selected['name']) ?>さんへのメールを入力…"
                            aria-label="メール返信内容"
                            data-support-body
                            <?= !$canReply || !$schemaReady ? 'disabled' : '' ?>
                        ></textarea>
                        <footer>
                            <span data-support-count>0 / 5000</span>
                            <small>アカウントの有無に関係なくメール送信できます</small>
                            <button type="submit" data-support-submit <?= !$canReply || !$schemaReady ? 'disabled' : '' ?>>
                                <span data-support-submit-label>メールを送信</span>
                                <?= staff_icon('send', '', 17) ?>
                            </button>
                        </footer>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</section>
<?php else: ?>
<section
    class="staff-support<?= $isEmailView ? ' support-email' : '' ?>"
    data-support-root
    data-support-view="<?= staff_ui_escape($view) ?>"
    data-support-ticket-id="<?= (int) $selectedId ?>"
    data-support-selected="<?= $selected !== null ? '1' : '0' ?>"
>
    <header class="support-page-header">
        <div>
            <p><?= $isEmailView ? 'SUPPORT MAIL' : 'SUPPORT CHAT' ?></p>
            <h2><?= staff_ui_escape($supportPageTitle) ?></h2>
            <span>
                <?= $isEmailView
                    ? 'お問い合わせメールの受信内容・返信履歴を確認し、そのままメールで返答できます。'
                    : '登録済みHCアカウントとの個別チャットを確認し、会話を続けます。' ?>
            </span>
        </div>

        <div class="support-metrics" aria-label="お問い合わせ対応状況">
            <div>
                <span>未対応</span>
                <strong><?= (int) $counts['open'] ?></strong>
            </div>
            <div>
                <span>対応中</span>
                <strong><?= (int) $counts['in_progress'] ?></strong>
            </div>
            <div>
                <span>返信待ち</span>
                <strong><?= (int) $counts['waiting'] ?></strong>
            </div>
            <div>
                <span>本日の解決</span>
                <strong><?= (int) $counts['resolved_today'] ?></strong>
            </div>
        </div>
    </header>

    <?php if (is_array($flash)): ?>
        <div
            class="support-flash support-flash--<?= staff_ui_escape(
                ($flash['type'] ?? '') === 'error'
                    ? 'error'
                    : 'success'
            ) ?>"
            role="status"
        >
            <?= staff_icon(
                ($flash['type'] ?? '') === 'error'
                    ? 'error_outline'
                    : 'check_circle',
                '',
                19
            ) ?>
            <span><?= staff_ui_escape($flash['message'] ?? '') ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="support-flash support-flash--warning" role="status">
            <?= staff_icon('database', '', 19) ?>
            <span>
                一覧は利用できます。返信・社内メモを使うには、
                最新のDBマイグレーションを適用してください。
            </span>
        </div>
    <?php endif; ?>

    <div class="support-toolbar">
        <nav class="support-tabs" aria-label="対応状況で絞り込み">
            <?php foreach ($statusTabs as $value => $label): ?>
                <?php
                $tabQuery = ['status' => $value];

                if ($query !== '') {
                    $tabQuery['q'] = $query;
                }
                ?>
                <a
                    href="<?= staff_ui_escape($supportPagePath) ?>?<?= staff_ui_escape(
                        http_build_query($tabQuery)
                    ) ?>"
                    class="<?= $statusFilter === $value
                        ? 'is-active'
                        : '' ?>"
                    <?= $statusFilter === $value
                        ? 'aria-current="page"'
                        : '' ?>
                >
                    <?= staff_ui_escape($label) ?>
                    <span><?= (int) ($counts[$value] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <form
            class="support-search"
            method="get"
            action="<?= staff_ui_escape($supportPagePath) ?>"
        >
            <input type="hidden" name="status" value="<?= staff_ui_escape($statusFilter) ?>">
            <?= staff_icon('search', '', 19) ?>
            <input
                type="search"
                name="q"
                value="<?= staff_ui_escape($query) ?>"
                placeholder="名前・内容・IDで検索"
                aria-label="お問い合わせを検索"
            >
            <?php if ($query !== ''): ?>
                <a
                    href="<?= staff_ui_escape($supportPagePath) ?>?status=<?= staff_ui_escape($statusFilter) ?>"
                    aria-label="検索条件をクリア"
                >
                    <?= staff_icon('close', '', 17) ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="support-inbox">
        <aside
            class="support-list-pane"
            aria-label="<?= $isEmailView
                ? 'サポートメール一覧'
                : 'サポート会話一覧' ?>"
        >
            <header class="support-pane-heading">
                <div>
                    <h3><?= $isEmailView ? 'メール一覧' : '会話一覧' ?></h3>
                    <span><?= count($tickets) ?>件</span>
                </div>
                <?= staff_icon('sort', '', 19) ?>
            </header>

            <div
                class="support-ticket-list"
                data-staff-preserve-scroll="support-ticket-list-<?= staff_ui_escape($view) ?>"
            >
                <?php if ($tickets === []): ?>
                    <div class="support-empty">
                        <?= staff_icon('inbox', '', 34) ?>
                        <strong>対象のお問い合わせはありません</strong>
                        <p>検索条件や対応状況を変更してください。</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($tickets as $ticket): ?>
                    <?php
                    $ticketId = (int) ($ticket['id'] ?? 0);
                    $ticketStatus = (string) ($ticket['status'] ?? 'open');
                    $ticketPriority = (string) ($ticket['priority'] ?? 'normal');
                    $ticketQuery = ['id' => $ticketId];

                    if ($statusFilter !== 'all') {
                        $ticketQuery['status'] = $statusFilter;
                    }

                    if ($query !== '') {
                        $ticketQuery['q'] = $query;
                    }
                    ?>
                    <a
                        href="<?= staff_ui_escape($supportPagePath) ?>?<?= staff_ui_escape(
                            http_build_query($ticketQuery)
                        ) ?>"
                        class="support-ticket<?= $ticketId === $selectedId
                            ? ' is-selected'
                            : '' ?>"
                    >
                        <span class="support-avatar support-avatar--<?= staff_ui_escape($ticketPriority) ?>">
                            <?= staff_ui_escape(
                                mb_strtoupper(
                                    mb_substr(
                                        (string) ($ticket['name'] ?? 'C'),
                                        0,
                                        1,
                                        'UTF-8'
                                    ),
                                    'UTF-8'
                                )
                            ) ?>
                        </span>

                        <span class="support-ticket__body">
                            <span class="support-ticket__topline">
                                <strong><?= staff_ui_escape($ticket['name'] ?? 'お客さま') ?></strong>
                                <time><?= staff_ui_escape(
                                    staff_support_time_label(
                                        $ticket['updated_at']
                                            ?? $ticket['created_at']
                                            ?? null
                                    )
                                ) ?></time>
                            </span>

                            <small>
                                <?= staff_ui_escape(
                                    staff_support_ticket_number($ticketId)
                                ) ?>
                            </small>

                            <b><?= staff_ui_escape($ticket['subject'] ?? '件名なし') ?></b>
                            <p><?= staff_ui_escape($ticket['latest_message'] ?? '') ?></p>

                            <span class="support-ticket__meta">
                                <i class="support-status support-status--<?= staff_ui_escape($ticketStatus) ?>">
                                    <?= staff_ui_escape(
                                        staff_support_status_label($ticketStatus)
                                    ) ?>
                                </i>
                                <em><?= staff_ui_escape(
                                    staff_support_category_label(
                                        (string) ($ticket['category'] ?? '')
                                    )
                                ) ?></em>
                                <?php if ($ticketPriority === 'high'): ?>
                                    <em class="is-priority">● 優先</em>
                                <?php endif; ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <main class="support-conversation-pane">
            <?php if ($selected === null): ?>
                <div class="support-empty support-empty--conversation">
                    <?= staff_icon($isEmailView ? 'mail' : 'forum', '', 42) ?>
                    <strong>お問い合わせを選択してください</strong>
                    <p>
                        <?= $isEmailView
                            ? 'メール一覧から受信内容と返信履歴を確認できます。'
                            : '会話一覧から内容を確認できます。' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php
                $selectedStatus = (string) $selected['status'];
                $selectedPriority = (string) $selected['priority'];
                $isAccountChat = !empty($selected['user_id']);
                ?>
                <header class="support-conversation-header">
                    <a
                        href="<?= staff_ui_escape($supportPagePath) ?>?status=<?= staff_ui_escape($statusFilter) ?>"
                        class="support-mobile-back"
                        aria-label="受信トレイに戻る"
                    >
                        <?= staff_icon('arrow_back', '', 20) ?>
                    </a>

                    <div>
                        <p>
                            <span class="support-status support-status--<?= staff_ui_escape($selectedStatus) ?>">
                                <?= staff_ui_escape(
                                    staff_support_status_label($selectedStatus)
                                ) ?>
                            </span>
                            <small><?= staff_ui_escape(
                                staff_support_ticket_number((int) $selected['id'])
                            ) ?></small>
                            <span class="support-channel support-channel--<?= $isEmailView
                                ? 'email'
                                : 'account' ?>">
                                <?= staff_icon(
                                    $isEmailView ? 'mail' : 'forum',
                                    '',
                                    14
                                ) ?>
                                <?= $isEmailView
                                    ? 'サポートメール'
                                    : 'HCアカウントチャット' ?>
                            </span>
                        </p>
                        <h3><?= staff_ui_escape($selected['subject'] ?? '件名なし') ?></h3>
                        <span>
                            <?= staff_ui_escape($selected['name'] ?? '') ?>
                            ・
                            <?= staff_ui_escape($selected['email'] ?? '') ?>
                        </span>
                    </div>

                    <?php if ($canReply): ?>
                        <form method="post" action="/staff/support/">
                            <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                            <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                            <input type="hidden" name="action" value="status">
                            <input
                                type="hidden"
                                name="return_view"
                                value="<?= staff_ui_escape($view) ?>"
                            >
                            <input
                                type="hidden"
                                name="status"
                                value="<?= $selectedStatus === 'resolved'
                                    ? 'in_progress'
                                    : 'resolved' ?>"
                            >
                            <button type="submit" class="support-resolve-button">
                                <?= staff_icon(
                                    $selectedStatus === 'resolved'
                                        ? 'restart_alt'
                                        : 'check',
                                    '',
                                    18
                                ) ?>
                                <?= $selectedStatus === 'resolved'
                                    ? '再開する'
                                    : '解決にする' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </header>

                <div
                    class="support-conversation"
                    data-support-conversation
                    data-staff-preserve-scroll="support-conversation-<?= (int) $selected['id'] ?>"
                >
                    <div class="support-date-divider">
                        <span><?= $isEmailView ? 'メール履歴' : '会話履歴' ?></span>
                    </div>

                    <?php foreach ($messages as $message): ?>
                        <?php
                        $isCustomer = ($message['author_type'] ?? '') === 'customer';
                        $isNote = ($message['visibility'] ?? '') === 'internal';
                        $messageClass = $isNote
                            ? 'note'
                            : ($isCustomer ? 'customer' : 'staff');
                        $deliveryStatus = (string) (
                            $message['delivery_status'] ?? ''
                        );
                        $messageChannel = (string) (
                            $message['delivery_channel'] ?? 'imported'
                        );
                        ?>
                        <article class="support-message support-message--<?= $messageClass ?>">
                            <span class="support-message__avatar">
                                <?= $isNote
                                    ? staff_icon('lock', '', 17)
                                    : staff_ui_escape(
                                        mb_strtoupper(
                                            mb_substr(
                                                (string) ($message['author_name'] ?? 'S'),
                                                0,
                                                1,
                                                'UTF-8'
                                            ),
                                            'UTF-8'
                                        )
                                    ) ?>
                            </span>

                            <div class="support-message__content">
                                <header>
                                    <strong><?= staff_ui_escape(
                                        $message['author_name'] ?? 'Staff'
                                    ) ?></strong>
                                    <?php if ($isNote): ?>
                                        <span>社内のみ</span>
                                    <?php elseif ($isCustomer && $isEmailView): ?>
                                        <span class="support-message-channel support-message-channel--email">
                                            受信メール
                                        </span>
                                    <?php elseif (!$isCustomer): ?>
                                        <span>スタッフ</span>
                                        <span class="support-message-channel support-message-channel--<?= staff_ui_escape(
                                            (string) ($message['delivery_channel'] ?? 'imported')
                                        ) ?>">
                                            <?= ($message['delivery_channel'] ?? '') === 'email'
                                                ? 'メール'
                                                : 'チャット' ?>
                                        </span>
                                    <?php endif; ?>
                                    <time><?= staff_ui_escape(
                                        staff_support_date_label(
                                            $message['created_at'] ?? null
                                        )
                                    ) ?></time>
                                </header>
                                <p><?= nl2br(
                                    staff_ui_escape($message['body'] ?? '')
                                ) ?></p>

                                <?php if ($deliveryStatus === 'failed'): ?>
                                    <small class="support-delivery support-delivery--failed">
                                        <?= staff_icon('error_outline', '', 15) ?>
                                        <?= $messageChannel === 'chat'
                                            ? 'HCアカウントへの通知に失敗'
                                            : 'メール送信に失敗' ?>
                                    </small>
                                <?php elseif ($deliveryStatus === 'logged'): ?>
                                    <small class="support-delivery">
                                        <?= staff_icon('terminal', '', 15) ?>
                                        開発メールログへ保存済み
                                    </small>
                                <?php elseif ($deliveryStatus === 'sent'): ?>
                                    <small class="support-delivery">
                                        <?= staff_icon('done_all', '', 15) ?>
                                        <?= $messageChannel === 'chat'
                                            ? 'HCアカウントへ送信済み'
                                            : 'メール送信済み' ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <form
                    method="post"
                    action="/staff/support/"
                    class="support-composer"
                    data-support-composer
                    data-support-reply-label="<?= $isEmailView
                        ? 'メールを送信'
                        : '送信する' ?>"
                >
                    <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                    <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                    <input type="hidden" name="action" value="message">
                    <input type="hidden" name="mode" value="reply" data-support-mode>
                    <input
                        type="hidden"
                        name="delivery_channel"
                        value="<?= $isEmailView ? 'email' : 'chat' ?>"
                    >
                    <input
                        type="hidden"
                        name="return_view"
                        value="<?= staff_ui_escape($view) ?>"
                    >

                    <div class="support-composer__tabs" role="tablist" aria-label="入力モード">
                        <button
                            type="button"
                            class="is-active"
                            role="tab"
                            aria-selected="true"
                            data-support-mode-button="reply"
                        >
                            <?= staff_icon($isEmailView ? 'mail' : 'reply', '', 17) ?>
                            <?= $isEmailView ? 'メール返信' : 'チャット' ?>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="false"
                            data-support-mode-button="note"
                        >
                            <?= staff_icon('edit_note', '', 18) ?>
                            社内メモ
                        </button>
                    </div>

                    <textarea
                        name="body"
                        maxlength="5000"
                        placeholder="<?= staff_ui_escape(
                            (string) $selected['name']
                        ) ?>さんへのメッセージを入力…"
                        aria-label="返信内容"
                        data-support-body
                        <?= !$canReply || !$schemaReady
                            ? 'disabled'
                            : '' ?>
                    ></textarea>

                    <footer>
                        <span data-support-count>0 / 5000</span>
                        <small>
                            <?= $isEmailView
                                ? staff_ui_escape($selected['email'] ?? '') . ' へ送信'
                                : '⌘ / Ctrl + Enter で送信' ?>
                        </small>
                        <button
                            type="submit"
                            data-support-submit
                            <?= !$canReply || !$schemaReady
                                ? 'disabled'
                                : '' ?>
                        >
                            <span data-support-submit-label>
                                <?= $isEmailView ? 'メールを送信' : '送信する' ?>
                            </span>
                            <?= staff_icon('arrow_forward', '', 17) ?>
                        </button>
                    </footer>
                </form>
            <?php endif; ?>
        </main>

        <aside class="support-detail-pane" aria-label="お問い合わせ詳細">
            <?php if ($selected !== null): ?>
                <section class="support-customer-card">
                    <span class="support-customer-card__avatar">
                        <?= staff_ui_escape(
                            mb_strtoupper(
                                mb_substr(
                                    (string) $selected['name'],
                                    0,
                                    1,
                                    'UTF-8'
                                ),
                                'UTF-8'
                            )
                        ) ?>
                    </span>
                    <h3><?= staff_ui_escape($selected['name']) ?></h3>
                    <p><?= staff_ui_escape($selected['email']) ?></p>
                    <span class="support-customer-channel support-customer-channel--<?= $isEmailView
                        ? 'email'
                        : 'account' ?>">
                        <?= staff_icon(
                            $isEmailView ? 'mail' : 'forum',
                            '',
                            14
                        ) ?>
                        <?= $isEmailView
                            ? 'メールで返信'
                            : 'HCアカウントで会話' ?>
                    </span>
                    <?php if (!empty($selected['user_id'])): ?>
                        <a href="/staff/customers/?id=<?= (int) $selected['user_id'] ?>">
                            顧客情報を開く
                            <?= staff_icon('open_in_new', '', 15) ?>
                        </a>
                    <?php else: ?>
                        <span class="support-guest-label">ゲストからのお問い合わせ</span>
                    <?php endif; ?>
                </section>

                <section class="support-detail-section">
                    <header><h3>対応情報</h3></header>

                    <form method="post" action="/staff/support/" class="support-detail-form">
                        <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                        <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                        <input type="hidden" name="action" value="status">
                        <input
                            type="hidden"
                            name="return_view"
                            value="<?= staff_ui_escape($view) ?>"
                        >
                        <label>
                            <span>ステータス</span>
                            <select
                                name="status"
                                data-support-auto-submit
                                <?= !$canReply ? 'disabled' : '' ?>
                            >
                                <?php foreach (staff_support_statuses() as $value => $label): ?>
                                    <option
                                        value="<?= staff_ui_escape($value) ?>"
                                        <?= $selectedStatus === $value
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= staff_ui_escape($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit">更新</button>
                    </form>

                    <form method="post" action="/staff/support/" class="support-detail-form">
                        <input type="hidden" name="csrf_token" value="<?= staff_ui_escape(csrf_token()) ?>">
                        <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                        <input type="hidden" name="action" value="assign">
                        <input
                            type="hidden"
                            name="return_view"
                            value="<?= staff_ui_escape($view) ?>"
                        >
                        <label>
                            <span>担当者</span>
                            <select
                                name="assigned_to"
                                data-support-auto-submit
                                <?= !$canReply ? 'disabled' : '' ?>
                            >
                                <option value="">未割り当て</option>
                                <?php foreach ($staffOptions as $staffOption): ?>
                                    <option
                                        value="<?= (int) $staffOption['id'] ?>"
                                        <?= (int) ($selected['assigned_to'] ?? 0)
                                            === (int) $staffOption['id']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= staff_ui_escape($staffOption['name'] ?? 'Staff') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit">更新</button>
                    </form>

                    <dl>
                        <div>
                            <dt>返信方法</dt>
                            <dd><?= $isEmailView
                                ? 'お問い合わせメール'
                                : 'HCアカウントチャット' ?></dd>
                        </div>
                        <div>
                            <dt>カテゴリ</dt>
                            <dd><?= staff_ui_escape(
                                staff_support_category_label(
                                    (string) $selected['category']
                                )
                            ) ?></dd>
                        </div>
                        <div>
                            <dt>優先度</dt>
                            <dd class="support-priority support-priority--<?= staff_ui_escape($selectedPriority) ?>">
                                ● <?= staff_ui_escape(
                                    $priorityLabels[$selectedPriority]
                                    ?? '通常'
                                ) ?>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="support-detail-section">
                    <header><h3>受付情報</h3></header>
                    <dl>
                        <div>
                            <dt>受付番号</dt>
                            <dd><?= staff_ui_escape(
                                staff_support_ticket_number(
                                    (int) $selected['id']
                                )
                            ) ?></dd>
                        </div>
                        <div>
                            <dt>受付日時</dt>
                            <dd><?= staff_ui_escape(
                                staff_support_date_label(
                                    $selected['created_at'] ?? null
                                )
                            ) ?></dd>
                        </div>
                        <div>
                            <dt>最終更新</dt>
                            <dd><?= staff_ui_escape(
                                staff_support_date_label(
                                    $selected['updated_at']
                                        ?? $selected['created_at']
                                        ?? null
                                )
                            ) ?></dd>
                        </div>
                        <div>
                            <dt>送信元IP</dt>
                            <dd><?= staff_ui_escape(
                                $selected['ip_address']
                                    ?? '未記録'
                            ) ?></dd>
                        </div>
                        <div>
                            <dt>アカウント</dt>
                            <dd><?= staff_ui_escape(
                                $selected['account_name']
                                    ?? 'ゲスト'
                            ) ?></dd>
                        </div>
                    </dl>
                </section>
            <?php else: ?>
                <div class="support-empty">
                    <?= staff_icon('info', '', 28) ?>
                    <strong>詳細情報</strong>
                    <p>お問い合わせを選択すると表示されます。</p>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</section>
<?php endif; ?>
<?php

staff_layout_end();
