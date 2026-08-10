(() => {
    'use strict';

    const pageSelector = '[data-staff-page]';
    const supportPathPrefix = '/staff/support/';
    const progress = document.querySelector(
        '[data-staff-navigation-progress]'
    );

    let currentController = null;
    let navigating = false;

    const normalizePath = (value) => {
        try {
            const url = new URL(
                value,
                window.location.origin
            );

            return (
                url.pathname.replace(/\/+$/, '')
                || '/'
            ) + '/';
        } catch {
            return '/';
        }
    };

    const isSupportUrl = (value) => {
        try {
            const url = new URL(
                value,
                window.location.origin
            );

            return normalizePath(url.toString())
                .startsWith(supportPathPrefix);
        } catch {
            return false;
        }
    };

    const captureScrollSnapshot = () => ({
        windowX: window.scrollX,
        windowY: window.scrollY,
        elements: Array.from(
            document.querySelectorAll(
                '[data-staff-preserve-scroll]'
            )
        ).reduce((positions, element) => {
            const key = element.dataset.staffPreserveScroll;

            if (key) {
                positions[key] = {
                    top: element.scrollTop,
                    left: element.scrollLeft
                };
            }

            return positions;
        }, {})
    });

    const restoreScrollSnapshot = (snapshot) => {
        if (!snapshot) {
            return;
        }

        requestAnimationFrame(() => {
            document.querySelectorAll(
                '[data-staff-preserve-scroll]'
            ).forEach((element) => {
                const key = element.dataset.staffPreserveScroll;
                const position = key
                    ? snapshot.elements[key]
                    : null;

                if (!position) {
                    return;
                }

                element.scrollTop = position.top;
                element.scrollLeft = position.left;
            });

            window.scrollTo({
                left: snapshot.windowX,
                top: snapshot.windowY,
                behavior: 'instant'
            });
        });
    };

    const setProgress = (state) => {
        if (!progress) {
            return;
        }

        progress.classList.toggle(
            'is-loading',
            state === 'loading'
        );

        progress.classList.toggle(
            'is-complete',
            state === 'complete'
        );

        if (state === 'idle') {
            progress.classList.remove(
                'is-loading',
                'is-complete'
            );
        }
    };

    const updateActiveNavigation = (url) => {
        const currentPath = normalizePath(url);
        const items = Array.from(
            document.querySelectorAll('.staff-nav-item')
        );

        const candidates = items
            .map((item) => {
                const href = item.getAttribute('href');

                if (!href) {
                    return null;
                }

                const itemPath = normalizePath(href);
                const exact = currentPath === itemPath;
                const prefix = itemPath !== '/staff/'
                    && currentPath.startsWith(itemPath);

                if (!exact && !prefix) {
                    return null;
                }

                const entry = item.closest(
                    '[data-staff-nav-entry]'
                );
                const depthClass = Array.from(
                    entry?.classList ?? []
                ).find((name) => (
                    name.startsWith('staff-nav-entry--depth-')
                ));
                const depth = Number(
                    depthClass?.replace(
                        'staff-nav-entry--depth-',
                        ''
                    ) ?? 0
                );

                return {
                    item,
                    itemPath,
                    exact,
                    depth
                };
            })
            .filter(Boolean)
            .sort((a, b) => (
                Number(b.exact) - Number(a.exact)
                || b.itemPath.length - a.itemPath.length
                || b.depth - a.depth
            ));

        const activeItem = candidates[0]?.item ?? null;

        items.forEach((item) => {
            const active = item === activeItem;

            item.classList.toggle(
                'staff-nav-item--active',
                active
            );
            item.classList.remove(
                'staff-nav-item--ancestor'
            );

            if (active) {
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        let childEntry = activeItem?.closest(
            '[data-staff-nav-entry]'
        ) ?? null;

        while (childEntry) {
            const parentEntry = childEntry.parentElement
                ?.closest('[data-staff-nav-entry]');

            if (!parentEntry) {
                break;
            }

            const parentLink = parentEntry.querySelector(
                ':scope > .staff-nav-entry__row > .staff-nav-item'
            );
            const parentToggle = parentEntry.querySelector(
                ':scope > .staff-nav-entry__row '
                + '[data-staff-nav-toggle]'
            );
            const parentChildren = parentEntry.querySelector(
                ':scope > [data-staff-nav-children]'
            );

            parentLink?.classList.add(
                'staff-nav-item--ancestor'
            );
            parentEntry.classList.add('is-expanded');
            parentToggle?.setAttribute('aria-expanded', 'true');

            if (parentChildren) {
                parentChildren.hidden = false;
                parentChildren.style.height = '';
            }

            childEntry = parentEntry;
        }
    };

    const animatePage = (page) => {
        page.classList.remove(
            'staff-page--enter'
        );

        void page.offsetWidth;

        page.classList.add(
            'staff-page--enter'
        );

        window.setTimeout(() => {
            page.classList.remove(
                'staff-page--enter'
            );
        }, 420);
    };

    const closeMobileSidebar = () => {
        const sidebar = document.querySelector(
            '[data-staff-sidebar]'
        );
        const openButton = document.querySelector(
            '[data-staff-sidebar-open]'
        );

        sidebar?.classList.remove(
            'staff-sidebar--open'
        );

        document
            .querySelector(
                '[data-staff-sidebar-backdrop]'
            )
            ?.classList.remove(
                'staff-sidebar-backdrop--visible'
            );

        document.body.style.overflow = '';
        openButton?.setAttribute('aria-expanded', 'false');

        if (window.matchMedia('(max-width: 1024px)').matches) {
            sidebar?.setAttribute('aria-hidden', 'true');
        }
    };

    const shouldHandleLink = (
        event,
        link
    ) => {
        if (
            event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
        ) {
            return false;
        }

        if (
            link.hasAttribute('download')
            || link.target === '_blank'
            || link.dataset.noStaffNavigation !== undefined
        ) {
            return false;
        }

        const href = link.getAttribute('href');

        if (
            !href
            || href.startsWith('#')
            || href.startsWith('mailto:')
            || href.startsWith('tel:')
            || href.startsWith('javascript:')
        ) {
            return false;
        }

        const url = new URL(
            href,
            window.location.origin
        );

        if (
            url.origin !== window.location.origin
            || !url.pathname.startsWith('/staff/')
        ) {
            return false;
        }

        return true;
    };

    const replacePage = (
        documentNode,
        targetUrl,
        {
            push,
            seamless,
            scrollSnapshot,
            previousUrl
        }
    ) => {
        const incomingPage =
            documentNode.querySelector(
                pageSelector
            );

        const currentPage =
            document.querySelector(
                pageSelector
            );

        if (!incomingPage || !currentPage) {
            window.location.href = targetUrl;
            return;
        }

        currentPage.replaceWith(
            incomingPage
        );

        document.title =
            documentNode.title
            || document.title;

        if (push) {
            window.history.pushState(
                {
                    staffNavigation: true,
                    preservePosition: seamless
                },
                '',
                targetUrl
            );
        }

        updateActiveNavigation(targetUrl);
        closeMobileSidebar();

        const newPage =
            document.querySelector(
                pageSelector
            );

        if (newPage && !seamless) {
            animatePage(newPage);
        }

        if (seamless) {
            restoreScrollSnapshot(scrollSnapshot);
        } else {
            window.scrollTo({
                top: 0,
                behavior: 'instant'
            });
        }

        document.dispatchEvent(
            new CustomEvent(
                'staff:navigation-complete',
                {
                    detail: {
                        url: targetUrl,
                        previousUrl,
                        seamless
                    }
                }
            )
        );
    };

    const navigate = async (
        target,
        {
            push = true
        } = {}
    ) => {
        const url = new URL(
            target,
            window.location.origin
        );
        const previousUrl = window.location.href;
        const seamless = isSupportUrl(previousUrl)
            && isSupportUrl(url.toString());
        const scrollSnapshot = seamless
            ? captureScrollSnapshot()
            : null;

        if (navigating) {
            currentController?.abort();
        }

        navigating = true;
        currentController =
            new AbortController();

        const currentPage =
            document.querySelector(
                pageSelector
            );

        if (!seamless) {
            currentPage?.classList.add(
                'staff-page--leaving'
            );
        }

        setProgress('loading');

        try {
            const response = await fetch(
                url.toString(),
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'text/html',
                        'X-Staff-Navigation': 'partial'
                    },
                    cache: 'no-store',
                    signal:
                        currentController.signal
                }
            );

            if (
                !response.ok
                || response.redirected
            ) {
                window.location.href =
                    response.url
                    || url.toString();
                return;
            }

            const html =
                await response.text();

            const documentNode =
                new DOMParser()
                    .parseFromString(
                        html,
                        'text/html'
                    );

            replacePage(
                documentNode,
                url.toString(),
                {
                    push,
                    seamless,
                    scrollSnapshot,
                    previousUrl
                }
            );

            setProgress('complete');

            window.setTimeout(() => {
                setProgress('idle');
            }, 320);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(
                'Staff navigation failed:',
                error
            );

            window.location.href =
                url.toString();
        } finally {
            navigating = false;

            document
                .querySelector(pageSelector)
                ?.classList.remove(
                    'staff-page--leaving'
                );
        }
    };

    document.addEventListener(
        'click',
        (event) => {
            const link =
                event.target.closest('a');

            if (
                !link
                || !shouldHandleLink(
                    event,
                    link
                )
            ) {
                return;
            }

            event.preventDefault();

            navigate(
                link.href
            );
        }
    );

    window.addEventListener(
        'popstate',
        () => {
            navigate(
                window.location.href,
                {
                    push: false
                }
            );
        }
    );

    window.staffNavigate = navigate;

    updateActiveNavigation(
        window.location.href
    );

    const initialPage =
        document.querySelector(
            pageSelector
        );

    if (initialPage) {
        animatePage(initialPage);
    }
})();
