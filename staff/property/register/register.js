(() => {
    'use strict';

    const form =
        document.getElementById('itemRegisterForm');

    const categoryInputs =
        form.querySelectorAll(
            'input[name="category"]'
        );

    const categorySections =
        form.querySelectorAll(
            '[data-category-section]'
        );

    const nameLabel =
        document.getElementById('itemNameLabel');

    const nameInput =
        document.getElementById('itemName');

    const statusInput =
        document.getElementById('itemStatus');

    const registerButton =
        document.getElementById('registerButton');

    const message =
        document.getElementById('registerMessage');

    const result =
        document.getElementById('registrationResult');

    const resultId =
        document.getElementById(
            'registeredManagementId'
        );

    const detailLink =
        document.getElementById(
            'registeredDetailLink'
        );

    const qrLink =
        document.getElementById(
            'registeredQrLink'
        );

    const categoryUi = {
        product: {
            nameLabel: '商品名 *',
            placeholder: '例：HC Gaming PC Entry',
            status: 'stock',
        },
        equipment: {
            nameLabel: '備品名 *',
            placeholder: '例：SHURE ワイヤレスマイク',
            status: 'active',
        },
        physical_server: {
            nameLabel: 'サーバー名 *',
            placeholder: '例：pve01',
            status: 'active',
        },
        network_device: {
            nameLabel: '機器名 *',
            placeholder: '例：Core Switch 01',
            status: 'active',
        },
        computer: {
            nameLabel: '端末名 *',
            placeholder: '例：編集用ワークステーション01',
            status: 'active',
        },
        storage_device: {
            nameLabel: 'ストレージ名 *',
            placeholder: '例：TrueNAS Storage 01',
            status: 'active',
        },
        rack: {
            nameLabel: 'ラック名 *',
            placeholder: '例：DCメインラックA01',
            status: 'active',
        },
        other: {
            nameLabel: '物品名 *',
            placeholder: '登録する物品の名称',
            status: 'active',
        },
    };

    function selectedCategory() {
        return form.querySelector(
            'input[name="category"]:checked'
        )?.value || 'product';
    }

    function updateCategorySections() {
        const category = selectedCategory();
        const ui = categoryUi[category];

        categorySections.forEach((section) => {
            const active =
                section.dataset.categorySection
                === category;

            section.hidden = !active;

            section
                .querySelectorAll(
                    'input, select, textarea'
                )
                .forEach((field) => {
                    field.disabled = !active;

                    if (
                        field.hasAttribute(
                            'data-category-required'
                        )
                    ) {
                        field.required = active;
                    }
                });
        });

        nameLabel.textContent = ui.nameLabel;
        nameInput.placeholder = ui.placeholder;
        statusInput.value = ui.status;

        result.hidden = true;
        setMessage('');
    }

    function setMessage(text, type = '') {
        message.textContent = text;
        message.className = type
            ? `is-${type}`
            : '';
    }

    function buildPayload() {
        const formData = new FormData(form);
        const payload = {};

        for (
            const [name, value]
            of formData.entries()
        ) {
            if (name in payload) {
                if (!Array.isArray(payload[name])) {
                    payload[name] = [
                        payload[name],
                    ];
                }

                payload[name].push(value);
            } else {
                payload[name] = value;
            }
        }

        payload.category = selectedCategory();

        payload.poe_supported =
            document.getElementById(
                'poeSupported'
            )?.checked || false;

        payload.loanable =
            form.querySelector(
                'input[name="loanable"]'
            )?.checked || false;

        return payload;
    }

    async function submitRegistration(event) {
        event.preventDefault();

        result.hidden = true;

        if (!form.reportValidity()) {
            setMessage(
                '必須項目を入力してください。',
                'error'
            );

            const invalidField =
                form.querySelector(':invalid');

            invalidField?.focus();

            return;
        }

        registerButton.disabled = true;

        setMessage(
            '管理IDを発行して登録しています。'
        );

        try {
            const response = await fetch(
                '/staff/property/api/assets/create.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type':
                            'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                    body: JSON.stringify(
                        buildPayload()
                    ),
                }
            );

            let data;

            try {
                data = await response.json();
            } catch (error) {
                throw new Error(
                    'サーバーから正しい応答が'
                    + '返されませんでした。'
                );
            }

            if (
                !response.ok
                || !data.success
                || !data.asset
            ) {
                throw new Error(
                    data.message
                    || '登録できませんでした。'
                );
            }

            resultId.textContent =
                data.asset.management_id;

            detailLink.href =
                data.detail_url;

            qrLink.href =
                data.qr_url;

            result.hidden = false;

            setMessage(
                `${data.asset.management_id}`
                + ' を登録しました。',
                'success'
            );

            result.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : '登録できませんでした。',
                'error'
            );
        } finally {
            registerButton.disabled = false;
        }
    }

    categoryInputs.forEach((input) => {
        input.addEventListener(
            'change',
            updateCategorySections
        );
    });

    form.addEventListener(
        'submit',
        submitRegistration
    );

    updateCategorySections();
})();
