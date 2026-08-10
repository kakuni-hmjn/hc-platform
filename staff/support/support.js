(() => {
    const scrollStoragePrefix = 'hc-staff-support-scroll-v2';

    const readScrollPosition = (key) => {
        try {
            const value = JSON.parse(
                sessionStorage.getItem(key) || 'null'
            );

            return value && Number.isFinite(value.top)
                ? value
                : null;
        } catch {
            return null;
        }
    };

    const writeScrollPosition = (element, key) => {
        try {
            sessionStorage.setItem(
                key,
                JSON.stringify({
                    top: element.scrollTop,
                    left: element.scrollLeft,
                    atEnd: (
                        element.scrollHeight
                        - element.clientHeight
                        - element.scrollTop
                    ) < 36
                })
            );
        } catch {
            // Storage can be unavailable in restricted browser modes.
        }
    };

    const keepScrollPosition = (
        element,
        key,
        {
            defaultToEnd = false,
            selectedItem = null,
            restore = true
        } = {}
    ) => {
        if (!element) {
            return;
        }

        const saved = readScrollPosition(key);
        let saveFrame = 0;

        if (restore) {
            requestAnimationFrame(() => {
                if (saved) {
                    element.scrollTop = defaultToEnd && saved.atEnd
                        ? element.scrollHeight
                        : saved.top;
                    element.scrollLeft = saved.left || 0;
                } else if (defaultToEnd) {
                    element.scrollTop = element.scrollHeight;
                } else if (selectedItem) {
                    const itemTop = selectedItem.offsetTop;
                    const itemBottom = itemTop + selectedItem.offsetHeight;
                    const visibleTop = element.scrollTop;
                    const visibleBottom = visibleTop + element.clientHeight;

                    if (itemTop < visibleTop) {
                        element.scrollTop = itemTop;
                    } else if (itemBottom > visibleBottom) {
                        element.scrollTop = itemBottom - element.clientHeight;
                    }
                }

                writeScrollPosition(element, key);
            });
        }

        element.addEventListener('scroll', () => {
            if (saveFrame) {
                return;
            }

            saveFrame = requestAnimationFrame(() => {
                saveFrame = 0;
                writeScrollPosition(element, key);
            });
        }, { passive: true });
    };

    const initializeSupport = (
        scope = document,
        {
            seamless = false
        } = {}
    ) => {
        const roots = scope.matches?.('[data-support-root]')
            ? [scope]
            : scope.querySelectorAll?.('[data-support-root]') ?? [];

        roots.forEach((root) => {
            if (root.dataset.supportInitialized === 'true') {
                return;
            }

            root.dataset.supportInitialized = 'true';

            const view = root.dataset.supportView || 'overview';
            const ticketId = Number(
                root.dataset.supportTicketId || 0
            );
            const hasSelectedTicket = (
                root.dataset.supportSelected === '1'
            );
            const ticketList = root.querySelector(
                '.support-ticket-list'
            );
            const selectedTicket = ticketList?.querySelector(
                '.support-ticket.is-selected'
            ) ?? null;

            keepScrollPosition(
                ticketList,
                `${scrollStoragePrefix}:list:${view}`,
                {
                    selectedItem: selectedTicket,
                    restore: !seamless || !hasSelectedTicket
                }
            );

            const detail = root.querySelector(
                '[data-support-detail-scroll]'
            );

            if (detail && ticketId > 0) {
                keepScrollPosition(
                    detail,
                    `${scrollStoragePrefix}:detail:${ticketId}`
                );
            }

            const conversation = root.querySelector(
                '[data-support-conversation]'
            );

            if (conversation && ticketId > 0) {
                keepScrollPosition(
                    conversation,
                    `${scrollStoragePrefix}:conversation:${ticketId}`,
                    {
                        defaultToEnd: true
                    }
                );
            }

            root.querySelectorAll('[data-support-auto-submit]')
                .forEach((select) => {
                    select.addEventListener('change', () => {
                        const form = select.closest('form');

                        if (!form || form.classList.contains('is-submitting')) {
                            return;
                        }

                        form.classList.add('is-submitting');
                        form.requestSubmit();
                    });
                });

            const composer = root.querySelector('[data-support-composer]');

            if (!composer) {
                return;
            }

            const body = composer.querySelector('[data-support-body]');
            const modeInput = composer.querySelector('[data-support-mode]');
            const modeButtons = composer.querySelectorAll(
                '[data-support-mode-button]'
            );
            const count = composer.querySelector('[data-support-count]');
            const submit = composer.querySelector('[data-support-submit]');
            const submitLabel = composer.querySelector(
                '[data-support-submit-label]'
            );
            const customerName = body?.placeholder
                ?.replace(/さんへのメッセージを入力…$/, '') ?? '';
            const replyLabel = composer.dataset.supportReplyLabel
                || '送信する';

            const updateCount = () => {
                if (!body || !count) {
                    return;
                }

                count.textContent = `${body.value.length} / 5000`;
            };

            const setMode = (mode) => {
                const isNote = mode === 'note';

                if (modeInput) {
                    modeInput.value = isNote ? 'note' : 'reply';
                }

                composer.classList.toggle('is-note', isNote);

                modeButtons.forEach((button) => {
                    const active = button.dataset.supportModeButton
                        === (isNote ? 'note' : 'reply');
                    button.classList.toggle('is-active', active);
                    button.setAttribute(
                        'aria-selected',
                        active ? 'true' : 'false'
                    );
                });

                if (body) {
                    body.placeholder = isNote
                        ? 'チーム内だけに共有するメモを入力…'
                        : `${customerName}さんへのメッセージを入力…`;
                    body.setAttribute(
                        'aria-label',
                        isNote ? '社内メモ' : '返信内容'
                    );
                }

                if (submitLabel) {
                    submitLabel.textContent = isNote
                        ? 'メモを追加'
                        : replyLabel;
                }
            };

            modeButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    setMode(button.dataset.supportModeButton ?? 'reply');
                    body?.focus();
                });
            });

            body?.addEventListener('input', updateCount);
            body?.addEventListener('keydown', (event) => {
                if (
                    event.key !== 'Enter'
                    || (!event.metaKey && !event.ctrlKey)
                ) {
                    return;
                }

                event.preventDefault();

                if (
                    body.disabled
                    || submit?.disabled
                    || body.value.trim() === ''
                ) {
                    return;
                }

                composer.requestSubmit();
            });

            composer.addEventListener('submit', (event) => {
                if (!body || body.value.trim() !== '') {
                    submit?.setAttribute('disabled', 'disabled');
                    return;
                }

                event.preventDefault();
                body.focus();
            });

            updateCount();
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initializeSupport();
    });

    document.addEventListener('staff:navigation-complete', (event) => {
        initializeSupport(
            event.target instanceof Element
                ? event.target
                : document,
            {
                seamless: Boolean(event.detail?.seamless)
            }
        );
    });
})();
