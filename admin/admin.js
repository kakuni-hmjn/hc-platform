(() => {
    'use strict';

    const body = document.body;

    const openButtons = document.querySelectorAll('[data-admin-menu-open]');
    const closeButtons = document.querySelectorAll('[data-admin-menu-close]');

    const openMenu = () => {
        body.classList.add('admin-menu-open');
    };

    const closeMenu = () => {
        body.classList.remove('admin-menu-open');
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', openMenu);
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 960) {
            closeMenu();
        }
    });

    const filter = document.querySelector('[data-admin-category-filter]');
    const categorySections = document.querySelectorAll(
        '[data-admin-category]'
    );

    if (filter) {
        filter.addEventListener('change', () => {
            const selectedCategory = filter.value;

            categorySections.forEach((section) => {
                const category = section.dataset.adminCategory;
                const shouldShow =
                    selectedCategory === 'all'
                    || selectedCategory === category;

                section.hidden = !shouldShow;
            });
        });
    }

    document.querySelectorAll('[data-admin-dialog-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialogId = button.dataset.adminDialogOpen;
            const dialog = document.getElementById(dialogId);

            if (dialog instanceof HTMLDialogElement) {
                dialog.showModal();
            }
        });
    });

    document.querySelectorAll('[data-admin-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = button.closest('dialog');

            if (dialog instanceof HTMLDialogElement) {
                dialog.close();
            }
        });
    });

    document.querySelectorAll('.admin-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });

    const editDialog = document.getElementById('edit-category-dialog');
    const editKeyInput = document.querySelector('[data-edit-category-key]');
    const editNameInput = document.querySelector('[data-edit-category-name]');
    const editDescriptionInput = document.querySelector(
        '[data-edit-category-description]'
    );

    document.querySelectorAll('[data-admin-category-edit]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!(editDialog instanceof HTMLDialogElement)) {
                return;
            }

            editKeyInput.value = button.dataset.categoryKey ?? '';
            editNameInput.value = button.dataset.categoryName ?? '';
            editDescriptionInput.value =
                button.dataset.categoryDescription ?? '';

            editDialog.showModal();
        });
    });

    const deleteButton = document.querySelector(
        '[data-admin-category-delete]'
    );

    const deleteForm = document.querySelector(
        '[data-admin-delete-category-form]'
    );

    const deleteKeyInput = document.querySelector(
        '[data-delete-category-key]'
    );

    if (deleteButton && deleteForm && deleteKeyInput) {
        deleteButton.addEventListener('click', () => {
            const categoryKey = editKeyInput.value;
            const categoryName = editNameInput.value;

            if (categoryKey === 'uncategorized') {
                window.alert('未分類カテゴリは削除できません。');
                return;
            }

            const confirmed = window.confirm(
                `「${categoryName}」を削除しますか？\n`
                + '登録されている管理ページは未分類へ移動します。'
            );

            if (!confirmed) {
                return;
            }

            deleteKeyInput.value = categoryKey;
            deleteForm.submit();
        });
    }

    const categorySelects = document.querySelectorAll(
        '[data-admin-category-select]'
    );

    categorySelects.forEach((select) => {
        select.dataset.initialValue = select.value;

        select.addEventListener('change', () => {
            const row = select.closest('[data-admin-page-row]');
            const descriptionInput = row?.querySelector(
                '[data-admin-description-input]'
            );

            const categoryChanged =
                select.value !== select.dataset.initialValue;

            const descriptionChanged =
                descriptionInput
                && descriptionInput.value
                    !== descriptionInput.dataset.initialValue;

            if (row) {
                row.classList.toggle(
                    'admin-horizontal-row--changed',
                    Boolean(categoryChanged || descriptionChanged)
                );
            }
        });
    });


    const categoryButtons = document.querySelectorAll(
        '[data-admin-category-button]'
    );

    const dashboardCategorySections = document.querySelectorAll(
        '.admin-category-section[data-admin-category]'
    );

    const dashboardCategorySelect = document.querySelector(
        '[data-admin-category-filter]'
    );

    const applyCategoryFilter = (selectedCategory) => {
        dashboardCategorySections.forEach((section) => {
            const category = section.dataset.adminCategory;
            const shouldShow =
                selectedCategory === 'all'
                || category === selectedCategory;

            section.hidden = !shouldShow;
        });

        categoryButtons.forEach((button) => {
            button.classList.toggle(
                'admin-category-navigation__item--active',
                button.dataset.adminCategoryButton === selectedCategory
            );
        });

        if (dashboardCategorySelect) {
            dashboardCategorySelect.value = selectedCategory;
        }
    };

    categoryButtons.forEach((button) => {
        button.addEventListener('click', () => {
            applyCategoryFilter(
                button.dataset.adminCategoryButton ?? 'all'
            );
        });
    });

    if (dashboardCategorySelect) {
        dashboardCategorySelect.addEventListener('change', () => {
            applyCategoryFilter(dashboardCategorySelect.value);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | POST送信後のスクロール位置を復元
    |--------------------------------------------------------------------------
    */

    const scrollStorageKey = `admin-scroll-position:${window.location.pathname}`;

    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual';
    }

    const saveAdminScrollPosition = () => {
        sessionStorage.setItem(
            scrollStorageKey,
            JSON.stringify({
                x: window.scrollX,
                y: window.scrollY,
                savedAt: Date.now()
            })
        );
    };

    document.querySelectorAll('form[method="post"]').forEach((form) => {
        form.addEventListener('submit', () => {
            saveAdminScrollPosition();
        });
    });

    const restoreAdminScrollPosition = () => {
        const storedValue = sessionStorage.getItem(scrollStorageKey);

        if (!storedValue) {
            return;
        }

        sessionStorage.removeItem(scrollStorageKey);

        try {
            const position = JSON.parse(storedValue);

            if (
                typeof position.y !== 'number'
                || typeof position.savedAt !== 'number'
            ) {
                return;
            }

            /*
             * 古い保存情報では戻らないようにする。
             */
            if (Date.now() - position.savedAt > 30000) {
                return;
            }

            const restore = () => {
                window.scrollTo({
                    left: typeof position.x === 'number'
                        ? position.x
                        : 0,
                    top: position.y,
                    behavior: 'instant'
                });
            };

            requestAnimationFrame(() => {
                requestAnimationFrame(restore);
            });
        } catch (error) {
            sessionStorage.removeItem(scrollStorageKey);
        }
    };

    window.addEventListener('DOMContentLoaded', restoreAdminScrollPosition);


    const descriptionInputs = document.querySelectorAll(
        '[data-admin-description-input]'
    );

    descriptionInputs.forEach((input) => {
        input.dataset.initialValue = input.value;

        input.addEventListener('input', () => {
            const row = input.closest('[data-admin-page-row]');
            const categorySelect = row?.querySelector(
                '[data-admin-category-select]'
            );

            const descriptionChanged =
                input.value !== input.dataset.initialValue;

            const categoryChanged =
                categorySelect
                && categorySelect.value
                    !== categorySelect.dataset.initialValue;

            if (row) {
                row.classList.toggle(
                    'admin-horizontal-row--changed',
                    Boolean(descriptionChanged || categoryChanged)
                );
            }
        });
    });

})();
