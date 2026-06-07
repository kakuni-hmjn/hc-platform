CREATE TABLE IF NOT EXISTS user_direct_notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT,
    link_url VARCHAR(255),
    status VARCHAR(40) NOT NULL DEFAULT 'published',
    priority INTEGER NOT NULL DEFAULT 0,
    published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_user_direct_notifications_user_id ON user_direct_notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_user_direct_notifications_status ON user_direct_notifications(status);
CREATE INDEX IF NOT EXISTS idx_user_direct_notifications_published_at ON user_direct_notifications(published_at);

CREATE TABLE IF NOT EXISTS header_operation_links (
    id SERIAL PRIMARY KEY,
    item_key VARCHAR(80) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    url VARCHAR(255) NOT NULL,
    required_role VARCHAR(40) NOT NULL DEFAULT 'staff',
    is_visible BOOLEAN NOT NULL DEFAULT true,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

INSERT INTO header_operation_links
(item_key, label, url, required_role, is_visible, sort_order)
VALUES
('user_notifications', '個別通知管理', '/admin/user-notifications/', 'admin', true, 76)
ON CONFLICT (item_key) DO UPDATE SET
    label = EXCLUDED.label,
    url = EXCLUDED.url,
    required_role = EXCLUDED.required_role,
    is_visible = EXCLUDED.is_visible,
    sort_order = EXCLUDED.sort_order,
    updated_at = NOW();
