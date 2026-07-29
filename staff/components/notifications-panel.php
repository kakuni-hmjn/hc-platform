<?php

declare(strict_types=1);
?>

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
