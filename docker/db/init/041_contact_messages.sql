BEGIN;

CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGSERIAL PRIMARY KEY,
    contact_id INTEGER NOT NULL
        REFERENCES contacts(id) ON DELETE CASCADE,
    author_account_id INTEGER NULL
        REFERENCES users(id) ON DELETE SET NULL,
    author_type VARCHAR(20) NOT NULL DEFAULT 'staff',
    visibility VARCHAR(20) NOT NULL DEFAULT 'public',
    delivery_channel VARCHAR(20) NOT NULL DEFAULT 'chat',
    body TEXT NOT NULL,
    delivery_status VARCHAR(20) NOT NULL DEFAULT 'saved',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT contact_messages_author_type_check
        CHECK (author_type IN ('customer', 'staff', 'system')),
    CONSTRAINT contact_messages_visibility_check
        CHECK (visibility IN ('public', 'internal')),
    CONSTRAINT contact_messages_delivery_channel_check
        CHECK (
            delivery_channel IN (
                'chat',
                'email',
                'internal',
                'imported'
            )
        ),
    CONSTRAINT contact_messages_delivery_status_check
        CHECK (
            delivery_status IN (
                'pending',
                'sent',
                'logged',
                'failed',
                'saved',
                'received'
            )
        )
);

CREATE INDEX IF NOT EXISTS idx_contact_messages_contact_created
    ON contact_messages(contact_id, created_at, id);

CREATE INDEX IF NOT EXISTS idx_contact_messages_visibility
    ON contact_messages(visibility);

CREATE INDEX IF NOT EXISTS idx_contact_messages_channel
    ON contact_messages(contact_id, delivery_channel, created_at, id);

COMMENT ON TABLE contact_messages IS
    'Customer and staff messages plus internal notes for support chats';

COMMENT ON COLUMN contact_messages.visibility IS
    'public: customer reply, internal: staff-only note';

COMMENT ON COLUMN contact_messages.delivery_channel IS
    'chat: HC Account chat, email: outbound email, internal: staff note, imported: legacy message';

COMMIT;
