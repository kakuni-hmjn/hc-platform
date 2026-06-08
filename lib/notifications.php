<?php

function hc_notifications_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_direct_notifications (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title VARCHAR(180) NOT NULL,
            body TEXT,
            link_url VARCHAR(255),
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )
    ");

    $pdo->exec("
        ALTER TABLE user_direct_notifications
        ADD COLUMN IF NOT EXISTS user_id INTEGER,
        ADD COLUMN IF NOT EXISTS title VARCHAR(180),
        ADD COLUMN IF NOT EXISTS body TEXT,
        ADD COLUMN IF NOT EXISTS link_url VARCHAR(255),
        ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_user_direct_notifications_user_id
        ON user_direct_notifications(user_id)
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_user_direct_notifications_created_at
        ON user_direct_notifications(created_at)
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_notification_reads (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            notification_type VARCHAR(60) NOT NULL,
            notification_id INTEGER NOT NULL,
            read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, notification_type, notification_id)
        )
    ");
}

function hc_notify_user(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    ?string $targetUrl = null,
    string $type = "info"
): void {
    if ($userId <= 0) {
        return;
    }

    hc_notifications_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO user_direct_notifications
        (
            user_id,
            title,
            body,
            link_url,
            created_at,
            updated_at
        )
        VALUES
        (
            :user_id,
            :title,
            :body,
            :link_url,
            NOW(),
            NOW()
        )
    ");

    $stmt->execute([
        "user_id" => $userId,
        "title" => $title,
        "body" => $message,
        "link_url" => $targetUrl,
    ]);
}
