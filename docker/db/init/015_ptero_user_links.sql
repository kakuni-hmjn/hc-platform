CREATE TABLE IF NOT EXISTS ptero_user_links (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    ptero_user_id INTEGER NOT NULL UNIQUE,
    ptero_external_id VARCHAR(120) NOT NULL UNIQUE,
    ptero_uuid VARCHAR(120),
    username VARCHAR(120),
    email VARCHAR(255),
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    last_synced_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_ptero_user_links_user_id ON ptero_user_links(user_id);
CREATE INDEX IF NOT EXISTS idx_ptero_user_links_ptero_user_id ON ptero_user_links(ptero_user_id);
CREATE INDEX IF NOT EXISTS idx_ptero_user_links_external_id ON ptero_user_links(ptero_external_id);
