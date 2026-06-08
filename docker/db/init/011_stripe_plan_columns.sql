ALTER TABLE game_server_plans
ADD COLUMN IF NOT EXISTS stripe_product_id VARCHAR(120),
ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(120),
ADD COLUMN IF NOT EXISTS stripe_sync_status VARCHAR(40) NOT NULL DEFAULT 'not_synced',
ADD COLUMN IF NOT EXISTS stripe_synced_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS stripe_sync_error TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_game_server_plans_stripe_product_id
ON game_server_plans(stripe_product_id);

CREATE INDEX IF NOT EXISTS idx_game_server_plans_stripe_price_id
ON game_server_plans(stripe_price_id);
