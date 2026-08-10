BEGIN;

ALTER TABLE contact_messages
    DROP CONSTRAINT IF EXISTS contact_messages_author_type_check;

ALTER TABLE contact_messages
    ADD CONSTRAINT contact_messages_author_type_check
        CHECK (author_type IN ('customer', 'staff', 'system'));

ALTER TABLE contact_messages
    DROP CONSTRAINT IF EXISTS contact_messages_delivery_status_check;

ALTER TABLE contact_messages
    ADD CONSTRAINT contact_messages_delivery_status_check
        CHECK (
            delivery_status IN (
                'pending',
                'sent',
                'logged',
                'failed',
                'saved',
                'received'
            )
        );

COMMENT ON TABLE contact_messages IS
    'Customer and staff messages plus internal notes for support chats';

COMMENT ON COLUMN contact_messages.author_type IS
    'customer: HC Account user, staff: support staff, system: automated event';

COMMIT;
