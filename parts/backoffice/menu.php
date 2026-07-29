<?php

declare(strict_types=1);

function hc_backoffice_menu(string $console): array
{
    if ($console === 'staff') {
        return [
            [
                'label' => '概要',
                'items' => [
                    [
                        'label' => 'スタッフホーム',
                        'href' => '/staff/',
                        'icon' => 'home',
                    ],
                ],
            ],
            [
                'label' => '顧客対応',
                'items' => [
                    [
                        'label' => 'お問い合わせ',
                        'href' => '/staff/contacts/',
                        'icon' => 'contacts',
                        'badge' => 'open_contacts',
                    ],
                    [
                        'label' => 'ユーザー確認',
                        'href' => '/staff/users/',
                        'icon' => 'users',
                    ],
                ],
            ],
            [
                'label' => '注文対応',
                'items' => [
                    [
                        'label' => 'サーバー申込',
                        'href' => '/staff/server-orders/',
                        'icon' => 'orders',
                        'badge' => 'staff_orders',
                    ],
                ],
            ],
            [
                'label' => 'その他',
                'items' => [
                    [
                        'label' => '自分宛の通知',
                        'href' => '/dashboard/notifications/',
                        'icon' => 'notifications',
                    ],
                    [
                        'label' => 'マイページ',
                        'href' => '/dashboard/',
                        'icon' => 'dashboard',
                    ],
                ],
            ],
        ];
    }

    return [
        [
            'label' => '概要',
            'items' => [
                [
                    'label' => 'ダッシュボード',
                    'href' => '/admin/',
                    'icon' => 'home',
                ],
            ],
        ],
        [
            'label' => '対応・オペレーション',
            'items' => [
                [
                    'label' => 'すべての申込',
                    'href' => '/admin/server-orders/',
                    'icon' => 'orders',
                ],
                [
                    'label' => '承認待ち',
                    'href' => '/admin/server-orders/pending/',
                    'icon' => 'approval',
                    'badge' => 'pending_approval',
                ],
                [
                    'label' => '作成準備完了',
                    'href' => '/admin/server-orders/ready/',
                    'icon' => 'ready',
                    'badge' => 'ready_orders',
                ],
                [
                    'label' => '自動作成',
                    'href' => '/admin/server-orders/provision/',
                    'icon' => 'provision',
                    'badge' => 'provision_jobs',
                ],
                [
                    'label' => '作成失敗',
                    'href' => '/admin/server-orders/provision/failed/',
                    'icon' => 'error',
                    'badge' => 'provision_failed',
                ],
                [
                    'label' => 'プラン変更申請',
                    'href' => '/admin/plan-change-requests/',
                    'icon' => 'change',
                    'badge' => 'plan_changes',
                ],
            ],
        ],
        [
            'label' => '顧客・組織',
            'items' => [
                [
                    'label' => 'ユーザー',
                    'href' => '/admin/users/',
                    'icon' => 'users',
                ],
                [
                    'label' => 'スタッフ',
                    'href' => '/admin/staff/',
                    'icon' => 'staff',
                ],
                [
                    'label' => 'お問い合わせ',
                    'href' => '/staff/contacts/',
                    'icon' => 'contacts',
                    'badge' => 'open_contacts',
                ],
                [
                    'label' => 'Pteroユーザー連携',
                    'href' => '/admin/ptero-users/',
                    'icon' => 'link',
                ],
                [
                    'label' => '個別通知',
                    'href' => '/admin/user-notifications/',
                    'icon' => 'direct',
                ],
            ],
        ],
        [
            'label' => '請求・商品',
            'items' => [
                [
                    'label' => '請求・支払い',
                    'href' => '/admin/billing/',
                    'icon' => 'billing',
                    'badge' => 'billing_attention',
                ],
                [
                    'label' => 'サーバープラン',
                    'href' => '/admin/game-plans/',
                    'icon' => 'plans',
                ],
                [
                    'label' => 'Stripeプラン',
                    'href' => '/admin/stripe-plans/',
                    'icon' => 'stripe',
                ],
                [
                    'label' => '申込受付設定',
                    'href' => '/admin/order-settings/',
                    'icon' => 'settings',
                ],
            ],
        ],
        [
            'label' => 'サービス・コンテンツ',
            'items' => [
                [
                    'label' => 'サービス管理',
                    'href' => '/admin/services/',
                    'icon' => 'services',
                ],
                [
                    'label' => 'ニュース',
                    'href' => '/admin/news/',
                    'icon' => 'news',
                ],
                [
                    'label' => '全体通知',
                    'href' => '/admin/site-notifications/',
                    'icon' => 'notifications',
                ],
                [
                    'label' => 'ヘッダー設定',
                    'href' => '/admin/header-settings/',
                    'icon' => 'header',
                ],
            ],
        ],
        [
            'label' => 'インフラ',
            'items' => [
                [
                    'label' => 'Pterodactyl',
                    'href' => '/admin/ptero/',
                    'icon' => 'server',
                ],
                [
                    'label' => 'Allocation',
                    'href' => '/admin/ptero/allocations/',
                    'icon' => 'network',
                ],
            ],
        ],
        [
            'label' => '公開サイト',
            'items' => [
                [
                    'label' => 'トップページ',
                    'href' => '/',
                    'icon' => 'external',
                    'external' => true,
                ],
                [
                    'label' => 'サービス一覧',
                    'href' => '/services/',
                    'icon' => 'external',
                    'external' => true,
                ],
                [
                    'label' => '運営者情報',
                    'href' => '/operator/',
                    'icon' => 'external',
                    'external' => true,
                ],
                [
                    'label' => '会社情報',
                    'href' => '/company/',
                    'icon' => 'external',
                    'external' => true,
                ],
            ],
        ],
        [
            'label' => 'システム',
            'developer_only' => true,
            'items' => [
                [
                    'label' => '開発者ページ',
                    'href' => '/admin/dev/',
                    'icon' => 'developer',
                ],
                [
                    'label' => 'ログ',
                    'href' => '/admin/dev/logs/',
                    'icon' => 'logs',
                ],
            ],
        ],
    ];
}
