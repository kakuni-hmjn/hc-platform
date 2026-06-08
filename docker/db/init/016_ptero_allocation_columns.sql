ALTER TABLE ptero_servers
ADD COLUMN IF NOT EXISTS ptero_allocation_id INTEGER NULL;

CREATE INDEX IF NOT EXISTS idx_ptero_servers_ptero_allocation_id
ON ptero_servers(ptero_allocation_id);

ALTER TABLE game_server_orders
ADD COLUMN IF NOT EXISTS provision_error TEXT NULL,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL;
