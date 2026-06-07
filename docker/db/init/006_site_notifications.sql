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
