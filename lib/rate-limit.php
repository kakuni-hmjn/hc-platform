<?php

require_once __DIR__ . "/db.php";

function count_recent_registration_attempts(string $ip, int $minutes): int
{
    $stmt = db()->prepare("
        SELECT COUNT(*) AS count
        FROM registration_logs
        WHERE ip_address = :ip
          AND created_at >= NOW() - (:minutes || ' minutes')::interval
    ");

    $stmt->execute([
        ":ip" => $ip,
        ":minutes" => $minutes,
    ]);

    $row = $stmt->fetch();

    return (int) ($row["count"] ?? 0);
}

function log_registration_attempt(?string $email, ?string $username, string $ip, string $result, string $message): void
{
    $stmt = db()->prepare("
        INSERT INTO registration_logs
            (email, username, ip_address, result, message)
        VALUES
            (:email, :username, :ip_address, :result, :message)
    ");

    $stmt->execute([
        ":email" => $email,
        ":username" => $username,
        ":ip_address" => $ip,
        ":result" => $result,
        ":message" => $message,
    ]);
}