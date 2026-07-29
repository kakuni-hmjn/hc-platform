ALTER TABLE user_direct_notifications
ADD COLUMN IF NOT EXISTS status VARCHAR(30) NULL,
ADD COLUMN IF NOT EXISTS published_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS created_by INTEGER NULL;

UPDATE user_direct_notifications
SET status = 'published'
WHERE status IS NULL OR status = '';

UPDATE user_direct_notifications
SET published_at = COALESCE(created_at, NOW())
WHERE published_at IS NULL;

ALTER TABLE user_direct_notifications
ALTER COLUMN status SET DEFAULT 'published',
ALTER COLUMN status SET NOT NULL,
ALTER COLUMN published_at SET DEFAULT CURRENT_TIMESTAMP,
ALTER COLUMN published_at SET NOT NULL;

CREATE INDEX IF NOT EXISTS idx_user_direct_notifications_status
ON user_direct_notifications(status);

CREATE INDEX IF NOT EXISTS idx_user_direct_notifications_published_at
ON user_direct_notifications(published_at);
