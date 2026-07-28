(() => {
    'use strict';

    const triggerSelector = '[data-staff-account-trigger]';
    const menuSelector = '[data-staff-account-menu]';
    const dropdownSelector = '[data-staff-account-dropdown]';

    const getParts = (trigger) => {
        const root = trigger.closest(menuSelector);

        if (!root) {
            return null;
        }

        const dropdown = root.querySelector(dropdownSelector);

        if (!dropdown) {
            return null;
        }

        return {
            root,
            trigger,
            dropdown
        };
    };

    const closeMenu = (parts) => {
        parts.dropdown.hidden = true;
        parts.dropdown.setAttribute('hidden', '');
        parts.root.classList.remove('is-open');
        parts.trigger.setAttribute('aria-expanded', 'false');
    };

    const openMenu = (parts) => {
        document
            .querySelectorAll(menuSelector)
            .forEach((otherRoot) => {
                if (otherRoot === parts.root) {
                    return;
                }

                const otherTrigger = otherRoot.querySelector(
                    triggerSelector
                );

                const otherDropdown = otherRoot.querySelector(
                    dropdownSelector
                );

                if (otherDropdown) {
                    otherDropdown.hidden = true;
                    otherDropdown.setAttribute('hidden', '');
                }

                if (otherTrigger) {
                    otherTrigger.setAttribute(
                        'aria-expanded',
                        'false'
                    );
                }

                otherRoot.classList.remove('is-open');
            });

        parts.dropdown.hidden = false;
        parts.dropdown.removeAttribute('hidden');
        parts.root.classList.add('is-open');
        parts.trigger.setAttribute('aria-expanded', 'true');
    };

    document.addEventListener(
        'click',
        (event) => {
            const trigger = event.target.closest(triggerSelector);

            if (trigger) {
                const parts = getParts(trigger);

                if (!parts) {
                    console.error(
                        'HC Staff: account menu elements not found'
                    );
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                const isOpen =
                    parts.trigger.getAttribute('aria-expanded')
                    === 'true';

                if (isOpen) {
                    closeMenu(parts);
                } else {
                    openMenu(parts);
                }

                return;
            }

            document
                .querySelectorAll(menuSelector)
                .forEach((root) => {
                    if (root.contains(event.target)) {
                        return;
                    }

                    const menuTrigger = root.querySelector(
                        triggerSelector
                    );

                    const dropdown = root.querySelector(
                        dropdownSelector
                    );

                    if (!menuTrigger || !dropdown) {
                        return;
                    }

                    closeMenu({
                        root,
                        trigger: menuTrigger,
                        dropdown
                    });
                });
        },
        true
    );

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document
            .querySelectorAll(menuSelector)
            .forEach((root) => {
                const trigger = root.querySelector(
                    triggerSelector
                );

                const dropdown = root.querySelector(
                    dropdownSelector
                );

                if (!trigger || !dropdown) {
                    return;
                }

                if (
                    trigger.getAttribute('aria-expanded')
                    === 'true'
                ) {
                    closeMenu({
                        root,
                        trigger,
                        dropdown
                    });

                    trigger.focus();
                }
            });
    });
})();
