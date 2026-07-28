<?php

declare(strict_types=1);

function hc_bo_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function hc_bo_rows(
    PDO $pdo,
    string $sql,
    array $params = []
): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function hc_bo_current_path(): string
{
    $path = parse_url(
        (string)($_SERVER['REQUEST_URI'] ?? '/'),
        PHP_URL_PATH
    );

    return is_string($path) && $path !== '' ? $path : '/';
}

function hc_bo_is_active(string $href): bool
{
    $current = rtrim(hc_bo_current_path(), '/') . '/';
    $target = rtrim($href, '/') . '/';

    if ($target === '/admin/' || $target === '/staff/') {
        return $current === $target;
    }

    return str_starts_with($current, $target);
}

function hc_bo_role_is_developer(array $user): bool
{
    return in_array(
        (string)($user['role'] ?? ''),
        ['owner', 'developer'],
        true
    );
}

function hc_bo_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        return (new DateTime($value))->format('Y/m/d H:i');
    } catch (Throwable $e) {
        return $value;
    }
}

function hc_bo_order_status_label(string $status): string
{
    return match ($status) {
        'pending_payment' => '支払い待ち',
        'paid' => '支払い済み',
        'creating' => '作成中',
        'provision_failed' => '作成失敗',
        'pending_approval' => '承認待ち',
        'activating' => '有効化中',
        'approval_failed' => '承認失敗',
        'active' => '稼働中',
        'cancelled' => 'キャンセル',
        'suspended' => '停止中',
        default => $status,
    };
}

function hc_bo_contact_status_label(string $status): string
{
    return match ($status) {
        'open' => '未対応',
        'in_progress' => '対応中',
        'closed' => '完了',
        default => $status,
    };
}
