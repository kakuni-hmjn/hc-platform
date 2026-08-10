<?php

declare(strict_types=1);

$staffSearchPages = [
    [
        'title' => 'ダッシュボード',
        'description' => 'スタッフコンソールのホーム',
        'url' => '/staff/',
        'icon' => 'dashboard',
        'keywords' => 'ホーム home dashboard overview',
    ],
    [
        'title' => 'マイタスク',
        'description' => '自分に割り当てられたタスク',
        'url' => '/staff/tasks/',
        'icon' => 'task_alt',
        'keywords' => '仕事 task todo 作業 進行中',
    ],
    [
        'title' => '通知',
        'description' => 'スタッフ向け通知を確認',
        'url' => '/staff/notifications/',
        'icon' => 'notifications',
        'keywords' => 'notification お知らせ 未読',
    ],
    [
        'title' => 'HCアカウント',
        'description' => 'アカウント情報を確認',
        'url' => '/dashboard/',
        'icon' => 'account_circle',
        'keywords' => 'プロフィール account user アカウント',
    ],
];

if (
    isset($staffContext)
    && is_array($staffContext)
    && function_exists('staff_has_permission')
    && (
        staff_has_permission(
            $staffContext,
            'support.tickets.view'
        )
        || (
            function_exists('staff_can_access_admin')
            && staff_can_access_admin($staffContext)
        )
    )
) {
    array_splice(
        $staffSearchPages,
        3,
        0,
        [
            [
                'title' => 'お問い合わせ概要',
                'description' => 'お問い合わせ内容と顧客情報を確認',
                'url' => '/staff/support/',
                'icon' => 'description',
                'keywords' => 'support contact inquiry ticket 問い合わせ 概要 内容',
            ],
            [
                'title' => 'サポートチャット',
                'description' => 'HCアカウントとの個別チャット',
                'url' => '/staff/support/chat/',
                'icon' => 'forum',
                'keywords' => 'support chat 問い合わせ チャット 返信',
            ],
        ]
    );
}

if (
    isset($staffContext)
    && is_array($staffContext)
    && function_exists('staff_can_access_admin')
    && staff_can_access_admin($staffContext)
) {
    $staffSearchPages[] = [
        'title' => '上位管理センター',
        'description' => 'Webサイト全体と各サービスの管理機能を開く',
        'url' => '/staff/admin/',
        'icon' => 'admin_panel_settings',
        'keywords' => 'admin 管理者 上位管理 全体管理 サービス管理 settings 設定',
    ];
}
?>

<div
        class="staff-command-palette"
        data-staff-search-palette
        hidden
    >
        <button
            type="button"
            class="staff-command-palette__backdrop"
            data-staff-search-close
            aria-label="検索を閉じる"
        ></button>

        <section
            class="staff-command-palette__dialog"
            role="dialog"
            aria-modal="true"
            aria-label="スタッフコンソール検索"
        >
            <header class="staff-command-palette__header">
                <span class="material-icons" aria-hidden="true">
                    search
                </span>

                <input
                    type="search"
                    class="staff-command-palette__input"
                    data-staff-search-input
                    placeholder="ページ名や機能を入力..."
                    autocomplete="off"
                    spellcheck="false"
                >

                <button
                    type="button"
                    class="staff-command-palette__escape"
                    data-staff-search-close
                >
                    ESC
                </button>
            </header>

            <div
                class="staff-command-palette__body"
                data-staff-search-body
            >
                <section
                    class="staff-command-section"
                    data-staff-search-recent-section
                    hidden
                >
                    <div class="staff-command-section__heading">
                        <span>最近開いたページ</span>

                        <button
                            type="button"
                            data-staff-search-clear-recent
                        >
                            履歴を消去
                        </button>
                    </div>

                    <div
                        class="staff-command-results"
                        data-staff-search-recent
                    ></div>
                </section>

                <section
                    class="staff-command-section"
                    data-staff-search-default-section
                >
                    <div class="staff-command-section__heading">
                        <span>ページ</span>
                    </div>

                    <div class="staff-command-results">
                        <?php foreach ($staffSearchPages as $page): ?>
                            <a
                                href="<?= htmlspecialchars(
                                    $page['url'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                class="staff-command-result"
                                data-staff-search-item
                                data-search-title="<?= htmlspecialchars(
                                    $page['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                data-search-description="<?= htmlspecialchars(
                                    $page['description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                data-search-keywords="<?= htmlspecialchars(
                                    $page['keywords'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                data-search-icon="<?= htmlspecialchars(
                                    $page['icon'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <span
                                    class="staff-command-result__icon
                                           material-icons"
                                    aria-hidden="true"
                                >
                                    <?= htmlspecialchars(
                                        $page['icon'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                                <span class="staff-command-result__copy">
                                    <strong>
                                        <?= htmlspecialchars(
                                            $page['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= htmlspecialchars(
                                            $page['description'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </small>
                                </span>

                                <span
                                    class="staff-command-result__arrow
                                           material-icons"
                                    aria-hidden="true"
                                >
                                    arrow_forward
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section
                    class="staff-command-section"
                    data-staff-search-results-section
                    hidden
                >
                    <div class="staff-command-section__heading">
                        <span>検索結果</span>

                        <small data-staff-search-count></small>
                    </div>

                    <div
                        class="staff-command-results"
                        data-staff-search-results
                    ></div>
                </section>

                <div
                    class="staff-command-empty"
                    data-staff-search-empty
                    hidden
                >
                    <span class="material-icons" aria-hidden="true">
                        search_off
                    </span>

                    <strong>検索結果がありません</strong>

                    <p>
                        別のキーワードで検索してください。
                    </p>
                </div>
            </div>

            <footer class="staff-command-palette__footer">
                <span>
                    <kbd>↑</kbd>
                    <kbd>↓</kbd>
                    移動
                </span>

                <span>
                    <kbd>Enter</kbd>
                    開く
                </span>

                <span>
                    <kbd>Esc</kbd>
                    閉じる
                </span>
            </footer>
        </section>
    </div>
