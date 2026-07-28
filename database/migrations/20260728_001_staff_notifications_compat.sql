BEGIN;

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS category VARCHAR(64);

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS level VARCHAR(32);

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS message TEXT;

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS link_url TEXT;

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS source VARCHAR(64);

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS metadata JSONB;

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS external_id VARCHAR(191);

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS dedupe_key VARCHAR(191);

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS published_at TIMESTAMPTZ;

ALTER TABLE staff_notifications
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ;

UPDATE staff_notifications
SET category = CASE
    WHEN NULLIF(TRIM(type), '') IS NULL THEN 'system'
    WHEN LOWER(type) IN (
        'system',
        'order',
        'user',
        'discord',
        'github',
        'development'
    ) THEN LOWER(type)
    WHEN LOWER(type) LIKE '%order%' THEN 'order'
    WHEN LOWER(type) LIKE '%discord%' THEN 'discord'
    WHEN LOWER(type) LIKE '%github%' THEN 'github'
    WHEN LOWER(type) LIKE '%develop%' THEN 'development'
    ELSE 'system'
END
WHERE category IS NULL
   OR TRIM(category) = '';

UPDATE staff_notifications
SET level = CASE
    WHEN LOWER(type) LIKE '%error%' THEN 'error'
    WHEN LOWER(type) LIKE '%danger%' THEN 'error'
    WHEN LOWER(type) LIKE '%fail%' THEN 'error'
    WHEN LOWER(type) LIKE '%warning%' THEN 'warning'
    WHEN LOWER(type) LIKE '%warn%' THEN 'warning'
    WHEN LOWER(type) LIKE '%success%' THEN 'success'
    WHEN LOWER(type) LIKE '%complete%' THEN 'success'
    ELSE 'info'
END
WHERE level IS NULL
   OR TRIM(level) = '';

UPDATE staff_notifications
SET message = body
WHERE message IS NULL;

UPDATE staff_notifications
SET body = message
WHERE body IS NULL;

UPDATE staff_notifications
SET link_url = action_url
WHERE link_url IS NULL;

UPDATE staff_notifications
SET action_url = link_url
WHERE action_url IS NULL;

UPDATE staff_notifications
SET source = category
WHERE source IS NULL
   OR TRIM(source) = '';

UPDATE staff_notifications
SET metadata = '{}'::jsonb
WHERE metadata IS NULL;

UPDATE staff_notifications
SET published_at = created_at
WHERE published_at IS NULL;

UPDATE staff_notifications
SET updated_at = created_at
WHERE updated_at IS NULL;

ALTER TABLE staff_notifications
    ALTER COLUMN category SET DEFAULT 'system';

ALTER TABLE staff_notifications
    ALTER COLUMN category SET NOT NULL;

ALTER TABLE staff_notifications
    ALTER COLUMN level SET DEFAULT 'info';

ALTER TABLE staff_notifications
    ALTER COLUMN level SET NOT NULL;

ALTER TABLE staff_notifications
    ALTER COLUMN metadata SET DEFAULT '{}'::jsonb;

ALTER TABLE staff_notifications
    ALTER COLUMN metadata SET NOT NULL;

ALTER TABLE staff_notifications
    ALTER COLUMN updated_at SET DEFAULT NOW();

ALTER TABLE staff_notifications
    ALTER COLUMN updated_at SET NOT NULL;

CREATE OR REPLACE FUNCTION sync_staff_notification_columns()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.category := COALESCE(
        NULLIF(TRIM(NEW.category), ''),
        NULLIF(TRIM(NEW.type), ''),
        'system'
    );

    NEW.type := COALESCE(
        NULLIF(TRIM(NEW.type), ''),
        NEW.category
    );

    NEW.level := COALESCE(
        NULLIF(TRIM(NEW.level), ''),
        'info'
    );

    NEW.message := COALESCE(
        NEW.message,
        NEW.body
    );

    NEW.body := COALESCE(
        NEW.body,
        NEW.message
    );

    NEW.link_url := COALESCE(
        NEW.link_url,
        NEW.action_url
    );

    NEW.action_url := COALESCE(
        NEW.action_url,
        NEW.link_url
    );

    NEW.source := COALESCE(
        NULLIF(TRIM(NEW.source), ''),
        NEW.category
    );

    NEW.metadata := COALESCE(
        NEW.metadata,
        '{}'::jsonb
    );

    NEW.published_at := COALESCE(
        NEW.published_at,
        NEW.created_at,
        NOW()
    );

    NEW.updated_at := NOW();

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS
    trg_sync_staff_notification_columns
ON staff_notifications;

CREATE TRIGGER trg_sync_staff_notification_columns
BEFORE INSERT OR UPDATE
ON staff_notifications
FOR EACH ROW
EXECUTE FUNCTION sync_staff_notification_columns();

CREATE INDEX IF NOT EXISTS
    idx_staff_notifications_user_created
ON staff_notifications (
    user_id,
    created_at DESC
);

CREATE INDEX IF NOT EXISTS
    idx_staff_notifications_user_read
ON staff_notifications (
    user_id,
    is_read,
    created_at DESC
);

CREATE INDEX IF NOT EXISTS
    idx_staff_notifications_user_category
ON staff_notifications (
    user_id,
    category,
    created_at DESC
);

CREATE INDEX IF NOT EXISTS
    idx_staff_notifications_category
ON staff_notifications (
    category,
    created_at DESC
);

CREATE UNIQUE INDEX IF NOT EXISTS
    uq_staff_notifications_dedupe_key
ON staff_notifications (dedupe_key)
WHERE dedupe_key IS NOT NULL;

COMMENT ON COLUMN staff_notifications.type IS
    '旧通知種別との後方互換用';

COMMENT ON COLUMN staff_notifications.body IS
    '旧通知本文との後方互換用';

COMMENT ON COLUMN staff_notifications.action_url IS
    '旧通知リンクとの後方互換用';

COMMENT ON COLUMN staff_notifications.category IS
    'system, order, user, discord, github, development';

COMMENT ON COLUMN staff_notifications.level IS
    'info, success, warning, error';

COMMIT;
