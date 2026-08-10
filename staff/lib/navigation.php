<?php

declare(strict_types=1);

require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/page-access.php';

function staff_navigation_build(array $context): array
{
    $groups = [];

    /*
    |--------------------------------------------------------------------------
    | ワークスペース
    |--------------------------------------------------------------------------
    */

    $groups[] = [
        'label' => 'ワークスペース',
        'items' => [
            [
                'label' => 'ダッシュボード',
                'href' => '/staff/',
                'icon' => 'home',
                'permission' => 'staff.dashboard.view',
            ],
            [
                'label' => 'マイタスク',
                'href' => '/staff/tasks/',
                'icon' => 'task_alt',
                'permission' => 'tasks.view.own',
            ],
            [
                'label' => '通知',
                'href' => '/staff/notifications/',
                'icon' => 'notifications',
                'permission' => 'staff.dashboard.view',
            ],
            [
                'label' => '社内連絡',
                'href' => '/staff/announcements/',
                'icon' => 'campaign',
                'permission' => 'announcements.view',
            ],
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | サービス
    |--------------------------------------------------------------------------
    */

    $serviceItems = [];

    $canAccessRentalServer = staff_can_access_service(
        $context,
        [
            'categories' => [
                'game-operations',
                'rental-server',
            ],
            'departments' => [
                'rental-server',
                'game-server',
                'infrastructure',
            ],
            'permissions' => [
                'orders.view',
                'orders.process',
                'infrastructure.servers.view',
            ],
        ]
    );

    if ($canAccessRentalServer) {
        $gameServerChildren = [
            [
                'label' => '概要',
                'href' => '/staff/rental-server/game-server/',
                'icon' => 'dashboard',
                'permission' => 'orders.view',
            ],
            [
                'label' => '申込・契約',
                'href' => '/staff/rental-server/game-server/contracts/',
                'icon' => 'receipt_long',
                'permission' => 'orders.view',
            ],
            [
                'label' => '作成・承認',
                'href' => '/staff/rental-server/game-server/approvals/',
                'icon' => 'approval',
                'permission' => 'orders.process',
            ],
            [
                'label' => 'Provisioning',
                'href' => '/staff/rental-server/game-server/provisioning/',
                'icon' => 'precision_manufacturing',
                'permission' => 'orders.process',
            ],
            [
                'label' => 'サーバー一覧',
                'href' => '/staff/rental-server/game-server/servers/',
                'icon' => 'storage',
                'permission' => 'orders.view',
            ],
            [
                'label' => 'プラン管理',
                'href' => '/staff/rental-server/game-server/plans/',
                'icon' => 'sell',
                'permission' => 'orders.process',
            ],
            [
                'label' => 'Node管理',
                'href' => '/staff/rental-server/game-server/nodes/',
                'icon' => 'hub',
                'permission' => 'infrastructure.servers.view',
            ],
        ];

        $serviceItems[] = [
            'label' => 'レンタルサーバー',
            'href' => '/staff/rental-server/',
            'icon' => 'dns',
            'permission' => 'orders.view',
            'children' => [
                [
                    'label' => 'サービス概要',
                    'href' => '/staff/rental-server/',
                    'icon' => 'space_dashboard',
                    'permission' => 'orders.view',
                ],
                [
                    'label' => 'ゲームサーバー',
                    'href' => '/staff/rental-server/game-server/',
                    'icon' => 'sports_esports',
                    'permission' => 'orders.view',
                    'children' => $gameServerChildren,
                ],
                [
                    'label' => 'VPS',
                    'href' => '/staff/rental-server/vps/',
                    'icon' => 'memory',
                    'permission' => 'orders.view',
                ],
                [
                    'label' => 'ホスティング',
                    'href' => '/staff/rental-server/hosting/',
                    'icon' => 'language',
                    'permission' => 'orders.view',
                ],
                [
                    'label' => '専用サーバー',
                    'href' => '/staff/rental-server/dedicated/',
                    'icon' => 'dns',
                    'permission' => 'orders.view',
                ],
                [
                    'label' => 'コロケーション',
                    'href' => '/staff/rental-server/colocation/',
                    'icon' => 'domain',
                    'permission' => 'orders.view',
                ],
            ],
        ];
    }

    if ($serviceItems !== []) {
        $groups[] = [
            'label' => 'サービス',
            'items' => $serviceItems,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 共通業務
    |--------------------------------------------------------------------------
    */

    $groups[] = [
        'label' => '共通業務',
        'items' => [
            [
                'label' => '顧客管理',
                'href' => '/staff/customers/',
                'icon' => 'group',
                'permission' => 'support.tickets.view',
            ],
            [
                'label' => 'お問い合わせ',
                'href' => '/staff/support/',
                'icon' => 'support_agent',
                'permission' => 'support.tickets.view',
                'children' => [
                    [
                        'label' => '概要',
                        'href' => '/staff/support/',
                        'icon' => 'description',
                        'permission' => 'support.tickets.view',
                    ],
                    [
                        'label' => 'サポートチャット',
                        'href' => '/staff/support/chat/',
                        'icon' => 'forum',
                        'permission' => 'support.tickets.view',
                    ],
                    [
                        'label' => 'サポートメール',
                        'href' => '/staff/support/email/',
                        'icon' => 'mail',
                        'permission' => 'support.tickets.view',
                    ],
                ],
            ],
            [
                'label' => '決済・請求',
                'href' => '/staff/billing/',
                'icon' => 'payments',
                'permission' => 'orders.view',
            ],
            [
                'label' => '操作ログ',
                'href' => '/staff/audit/',
                'icon' => 'history',
                'permission' => 'audit.logs.view',
            ],
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | 開発
    |--------------------------------------------------------------------------
    */

    if (
        staff_can_access_service(
            $context,
            [
                'categories' => [
                    'development',
                ],
                'departments' => [
                    'development',
                    'web-development',
                ],
                'permissions' => [
                    'development.projects.view',
                    'development.deploy.staging',
                ],
            ]
        )
    ) {
        $groups[] = [
            'label' => '開発',
            'items' => [
                [
                    'label' => 'プロジェクト',
                    'href' => '/staff/development/',
                    'icon' => 'code',
                    'permission' => 'development.projects.view',
                ],
                [
                    'label' => 'デプロイ',
                    'href' => '/staff/deployments/',
                    'icon' => 'rocket_launch',
                    'permission' => 'development.deploy.staging',
                ],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | インフラ
    |--------------------------------------------------------------------------
    */

    if (
        staff_can_access_service(
            $context,
            [
                'categories' => [
                    'infrastructure',
                ],
                'departments' => [
                    'infrastructure',
                    'rental-server',
                ],
                'permissions' => [
                    'infrastructure.servers.view',
                ],
            ]
        )
    ) {
        $groups[] = [
            'label' => 'インフラ',
            'items' => [
                [
                    'label' => 'システム状態',
                    'href' => '/staff/infrastructure/',
                    'icon' => 'monitor_heart',
                    'permission' => 'infrastructure.servers.view',
                ],
                [
                    'label' => '物理・仮想サーバー',
                    'href' => '/staff/infrastructure/servers/',
                    'icon' => 'storage',
                    'permission' => 'infrastructure.servers.view',
                ],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | システム管理
    |--------------------------------------------------------------------------
    */

    if (
        staff_has_permission($context, 'staff.users.view')
        || staff_has_permission($context, 'audit.logs.view')
        || staff_can_access_admin($context)
    ) {
        $groups[] = [
            'label' => staff_can_access_admin($context)
                ? '上位管理'
                : 'システム管理',
            'items' => [
                [
                    'label' => '管理センター',
                    'href' => '/staff/admin/',
                    'icon' => 'admin_panel_settings',
                    'admin_only' => true,
                ],
                [
                    'label' => 'スタッフ管理',
                    'href' => '/staff/admin/users/',
                    'icon' => 'groups',
                    'permission' => 'staff.users.view',
                ],
                [
                    'label' => 'ロール・権限管理',
                    'href' => '/staff/admin/roles/',
                    'icon' => 'admin_panel_settings',
                    'permission' => 'staff.roles.manage',
                ],
                [
                    'label' => '有効権限',
                    'href' => '/staff/account/permissions/',
                    'icon' => 'key',
                    'permission' => 'staff.dashboard.view',
                ],
                [
                    'label' => '承認センター',
                    'href' => '/staff/approvals/',
                    'icon' => 'approval',
                    'permission' => 'orders.approve',
                ],
                [
                    'label' => 'システム設定',
                    'href' => '/staff/settings/',
                    'icon' => 'settings',
                    'permission' => 'staff.users.view',
                ],
            ],
        ];
    }

    return staff_navigation_filter_groups(
        $groups,
        $context
    );
}

function staff_navigation_filter_groups(
    array $groups,
    array $context
): array {
    $visibleGroups = [];

    foreach ($groups as $group) {
        $visibleItems = staff_navigation_filter_items(
            (array) ($group['items'] ?? []),
            $context
        );

        if ($visibleItems === []) {
            continue;
        }

        $group['items'] = $visibleItems;
        $visibleGroups[] = $group;
    }

    return $visibleGroups;
}

function staff_navigation_filter_items(
    array $items,
    array $context
): array {
    $visibleItems = [];

    foreach ($items as $item) {
        if (!staff_page_access_allowed($context, (string) ($item['href'] ?? ''))) {
            continue;
        }
        if (
            !empty($item['admin_only'])
            && !staff_can_access_admin($context)
        ) {
            continue;
        }

        $permission = trim(
            (string) ($item['permission'] ?? '')
        );

        $allowed = (
            $permission === ''
            || staff_has_permission($context, $permission)
            || staff_can_access_admin($context)
        );

        if (!$allowed) {
            continue;
        }

        $children = staff_navigation_filter_items(
            (array) ($item['children'] ?? []),
            $context
        );

        if (array_key_exists('children', $item)) {
            $item['children'] = $children;
        }

        $visibleItems[] = $item;
    }

    return $visibleItems;
}
