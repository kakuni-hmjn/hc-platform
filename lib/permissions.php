<?php

function role_levels(): array
{
    return [
        "user" => 10,
        "staff" => 20,
        "developer" => 30,
        "admin" => 40,
        "owner" => 50,
    ];
}

function role_label(string $role): string
{
    $labels = [
        "user" => "一般ユーザー",
        "staff" => "スタッフ",
        "developer" => "デベロッパー",
        "admin" => "管理者",
        "owner" => "オーナー",
    ];

    return $labels[$role] ?? "不明";
}

function role_badge_class(string $role): string
{
    $classes = [
        "user" => "role-user",
        "staff" => "role-staff",
        "developer" => "role-developer",
        "admin" => "role-admin",
        "owner" => "role-owner",
    ];

    return $classes[$role] ?? "role-user";
}

function has_role(array $user, string $requiredRole): bool
{
    $levels = role_levels();

    $current = $user["role"] ?? "user";

    if (!isset($levels[$current], $levels[$requiredRole])) {
        return false;
    }

    return $levels[$current] >= $levels[$requiredRole];
}

function require_role(string $requiredRole): array
{
    $user = require_login();

    if (!has_role($user, $requiredRole)) {
        http_response_code(403);
        require __DIR__ . "/../parts/error-403.php";
        exit;
    }

    return $user;
}

function is_admin_user(array $user): bool
{
    return has_role($user, "admin");
}

function is_staff_user(array $user): bool
{
    return has_role($user, "staff");
}

function is_developer_user(array $user): bool
{
    return has_role($user, "developer");
}
