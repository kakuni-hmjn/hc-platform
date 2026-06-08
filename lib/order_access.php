<?php

function hc_order_bool_value(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value === 1;
    }

    $value = strtolower(trim((string)$value));

    return in_array($value, ["1", "true", "t", "yes", "on"], true);
}

function hc_order_settings_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_order_settings (
            service_key VARCHAR(80) PRIMARY KEY,
            service_name VARCHAR(120) NOT NULL,
            is_enabled BOOLEAN NOT NULL DEFAULT true,
            disabled_message TEXT,
            admin_memo TEXT,
            updated_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )
    ");

    $pdo->exec("
        INSERT INTO service_order_settings
        (
            service_key,
            service_name,
            is_enabled,
            disabled_message,
            admin_memo,
            created_at,
            updated_at
        )
        VALUES
        (
            'game_server',
            'ゲームサーバー',
            true,
            '現在、ゲームサーバーの新規申込受付を一時停止しています。メンテナンス完了後に再度お試しください。',
            '',
            NOW(),
            NOW()
        )
        ON CONFLICT (service_key) DO NOTHING
    ");
}

function hc_order_get_setting(PDO $pdo, string $serviceKey): array
{
    hc_order_settings_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT
            service_key,
            service_name,
            is_enabled,
            disabled_message,
            admin_memo,
            updated_by,
            created_at,
            updated_at
        FROM service_order_settings
        WHERE service_key = :service_key
        LIMIT 1
    ");

    $stmt->execute([
        "service_key" => $serviceKey,
    ]);

    $setting = $stmt->fetch();

    if ($setting) {
        return $setting;
    }

    return [
        "service_key" => $serviceKey,
        "service_name" => $serviceKey,
        "is_enabled" => true,
        "disabled_message" => "",
        "admin_memo" => "",
        "updated_by" => null,
        "created_at" => null,
        "updated_at" => null,
    ];
}

function hc_order_user_can_bypass(?array $user): bool
{
    if (!$user) {
        return false;
    }

    $role = (string)($user["role"] ?? "");

    return in_array($role, ["admin", "staff", "operator"], true);
}

function hc_order_can_create(PDO $pdo, string $serviceKey, ?array $user): bool
{
    $setting = hc_order_get_setting($pdo, $serviceKey);

    if (hc_order_bool_value($setting["is_enabled"] ?? true)) {
        return true;
    }

    return hc_order_user_can_bypass($user);
}

function hc_order_disabled_message(PDO $pdo, string $serviceKey): string
{
    $setting = hc_order_get_setting($pdo, $serviceKey);

    $message = trim((string)($setting["disabled_message"] ?? ""));

    if ($message !== "") {
        return $message;
    }

    return "現在、このサービスの新規申込受付を一時停止しています。";
}

function hc_order_update_setting(
    PDO $pdo,
    string $serviceKey,
    bool $isEnabled,
    string $disabledMessage,
    string $adminMemo,
    ?int $updatedBy
): void {
    hc_order_settings_ensure_schema($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO service_order_settings
        (
            service_key,
            service_name,
            is_enabled,
            disabled_message,
            admin_memo,
            updated_by,
            created_at,
            updated_at
        )
        VALUES
        (
            :service_key,
            :service_name,
            CAST(:is_enabled AS boolean),
            :disabled_message,
            :admin_memo,
            :updated_by,
            NOW(),
            NOW()
        )
        ON CONFLICT (service_key) DO UPDATE SET
            is_enabled = EXCLUDED.is_enabled,
            disabled_message = EXCLUDED.disabled_message,
            admin_memo = EXCLUDED.admin_memo,
            updated_by = EXCLUDED.updated_by,
            updated_at = NOW()
    ");

    $serviceName = match ($serviceKey) {
        "game_server" => "ゲームサーバー",
        default => $serviceKey,
    };

    $stmt->execute([
        "service_key" => $serviceKey,
        "service_name" => $serviceName,
        "is_enabled" => $isEnabled ? "true" : "false",
        "disabled_message" => $disabledMessage,
        "admin_memo" => $adminMemo,
        "updated_by" => $updatedBy,
    ]);
}
