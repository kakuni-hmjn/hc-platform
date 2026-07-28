<?php

declare(strict_types=1);
?>

<div class="staff-notifications" data-staff-notifications>
    <button
        type="button"
        class="staff-notifications__trigger"
        data-notification-trigger
        aria-label="通知を開く"
        aria-expanded="false"
        aria-controls="staff-notification-panel"
    >
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
            class="staff-notifications__bell"
        >
            <path
                d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            <path
                d="M10 21h4"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />
        </svg>

        <span
            class="staff-notifications__badge"
            data-notification-badge
            hidden
        >
            0
        </span>
    </button>

    <section
        id="staff-notification-panel"
        class="staff-notifications__panel"
        data-notification-panel
        aria-label="通知"
        hidden
    >
        <header class="staff-notifications__header">
            <div>
                <span class="staff-notifications__eyebrow">
                    Notification Center
                </span>

                <h2>通知</h2>
            </div>

            <button
                type="button"
                class="staff-notifications__read-all"
                data-notification-read-all
            >
                すべて既読
            </button>
        </header>

        <nav
            class="staff-notifications__filters"
            aria-label="通知カテゴリ"
        >
            <button
                type="button"
                class="is-active"
                data-notification-category="all"
            >
                すべて
            </button>

            <button
                type="button"
                data-notification-category="system"
            >
                システム
            </button>

            <button
                type="button"
                data-notification-category="order"
            >
                注文
            </button>

            <button
                type="button"
                data-notification-category="discord"
            >
                Discord
            </button>

            <button
                type="button"
                data-notification-category="github"
            >
                GitHub
            </button>

            <button
                type="button"
                data-notification-category="development"
            >
                開発
            </button>
        </nav>

        <div
            class="staff-notifications__body"
            data-notification-body
        >
            <div class="staff-notifications__loading">
                通知を読み込んでいます
            </div>
        </div>

        <footer class="staff-notifications__footer">
            <span data-notification-footer-count>
                通知を取得中
            </span>
        </footer>
    </section>
</div>
