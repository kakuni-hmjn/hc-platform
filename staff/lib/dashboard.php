<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function staff_dashboard_load(int $staffUserId): array
{
    $pdo = staff_db();

    $countsStatement = $pdo->prepare(
        'SELECT
            COUNT(*) FILTER (
                WHERE assigned_user_id = :user_id
                  AND status = \'todo\'
            ) AS todo_count,

            COUNT(*) FILTER (
                WHERE assigned_user_id = :user_id
                  AND status = \'in_progress\'
            ) AS in_progress_count,

            COUNT(*) FILTER (
                WHERE assigned_user_id = :user_id
                  AND status = \'review\'
            ) AS review_count,

            COUNT(*) FILTER (
                WHERE assigned_user_id = :user_id
                  AND due_at < CURRENT_TIMESTAMP
                  AND status NOT IN (
                      \'completed\',
                      \'cancelled\'
                  )
            ) AS overdue_count
        FROM staff_tasks'
    );

    $countsStatement->execute([
        'user_id' => $staffUserId,
    ]);

    $counts = $countsStatement->fetch();

    if (!is_array($counts)) {
        $counts = [];
    }

    $notificationStatement = $pdo->prepare(
        'SELECT
            id,
            type,
            title,
            body,
            action_url,
            is_read,
            created_at
        FROM staff_notifications
        WHERE user_id = :user_id
          AND is_read = FALSE
        ORDER BY created_at DESC
        LIMIT 6'
    );

    $notificationStatement->execute([
        'user_id' => $staffUserId,
    ]);

    $notifications = $notificationStatement->fetchAll();

    $taskStatement = $pdo->prepare(
        'SELECT
            task.id,
            task.task_number,
            task.title,
            task.description,
            task.priority,
            task.status,
            task.due_at,
            task.created_at,
            category.name AS category_name,
            category.slug AS category_slug
        FROM staff_tasks task
        LEFT JOIN staff_categories category
            ON category.id = task.category_id
        WHERE task.assigned_user_id = :user_id
          AND task.status NOT IN (
              \'completed\',
              \'cancelled\'
          )
        ORDER BY
            CASE task.priority
                WHEN \'urgent\' THEN 1
                WHEN \'high\' THEN 2
                WHEN \'normal\' THEN 3
                ELSE 4
            END,
            task.due_at ASC NULLS LAST,
            task.created_at DESC
        LIMIT 8'
    );

    $taskStatement->execute([
        'user_id' => $staffUserId,
    ]);

    $tasks = $taskStatement->fetchAll();

    $announcementStatement = $pdo->prepare(
        'SELECT
            announcement.id,
            announcement.title,
            announcement.body,
            announcement.priority,
            announcement.requires_confirmation,
            announcement.published_at
        FROM staff_announcements announcement
        WHERE announcement.published_at IS NOT NULL
          AND announcement.published_at <= CURRENT_TIMESTAMP
          AND (
              announcement.expires_at IS NULL
              OR announcement.expires_at > CURRENT_TIMESTAMP
          )
          AND (
              announcement.target_type = \'all\'
              OR (
                  announcement.target_type = \'user\'
                  AND announcement.target_id = :user_id
              )
          )
        ORDER BY
            CASE announcement.priority
                WHEN \'urgent\' THEN 1
                WHEN \'important\' THEN 2
                ELSE 3
            END,
            announcement.published_at DESC
        LIMIT 5'
    );

    $announcementStatement->execute([
        'user_id' => $staffUserId,
    ]);

    return [
        'counts' => [
            'todo' => (int) ($counts['todo_count'] ?? 0),
            'in_progress' =>
                (int) ($counts['in_progress_count'] ?? 0),
            'review' => (int) ($counts['review_count'] ?? 0),
            'overdue' => (int) ($counts['overdue_count'] ?? 0),
            'notifications' => count($notifications),
        ],
        'tasks' => $tasks,
        'notifications' => $notifications,
        'announcements' =>
            $announcementStatement->fetchAll(),
    ];
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
            'message' => '現在の仕事はこんな感じです。',
        ];
    }

    if ($hour >= 17 && $hour < 22) {
        return [
            'title' => 'お疲れさまです',
            'message' =>
                '今日の残りの仕事を確認しましょう。',
        ];
    }

    return [
        'title' => '遅い時間までお疲れさまです',
        'message' =>
            '緊急の仕事と未確認通知を確認しましょう。',
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

function staff_format_due_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '期限なし';
    }

    $timestamp = strtotime($datetime);

    if ($timestamp === false) {
        return '期限未設定';
    }

    return date('m/d H:i', $timestamp);
}
