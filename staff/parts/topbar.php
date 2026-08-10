<?php

declare(strict_types=1);

$staffNotificationCount = (int) (
    $staffDashboard['counts']['notifications']
    ?? 0
);

$staffWorkStatus = trim(
    (string) (
        $staffContext['user']['work_status']
        ?? 'online'
    )
);

$staffWorkStatusLabel = match ($staffWorkStatus) {
    'busy' => '対応中',
    'away' => '離席中',
    'offline' => 'オフライン',
    default => 'オンライン',
};

$staffAvatarUrl = staff_workspace_asset_url(
    $staffWorkspacePreferences['avatar_image_path'] ?? null
);
?>

<header class="staff-topbar">
    <div class="staff-topbar__left">
        <button
            type="button"
            class="staff-mobile-menu"
            data-staff-sidebar-open
            aria-label="メニューを開く"
            aria-controls="staff-sidebar"
            aria-expanded="false"
        >
            <?= staff_icon(
                'menu',
                'staff-mobile-menu__icon',
                24
            ) ?>
        </button>

        <div class="staff-topbar__title">
            <p class="staff-topbar__eyebrow">
                HC PLATFORM
            </p>

            <h1>スタッフコンソール</h1>
        </div>
    </div>

    <div class="staff-topbar__actions">
        
<div class="staff-command-search">
            <button
                type="button"
                class="staff-command-search__trigger"
                data-staff-search-trigger
                aria-label="検索を開く"
                aria-haspopup="dialog"
                aria-expanded="false"
            >
                <span
                    class="material-icons"
                    aria-hidden="true"
                >
                    search
                </span>

                <span class="staff-command-search__placeholder">
                    ページ、タスク、スタッフを検索
                </span>

                <span class="staff-command-search__shortcut">
                    <span data-staff-search-modifier>⌘</span>
                    <span>K</span>
                </span>
            </button>
        </div>


        <div
            class="staff-status-menu"
            data-staff-status-menu
        >
            <button
                type="button"
                class="staff-status-button"
                data-staff-status-trigger
                aria-expanded="false"
                aria-controls="staff-status-dropdown"
                title="<?= htmlspecialchars(
                    $staffWorkStatusLabel,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >
                <span
                    class="staff-status-button__dot
                           staff-status-button__dot--<?= htmlspecialchars(
                               $staffWorkStatus,
                               ENT_QUOTES,
                               'UTF-8'
                           ) ?>"
                    data-staff-status-dot
                ></span>

                <span
                    class="staff-status-button__label"
                    data-staff-status-label
                >
                    <?= htmlspecialchars(
                        $staffWorkStatusLabel,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

                <?= staff_icon(
                    'expand_more',
                    'staff-status-button__arrow',
                    18
                ) ?>
            </button>

            <div
                id="staff-status-dropdown"
                class="staff-status-dropdown"
                data-staff-status-dropdown
                hidden
            >
                <div class="staff-status-dropdown__header">
                    <strong>勤務状態</strong>
                    <span>現在の対応状況を設定</span>
                </div>

                <?php
                $staffStatusOptions = [
                    'online' => [
                        'label' => 'オンライン',
                        'description' => '対応可能です',
                    ],
                    'working' => [
                        'label' => '作業中',
                        'description' => '通常業務を進めています',
                    ],
                    'busy' => [
                        'label' => '対応中',
                        'description' => '別の対応を行っています',
                    ],
                    'away' => [
                        'label' => '離席中',
                        'description' => '一時的に離れています',
                    ],
                    'break' => [
                        'label' => '休憩中',
                        'description' => '休憩しています',
                    ],
                    'offline' => [
                        'label' => 'オフライン',
                        'description' => '現在対応できません',
                    ],
                ];
                ?>

                <div class="staff-status-dropdown__list">
                    <?php foreach (
                        $staffStatusOptions as
                        $statusValue => $statusOption
                    ): ?>
                        <button
                            type="button"
                            class="staff-status-option<?= (
                                $statusValue === $staffWorkStatus
                            ) ? ' is-active' : '' ?>"
                            data-staff-status-option="<?= htmlspecialchars(
                                $statusValue,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            <span
                                class="staff-status-option__dot
                                       staff-status-option__dot--<?= htmlspecialchars(
                                           $statusValue,
                                           ENT_QUOTES,
                                           'UTF-8'
                                       ) ?>"
                            ></span>

                            <span class="staff-status-option__copy">
                                <strong>
                                    <?= htmlspecialchars(
                                        $statusOption['label'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars(
                                        $statusOption['description'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </small>
                            </span>

                            <?= staff_icon(
                                'check',
                                'staff-status-option__check',
                                18
                            ) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <p
                    class="staff-status-dropdown__message"
                    data-staff-status-message
                    hidden
                ></p>
            </div>
        </div>

        <div
            class="staff-notification-menu"
            data-staff-notifications
        >
            <button
                type="button"
                class="staff-icon-button"
                data-notification-trigger
                aria-label="通知を開く"
                aria-expanded="false"
                aria-controls="staff-notification-panel"
                title="通知"
            >
                <?= staff_icon(
                    $staffNotificationCount > 0
                        ? 'notifications_active'
                        : 'notifications',
                    'notification-bell-icon',
                    22
                ) ?>

                <small
                    data-notification-badge
                    <?= $staffNotificationCount > 0
                        ? ''
                        : 'hidden' ?>
                >
                    <?= min(
                        $staffNotificationCount,
                        99
                    ) ?>
                </small>
            </button>

            <?php require dirname(__DIR__)
                . '/components/notifications-panel.php'; ?>
        </div>

        <?php if (
            staff_can_access_admin(
                $staffContext
            )
        ): ?>
            <a
                href="/staff/admin/"
                class="staff-icon-button"
                aria-label="上位管理センター"
                title="上位管理センター"
            >
                <?= staff_icon(
                    'admin_panel_settings',
                    '',
                    22
                ) ?>
            </a>
        <?php endif; ?>

        <div
            class="staff-account-menu"
            data-staff-account-menu
        >
            <button
                type="button"
                class="staff-topbar-profile"
                data-staff-account-trigger
                aria-expanded="false"
                aria-controls="staff-account-dropdown"
                title="アカウントメニュー"
            >
                <span class="staff-topbar-profile__avatar">
                    <?php if ($staffAvatarUrl !== null): ?>
                        <img src="<?= htmlspecialchars($staffAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars(
                            mb_strtoupper(
                                mb_substr(
                                    $staffDisplayName,
                                    0,
                                    1,
                                    'UTF-8'
                                ),
                                'UTF-8'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    <?php endif; ?>

                    <span
                        class="staff-topbar-profile__status
                               staff-topbar-profile__status--<?= htmlspecialchars(
                                   $staffWorkStatus,
                                   ENT_QUOTES,
                                   'UTF-8'
                               ) ?>"
                        data-staff-account-status-dot
                    ></span>
                </span>

                <span class="staff-topbar-profile__copy">
                    <strong>
                        <?= htmlspecialchars(
                            $staffDisplayName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                    <small>
                        <?= htmlspecialchars(
                            $staffRoleName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </small>
                </span>

                <?= staff_icon(
                    'expand_more',
                    'staff-topbar-profile__arrow',
                    18
                ) ?>
            </button>

            <section
                id="staff-account-dropdown"
                class="staff-account-dropdown"
                data-staff-account-dropdown
                hidden
            >
                <header class="staff-account-dropdown__header">
                    <span class="staff-account-dropdown__avatar">
                        <?php if ($staffAvatarUrl !== null): ?>
                            <img src="<?= htmlspecialchars($staffAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars(
                                mb_strtoupper(
                                    mb_substr(
                                        $staffDisplayName,
                                        0,
                                        1,
                                        'UTF-8'
                                    ),
                                    'UTF-8'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        <?php endif; ?>

                        <span
                            class="staff-account-dropdown__status
                                   staff-account-dropdown__status--<?= htmlspecialchars(
                                       $staffWorkStatus,
                                       ENT_QUOTES,
                                       'UTF-8'
                                   ) ?>"
                            data-staff-account-dropdown-status
                        ></span>
                    </span>

                    <div class="staff-account-dropdown__identity">
                        <strong>
                            <?= htmlspecialchars(
                                $staffDisplayName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                $staffRoleName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <small data-staff-account-status-label>
                            <?= htmlspecialchars(
                                $staffWorkStatusLabel,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </small>
                    </div>
                </header>

                <nav class="staff-account-dropdown__nav">
                    <a href="/staff/account/">
                        <?= staff_icon('manage_accounts', '', 19) ?>
                        <span>スタッフアカウント</span>
                    </a>

                    <a href="/staff/account/customize/">
                        <?= staff_icon('palette', '', 19) ?>
                        <span>ダッシュボード設定</span>
                    </a>

                    <a href="/staff/">
                        <?= staff_icon('dashboard', '', 19) ?>
                        <span>スタッフダッシュボード</span>
                    </a>

                    <a href="/dashboard/">
                        <?= staff_icon('account_circle', '', 19) ?>
                        <span>HCアカウント</span>
                    </a>

                    <a href="/staff/tasks/">
                        <?= staff_icon('task_alt', '', 19) ?>
                        <span>マイタスク</span>
                    </a>

                    <a href="/staff/notifications/">
                        <?= staff_icon('notifications', '', 19) ?>
                        <span>通知一覧</span>
                    </a>
                </nav>

                <?php if (
                    staff_can_access_admin(
                        $staffContext
                    )
                ): ?>
                    <div class="staff-account-dropdown__divider"></div>

                    <nav class="staff-account-dropdown__nav">
                        <a href="/staff/admin/">
                            <?= staff_icon(
                                'admin_panel_settings',
                                '',
                                19
                            ) ?>
                            <span>上位管理センター</span>
                        </a>
                    </nav>
                <?php endif; ?>

                <div class="staff-account-dropdown__divider"></div>

                <nav class="staff-account-dropdown__nav">
                    <a
                        href="/logout/"
                        class="staff-account-dropdown__logout"
                    >
                        <?= staff_icon('logout', '', 19) ?>
                        <span>ログアウト</span>
                    </a>
                </nav>
            </section>
        </div>
    </div>

</header>
