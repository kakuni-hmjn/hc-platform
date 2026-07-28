BEGIN;

CREATE TABLE IF NOT EXISTS staff_notifications (
    id BIGSERIAL PRIMARY KEY,

    /* NULLの場合はスタッフ全体向け */
    recipient_user_id BIGINT NULL,

    category VARCHAR(32) NOT NULL DEFAULT 'system',
    level VARCHAR(32) NOT NULL DEFAULT 'info',

    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL DEFAULT '',

    url VARCHAR(500) NULL,
    source VARCHAR(64) NOT NULL DEFAULT 'hc-platform',
    icon VARCHAR(64) NULL,

    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,

    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    read_at TIMESTAMPTZ NULL,

    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT staff_notifications_category_check
        CHECK (
            category IN (
                'system',
                'order',
                'user',
                'discord',
                'github',
                'development'
            )
        ),

    CONSTRAINT staff_notifications_level_check
        CHECK (
            level IN (
                'critical',
                'warning',
                'success',
                'info',
                'development'
            )
        ),

    CONSTRAINT staff_notifications_read_state_check
        CHECK (
            (is_read = FALSE AND read_at IS NULL)
            OR
            (is_read = TRUE)
        )
);

CREATE INDEX IF NOT EXISTS idx_staff_notifications_recipient
    ON staff_notifications (recipient_user_id);

CREATE INDEX IF NOT EXISTS idx_staff_notifications_unread
    ON staff_notifications (recipient_user_id, created_at DESC)
    WHERE is_read = FALSE;

CREATE INDEX IF NOT EXISTS idx_staff_notifications_category
    ON staff_notifications (category, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_staff_notifications_created_at
    ON staff_notifications (created_at DESC);

CREATE INDEX IF NOT EXISTS idx_staff_notifications_metadata
    ON staff_notifications USING GIN (metadata);

CREATE OR REPLACE FUNCTION update_staff_notifications_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'trigger_staff_notifications_updated_at'
    ) THEN
        CREATE TRIGGER trigger_staff_notifications_updated_at
            BEFORE UPDATE ON staff_notifications
            FOR EACH ROW
            EXECUTE FUNCTION update_staff_notifications_updated_at();
    END IF;
END;
$$;

COMMENT ON TABLE staff_notifications IS
    'HC Staff Workspace notification center';

COMMENT ON COLUMN staff_notifications.recipient_user_id IS
    '通知対象ユーザー。NULLの場合はスタッフ全体向け';

COMMENT ON COLUMN staff_notifications.category IS
    'system, order, user, discord, github, development';

COMMENT ON COLUMN staff_notifications.level IS
    'critical, warning, success, info, development';

COMMIT;
