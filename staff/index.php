<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

$staffDashboard = staff_dashboard_load(
    (int) $staffContext['user']['id']
);

$staffGreeting = staff_greeting();

$pageTitle = 'スタッフコンソール';

require_once __DIR__
    . '/components/layout.php';

staff_layout_start([
    'title' => 'スタッフダッシュボード',
    'heading' => 'ダッシュボード',
    'eyebrow' => 'HC STAFF WORKSPACE',
    'description' => '担当業務、通知、システム状況を確認できます。',
    'active_navigation' => 'dashboard',
    'show_heading' => false,
]);

?>
<section class="staff-page-heading">
                    <div>
                        <p
                            class="staff-page-heading__eyebrow"
                        >
                            HC STAFF WORKSPACE
                        </p>

                        <h2>
                            <?= htmlspecialchars(
                                $staffGreeting['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>、
                            <?= htmlspecialchars(
                                $staffDisplayName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>さん
                        </h2>

                        <p
                            class="staff-page-heading__description"
                        >
                            <?= htmlspecialchars(
                                $staffGreeting['message'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                            タスク、通知、社内連絡、
                            担当業務をまとめて確認できます。
                        </p>
                    </div>

                    <div
                        class="staff-page-heading__status"
                    >
                        <span></span>

                        <?= htmlspecialchars(
                            $staffRoleName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>
                </section>

                <section class="staff-summary-grid">
                    <a
                        href="/staff/tasks/?status=todo"
                        class="staff-summary-card"
                    >
                        <span>今日のタスク</span>

                        <strong>
                            <?= (int) $staffDashboard[
                                'counts'
                            ]['todo'] ?>
                        </strong>

                        <small>
                            未着手の担当業務
                        </small>
                    </a>

                    <a
                        href="/staff/tasks/?status=in_progress"
                        class="staff-summary-card"
                    >
                        <span>対応中</span>

                        <strong>
                            <?= (int) $staffDashboard[
                                'counts'
                            ]['in_progress'] ?>
                        </strong>

                        <small>
                            現在進行中の仕事
                        </small>
                    </a>

                    <a
                        href="/staff/tasks/?filter=overdue"
                        class="staff-summary-card
                               staff-summary-card--warning"
                    >
                        <span>期限超過</span>

                        <strong>
                            <?= (int) $staffDashboard[
                                'counts'
                            ]['overdue'] ?>
                        </strong>

                        <small>
                            早めの確認が必要
                        </small>
                    </a>

                    <a
                        href="/staff/notifications/"
                        class="staff-summary-card"
                    >
                        <span>未読通知</span>

                        <strong>
                            <?= (int) $staffDashboard[
                                'counts'
                            ]['notifications'] ?>
                        </strong>

                        <small>
                            まだ確認していない通知
                        </small>
                    </a>
                </section>

                <div class="staff-dashboard-grid">
                    <div class="staff-dashboard-column">
                        <section class="staff-panel">
                            <header
                                class="staff-panel__header"
                            >
                                <div>
                                    <h3>自分のタスク</h3>

                                    <p>
                                        優先度と期限順に表示
                                    </p>
                                </div>

                                <a href="/staff/tasks/">
                                    すべて見る
                                </a>
                            </header>

                            <div class="staff-list">
                                <?php if (
                                    $staffDashboard['tasks']
                                    === []
                                ): ?>
                                    <div
                                        class="staff-empty-state"
                                    >
                                        <strong>
                                            担当タスクはありません
                                        </strong>

                                        <p>
                                            新しい仕事が割り当て
                                            られるとここに表示されます。
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (
                                        $staffDashboard['tasks']
                                        as $task
                                    ): ?>
                                        <a
                                            href="/staff/tasks/detail/?id=<?= (int) $task['id'] ?>"
                                            class="staff-list-row"
                                        >
                                            <div>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        (string) $task[
                                                            'title'
                                                        ],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </strong>

                                                <p>
                                                    <?= htmlspecialchars(
                                                        (string) $task[
                                                            'task_number'
                                                        ],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                    ·

                                                    <?= htmlspecialchars(
                                                        staff_format_due_date(
                                                            $task[
                                                                'due_at'
                                                            ] ?? null
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </p>
                                            </div>

                                            <span
                                                class="staff-status
                                                       staff-status--<?= htmlspecialchars(
                                                           (string) $task[
                                                               'status'
                                                           ],
                                                           ENT_QUOTES,
                                                           'UTF-8'
                                                       ) ?>"
                                            >
                                                <?= htmlspecialchars(
                                                    staff_task_status_label(
                                                        (string) $task[
                                                            'status'
                                                        ]
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="staff-panel">
                            <header
                                class="staff-panel__header"
                            >
                                <div>
                                    <h3>社内連絡</h3>

                                    <p>
                                        最新のお知らせ
                                    </p>
                                </div>

                                <a
                                    href="/staff/announcements/"
                                >
                                    一覧を見る
                                </a>
                            </header>

                            <div class="staff-list">
                                <?php if (
                                    $staffDashboard[
                                        'announcements'
                                    ] === []
                                ): ?>
                                    <div
                                        class="staff-empty-state"
                                    >
                                        <strong>
                                            新しい社内連絡はありません
                                        </strong>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (
                                        $staffDashboard[
                                            'announcements'
                                        ]
                                        as $announcement
                                    ): ?>
                                        <article
                                            class="staff-list-row"
                                        >
                                            <div>
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        (string) $announcement[
                                                            'title'
                                                        ],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </strong>

                                                <p>
                                                    <?= htmlspecialchars(
                                                        (string) $announcement[
                                                            'body'
                                                        ],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </p>
                                            </div>

                                            <?php if (
                                                !empty(
                                                    $announcement[
                                                        'requires_confirmation'
                                                    ]
                                                )
                                            ): ?>
                                                <span
                                                    class="staff-status
                                                           staff-status--waiting"
                                                >
                                                    確認必須
                                                </span>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <aside class="staff-dashboard-side">
                        <section class="staff-panel">
                            <header
                                class="staff-panel__header"
                            >
                                <div>
                                    <h3>担当業務</h3>

                                    <p>
                                        カテゴリと権限から生成
                                    </p>
                                </div>
                            </header>

                            <div class="staff-category-grid">
                                <?php foreach (
                                    $staffContext['categories']
                                    as $category
                                ): ?>
                                    <a
                                        href="/staff/category/?slug=<?= urlencode(
                                            (string) $category['slug']
                                        ) ?>"
                                        class="staff-category-card"
                                    >
                                        <strong>
                                            <?= htmlspecialchars(
                                                (string) $category[
                                                    'name'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars(
                                                (string) (
                                                    $category[
                                                        'description'
                                                    ] ?? ''
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>

                                <?php if (
                                    staff_can_access_admin(
                                        $staffContext
                                    )
                                ): ?>
                                    <a
                                        href="/staff/admin/"
                                        class="staff-category-card"
                                    >
                                        <strong>
                                            全体管理
                                        </strong>

                                        <small>
                                            管理者向け業務機能
                                        </small>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="staff-panel">
                            <header
                                class="staff-panel__header"
                            >
                                <div>
                                    <h3>所属・権限</h3>

                                    <p>
                                        現在有効なスタッフ情報
                                    </p>
                                </div>
                            </header>

                            <div class="staff-context-grid">
                                <div>
                                    <span>基本ロール</span>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $staffRoleName,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </strong>
                                </div>

                                <div>
                                    <span>カテゴリ</span>

                                    <strong>
                                        <?= count(
                                            $staffContext[
                                                'categories'
                                            ]
                                        ) ?>
                                    </strong>
                                </div>

                                <div>
                                    <span>所属部署</span>

                                    <strong>
                                        <?= count(
                                            $staffContext[
                                                'departments'
                                            ]
                                        ) ?>
                                    </strong>
                                </div>

                                <div>
                                    <span>有効権限</span>

                                    <strong>
                                        <?= count(
                                            $staffContext[
                                                'permissions'
                                            ]
                                        ) ?>
                                    </strong>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
<?php

staff_layout_end();
