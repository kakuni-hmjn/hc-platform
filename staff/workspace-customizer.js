(() => {
    'use strict';

    const root = document.querySelector('[data-workspace-customizer]');
    if (!root) return;

    const list = root.querySelector('[data-widget-list]');
    list?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-widget-up], [data-widget-down]');
        if (!button) return;
        event.preventDefault();
        const item = button.closest('[data-widget-item]');
        if (!item) return;
        if (button.hasAttribute('data-widget-up') && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        } else if (button.hasAttribute('data-widget-down') && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }
    });

    root.querySelectorAll('[data-widget-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            const preset = button.dataset.widgetPreset;
            const focusWidgets = ['summary', 'tasks', 'announcements'];
            root.querySelectorAll('[data-widget-item]').forEach((item) => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (!checkbox) return;
                checkbox.checked = preset === 'all' || focusWidgets.includes(item.dataset.widgetItem || '');
            });
        });
    });

    const fileInput = root.querySelector('[data-background-file]');
    const preview = root.querySelector('[data-background-preview]');
    let previewUrl = '';
    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file || !preview) return;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        preview.style.setProperty('--workspace-preview-image', `url("${previewUrl}")`);
        preview.classList.add('has-image');
        const imageMode = root.querySelector('input[name="background_mode"][value="image"]');
        if (imageMode) imageMode.checked = true;
    });

    const overlay = root.querySelector('[data-overlay-range]');
    const output = root.querySelector('[data-overlay-output]');
    overlay?.addEventListener('input', () => {
        if (output) output.textContent = `${overlay.value}%`;
    });

    const scale = root.querySelector('[data-background-scale]');
    const scaleOutput = root.querySelector('[data-background-scale-output]');
    const updateScalePreview = () => {
        if (scaleOutput && scale) scaleOutput.textContent = `${scale.value}%`;
        if (preview && scale) {
            preview.style.setProperty(
                '--workspace-preview-scale',
                String(Number(scale.value) / 100)
            );
        }
    };
    scale?.addEventListener('input', updateScalePreview);
    updateScalePreview();

    const position = root.querySelector('[data-background-position]');
    const updatePositionPreview = () => {
        if (preview && position) {
            preview.style.setProperty(
                '--workspace-preview-position',
                position.value || 'center'
            );
        }
    };
    position?.addEventListener('change', updatePositionPreview);
    updatePositionPreview();

    const customLinkList = root.querySelector('[data-custom-link-list]');
    const customLinkTemplate = root.querySelector('[data-custom-link-template]');
    const customLinkAdd = root.querySelector('[data-custom-link-add]');
    const customLinkEmpty = root.querySelector('[data-custom-link-empty]');

    const updateCustomLinkState = () => {
        const count = customLinkList?.querySelectorAll('[data-custom-link-row]').length || 0;
        if (customLinkEmpty) customLinkEmpty.hidden = count > 0;
        if (customLinkAdd) customLinkAdd.disabled = count >= 12;
    };

    const updateCustomLinkIcon = (row) => {
        if (!row) return;
        const selectedIcon = row.querySelector('[data-custom-link-icon]:checked');
        const previewIcon = row.querySelector('[data-custom-link-icon-preview]');
        const summaryIcon = row.querySelector('[data-custom-link-icon-summary]');
        const summaryLabel = row.querySelector('[data-custom-link-icon-label]');
        const icon = selectedIcon?.value || 'link';
        const label = selectedIcon?.dataset.iconLabel || 'リンク';
        if (previewIcon) window.staffSetIcon?.(previewIcon, icon);
        if (summaryIcon) window.staffSetIcon?.(summaryIcon, icon);
        if (summaryLabel) summaryLabel.textContent = label;
    };

    customLinkList?.querySelectorAll('[data-custom-link-row]').forEach(updateCustomLinkIcon);

    customLinkAdd?.addEventListener('click', () => {
        if (!customLinkList || !customLinkTemplate) return;
        const count = customLinkList.querySelectorAll('[data-custom-link-row]').length;
        if (count >= 12) return;

        const index = Number(customLinkList.dataset.nextIndex || count);
        customLinkList.dataset.nextIndex = String(index + 1);
        const fragment = customLinkTemplate.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replace('__INDEX__', String(index));
        });
        const row = fragment.querySelector('[data-custom-link-row]');
        customLinkList.appendChild(fragment);
        if (row) {
            updateCustomLinkIcon(row);
            row.querySelector('input[name$="[title]"]')?.focus();
        }
        const widgetToggle = root.querySelector('input[name="widgets[]"][value="custom_links"]');
        if (widgetToggle) widgetToggle.checked = true;
        updateCustomLinkState();
    });

    customLinkList?.addEventListener('change', (event) => {
        const iconInput = event.target.closest('[data-custom-link-icon]');
        if (!iconInput) return;
        const row = iconInput.closest('[data-custom-link-row]');
        updateCustomLinkIcon(row);
        const picker = iconInput.closest('[data-custom-link-icon-picker]');
        if (picker) picker.open = false;
    });

    customLinkList?.addEventListener('click', (event) => {
        const button = event.target.closest(
            '[data-custom-link-up], [data-custom-link-down], [data-custom-link-remove]'
        );
        if (!button) return;
        event.preventDefault();
        const row = button.closest('[data-custom-link-row]');
        if (!row) return;

        if (button.hasAttribute('data-custom-link-remove')) {
            row.remove();
        } else if (button.hasAttribute('data-custom-link-up') && row.previousElementSibling?.matches('[data-custom-link-row]')) {
            customLinkList.insertBefore(row, row.previousElementSibling);
        } else if (button.hasAttribute('data-custom-link-down') && row.nextElementSibling) {
            customLinkList.insertBefore(row.nextElementSibling, row);
        }
        updateCustomLinkState();
    });

    updateCustomLinkState();
})();
