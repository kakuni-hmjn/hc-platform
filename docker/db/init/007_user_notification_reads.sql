CREATE TABLE IF NOT EXISTS user_notification_reads (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    notification_type VARCHAR(40) NOT NULL,
    notification_id INTEGER NOT NULL,
    read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, notification_type, notification_id)
);

CREATE INDEX IF NOT EXISTS idx_user_notification_reads_user_id ON user_notification_reads(user_id);
CREATE INDEX IF NOT EXISTS idx_user_notification_reads_type_id ON user_notification_reads(notification_type, notification_id);
