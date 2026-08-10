<?php

declare(strict_types=1);

require_once __DIR__
    . '/../lib/bootstrap.php';

require_once __DIR__
    . '/../components/layout.php';

require_once __DIR__
    . '/../components/ui.php';

staff_require_permission(
    $staffContext,
    'tasks.view.own'
);

$staffUserId = (int) (
    $staffContext['user']['id']
    ?? 0
);

$statusFilter = trim(
    (string) (
        $_GET['status']
        ?? ''
    )
);

$filter = trim(
    (string) (
        $_GET['filter']
        ?? ''
    )
);

/*
|--------------------------------------------------------------------------
| タスク件数
|--------------------------------------------------------------------------
*/

$countStatement = staff_db()->prepare(
    'SELECT
        COUNT(*) FILTER (
            WHERE status = \'todo\'
        ) AS todo_count,

        COUNT(*) FILTER (
            WHERE status = \'in_progress\'
        ) AS in_progress_count,

        COUNT(*) FILTER (
            WHERE status = \'review\'
        ) AS review_count,

        COUNT(*) FILTER (
            WHERE status = \'completed\'
        ) AS completed_count,

        COUNT(*) FILTER (
            WHERE due_at < CURRENT_TIMESTAMP
              AND status NOT IN (
                  \'completed\',
                  \'cancelled\'
              )
        ) AS overdue_count
    FROM staff_tasks
    WHERE assigned_user_id = :staff_user_id'
);

$countStatement->execute([
    'staff_user_id' => $staffUserId,
]);

$taskCounts = $countStatement->fetch();

if (!is_array($taskCounts)) {
    $taskCounts = [];
}

/*
|--------------------------------------------------------------------------
| タスク一覧
|--------------------------------------------------------------------------
*/

$where = [
    'assigned_user_id = :staff_user_id',
];

$params = [
    'staff_user_id' => $staffUserId,
];

$allowedStatuses = [
    'todo',
    'in_progress',
    'review',
    'waiting',
    'completed',
    'cancelled',
];

if (
    $statusFilter !== ''
    && in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $where[] = 'status = :status';

    $params['status'] = $statusFilter;
}

if ($filter === 'overdue') {
    $where[] = 'due_at < CURRENT_TIMESTAMP';

    $where[] = 'status NOT IN (
        \'completed\',
        \'cancelled\'
    )';
}

$taskStatement = staff_db()->prepare(
    'SELECT
        id,
        task_number,
        title,
        description,
        status,
        priority,
        due_at,
        created_at,
        updated_at
    FROM staff_tasks
    WHERE '
    . implode(
        ' AND ',
        $where
    )
    . '
    ORDER BY
        CASE priority
            WHEN \'urgent\' THEN 1
            WHEN \'high\' THEN 2
            WHEN \'normal\' THEN 3
            WHEN \'low\' THEN 4
            ELSE 5
        END ASC,
        due_at ASC NULLS LAST,
        id DESC
    LIMIT 100'
);

$taskStatement->execute($params);

$tasks = $taskStatement->fetchAll();

if (!is_array($tasks)) {
    $tasks = [];
}

function staff_tasks_status_label(
    string $status
): string {
    return match ($status) {
        'todo' => '未着手',
        'in_progress' => '対応中',
        'review' => 'レビュー',
        'waiting' => '待機中',
        'completed' => '完了',
        'cancelled' => 'キャンセル',
        default => '不明',
    };
}

function staff_tasks_priority_label(
    string $priority
): string {
    return match ($priority) {
        'urgent' => '緊急',
        'high' => '高',
        'normal' => '通常',
        'low' => '低',
        default => '通常',
    };
}

function staff_tasks_due_label(
    mixed $dueAt
): string {
    if (
        $dueAt === null
        || trim((string) $dueAt) === ''
    ) {
        return '期限なし';
    }

    try {
        $date = new DateTimeImmutable(
            (string) $dueAt
        );

        return $date->format(
            'Y/m/d H:i'
        );
    } catch (Throwable) {
        return '期限未設定';
    }
}

staff_layout_start([
    'title' => 'タスク管理',
    'heading' => 'タスク管理',
    'eyebrow' => 'HC TASK MANAGEMENT',
    'description' =>
        '自分に割り当てられた業務、期限、優先度、進行状況を確認できます。',
    'active_navigation' => 'tasks',
]);

?>
<section class="staff-summary-grid">
    <?php staff_ui_summary_card(
        '未着手',
        (int) (
            $taskCounts['todo_count']
            ?? 0
        ),
        'これから対応する業務',
        '/staff/tasks/?status=todo'
    ); ?>

    <?php staff_ui_summary_card(
        '対応中',
        (int) (
            $taskCounts['in_progress_count']
            ?? 0
        ),
        '現在進行中の業務',
        '/staff/tasks/?status=in_progress'
    ); ?>

    <?php staff_ui_summary_card(
        'レビュー待ち',
        (int) (
            $taskCounts['review_count']
            ?? 0
        ),
        '確認が必要な業務',
        '/staff/tasks/?status=review'
    ); ?>

    <?php staff_ui_summary_card(
        '期限超過',
        (int) (
            $taskCounts['overdue_count']
            ?? 0
        ),
        '早めの確認が必要',
        '/staff/tasks/?filter=overdue',
        'warning'
    ); ?>
</section>

<div class="staff-dashboard-grid">
    <div class="staff-dashboard-column">
        <?php staff_ui_panel_start(
            '自分のタスク',
            '優先度と期限順に表示'
        ); ?>

        <div class="staff-list">
            <?php if ($tasks === []): ?>
                <?php staff_ui_empty(
                    '対象のタスクはありません',
                    '新しい業務が割り当てられるとここに表示されます。'
                ); ?>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <a
                        href="/staff/tasks/detail/?id=<?= (int) (
                            $task['id']
                            ?? 0
                        ) ?>"
                        class="staff-list-row"
                    >
                        <div>
                            <strong>
                                <?= staff_ui_escape(
                                    $task['title']
                                    ?? '無題のタスク'
                                ) ?>
                            </strong>

                            <p>
                                <?= staff_ui_escape(
                                    $task['task_number']
                                    ?? ''
                                ) ?>

                                ·

                                <?= staff_ui_escape(
                                    staff_tasks_due_label(
                                        $task['due_at']
                                        ?? null
                                    )
                                ) ?>

                                · 優先度:

                                <?= staff_ui_escape(
                                    staff_tasks_priority_label(
                                        (string) (
                                            $task['priority']
                                            ?? 'normal'
                                        )
                                    )
                                ) ?>
                            </p>
                        </div>

                        <?php staff_ui_status(
                            staff_tasks_status_label(
                                (string) (
                                    $task['status']
                                    ?? ''
                                )
                            ),
                            (string) (
                                $task['status']
                                ?? ''
                            )
                        ); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php staff_ui_panel_end(); ?>
    </div>

    <aside class="staff-dashboard-column">
        <?php staff_ui_panel_start(
            '表示条件',
            '現在の絞り込み'
        ); ?>

        <div class="staff-list">
            <a
                href="/staff/tasks/"
                class="staff-list-row"
            >
                <div>
                    <strong>すべて</strong>

                    <p>
                        自分に割り当てられた全タスク
                    </p>
                </div>
            </a>

            <a
                href="/staff/tasks/?status=todo"
                class="staff-list-row"
            >
                <div>
                    <strong>未着手</strong>

                    <p>
                        まだ対応を開始していない業務
                    </p>
                </div>
            </a>

            <a
                href="/staff/tasks/?status=in_progress"
                class="staff-list-row"
            >
                <div>
                    <strong>対応中</strong>

                    <p>
                        現在作業している業務
                    </p>
                </div>
            </a>

            <a
                href="/staff/tasks/?status=review"
                class="staff-list-row"
            >
                <div>
                    <strong>レビュー待ち</strong>

                    <p>
                        確認や承認を待っている業務
                    </p>
                </div>
            </a>

            <a
                href="/staff/tasks/?filter=overdue"
                class="staff-list-row"
            >
                <div>
                    <strong>期限超過</strong>

                    <p>
                        期限を過ぎている未完了業務
                    </p>
                </div>
            </a>
        </div>

        <?php staff_ui_panel_end(); ?>

        <?php staff_ui_panel_start(
            'タスク状況',
            '現在の集計'
        ); ?>

        <div class="staff-list">
            <div class="staff-list-row">
                <div>
                    <strong>完了済み</strong>

                    <p>
                        完了した担当業務
                    </p>
                </div>

                <strong>
                    <?= (int) (
                        $taskCounts[
                            'completed_count'
                        ]
                        ?? 0
                    ) ?>
                </strong>
            </div>
        </div>

        <?php staff_ui_panel_end(); ?>
    </aside>
</div>
<?php

staff_layout_end();
