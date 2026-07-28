<?php

require_once __DIR__ . "/db.php";

function hc_provisioning_jobs_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
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
            UNIQUE (order_id, job_type)
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_provisioning_jobs_queue
        ON provisioning_jobs(status, available_at, id)
    ");
}

function hc_enqueue_provisioning_job(PDO $pdo, int $orderId): array
{
    if ($orderId <= 0) {
        return [
            "ok" => false,
            "error" => "注文IDが不正です。",
        ];
    }

    hc_provisioning_jobs_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO provisioning_jobs (
            order_id,
            job_type,
            status,
            attempts,
            available_at,
            created_at,
            updated_at
        )
        VALUES (
            :order_id,
            'provision_server',
            'pending',
            0,
            NOW(),
            NOW(),
            NOW()
        )
        ON CONFLICT (order_id, job_type)
        DO UPDATE SET
            status = CASE
                WHEN provisioning_jobs.status = 'completed'
                    THEN provisioning_jobs.status
                ELSE 'pending'
            END,
            last_error = CASE
                WHEN provisioning_jobs.status = 'completed'
                    THEN provisioning_jobs.last_error
                ELSE NULL
            END,
            available_at = CASE
                WHEN provisioning_jobs.status = 'completed'
                    THEN provisioning_jobs.available_at
                ELSE NOW()
            END,
            updated_at = NOW()
        RETURNING *
    ");

    $stmt->execute([
        "order_id" => $orderId,
    ]);

    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        "ok" => true,
        "job" => $job,
        "error" => null,
    ];
}

function hc_recover_stale_provisioning_jobs(
    PDO $pdo,
    int $timeoutMinutes = 10
): int {
    hc_provisioning_jobs_ensure_schema($pdo);

    $timeoutMinutes = max(1, min($timeoutMinutes, 1440));

    $stmt = $pdo->prepare("
        UPDATE provisioning_jobs
        SET
            status = CASE
                WHEN attempts >= max_attempts
                    THEN 'failed'
                ELSE 'pending'
            END,
            last_error = CASE
                WHEN attempts >= max_attempts
                    THEN COALESCE(
                        last_error,
                        'Worker停止後の復旧時に最大試行回数へ到達しました。'
                    )
                ELSE COALESCE(
                    last_error,
                    'Worker停止を検出したため自動的に再試行します。'
                )
            END,
            available_at = CASE
                WHEN attempts >= max_attempts
                    THEN available_at
                ELSE NOW()
            END,
            locked_at = NULL,
            worker_id = NULL,
            updated_at = NOW()
        WHERE status = 'processing'
          AND (
              locked_at IS NULL
              OR locked_at < NOW() - (:timeout_minutes * INTERVAL '1 minute')
          )
    ");

    $stmt->execute([
        "timeout_minutes" => $timeoutMinutes,
    ]);

    return $stmt->rowCount();
}

function hc_claim_provisioning_job(
    PDO $pdo,
    string $workerId
): ?array {
    hc_provisioning_jobs_ensure_schema($pdo);

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->query("
            SELECT *
            FROM provisioning_jobs
            WHERE status = 'pending'
              AND available_at <= NOW()
              AND attempts < max_attempts
            ORDER BY available_at ASC, id ASC
            LIMIT 1
            FOR UPDATE SKIP LOCKED
        ");

        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $pdo->commit();
            return null;
        }

        $update = $pdo->prepare("
            UPDATE provisioning_jobs
            SET
                status = 'processing',
                attempts = attempts + 1,
                started_at = NOW(),
                locked_at = NOW(),
                worker_id = :worker_id,
                updated_at = NOW()
            WHERE id = :id
            RETURNING *
        ");

        $update->execute([
            "id" => (int)$job["id"],
            "worker_id" => $workerId,
        ]);

        $claimed = $update->fetch(PDO::FETCH_ASSOC);

        $pdo->commit();

        return $claimed ?: null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function hc_complete_provisioning_job(PDO $pdo, int $jobId): void
{
    $stmt = $pdo->prepare("
        UPDATE provisioning_jobs
        SET
            status = 'completed',
            completed_at = NOW(),
            last_error = NULL,
            locked_at = NULL,
            worker_id = NULL,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        "id" => $jobId,
    ]);
}

function hc_fail_provisioning_job(
    PDO $pdo,
    int $jobId,
    string $error
): void {
    $stmt = $pdo->prepare("
        UPDATE provisioning_jobs
        SET
            status = CASE
                WHEN attempts >= max_attempts THEN 'failed'
                ELSE 'pending'
            END,
            last_error = :last_error,
            available_at = CASE
                WHEN attempts >= max_attempts
                    THEN available_at
                ELSE NOW() + INTERVAL '2 minutes'
            END,
            locked_at = NULL,
            worker_id = NULL,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        "id" => $jobId,
        "last_error" => $error,
    ]);
}
