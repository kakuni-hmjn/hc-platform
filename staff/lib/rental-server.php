<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * レンタルサーバー事業ダッシュボードの集計情報を取得する。
 *
 * テーブルがまだ存在しない開発環境でも、
 * Staff Console全体を停止させないように個別に例外処理する。
 */
function staff_rental_server_dashboard_load(): array
{
    $pdo = staff_db();

    $result = [
        'counts' => [
            'total' => 0,
            'active' => 0,
            'waiting_payment' => 0,
            'paid' => 0,
            'creating' => 0,
            'pending_approval' => 0,
            'failed' => 0,
            'suspended' => 0,
            'plans' => 0,
            'nodes' => 0,
        ],
        'revenue' => [
            'paid_total' => 0,
            'monthly_estimate' => 0,
        ],
        'latest_orders' => [],
        'errors' => [],
    ];

    try {
        $statement = $pdo->query(
            "SELECT
                COUNT(*) AS total,

                COUNT(*) FILTER (
                    WHERE status = 'active'
                ) AS active,

                COUNT(*) FILTER (
                    WHERE status = 'pending_payment'
                ) AS waiting_payment,

                COUNT(*) FILTER (
                    WHERE status = 'paid'
                ) AS paid,

                COUNT(*) FILTER (
                    WHERE status = 'creating'
                ) AS creating,

                COUNT(*) FILTER (
                    WHERE status IN (
                        'pending_approval',
                        'approval_failed'
                    )
                ) AS pending_approval,

                COUNT(*) FILTER (
                    WHERE status IN (
                        'provision_failed',
                        'approval_failed'
                    )
                ) AS failed,

                COUNT(*) FILTER (
                    WHERE status = 'suspended'
                ) AS suspended,

                COALESCE(
                    SUM(
                        CASE
                            WHEN payment_status = 'paid'
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS paid_total
            FROM game_server_orders"
        );

        $row = $statement->fetch();

        if (is_array($row)) {
            foreach ([
                'total',
                'active',
                'waiting_payment',
                'paid',
                'creating',
                'pending_approval',
                'failed',
                'suspended',
            ] as $key) {
                $result['counts'][$key] = (int) (
                    $row[$key] ?? 0
                );
            }

            $result['revenue']['paid_total'] = (int) (
                $row['paid_total'] ?? 0
            );
        }
    } catch (Throwable $exception) {
        $result['errors'][] =
            '契約集計を取得できませんでした。';
    }

    try {
        $statement = $pdo->query(
            "SELECT COUNT(*)
             FROM game_server_plans
             WHERE COALESCE(is_active, TRUE) = TRUE"
        );

        $result['counts']['plans'] = (int) (
            $statement->fetchColumn() ?: 0
        );
    } catch (Throwable $exception) {
        $result['errors'][] =
            'プラン数を取得できませんでした。';
    }

    try {
        $statement = $pdo->query(
            "SELECT COUNT(*)
             FROM ptero_nodes"
        );

        $result['counts']['nodes'] = (int) (
            $statement->fetchColumn() ?: 0
        );
    } catch (Throwable $exception) {
        $result['errors'][] =
            'Node数を取得できませんでした。';
    }

    try {
        $statement = $pdo->query(
            "SELECT
                COALESCE(
                    SUM(
                        COALESCE(
                            gso.amount,
                            gsp.price_monthly,
                            0
                        )
                    ),
                    0
                )
             FROM game_server_orders gso
             LEFT JOIN game_server_plans gsp
                ON gsp.id = gso.plan_id
             WHERE gso.status = 'active'"
        );

        $result['revenue']['monthly_estimate'] = (int) (
            $statement->fetchColumn() ?: 0
        );
    } catch (Throwable $exception) {
        $result['errors'][] =
            '月額売上見込みを取得できませんでした。';
    }

    try {
        $statement = $pdo->query(
            "SELECT
                gso.id,
                gso.server_name,
                gso.status,
                gso.payment_status,
                gso.amount,
                gso.currency,
                gso.created_at,

                gsp.name AS plan_name,

                users.username,
                users.email

             FROM game_server_orders gso

             LEFT JOIN game_server_plans gsp
                ON gsp.id = gso.plan_id

             LEFT JOIN users
                ON users.id = gso.user_id

             ORDER BY
                gso.created_at DESC,
                gso.id DESC

             LIMIT 8"
        );

        $orders = $statement->fetchAll();

        $result['latest_orders'] = is_array($orders)
            ? $orders
            : [];
    } catch (Throwable $exception) {
        $result['errors'][] =
            '最新契約を取得できませんでした。';
    }

    return $result;
}

function staff_rental_order_status_label(
    string $status
): string {
    return match ($status) {
        'pending_payment' => '決済待ち',
        'paid' => '作成待ち',
        'creating' => '作成中',
        'pending_approval' => '承認待ち',
        'approval_failed' => '承認失敗',
        'active' => '稼働中',
        'provision_failed' => '作成失敗',
        'suspended' => '停止中',
        'cancelled' => 'キャンセル',
        'expired' => '期限切れ',
        default => $status,
    };
}

function staff_rental_payment_status_label(
    string $status
): string {
    return match ($status) {
        'unpaid' => '未払い',
        'checkout_created' => 'Checkout作成済み',
        'paid' => '支払い済み',
        'failed' => '支払い失敗',
        'refunded' => '返金済み',
        'cancelled' => '決済キャンセル',
        default => $status,
    };
}

function staff_rental_price(
    int|string|null $amount,
    ?string $currency = 'jpy'
): string {
    $value = (int) ($amount ?? 0);
    $currency = strtolower(
        trim((string) ($currency ?: 'jpy'))
    );

    if ($currency === 'jpy') {
        return '¥' . number_format($value);
    }

    return strtoupper($currency)
        . ' '
        . number_format($value);
}

function staff_rental_datetime(
    string|null $value
): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))
            ->format('Y/m/d H:i');
    } catch (Throwable $exception) {
        return $value;
    }
}
