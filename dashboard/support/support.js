(() => {
    const root = document.querySelector('[data-contact-chat-root]');

    if (!root) {
        return;
    }

    const messages = root.querySelector('[data-contact-chat-messages]');

    if (messages) {
        requestAnimationFrame(() => {
            messages.scrollTop = messages.scrollHeight;
        });
    }

    const composer = root.querySelector('[data-contact-chat-composer]');
    const body = composer?.querySelector('[data-contact-chat-body]');
    const count = composer?.querySelector('[data-contact-chat-count]');
    const submit = composer?.querySelector('button[type="submit"]');

    const updateCount = () => {
        if (body && count) {
            count.textContent = `${body.value.length} / 5000`;
        }
    };

    body?.addEventListener('input', updateCount);
    body?.addEventListener('keydown', (event) => {
        if (
            event.key !== 'Enter'
            || (!event.metaKey && !event.ctrlKey)
        ) {
            return;
        }

        event.preventDefault();

        if (body.value.trim() !== '') {
            composer?.requestSubmit();
        }
    });

    composer?.addEventListener('submit', (event) => {
        if (!body || body.value.trim() === '') {
            event.preventDefault();
            body?.focus();
            return;
        }

        submit?.setAttribute('disabled', 'disabled');
    });

    updateCount();
})();
