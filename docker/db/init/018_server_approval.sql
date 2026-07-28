ALTER TABLE game_server_orders
ADD COLUMN IF NOT EXISTS approval_requested_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS approval_started_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS approved_by INTEGER NULL,
ADD COLUMN IF NOT EXISTS approved_via VARCHAR(30) NULL,
ADD COLUMN IF NOT EXISTS approval_error TEXT NULL,
ADD COLUMN IF NOT EXISTS approval_attempts INTEGER NOT NULL DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_game_server_orders_pending_approval
ON game_server_orders(status, payment_status);

ALTER TABLE game_server_orders
DROP CONSTRAINT IF EXISTS game_server_orders_status_check;

ALTER TABLE game_server_orders
ADD CONSTRAINT game_server_orders_status_check
CHECK (
    status IN (
        'pending_payment',
        'paid',
        'creating',
        'provisioning',
        'pending_approval',
        'activating',
        'active',
        'provision_failed',
        'approval_failed',
        'rejected',
        'suspended',
        'cancelled',
        'expired'
    )
);

DO $$
DECLARE
    constraint_record RECORD;
BEGIN
    FOR constraint_record IN
        SELECT conname
        FROM pg_constraint
        WHERE conrelid = 'ptero_servers'::regclass
          AND contype = 'c'
          AND pg_get_constraintdef(oid) ILIKE '%status%'
    LOOP
        EXECUTE format(
            'ALTER TABLE ptero_servers DROP CONSTRAINT %I',
            constraint_record.conname
        );
    END LOOP;
END
$$;

ALTER TABLE ptero_servers
ADD CONSTRAINT ptero_servers_status_check
CHECK (
    status IN (
        'creating',
        'pending_approval',
        'active',
        'suspend_failed',
        'suspended',
        'deleted',
        'failed'
    )
);
