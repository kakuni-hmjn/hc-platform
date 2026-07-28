(() => {
    'use strict';

    const pageSelector = '[data-staff-page]';
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

        document
            .querySelectorAll('.staff-nav-item')
            .forEach((item) => {
                const href = item.getAttribute('href');

                if (!href) {
                    return;
                }

                const itemPath = normalizePath(href);

                const active =
                    currentPath === itemPath
                    || (
                        itemPath !== '/staff/'
                        && currentPath.startsWith(
                            itemPath
                        )
                    );

                item.classList.toggle(
                    'staff-nav-item--active',
                    active
                );

                if (active) {
                    item.setAttribute(
                        'aria-current',
                        'page'
                    );
                } else {
                    item.removeAttribute(
                        'aria-current'
                    );
                }
            });
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
        document
            .querySelector('[data-staff-sidebar]')
            ?.classList.remove(
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
        push
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
                    staffNavigation: true
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

        if (newPage) {
            animatePage(newPage);
        }

        window.scrollTo({
            top: 0,
            behavior: 'instant'
        });

        document.dispatchEvent(
            new CustomEvent(
                'staff:navigation-complete',
                {
                    detail: {
                        url: targetUrl
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

        currentPage?.classList.add(
            'staff-page--leaving'
        );

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
                push
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
