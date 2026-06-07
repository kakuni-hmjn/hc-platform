CREATE TABLE IF NOT EXISTS site_notifications (
    id SERIAL PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    body TEXT,
    link_url VARCHAR(255),
    target_scope VARCHAR(40) NOT NULL DEFAULT 'all',
    status VARCHAR(40) NOT NULL DEFAULT 'published',
    priority INTEGER NOT NULL DEFAULT 0,
    published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_site_notifications_scope ON site_notifications(target_scope);
CREATE INDEX IF NOT EXISTS idx_site_notifications_status ON site_notifications(status);
CREATE INDEX IF NOT EXISTS idx_site_notifications_published_at ON site_notifications(published_at);

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
('site_notifications', '全体通知管理', '/admin/site-notifications/', 'admin', true, 75)
ON CONFLICT (item_key) DO UPDATE SET
    label = EXCLUDED.label,
    url = EXCLUDED.url,
    required_role = EXCLUDED.required_role,
    is_visible = EXCLUDED.is_visible,
    sort_order = EXCLUDED.sort_order,
    updated_at = NOW();
