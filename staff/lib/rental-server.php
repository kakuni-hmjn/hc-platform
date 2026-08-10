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
             WHERE status = 'published'"
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

function staff_rental_section_load(
    string $section,
    string $search = '',
    string $status = '',
    int $selectedId = 0
): array {
    $status = strtolower(trim($status));

    if (in_array($status, ['all', 'any', '*'], true)) {
        $status = '';
    }

    $pdo = staff_db();
    $result = ['rows' => [], 'selected' => null, 'events' => [], 'errors' => []];

    try {
        if (in_array($section, ['contracts', 'approvals'], true)) {
            $where = ['1 = 1'];
            $params = [];
            if ($section === 'approvals') {
                $where[] = "gso.status IN ('pending_approval', 'approval_failed', 'rejected')";
            }
            if ($search !== '') {
                $where[] = '(gso.server_name ILIKE :search OR u.username ILIKE :search OR u.email ILIKE :search OR CAST(gso.id AS TEXT) = :exact_id)';
                $params['search'] = '%' . $search . '%';
                $params['exact_id'] = ctype_digit($search) ? $search : '0';
            }
            if ($status !== '') {
                $where[] = 'gso.status = :status';
                $params['status'] = $status;
            }
            $statement = $pdo->prepare(
                'SELECT gso.*, u.username, u.email, gsp.name AS plan_name,
                        pn.label AS node_label
                 FROM game_server_orders gso
                 LEFT JOIN users u ON u.id = gso.user_id
                 LEFT JOIN game_server_plans gsp ON gsp.id = gso.plan_id
                 LEFT JOIN ptero_nodes pn ON pn.id = gso.selected_node_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY gso.created_at DESC, gso.id DESC LIMIT 150'
            );
            $statement->execute($params);
            $result['rows'] = $statement->fetchAll() ?: [];
        } elseif ($section === 'provisioning') {
            $where = ['1 = 1'];
            $params = [];
            if ($search !== '') {
                $where[] = '(gso.server_name ILIKE :search OR u.username ILIKE :search OR CAST(pj.order_id AS TEXT) = :exact_id)';
                $params['search'] = '%' . $search . '%';
                $params['exact_id'] = ctype_digit($search) ? $search : '0';
            }
            if ($status !== '') {
                $where[] = 'pj.status = :status';
                $params['status'] = $status;
            }
            $statement = $pdo->prepare(
                'SELECT pj.*, gso.server_name, gso.status AS order_status,
                        u.username, u.email
                 FROM provisioning_jobs pj
                 LEFT JOIN game_server_orders gso ON gso.id = pj.order_id
                 LEFT JOIN users u ON u.id = gso.user_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY pj.created_at DESC, pj.id DESC LIMIT 150'
            );
            $statement->execute($params);
            $result['rows'] = $statement->fetchAll() ?: [];
        } elseif ($section === 'servers') {
            $where = ['ps.deleted_at IS NULL'];
            $params = [];
            if ($search !== '') {
                $where[] = '(ps.name ILIKE :search OR ps.ptero_identifier ILIKE :search OR u.username ILIKE :search OR u.email ILIKE :search)';
                $params['search'] = '%' . $search . '%';
            }
            if ($status !== '') {
                $where[] = 'ps.status = :status';
                $params['status'] = $status;
            }
            $statement = $pdo->prepare(
                'SELECT ps.*, u.username, u.email, gsp.name AS plan_name,
                        pn.label AS node_label, gso.payment_status
                 FROM ptero_servers ps
                 LEFT JOIN users u ON u.id = ps.user_id
                 LEFT JOIN game_server_plans gsp ON gsp.id = ps.plan_id
                 LEFT JOIN ptero_nodes pn ON pn.id = ps.node_id
                 LEFT JOIN game_server_orders gso ON gso.id = ps.order_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY ps.created_at DESC, ps.id DESC LIMIT 150'
            );
            $statement->execute($params);
            $result['rows'] = $statement->fetchAll() ?: [];
        } elseif ($section === 'plans') {
            $statement = $pdo->prepare(
                "SELECT gsp.*,
                        COALESCE(STRING_AGG(DISTINCT pn.label, ', '), '-') AS node_labels,
                        COUNT(DISTINCT gso.id) FILTER (WHERE gso.status = 'active') AS active_contracts
                 FROM game_server_plans gsp
                 LEFT JOIN game_server_plan_nodes gspn ON gspn.plan_id = gsp.id
                 LEFT JOIN ptero_nodes pn ON pn.id = gspn.node_id
                 LEFT JOIN game_server_orders gso ON gso.plan_id = gsp.id
                 WHERE (:search = '' OR gsp.name ILIKE :search_like OR gsp.slug ILIKE :search_like)
                   AND (:status = '' OR gsp.status = :status)
                 GROUP BY gsp.id ORDER BY gsp.sort_order, gsp.id"
            );
            $statement->execute(['search' => $search, 'search_like' => '%' . $search . '%', 'status' => $status]);
            $result['rows'] = $statement->fetchAll() ?: [];
        } elseif ($section === 'nodes') {
            $statement = $pdo->prepare(
                "SELECT pn.*,
                        COUNT(DISTINCT ps.id) FILTER (WHERE ps.deleted_at IS NULL) AS server_count,
                        COUNT(DISTINCT gspn.plan_id) AS plan_count
                 FROM ptero_nodes pn
                 LEFT JOIN ptero_servers ps ON ps.node_id = pn.id
                 LEFT JOIN game_server_plan_nodes gspn ON gspn.node_id = pn.id
                 WHERE (:search = '' OR pn.name ILIKE :search_like OR pn.label ILIKE :search_like OR pn.fqdn ILIKE :search_like)
                   AND (:status = '' OR pn.status = :status)
                 GROUP BY pn.id ORDER BY pn.sort_order, pn.id"
            );
            $statement->execute(['search' => $search, 'search_like' => '%' . $search . '%', 'status' => $status]);
            $result['rows'] = $statement->fetchAll() ?: [];
        }
    } catch (Throwable $exception) {
        $result['errors'][] = '管理データを取得できませんでした。DBマイグレーションの適用状況を確認してください。';
    }

    if ($selectedId <= 0 && in_array($section, ['contracts', 'approvals'], true) && $result['rows'] !== []) {
        $selectedId = (int) ($result['rows'][0]['id'] ?? 0);
    }
    if ($selectedId > 0 && in_array($section, ['contracts', 'approvals'], true)) {
        try {
            $statement = $pdo->prepare(
                'SELECT gso.*, u.username, u.email, gsp.name AS plan_name,
                        pn.label AS node_label, ps.ptero_identifier, ps.status AS server_status
                 FROM game_server_orders gso
                 LEFT JOIN users u ON u.id = gso.user_id
                 LEFT JOIN game_server_plans gsp ON gsp.id = gso.plan_id
                 LEFT JOIN ptero_nodes pn ON pn.id = gso.selected_node_id
                 LEFT JOIN ptero_servers ps ON ps.order_id = gso.id
                 WHERE gso.id = :id LIMIT 1'
            );
            $statement->execute(['id' => $selectedId]);
            $selected = $statement->fetch();
            $result['selected'] = is_array($selected) ? $selected : null;
            $statement = $pdo->prepare(
                'SELECT event_type, title, message, old_status, new_status,
                        old_payment_status, new_payment_status, created_at
                 FROM server_order_events WHERE order_id = :id
                 ORDER BY created_at DESC, id DESC LIMIT 100'
            );
            $statement->execute(['id' => $selectedId]);
            $result['events'] = $statement->fetchAll() ?: [];
        } catch (Throwable $exception) {
            $result['errors'][] = '選択した契約の詳細を取得できませんでした。';
        }
    }

    return $result;
}
