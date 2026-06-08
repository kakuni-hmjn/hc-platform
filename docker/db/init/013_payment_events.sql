CREATE TABLE IF NOT EXISTS payment_events (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NULL REFERENCES game_server_orders(id) ON DELETE SET NULL,
    user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    event_type VARCHAR(120) NOT NULL,
    payment_status VARCHAR(60),
    amount INTEGER,
    currency VARCHAR(12) NOT NULL DEFAULT 'jpy',
    provider VARCHAR(40) NOT NULL DEFAULT 'stripe',
    provider_event_id VARCHAR(180) UNIQUE,
    provider_object_id VARCHAR(180),
    message TEXT,
    raw_payload JSONB,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_payment_events_order_id ON payment_events(order_id);
CREATE INDEX IF NOT EXISTS idx_payment_events_user_id ON payment_events(user_id);
CREATE INDEX IF NOT EXISTS idx_payment_events_event_type ON payment_events(event_type);
CREATE INDEX IF NOT EXISTS idx_payment_events_provider_event_id ON payment_events(provider_event_id);
CREATE INDEX IF NOT EXISTS idx_payment_events_created_at ON payment_events(created_at);

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
