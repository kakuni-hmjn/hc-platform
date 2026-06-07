CREATE TABLE IF NOT EXISTS server_order_events (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES game_server_orders(id) ON DELETE CASCADE,
    actor_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    event_type VARCHAR(80) NOT NULL,
    title VARCHAR(160) NOT NULL,
    message TEXT,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    old_payment_status VARCHAR(50),
    new_payment_status VARCHAR(50),
    ip_address INET,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_server_order_events_order_id ON server_order_events(order_id);
CREATE INDEX IF NOT EXISTS idx_server_order_events_actor_user_id ON server_order_events(actor_user_id);
CREATE INDEX IF NOT EXISTS idx_server_order_events_event_type ON server_order_events(event_type);
CREATE INDEX IF NOT EXISTS idx_server_order_events_created_at ON server_order_events(created_at);
