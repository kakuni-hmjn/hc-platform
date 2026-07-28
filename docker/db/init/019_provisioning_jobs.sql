CREATE TABLE IF NOT EXISTS provisioning_jobs (
    id BIGSERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    job_type VARCHAR(50) NOT NULL DEFAULT 'provision_server',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 5,
    last_error TEXT NULL,
    available_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    locked_at TIMESTAMP NULL,
    worker_id VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT provisioning_jobs_unique_order_type
        UNIQUE (order_id, job_type),

    CONSTRAINT provisioning_jobs_status_check
        CHECK (
            status IN (
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled'
            )
        )
);

CREATE INDEX IF NOT EXISTS idx_provisioning_jobs_queue
ON provisioning_jobs(status, available_at, id);

CREATE INDEX IF NOT EXISTS idx_provisioning_jobs_order
ON provisioning_jobs(order_id);
