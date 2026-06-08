ALTER TABLE game_server_orders
ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(160),
ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(160),
ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(160);

CREATE INDEX IF NOT EXISTS idx_game_server_orders_stripe_checkout_session_id
ON game_server_orders(stripe_checkout_session_id);

CREATE INDEX IF NOT EXISTS idx_game_server_orders_stripe_subscription_id
ON game_server_orders(stripe_subscription_id);

CREATE INDEX IF NOT EXISTS idx_game_server_orders_stripe_customer_id
ON game_server_orders(stripe_customer_id);
