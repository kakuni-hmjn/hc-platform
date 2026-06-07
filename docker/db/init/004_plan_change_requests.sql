CREATE TABLE IF NOT EXISTS server_order_plan_change_requests (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES game_server_orders(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    current_plan_id INTEGER NOT NULL REFERENCES game_server_plans(id) ON DELETE RESTRICT,
    requested_plan_id INTEGER NOT NULL REFERENCES game_server_plans(id) ON DELETE RESTRICT,
    change_type VARCHAR(40) NOT NULL DEFAULT 'next_renewal',
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    user_note TEXT,
    admin_note TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    processed_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_plan_change_requests_order_id ON server_order_plan_change_requests(order_id);
CREATE INDEX IF NOT EXISTS idx_plan_change_requests_user_id ON server_order_plan_change_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_plan_change_requests_status ON server_order_plan_change_requests(status);
CREATE INDEX IF NOT EXISTS idx_plan_change_requests_created_at ON server_order_plan_change_requests(created_at);
