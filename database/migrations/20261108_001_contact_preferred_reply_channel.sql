-- HC Phase 3h: additive only. Legacy contacts remain NULL (no preference assumed).
-- Apply ONLY after isolated Postgres E2E; production must have a verified support inbound mailbox.
BEGIN;
ALTER TABLE public.contacts
  ADD COLUMN IF NOT EXISTS preferred_reply_channel VARCHAR(10);
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint
    WHERE conrelid = 'public.contacts'::regclass
      AND conname = 'contacts_preferred_reply_channel_check'
  ) THEN
    ALTER TABLE public.contacts
      ADD CONSTRAINT contacts_preferred_reply_channel_check
      CHECK (preferred_reply_channel IN ('chat', 'email'));
  END IF;
END;
$$;
COMMENT ON COLUMN public.contacts.preferred_reply_channel IS
 'Requested channel: chat/email; NULL for legacy tickets. Guest requests must use email (app validation).';
COMMIT;
