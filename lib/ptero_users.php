<?php

require_once __DIR__ . "/pterodactyl.php";

function hc_ptero_user_links_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ptero_user_links (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
            ptero_user_id INTEGER NOT NULL UNIQUE,
            ptero_external_id VARCHAR(120) NOT NULL UNIQUE,
            ptero_uuid VARCHAR(120),
            username VARCHAR(120),
            email VARCHAR(255),
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            last_synced_at TIMESTAMP NULL
        )
    ");

    $pdo->exec("
        ALTER TABLE ptero_user_links
        ADD COLUMN IF NOT EXISTS initial_password TEXT NULL,
        ADD COLUMN IF NOT EXISTS initial_password_created_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS initial_password_viewed_at TIMESTAMP NULL,
        ADD COLUMN IF NOT EXISTS password_setup_completed_at TIMESTAMP NULL
    ");
}

function hc_ptero_external_id_for_user(int $userId): string
{
    return "hc_user_" . (string)$userId;
}

function hc_ptero_safe_username(string $username, int $userId): string
{
    $base = strtolower($username);
    $base = preg_replace('/[^a-z0-9_]/', '_', $base) ?: "user";
    $base = trim($base, "_");

    if ($base === "") {
        $base = "user";
    }

    $prefix = "hc" . (string)$userId . "_";
    $maxBaseLength = 32 - strlen($prefix);

    if ($maxBaseLength < 4) {
        $maxBaseLength = 4;
    }

    $base = substr($base, 0, $maxBaseLength);

    return $prefix . $base;
}

function hc_ptero_random_password(): string
{
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%_-";
    $password = "";

    for ($i = 0; $i < 20; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $password . "Aa1!";
}

function hc_ptero_upsert_user_link(PDO $pdo, int $userId, string $externalId, array $pteroUser, ?string $initialPassword = null): array
{
    $attributes = $pteroUser["attributes"] ?? $pteroUser;

    $pteroUserId = (int)($attributes["id"] ?? 0);
    $pteroUuid = (string)($attributes["uuid"] ?? "");
    $username = (string)($attributes["username"] ?? "");
    $email = (string)($attributes["email"] ?? "");

    if ($pteroUserId <= 0) {
        throw new RuntimeException("PterodactylユーザーIDを取得できませんでした。");
    }

    $stmt = $pdo->prepare("
        INSERT INTO ptero_user_links
        (
            user_id,
            ptero_user_id,
            ptero_external_id,
            ptero_uuid,
            username,
            email,
            status,
            initial_password,
            initial_password_created_at,
            created_at,
            updated_at,
            last_synced_at
        )
        VALUES
        (
            :user_id,
            :ptero_user_id,
            :ptero_external_id,
            :ptero_uuid,
            :username,
            :email,
            'active',
            :initial_password,
            CASE WHEN :initial_password_created IS NULL THEN NULL ELSE NOW() END,
            NOW(),
            NOW(),
            NOW()
        )
        ON CONFLICT (user_id) DO UPDATE SET
            ptero_user_id = EXCLUDED.ptero_user_id,
            ptero_external_id = EXCLUDED.ptero_external_id,
            ptero_uuid = EXCLUDED.ptero_uuid,
            username = EXCLUDED.username,
            email = EXCLUDED.email,
            status = 'active',
            initial_password = COALESCE(EXCLUDED.initial_password, ptero_user_links.initial_password),
            initial_password_created_at = COALESCE(EXCLUDED.initial_password_created_at, ptero_user_links.initial_password_created_at),
            updated_at = NOW(),
            last_synced_at = NOW()
    ");

    $stmt->execute([
        "user_id" => $userId,
        "ptero_user_id" => $pteroUserId,
        "ptero_external_id" => $externalId,
        "ptero_uuid" => $pteroUuid !== "" ? $pteroUuid : null,
        "username" => $username !== "" ? $username : null,
        "email" => $email !== "" ? $email : null,
        "initial_password" => $initialPassword,
        "initial_password_created" => $initialPassword,
    ]);

    return [
        "user_id" => $userId,
        "ptero_user_id" => $pteroUserId,
        "ptero_external_id" => $externalId,
        "ptero_uuid" => $pteroUuid,
        "username" => $username,
        "email" => $email,
        "initial_password_created" => $initialPassword !== null,
    ];
}

function hc_ptero_get_local_user_link(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            user_id,
            ptero_user_id,
            ptero_external_id,
            ptero_uuid,
            username,
            email,
            status,
            initial_password,
            initial_password_created_at,
            initial_password_viewed_at,
            password_setup_completed_at,
            last_synced_at
        FROM ptero_user_links
        WHERE user_id = :user_id
          AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        "user_id" => $userId,
    ]);

    $link = $stmt->fetch();

    return $link ?: null;
}

function hc_ptero_ensure_user_for_hc_user(PDO $pdo, int $userId): array
{
    hc_ptero_user_links_ensure_schema($pdo);

    $existingLink = hc_ptero_get_local_user_link($pdo, $userId);

    if ($existingLink && (int)$existingLink["ptero_user_id"] > 0) {
        return [
            "user_id" => $userId,
            "ptero_user_id" => (int)$existingLink["ptero_user_id"],
            "ptero_external_id" => (string)$existingLink["ptero_external_id"],
            "ptero_uuid" => (string)($existingLink["ptero_uuid"] ?? ""),
            "username" => (string)($existingLink["username"] ?? ""),
            "email" => (string)($existingLink["email"] ?? ""),
            "source" => "local",
        ];
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            email
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "id" => $userId,
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException("HCユーザーが見つかりません。");
    }

    $externalId = hc_ptero_external_id_for_user($userId);

    try {
        $pteroUser = hc_ptero_get_user_by_external_id($externalId);
        $link = hc_ptero_upsert_user_link($pdo, $userId, $externalId, $pteroUser, null);
        $link["source"] = "external_id";
        return $link;
    } catch (Throwable $e) {
        // external_idで見つからない場合は新規作成へ進む
    }

    $safeUsername = hc_ptero_safe_username((string)$user["username"], $userId);
    $email = trim((string)$user["email"]);

    if ($email === "") {
        throw new RuntimeException("Pterodactylユーザー作成にはメールアドレスが必要です。");
    }

    $initialPassword = hc_ptero_random_password();

    $created = hc_ptero_create_user([
        "external_id" => $externalId,
        "email" => $email,
        "username" => $safeUsername,
        "first_name" => (string)$user["username"],
        "last_name" => "HC",
        "password" => $initialPassword,
        "root_admin" => false,
        "language" => "en",
    ]);

    $link = hc_ptero_upsert_user_link($pdo, $userId, $externalId, $created, $initialPassword);
    $link["source"] = "created";
    $link["initial_password_created"] = true;

    return $link;
}
