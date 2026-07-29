ALTER TABLE user_direct_notifications
ADD COLUMN IF NOT EXISTS dedupe_key VARCHAR(180) NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_user_direct_notifications_dedupe
ON user_direct_notifications(user_id, dedupe_key)
WHERE dedupe_key IS NOT NULL;
