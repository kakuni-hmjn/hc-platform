(() => {
    'use strict';

    const mobileSidebarQuery = window.matchMedia(
        '(max-width: 1024px)'
    );
    const sidebarScrollKey = 'hc-staff-sidebar-scroll-v1';

    const sidebar = document.querySelector(
        '[data-staff-sidebar]'
    );

    const backdrop = document.querySelector(
        '[data-staff-sidebar-backdrop]'
    );

    const openButton = document.querySelector(
        '[data-staff-sidebar-open]'
    );

    const closeButton = document.querySelector(
        '[data-staff-sidebar-close]'
    );

    const navigation = sidebar?.querySelector(
        '[data-staff-navigation]'
    );

    const setSidebarAccessibility = (open) => {
        const mobile = mobileSidebarQuery.matches;

        openButton?.setAttribute(
            'aria-expanded',
            mobile && open ? 'true' : 'false'
        );

        if (!sidebar) {
            return;
        }

        if (mobile) {
            sidebar.setAttribute(
                'aria-hidden',
                open ? 'false' : 'true'
            );
        } else {
            sidebar.removeAttribute('aria-hidden');
        }
    };

    const openSidebar = () => {
        if (!mobileSidebarQuery.matches) {
            return;
        }

        sidebar?.classList.add(
            'staff-sidebar--open'
        );

        backdrop?.classList.add(
            'staff-sidebar-backdrop--visible'
        );

        document.body.style.overflow = 'hidden';
        setSidebarAccessibility(true);
        closeButton?.focus({ preventScroll: true });
    };

    const closeSidebar = () => {
        sidebar?.classList.remove(
            'staff-sidebar--open'
        );

        backdrop?.classList.remove(
            'staff-sidebar-backdrop--visible'
        );

        document.body.style.overflow = '';
        setSidebarAccessibility(false);
    };

    const syncSidebarMode = () => {
        if (!mobileSidebarQuery.matches) {
            closeSidebar();
            return;
        }

        setSidebarAccessibility(
            sidebar?.classList.contains('staff-sidebar--open')
                ?? false
        );
    };

    openButton?.addEventListener(
        'click',
        openSidebar
    );

    closeButton?.addEventListener(
        'click',
        closeSidebar
    );

    backdrop?.addEventListener(
        'click',
        closeSidebar
    );

    window.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key === 'Escape'
                && sidebar?.classList.contains(
                    'staff-sidebar--open'
                )
            ) {
                closeSidebar();
                openButton?.focus({ preventScroll: true });
            }
        }
    );

    mobileSidebarQuery.addEventListener(
        'change',
        syncSidebarMode
    );

    if (navigation) {
        try {
            const savedScroll = Number(
                sessionStorage.getItem(sidebarScrollKey) || 0
            );

            requestAnimationFrame(() => {
                navigation.scrollTop = Number.isFinite(savedScroll)
                    ? savedScroll
                    : 0;
            });
        } catch {
            // Storage can be unavailable in restricted browser modes.
        }

        let saveFrame = 0;

        navigation.addEventListener('scroll', () => {
            if (saveFrame) {
                return;
            }

            saveFrame = requestAnimationFrame(() => {
                saveFrame = 0;

                try {
                    sessionStorage.setItem(
                        sidebarScrollKey,
                        String(navigation.scrollTop)
                    );
                } catch {
                    // Storage can be unavailable in restricted browser modes.
                }
            });
        }, { passive: true });
    }

    syncSidebarMode();
})();

/* Mobile management: selecting a row reveals its detail panel. */
(() => {
    'use strict';

    const mobileQuery = window.matchMedia('(max-width: 820px)');

    const revealSelectedDetail = () => {
        if (!mobileQuery.matches) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const detail = document.querySelector('.ops-detail');

        if (!params.has('id') || !detail) {
            return;
        }

        requestAnimationFrame(() => {
            detail.scrollIntoView({
                behavior: 'auto',
                block: 'start'
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            revealSelectedDetail,
            { once: true }
        );
    } else {
        revealSelectedDetail();
    }

    document.addEventListener(
        'staff:navigation-complete',
        revealSelectedDetail
    );
})();

/* ==========================================================
   Staff Notification Center
   ========================================================== */

(() => {
    'use strict';

    const root = document.querySelector('[data-staff-notifications]');

    if (!root) {
        return;
    }

    const trigger = root.querySelector('[data-notification-trigger]');
    const panel = root.querySelector('[data-notification-panel]');
    const badge = root.querySelector('[data-notification-badge]');
    const body = root.querySelector('[data-notification-body]');
    const readAllButton = root.querySelector(
        '[data-notification-read-all]'
    );
    const footerCount = root.querySelector(
        '[data-notification-footer-count]'
    );
    const categoryButtons = Array.from(
        root.querySelectorAll('[data-notification-category]')
    );

    let activeCategory = 'all';
    let notifications = [];
    let loading = false;

    const categoryLabels = {
        system: 'システム',
        order: '注文',
        user: 'ユーザー',
        discord: 'Discord',
        github: 'GitHub',
        development: '開発'
    };

    const levelSymbols = {
        critical: '!',
        warning: '!',
        success: '✓',
        info: 'i',
        development: '</>'
    };

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    };

    const formatDate = (value) => {
        if (!value) {
            return '';
        }

        const normalized = String(value).replace(
            ' ',
            'T'
        );

        const date = new Date(normalized);

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        const seconds = Math.floor(
            (Date.now() - date.getTime()) / 1000
        );

        if (seconds < 60) {
            return 'たった今';
        }

        const minutes = Math.floor(seconds / 60);

        if (minutes < 60) {
            return `${minutes}分前`;
        }

        const hours = Math.floor(minutes / 60);

        if (hours < 24) {
            return `${hours}時間前`;
        }

        const days = Math.floor(hours / 24);

        if (days < 7) {
            return `${days}日前`;
        }

        return new Intl.DateTimeFormat('ja-JP', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    };

    const updateBadge = (count) => {
        const unread = Number(count) || 0;

        if (unread <= 0) {
            badge.hidden = true;
            badge.textContent = '0';
            return;
        }

        badge.hidden = false;
        badge.textContent = unread > 99
            ? '99+'
            : String(unread);
    };

    const notificationItemHtml = (item) => {
        const level = [
            'critical',
            'warning',
            'success',
            'info',
            'development'
        ].includes(item.level)
            ? item.level
            : 'info';

        const symbol = levelSymbols[level] || 'i';
        const category = categoryLabels[item.category]
            || item.category
            || '通知';

        return `
            <button
                type="button"
                class="staff-notifications__item${
                    item.is_read ? '' : ' is-unread'
                }"
                data-notification-id="${Number(item.id)}"
                data-notification-url="${
                    escapeHtml(item.url || '')
                }"
            >
                <span
                    class="staff-notifications__icon
                    staff-notifications__icon--${level}"
                    aria-hidden="true"
                >
                    ${escapeHtml(symbol)}
                </span>

                <span class="staff-notifications__content">
                    <strong class="staff-notifications__item-title">
                        ${escapeHtml(item.title)}
                    </strong>

                    <span class="staff-notifications__message">
                        ${escapeHtml(item.message || '')}
                    </span>

                    <span class="staff-notifications__meta">
                        <span class="staff-notifications__category">
                            ${escapeHtml(category)}
                        </span>

                        <time>
                            ${escapeHtml(formatDate(item.created_at))}
                        </time>

                        ${
                            item.url
                                ? `
                                    <span
                                        class="staff-notifications__open"
                                        data-notification-open
                                        role="link"
                                        tabindex="0"
                                    >
                                        開く
                                        <span aria-hidden="true">→</span>
                                    </span>
                                `
                                : ''
                        }
                    </span>
                </span>
            </button>
        `;
    };

    const render = () => {
        if (!notifications.length) {
            body.innerHTML = `
                <div class="staff-notifications__empty">
                    現在表示できる通知はありません。
                </div>
            `;

            footerCount.textContent = '通知はありません';
            readAllButton.disabled = true;
            return;
        }

        body.innerHTML = notifications
            .map(notificationItemHtml)
            .join('');

        const unreadCount = notifications.filter(
            (item) => !item.is_read
        ).length;

        footerCount.textContent =
            `${notifications.length}件を表示中`;

        readAllButton.disabled = unreadCount === 0;
    };

    const loadNotifications = async ({
        silent = false
    } = {}) => {
        if (loading) {
            return;
        }

        loading = true;

        if (!silent) {
            body.innerHTML = `
                <div class="staff-notifications__loading">
                    通知を読み込んでいます
                </div>
            `;
        }

        try {
            const response = await fetch(
                `/staff/api/notifications/?category=${
                    encodeURIComponent(activeCategory)
                }&limit=30`,
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json'
                    },
                    cache: 'no-store'
                }
            );

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(
                    data.message || '通知の取得に失敗しました。'
                );
            }

            notifications = Array.isArray(data.items)
                ? data.items
                : [];

            updateBadge(data.unread);
            render();
        } catch (error) {
            if (!silent) {
                body.innerHTML = `
                    <div class="staff-notifications__error">
                        ${escapeHtml(
                            error.message
                            || '通知の取得に失敗しました。'
                        )}
                    </div>
                `;

                footerCount.textContent =
                    '通知を取得できませんでした';
            }
        } finally {
            loading = false;
        }
    };

    const markAsRead = async (id) => {
        const response = await fetch(
            '/staff/api/notifications/read.php',
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json'
                },
                body: JSON.stringify({
                    id
                })
            }
        );

        const data = await response.json();

        if (!response.ok || !data.ok) {
            throw new Error(
                data.message || '既読処理に失敗しました。'
            );
        }

        const item = notifications.find(
            (notification) => Number(notification.id) === Number(id)
        );

        if (item) {
            item.is_read = true;
        }

        return data;
    };

    const markAllAsRead = async () => {
        readAllButton.disabled = true;

        try {
            const response = await fetch(
                '/staff/api/notifications/read-all.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json'
                    }
                }
            );

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(
                    data.message
                    || '一括既読処理に失敗しました。'
                );
            }

            notifications = notifications.map((item) => ({
                ...item,
                is_read: true
            }));

            updateBadge(0);
            render();
        } catch (error) {
            console.error(error);
            readAllButton.disabled = false;
        }
    };

    const openPanel = () => {
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        loadNotifications();
    };

    const closePanel = () => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();

        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });

    categoryButtons.forEach((button) => {
        button.addEventListener('click', () => {
            activeCategory =
                button.dataset.notificationCategory || 'all';

            categoryButtons.forEach((item) => {
                item.classList.toggle(
                    'is-active',
                    item === button
                );
            });

            loadNotifications();
        });
    });

    readAllButton.addEventListener('click', () => {
        markAllAsRead();
    });

    body.addEventListener('click', async (event) => {
        const item = event.target.closest(
            '[data-notification-id]'
        );

        if (!item) {
            return;
        }

        const openButton = event.target.closest(
            '[data-notification-open]'
        );

        const url = item.dataset.notificationUrl || '';

        if (openButton) {
            event.preventDefault();
            event.stopPropagation();

            if (url) {
                window.location.href = url;
            }

            return;
        }

        event.preventDefault();

        const id = Number(item.dataset.notificationId);

        if (
            !id
            || !item.classList.contains('is-unread')
            || item.dataset.notificationReading === 'true'
        ) {
            return;
        }

        item.dataset.notificationReading = 'true';

        try {
            await markAsRead(id);

            item.classList.remove('is-unread');

            const notification = notifications.find(
                (entry) => Number(entry.id) === id
            );

            if (notification) {
                notification.is_read = true;
            }

            const unreadCount = notifications.filter(
                (entry) => !entry.is_read
            ).length;

            updateBadge(unreadCount);
            readAllButton.disabled = unreadCount === 0;
        } catch (error) {
            console.error(error);
        } finally {
            delete item.dataset.notificationReading;
        }
    });

    body.addEventListener('keydown', (event) => {
        const openButton = event.target.closest(
            '[data-notification-open]'
        );

        if (
            !openButton
            || (
                event.key !== 'Enter'
                && event.key !== ' '
            )
        ) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const item = openButton.closest(
            '[data-notification-id]'
        );

        const url = item?.dataset.notificationUrl || '';

        if (url) {
            window.location.href = url;
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closePanel();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanel();
            trigger.focus();
        }
    });

    loadNotifications({
        silent: true
    });

    window.setInterval(() => {
        loadNotifications({
            silent: true
        });
    }, 30000);
})();

/* ==========================================================
   Staff work status menu
   ========================================================== */

(() => {
    'use strict';

    const root = document.querySelector(
        '[data-staff-status-menu]'
    );

    if (!root) {
        return;
    }

    const trigger = root.querySelector(
        '[data-staff-status-trigger]'
    );
    const dropdown = root.querySelector(
        '[data-staff-status-dropdown]'
    );
    const label = root.querySelector(
        '[data-staff-status-label]'
    );
    const dot = root.querySelector(
        '[data-staff-status-dot]'
    );
    const message = root.querySelector(
        '[data-staff-status-message]'
    );
    const options = Array.from(
        root.querySelectorAll(
            '[data-staff-status-option]'
        )
    );

    if (
        !trigger
        || !dropdown
        || !label
        || !dot
        || !message
    ) {
        return;
    }

    const allowedStatuses = [
        'online',
        'working',
        'busy',
        'away',
        'break',
        'offline'
    ];

    const close = () => {
        dropdown.hidden = true;
        trigger.setAttribute(
            'aria-expanded',
            'false'
        );
    };

    const open = () => {
        dropdown.hidden = false;
        trigger.setAttribute(
            'aria-expanded',
            'true'
        );
        message.hidden = true;
    };

    const setLoading = (loading) => {
        options.forEach((option) => {
            option.disabled = loading;
        });
    };

    const updateDisplay = (status, statusLabel) => {
        label.textContent = statusLabel;
        trigger.title = statusLabel;

        allowedStatuses.forEach((value) => {
            dot.classList.remove(
                `staff-status-button__dot--${value}`
            );
        });

        dot.classList.add(
            `staff-status-button__dot--${status}`
        );

        options.forEach((option) => {
            option.classList.toggle(
                'is-active',
                option.dataset.staffStatusOption
                    === status
            );
        });

        const accountDots = document.querySelectorAll(
            '[data-staff-account-status-dot], '
            + '[data-staff-account-dropdown-status]'
        );

        accountDots.forEach((accountDot) => {
            allowedStatuses.forEach((value) => {
                accountDot.classList.remove(
                    `staff-topbar-profile__status--${value}`,
                    `staff-account-dropdown__status--${value}`
                );
            });

            if (
                accountDot.hasAttribute(
                    'data-staff-account-status-dot'
                )
            ) {
                accountDot.classList.add(
                    `staff-topbar-profile__status--${status}`
                );
            } else {
                accountDot.classList.add(
                    `staff-account-dropdown__status--${status}`
                );
            }
        });

        const accountStatusLabel = document.querySelector(
            '[data-staff-account-status-label]'
        );

        if (accountStatusLabel) {
            accountStatusLabel.textContent = statusLabel;
        }
    };

    const updateStatus = async (status) => {
        setLoading(true);
        message.hidden = true;

        try {
            const response = await fetch(
                '/staff/api/status/update.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({
                        status
                    })
                }
            );

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(
                    data.message
                    || '勤務状態を更新できませんでした。'
                );
            }

            updateDisplay(
                data.status,
                data.label
            );

            close();
        } catch (error) {
            message.textContent =
                error.message
                || '勤務状態を更新できませんでした。';

            message.hidden = false;
        } finally {
            setLoading(false);
        }
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();

        if (dropdown.hidden) {
            open();
        } else {
            close();
        }
    });

    options.forEach((option) => {
        option.addEventListener('click', () => {
            const status =
                option.dataset.staffStatusOption;

            if (
                !status
                || !allowedStatuses.includes(status)
                || option.classList.contains('is-active')
            ) {
                close();
                return;
            }

            updateStatus(status);
        });
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape'
            && !dropdown.hidden
        ) {
            close();
            trigger.focus();
        }
    });
})();

/* ==========================================================
   Staff account menu
   ========================================================== */

(() => {
    'use strict';

    const root = document.querySelector(
        '[data-staff-account-menu]'
    );

    if (!root) {
        return;
    }

    const trigger = root.querySelector(
        '[data-staff-account-trigger]'
    );
    const dropdown = root.querySelector(
        '[data-staff-account-dropdown]'
    );

    if (!trigger || !dropdown) {
        return;
    }

    const close = () => {
        dropdown.hidden = true;
        trigger.setAttribute(
            'aria-expanded',
            'false'
        );
    };

    const open = () => {
        dropdown.hidden = false;
        trigger.setAttribute(
            'aria-expanded',
            'true'
        );
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();

        if (dropdown.hidden) {
            open();
        } else {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape'
            && !dropdown.hidden
        ) {
            close();
            trigger.focus();
        }
    });
})();

/* ==========================================================
   Staff account menu
   ========================================================== */

(() => {
    'use strict';

    const root = document.querySelector(
        '[data-staff-account-menu]'
    );

    if (!root) {
        return;
    }

    const trigger = root.querySelector(
        '[data-staff-account-trigger]'
    );
    const dropdown = root.querySelector(
        '[data-staff-account-dropdown]'
    );

    if (!trigger || !dropdown) {
        return;
    }

    const close = () => {
        dropdown.hidden = true;
        trigger.setAttribute(
            'aria-expanded',
            'false'
        );
    };

    const open = () => {
        dropdown.hidden = false;
        trigger.setAttribute(
            'aria-expanded',
            'true'
        );
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();

        if (dropdown.hidden) {
            open();
        } else {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (
            event.key === 'Escape'
            && !dropdown.hidden
        ) {
            close();
            trigger.focus();
        }
    });
})();

/* HC_STAFF_NAV_ACCORDION_START */

/* ==========================================================
   Staff sidebar accordion
   ========================================================== */

(() => {
    'use strict';

    const STORAGE_KEY = 'hc-staff-open-navigation';

    const initializeStaffNavigation = () => {
        const navigation = document.querySelector(
            '[data-staff-navigation]'
        );

        if (!navigation) {
            return;
        }

        const alreadyInitialized = (
            navigation.dataset.staffNavigationInitialized
            === 'true'
        );

        const entries = Array.from(
            navigation.querySelectorAll(
                '[data-staff-nav-entry]'
            )
        );

        const loadStoredKeys = () => {
            try {
                const value = JSON.parse(
                    sessionStorage.getItem(STORAGE_KEY) || '[]'
                );

                return Array.isArray(value)
                    ? value
                    : [];
            } catch {
                return [];
            }
        };

        const saveOpenKeys = () => {
            const openKeys = entries
                .filter((entry) => (
                    entry.classList.contains('is-expanded')
                    && entry.dataset.staffNavKey
                ))
                .map((entry) => entry.dataset.staffNavKey);

            sessionStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(openKeys)
            );
        };

        const setExpanded = (
            entry,
            expanded,
            animate = true
        ) => {
            const toggle = entry.querySelector(
                ':scope > .staff-nav-entry__row '
                + '[data-staff-nav-toggle]'
            );

            const children = entry.querySelector(
                ':scope > [data-staff-nav-children]'
            );

            if (!toggle || !children) {
                return;
            }

            entry.classList.toggle(
                'is-expanded',
                expanded
            );

            toggle.setAttribute(
                'aria-expanded',
                expanded ? 'true' : 'false'
            );

            if (!animate) {
                children.hidden = !expanded;
                children.style.height = '';
                return;
            }

            children.hidden = false;

            const currentHeight = children.getBoundingClientRect().height;
            const targetHeight = expanded
                ? children.scrollHeight
                : 0;

            children.style.height = `${currentHeight}px`;
            children.offsetHeight;
            children.style.height = `${targetHeight}px`;

            const finish = () => {
                children.removeEventListener(
                    'transitionend',
                    finish
                );

                children.style.height = '';

                if (!expanded) {
                    children.hidden = true;
                }
            };

            children.addEventListener(
                'transitionend',
                finish
            );
        };

        const closeSiblingEntries = (entry) => {
            const parent = entry.parentElement;

            if (!parent) {
                return;
            }

            Array.from(parent.children).forEach((sibling) => {
                if (
                    sibling === entry
                    || !sibling.matches(
                        '[data-staff-nav-entry]'
                    )
                ) {
                    return;
                }

                setExpanded(
                    sibling,
                    false,
                    true
                );
            });
        };

        const storedKeys = loadStoredKeys();

        entries.forEach((entry) => {
            const hasActiveItem = Boolean(
                entry.querySelector(
                    ':scope > .staff-nav-entry__row '
                    + '.staff-nav-item--active, '
                    + ':scope > .staff-nav-entry__row '
                    + '.staff-nav-item--ancestor'
                )
            );

            const shouldOpen = (
                entry.classList.contains('is-expanded')
                || hasActiveItem
                || storedKeys.includes(
                    entry.dataset.staffNavKey
                )
            );

            setExpanded(
                entry,
                shouldOpen,
                false
            );
        });

        if (alreadyInitialized) {
            return;
        }

        navigation.dataset.staffNavigationInitialized = 'true';

        navigation.addEventListener(
            'click',
            (event) => {
                const toggle = event.target.closest(
                    '[data-staff-nav-toggle]'
                );

                if (!toggle) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const entry = toggle.closest(
                    '[data-staff-nav-entry]'
                );

                if (!entry) {
                    return;
                }

                const willExpand = !entry.classList.contains(
                    'is-expanded'
                );

                if (willExpand) {
                    closeSiblingEntries(entry);
                }

                setExpanded(
                    entry,
                    willExpand,
                    true
                );

                saveOpenKeys();
            }
        );

        navigation.addEventListener(
            'click',
            (event) => {
                const parentLink = event.target.closest(
                    '.staff-nav-item--parent'
                );

                if (!parentLink) {
                    return;
                }

                if (
                    event.metaKey
                    || event.ctrlKey
                    || event.shiftKey
                    || event.altKey
                ) {
                    return;
                }

                const entry = parentLink.closest(
                    '[data-staff-nav-entry]'
                );

                if (!entry) {
                    return;
                }

                const isExactPage = parentLink.matches(
                    '[aria-current="page"]'
                );

                if (!isExactPage) {
                    return;
                }

                event.preventDefault();

                const toggle = entry.querySelector(
                    ':scope > .staff-nav-entry__row '
                    + '[data-staff-nav-toggle]'
                );

                toggle?.click();
            }
        );
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initializeStaffNavigation,
            {
                once: true
            }
        );
    } else {
        initializeStaffNavigation();
    }

    window.addEventListener(
        'staff:navigation-complete',
        initializeStaffNavigation
    );
})();

/* HC_STAFF_NAV_ACCORDION_END */

/* HPMC sidebar dropdown state */
(() => {
    'use strict';

    const group = document.querySelector(
        '[data-staff-sidebar-group="hpmc"]'
    );

    if (!(group instanceof HTMLDetailsElement)) {
        return;
    }

    const storageKey =
        'hc_staff_sidebar_hpmc_open_v1';

    const path = window.location.pathname;
    const isHpmcPage =
        path.startsWith('/staff/property/');

    if (isHpmcPage) {
        group.open = true;
    } else {
        const saved =
            window.localStorage.getItem(storageKey);

        if (saved === '1') {
            group.open = true;
        }

        if (saved === '0') {
            group.open = false;
        }
    }

    group.addEventListener('toggle', () => {
        window.localStorage.setItem(
            storageKey,
            group.open ? '1' : '0'
        );
    });
})();

/* HPMC sidebar dropdown state */
(() => {
    'use strict';

    const group = document.querySelector(
        '[data-staff-sidebar-group="hpmc"]'
    );

    if (!(group instanceof HTMLDetailsElement)) {
        return;
    }

    const storageKey =
        'hc_staff_sidebar_hpmc_open_v1';

    const path = window.location.pathname;
    const isHpmcPage =
        path.startsWith('/staff/property/');

    if (isHpmcPage) {
        group.open = true;
    } else {
        const saved =
            window.localStorage.getItem(storageKey);

        if (saved === '1') {
            group.open = true;
        }

        if (saved === '0') {
            group.open = false;
        }
    }

    group.addEventListener('toggle', () => {
        window.localStorage.setItem(
            storageKey,
            group.open ? '1' : '0'
        );
    });
})();

/* ==================================================
   HPMC full page navigation
   HPMCはページ固有JSを確実に初期化するため、
   スタッフコンソールの部分読み込みを使用しない。
   ================================================== */
(() => {
    'use strict';

    const hpmcPrefix = '/staff/property/';

    document.addEventListener(
        'click',
        (event) => {
            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
            ) {
                return;
            }

            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const link = target.closest('a[href]');

            if (!(link instanceof HTMLAnchorElement)) {
                return;
            }

            if (
                link.target === '_blank'
                || link.hasAttribute('download')
            ) {
                return;
            }

            let url;

            try {
                url = new URL(
                    link.href,
                    window.location.origin
                );
            } catch (error) {
                return;
            }

            if (
                url.origin !== window.location.origin
                || !url.pathname.startsWith(hpmcPrefix)
            ) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            const currentUrl =
                window.location.pathname
                + window.location.search
                + window.location.hash;

            const destinationUrl =
                url.pathname
                + url.search
                + url.hash;

            if (currentUrl === destinationUrl) {
                window.location.reload();
                return;
            }

            window.location.assign(url.href);
        },
        true
    );
})();
