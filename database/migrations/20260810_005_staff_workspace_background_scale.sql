BEGIN;

ALTER TABLE staff_workspace_preferences
    ADD COLUMN IF NOT EXISTS background_scale SMALLINT NOT NULL DEFAULT 100;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'staff_workspace_background_scale_check'
          AND conrelid = 'staff_workspace_preferences'::regclass
    ) THEN
        ALTER TABLE staff_workspace_preferences
            ADD CONSTRAINT staff_workspace_background_scale_check
            CHECK (background_scale BETWEEN 100 AND 200);
    END IF;
END;
$$;

COMMIT;
