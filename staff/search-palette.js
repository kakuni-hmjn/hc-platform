(() => {
    'use strict';

    const trigger = document.querySelector(
        '[data-staff-search-trigger]'
    );

    const palette = document.querySelector(
        '[data-staff-search-palette]'
    );

    if (!trigger || !palette) {
        console.warn('Staff search elements missing', {
            trigger: Boolean(trigger),
            palette: Boolean(palette),
        });

        return;
    }

    const input = palette.querySelector(
        '[data-staff-search-input]'
    );

    const defaultSection = palette.querySelector(
        '[data-staff-search-default-section]'
    );

    const resultsSection = palette.querySelector(
        '[data-staff-search-results-section]'
    );

    const resultsContainer = palette.querySelector(
        '[data-staff-search-results]'
    );

    const resultCount = palette.querySelector(
        '[data-staff-search-count]'
    );

    const emptyState = palette.querySelector(
        '[data-staff-search-empty]'
    );

    const recentSection = palette.querySelector(
        '[data-staff-search-recent-section]'
    );

    const recentContainer = palette.querySelector(
        '[data-staff-search-recent]'
    );

    const sourceItems = Array.from(
        palette.querySelectorAll(
            '[data-staff-search-item]'
        )
    );

    const storageKey = 'hc_staff_recent_pages';

    let activeIndex = -1;

    const normalize = (value) => {
        return String(value || '')
            .normalize('NFKC')
            .toLowerCase()
            .trim();
    };

    const escapeHtml = (value) => {
        const element = document.createElement('div');

        element.textContent = String(value || '');

        return element.innerHTML;
    };

    const sourceData = sourceItems.map((item) => ({
        title: item.dataset.searchTitle || '',
        description:
            item.dataset.searchDescription || '',
        keywords: item.dataset.searchKeywords || '',
        icon: item.dataset.searchIcon || 'search',
        url: item.getAttribute('href') || '#',
    }));

    const getRecent = () => {
        try {
            const value = JSON.parse(
                localStorage.getItem(storageKey) || '[]'
            );

            return Array.isArray(value)
                ? value.slice(0, 5)
                : [];
        } catch {
            return [];
        }
    };

    const saveRecent = (item) => {
        const recent = getRecent().filter(
            (entry) => entry.url !== item.url
        );

        recent.unshift(item);

        localStorage.setItem(
            storageKey,
            JSON.stringify(recent.slice(0, 5))
        );
    };

    const createResultMarkup = (
        item,
        isRecent = false
    ) => {
        return `
            <a
                href="${escapeHtml(item.url)}"
                class="staff-command-result"
                data-staff-search-selectable
                data-staff-search-navigation
                data-search-url="${escapeHtml(item.url)}"
                data-search-title="${escapeHtml(item.title)}"
                data-search-description="${escapeHtml(
                    item.description
                )}"
                data-search-icon="${escapeHtml(item.icon)}"
            >
                <span
                    class="staff-command-result__icon material-icons"
                    aria-hidden="true"
                >
                    ${escapeHtml(item.icon)}
                </span>

                <span class="staff-command-result__copy">
                    <strong>
                        ${escapeHtml(item.title)}
                    </strong>

                    <small>
                        ${escapeHtml(item.description)}
                    </small>
                </span>

                <span
                    class="staff-command-result__arrow material-icons"
                    aria-hidden="true"
                >
                    ${isRecent ? 'history' : 'arrow_forward'}
                </span>
            </a>
        `;
    };

    const getSelectableItems = () => {
        return Array.from(
            palette.querySelectorAll(
                '[data-staff-search-selectable]'
            )
        ).filter((item) => {
            return (
                !item.hidden
                && item.offsetParent !== null
            );
        });
    };

    const updateActiveItem = (nextIndex) => {
        const items = getSelectableItems();

        items.forEach((item) => {
            item.classList.remove('is-active');
        });

        if (!items.length) {
            activeIndex = -1;
            return;
        }

        activeIndex = Math.max(
            0,
            Math.min(nextIndex, items.length - 1)
        );

        const activeItem = items[activeIndex];

        activeItem.classList.add('is-active');

        activeItem.scrollIntoView({
            block: 'nearest',
        });
    };

    const renderRecent = () => {
        if (!recentSection || !recentContainer) {
            return;
        }

        const recent = getRecent();

        if (!recent.length) {
            recentSection.hidden = true;
            recentContainer.innerHTML = '';
            return;
        }

        recentContainer.innerHTML = recent
            .map((item) => {
                return createResultMarkup(item, true);
            })
            .join('');

        recentSection.hidden = false;
    };

    const renderSearch = (query) => {
        const normalizedQuery = normalize(query);

        activeIndex = -1;

        if (!normalizedQuery) {
            defaultSection.hidden = false;
            resultsSection.hidden = true;
            emptyState.hidden = true;

            renderRecent();
            return;
        }

        recentSection.hidden = true;
        defaultSection.hidden = true;

        const terms = normalizedQuery
            .split(/\s+/)
            .filter(Boolean);

        const matches = sourceData
            .map((item) => {
                const searchable = normalize(
                    [
                        item.title,
                        item.description,
                        item.keywords,
                    ].join(' ')
                );

                if (
                    !terms.every((term) => {
                        return searchable.includes(term);
                    })
                ) {
                    return null;
                }

                const title = normalize(item.title);
                let score = 0;

                if (title === normalizedQuery) {
                    score = 100;
                } else if (
                    title.startsWith(normalizedQuery)
                ) {
                    score = 50;
                } else if (
                    title.includes(normalizedQuery)
                ) {
                    score = 25;
                }

                return {
                    ...item,
                    score,
                };
            })
            .filter(Boolean)
            .sort((a, b) => b.score - a.score);

        resultsContainer.innerHTML = matches
            .map((item) => createResultMarkup(item))
            .join('');

        resultCount.textContent =
            `${matches.length}件`;

        resultsSection.hidden =
            matches.length === 0;

        emptyState.hidden =
            matches.length !== 0;

        if (matches.length) {
            updateActiveItem(0);
        }
    };

    const openPalette = () => {
        palette.hidden = false;
        palette.classList.add('is-open');

        trigger.setAttribute(
            'aria-expanded',
            'true'
        );

        document.documentElement.classList.add(
            'staff-command-palette-open'
        );

        document.body.classList.add(
            'staff-command-palette-open'
        );

        input.value = '';
        renderSearch('');

        window.requestAnimationFrame(() => {
            input.focus();
        });
    };

    const closePalette = () => {
        palette.classList.remove('is-open');
        palette.hidden = true;

        trigger.setAttribute(
            'aria-expanded',
            'false'
        );

        document.documentElement.classList.remove(
            'staff-command-palette-open'
        );

        document.body.classList.remove(
            'staff-command-palette-open'
        );

        activeIndex = -1;
    };

    const saveNavigation = (element) => {
        saveRecent({
            title: element.dataset.searchTitle || '',
            description:
                element.dataset.searchDescription || '',
            icon:
                element.dataset.searchIcon || 'search',
            url:
                element.dataset.searchUrl
                || element.getAttribute('href')
                || '#',
        });
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();

        if (palette.hidden) {
            openPalette();
        } else {
            closePalette();
        }
    });

    input.addEventListener('input', () => {
        renderSearch(input.value);
    });

    palette.addEventListener('click', (event) => {
        const closeButton = event.target.closest(
            '[data-staff-search-close]'
        );

        if (closeButton) {
            event.preventDefault();
            closePalette();
            return;
        }

        const clearRecent = event.target.closest(
            '[data-staff-search-clear-recent]'
        );

        if (clearRecent) {
            event.preventDefault();
            localStorage.removeItem(storageKey);
            renderRecent();
            return;
        }

        const navigation = event.target.closest(
            '[data-staff-search-navigation],'
            + '[data-staff-search-item]'
        );

        if (navigation) {
            saveNavigation(navigation);
        }
    });

    document.addEventListener('keydown', (event) => {
        const shortcutPressed =
            (event.metaKey || event.ctrlKey)
            && event.key.toLowerCase() === 'k';

        if (shortcutPressed) {
            event.preventDefault();

            if (palette.hidden) {
                openPalette();
            } else {
                closePalette();
            }

            return;
        }

        if (palette.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closePalette();
            trigger.focus();
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            updateActiveItem(activeIndex + 1);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();

            const items = getSelectableItems();

            updateActiveItem(
                activeIndex <= 0
                    ? items.length - 1
                    : activeIndex - 1
            );

            return;
        }

        if (event.key === 'Enter') {
            const items = getSelectableItems();
            const activeItem = items[activeIndex];

            if (!activeItem) {
                return;
            }

            event.preventDefault();
            saveNavigation(activeItem);

            window.location.href =
                activeItem.getAttribute('href');
        }
    });

    const isMac =
        navigator.userAgentData?.platform === 'macOS'
        || /Mac|iPhone|iPad|iPod/i.test(
            navigator.platform
            || navigator.userAgent
        );

    document
        .querySelectorAll(
            '[data-staff-search-modifier]'
        )
        .forEach((element) => {
            element.textContent =
                isMac ? '⌘' : 'Ctrl';
        });

    palette.hidden = true;
})();
