BEGIN;

ALTER TABLE contact_messages
    ADD COLUMN IF NOT EXISTS delivery_channel VARCHAR(20);

UPDATE contact_messages message
SET delivery_channel = CASE
    WHEN message.visibility = 'internal' THEN 'internal'
    WHEN message.author_type = 'customer' THEN 'chat'
    WHEN EXISTS (
        SELECT 1
        FROM contacts contact
        WHERE contact.id = message.contact_id
          AND contact.user_id IS NOT NULL
    ) AND message.delivery_status = 'sent' THEN 'chat'
    WHEN message.delivery_status IN ('logged', 'sent', 'failed') THEN 'email'
    ELSE 'imported'
END
WHERE message.delivery_channel IS NULL
   OR message.delivery_channel = '';

ALTER TABLE contact_messages
    ALTER COLUMN delivery_channel SET DEFAULT 'chat',
    ALTER COLUMN delivery_channel SET NOT NULL;

ALTER TABLE contact_messages
    DROP CONSTRAINT IF EXISTS contact_messages_delivery_channel_check;

ALTER TABLE contact_messages
    ADD CONSTRAINT contact_messages_delivery_channel_check
        CHECK (
            delivery_channel IN (
                'chat',
                'email',
                'internal',
                'imported'
            )
        );

CREATE INDEX IF NOT EXISTS idx_contact_messages_channel
    ON contact_messages(contact_id, delivery_channel, created_at, id);

COMMENT ON COLUMN contact_messages.delivery_channel IS
    'chat: HC Account chat, email: outbound email, internal: staff note, imported: legacy message';

COMMIT;
