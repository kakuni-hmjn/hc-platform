(() => {
    'use strict';

    const form = document.getElementById(
        'hpmcSettingsForm'
    );

    const button = document.getElementById(
        'settingsSaveButton'
    );

    const message = document.getElementById(
        'settingsMessage'
    );

    function setMessage(text, type = '') {
        message.textContent = text;
        message.className = type
            ? `is-${type}`
            : '';
    }

    form.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();

            button.disabled = true;

            setMessage(
                '設定を保存しています。'
            );

            const data = Object.fromEntries(
                new FormData(form).entries()
            );

            try {
                const response = await fetch(
                    '/staff/property/api/settings/save.php',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type':
                                'application/json',
                            'X-Requested-With':
                                'XMLHttpRequest',
                        },
                        body: JSON.stringify(data),
                    }
                );

                const result =
                    await response.json();

                if (
                    !response.ok
                    || !result.success
                ) {
                    throw new Error(
                        result.message
                        || '設定を保存できませんでした。'
                    );
                }

                setMessage(
                    '設定を保存しました。',
                    'success'
                );
            } catch (error) {
                setMessage(
                    error instanceof Error
                        ? error.message
                        : '設定を保存できませんでした。',
                    'error'
                );
            } finally {
                button.disabled = false;
            }
        }
    );
})();
