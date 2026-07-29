<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * セッション形式が異なっても表示名を取得できるようにする。
 */
function staff_current_user(): array
{
    $user = $_SESSION['user']
        ?? $_SESSION['current_user']
        ?? [];

    if (!is_array($user)) {
        $user = [];
    }

    $name = (string) (
        $user['username']
        ?? $user['name']
        ?? $_SESSION['username']
        ?? 'sou'
    );

    $role = strtolower(
        (string) (
            $user['role']
            ?? $user['role_name']
            ?? $_SESSION['role']
            ?? 'owner'
        )
    );

    return [
        'name' => $name !== '' ? $name : 'Staff',
        'role' => $role !== '' ? $role : 'staff',
    ];
}

function staff_role_label(string $role): string
{
    return match ($role) {
        'owner' => 'オーナー',
        'admin', 'administrator' => '管理者',
        'staff' => 'スタッフ',
        'support' => 'サポート',
        default => 'スタッフ',
    };
}

function staff_role_class(string $role): string
{
    return match ($role) {
        'owner' => 'owner',
        'admin', 'administrator' => 'admin',
        default => 'staff',
    };
}

function staff_can_open_admin(string $role): bool
{
    return in_array(
        $role,
        ['owner', 'admin', 'administrator'],
        true
    );
}

function staff_initial(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'S';
    }

    if (function_exists('mb_substr')) {
        return mb_strtoupper(
            mb_substr($name, 0, 1, 'UTF-8'),
            'UTF-8'
        );
    }

    return strtoupper(substr($name, 0, 1));
}

function staff_status_label(string $status): string
{
    return match ($status) {
        'waiting' => '未対応',
        'working' => '対応中',
        'completed' => '完了',
        'attention' => '要確認',
        default => '確認待ち',
    };
}

function staff_format_datetime(string $datetime): string
{
    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return $datetime;
    }

    return date('Y/m/d H:i', $timestamp);
}

function staff_greeting(): array
{
    $hour = (int) date('G');

    if ($hour >= 5 && $hour < 11) {
        return [
            'title' => 'おはようございます',
            'message' => '今日の仕事はこんな感じです。',
        ];
    }

    if ($hour >= 11 && $hour < 17) {
        return [
            'title' => 'こんにちは',
            'message' => '現在の担当業務を確認しましょう。',
        ];
    }

    if ($hour >= 17 && $hour < 22) {
        return [
            'title' => 'お疲れさまです',
            'message' => '残っている仕事と連絡を確認しましょう。',
        ];
    }

    return [
        'title' => '遅い時間までお疲れさまです',
        'message' => '緊急の仕事だけ確認して、無理せず進めましょう。',
    ];
}

function staff_task_status_label(string $status): string
{
    return match ($status) {
        'todo' => '未着手',
        'in_progress' => '対応中',
        'review' => '確認待ち',
        'waiting' => '保留',
        'completed' => '完了',
        'cancelled' => 'キャンセル',
        default => '不明',
    };
}

function staff_task_priority_label(string $priority): string
{
    return match ($priority) {
        'urgent' => '緊急',
        'high' => '高',
        'normal' => '通常',
        'low' => '低',
        default => '通常',
    };
}
