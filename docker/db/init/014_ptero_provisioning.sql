CREATE TABLE IF NOT EXISTS ptero_servers (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES game_server_orders(id) ON DELETE CASCADE,
    node_id INTEGER NULL,
    ptero_user_id INTEGER NULL,
    ptero_server_id INTEGER NULL,
    ptero_identifier VARCHAR(80),
    ptero_uuid VARCHAR(120),
    name VARCHAR(160),
    status VARCHAR(40) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_ptero_servers_order_id ON ptero_servers(order_id);
CREATE INDEX IF NOT EXISTS idx_ptero_servers_ptero_server_id ON ptero_servers(ptero_server_id);
CREATE INDEX IF NOT EXISTS idx_ptero_servers_ptero_identifier ON ptero_servers(ptero_identifier);

ALTER TABLE game_server_orders
ADD COLUMN IF NOT EXISTS provisioning_started_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS provisioned_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS failed_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS provision_error TEXT NULL,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL;
