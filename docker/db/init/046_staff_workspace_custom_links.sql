BEGIN;

ALTER TABLE staff_workspace_preferences
    ADD COLUMN IF NOT EXISTS custom_links JSONB NOT NULL DEFAULT '[]'::JSONB;

COMMIT;
