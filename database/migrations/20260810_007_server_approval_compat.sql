BEGIN;

ALTER TABLE ptero_servers
    ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMP NULL;

ALTER TABLE server_order_events
    ADD COLUMN IF NOT EXISTS metadata_json JSONB NULL;

COMMIT;
