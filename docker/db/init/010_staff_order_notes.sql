CREATE TABLE IF NOT EXISTS staff_order_notes (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES game_server_orders(id) ON DELETE CASCADE,
    staff_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_staff_order_notes_order_id ON staff_order_notes(order_id);
CREATE INDEX IF NOT EXISTS idx_staff_order_notes_staff_user_id ON staff_order_notes(staff_user_id);
CREATE INDEX IF NOT EXISTS idx_staff_order_notes_created_at ON staff_order_notes(created_at);
