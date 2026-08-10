<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function staff_ops_like(string $value): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($value)) . '%';
}

function staff_ops_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y/m/d H:i');
    } catch (Throwable $exception) {
        return $value;
    }
}

function staff_ops_user_status_label(string $status): string
{
    return match ($status) {
        'active' => '有効',
        'inactive' => '無効',
        'suspended' => '停止中',
        'temporary' => '一時スタッフ',
        'external' => '外部スタッフ',
        default => $status !== '' ? $status : '未設定',
    };
}

function staff_ops_work_status_label(string $status): string
{
    return match ($status) {
        'online' => 'オンライン',
        'working' => '勤務中',
        'busy' => '取り込み中',
        'away' => '離席中',
        'break' => '休憩中',
        'offline' => 'オフライン',
        default => $status !== '' ? $status : '未設定',
    };
}

function staff_ops_audit(
    int $actorStaffId,
    string $action,
    string $targetType,
    int|string $targetId,
    string $description,
    ?array $oldData = null,
    ?array $newData = null
): void {
    try {
        $statement = staff_db()->prepare(
            'INSERT INTO staff_audit_logs (
                actor_staff_id, action, target_type, target_id,
                description, old_data, new_data, ip_address,
                user_agent, source, result
             ) VALUES (
                :actor_staff_id, :action, :target_type, :target_id,
                :description, CAST(:old_data AS JSONB), CAST(:new_data AS JSONB),
                :ip_address, :user_agent, \'web\', \'success\'
             )'
        );
        $statement->execute([
            'actor_staff_id' => $actorStaffId > 0 ? $actorStaffId : null,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => (string) $targetId,
            'description' => $description,
            'old_data' => $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE),
            'new_data' => $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
        ]);
    } catch (Throwable $exception) {
        // 監査テーブルが未適用の開発環境でも本処理は継続する。
    }
}

function staff_customers_load(string $search = '', string $status = '', int $selectedId = 0): array
{
    $pdo = staff_db();
    $result = [
        'counts' => ['total' => 0, 'active' => 0, 'suspended' => 0, 'with_contracts' => 0],
        'customers' => [],
        'selected' => null,
        'orders' => [],
        'contacts' => [],
        'errors' => [],
    ];

    try {
        $row = $pdo->query(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'active' AND deleted_at IS NULL) AS active,
                    COUNT(*) FILTER (WHERE status = 'suspended') AS suspended,
                    (SELECT COUNT(DISTINCT user_id) FROM game_server_orders) AS with_contracts
             FROM users"
        )->fetch();
        if (is_array($row)) {
            $result['counts']['total'] = (int) ($row['total'] ?? 0);
            $result['counts']['active'] = (int) ($row['active'] ?? 0);
            $result['counts']['suspended'] = (int) ($row['suspended'] ?? 0);
            $result['counts']['with_contracts'] = (int) ($row['with_contracts'] ?? 0);
        }
    } catch (Throwable $exception) {
        try {
            $row = $pdo->query(
                "SELECT COUNT(*) AS total,
                        COUNT(*) FILTER (WHERE status = 'active' AND deleted_at IS NULL) AS active,
                        COUNT(*) FILTER (WHERE status = 'suspended') AS suspended
                 FROM users"
            )->fetch();
            if (is_array($row)) {
                $result['counts']['total'] = (int) ($row['total'] ?? 0);
                $result['counts']['active'] = (int) ($row['active'] ?? 0);
                $result['counts']['suspended'] = (int) ($row['suspended'] ?? 0);
            }
        } catch (Throwable $innerException) {
            $result['errors'][] = '顧客集計を取得できませんでした。';
        }
    }

    try {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.username ILIKE :search OR u.email ILIKE :search OR CAST(u.id AS TEXT) = :exact_id)';
            $params['search'] = '%' . $search . '%';
            $params['exact_id'] = ctype_digit($search) ? $search : '0';
        }
        if ($status !== '' && in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }

        $statement = $pdo->prepare(
            'SELECT u.id, u.username, u.email, u.status, u.email_verified,
                    u.last_login, u.created_at,
                    COUNT(DISTINCT gso.id) AS contract_count,
                    COUNT(DISTINCT gso.id) FILTER (WHERE gso.status = \'active\') AS active_contract_count,
                    COUNT(DISTINCT c.id) AS contact_count
             FROM users u
             LEFT JOIN game_server_orders gso ON gso.user_id = u.id
             LEFT JOIN contacts c ON c.user_id = u.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY u.id
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT 100'
        );
        $statement->execute($params);
        $result['customers'] = $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        $result['errors'][] = '顧客一覧を取得できませんでした。';
    }

    if ($selectedId <= 0 && $result['customers'] !== []) {
        $selectedId = (int) ($result['customers'][0]['id'] ?? 0);
    }

    if ($selectedId > 0) {
        try {
            $statement = $pdo->prepare(
                'SELECT id, username, email, role, status, email_verified,
                        email_verified_at, last_login, created_at
                 FROM users WHERE id = :id LIMIT 1'
            );
            $statement->execute(['id' => $selectedId]);
            $selected = $statement->fetch();
            $result['selected'] = is_array($selected) ? $selected : null;

            $statement = $pdo->prepare(
                'SELECT gso.id, gso.server_name, gso.status, gso.payment_status,
                        gso.amount, gso.currency, gso.billing_period,
                        gso.created_at, gso.next_payment_due_at, gsp.name AS plan_name
                 FROM game_server_orders gso
                 LEFT JOIN game_server_plans gsp ON gsp.id = gso.plan_id
                 WHERE gso.user_id = :id
                 ORDER BY gso.created_at DESC, gso.id DESC LIMIT 50'
            );
            $statement->execute(['id' => $selectedId]);
            $result['orders'] = $statement->fetchAll() ?: [];

            $statement = $pdo->prepare(
                'SELECT id, subject, status, created_at, updated_at
                 FROM contacts WHERE user_id = :id
                 ORDER BY created_at DESC, id DESC LIMIT 30'
            );
            $statement->execute(['id' => $selectedId]);
            $result['contacts'] = $statement->fetchAll() ?: [];
        } catch (Throwable $exception) {
            $result['errors'][] = '選択した顧客の詳細を取得できませんでした。';
        }
    }

    return $result;
}

function staff_billing_load(string $search = '', string $paymentStatus = '', int $selectedId = 0): array
{
    $pdo = staff_db();
    $result = [
        'counts' => ['paid_total' => 0, 'paid_orders' => 0, 'unpaid_orders' => 0, 'failed_orders' => 0, 'refunded_total' => 0],
        'orders' => [], 'selected' => null, 'events' => [], 'errors' => [],
    ];

    try {
        $row = $pdo->query(
            "SELECT COALESCE(SUM(amount) FILTER (WHERE payment_status = 'paid'), 0) AS paid_total,
                    COUNT(*) FILTER (WHERE payment_status = 'paid') AS paid_orders,
                    COUNT(*) FILTER (WHERE payment_status IN ('unpaid', 'checkout_created')) AS unpaid_orders,
                    COUNT(*) FILTER (WHERE payment_status = 'failed') AS failed_orders
             FROM game_server_orders"
        )->fetch();
        if (is_array($row)) {
            foreach (['paid_total', 'paid_orders', 'unpaid_orders', 'failed_orders'] as $key) {
                $result['counts'][$key] = (int) ($row[$key] ?? 0);
            }
        }
        $result['counts']['refunded_total'] = (int) ($pdo->query(
            "SELECT COALESCE(SUM(ABS(amount)), 0) FROM payment_events
             WHERE payment_status = 'refunded' OR event_type ILIKE '%refund%'"
        )->fetchColumn() ?: 0);
    } catch (Throwable $exception) {
        $result['errors'][] = '決済集計を取得できませんでした。';
    }

    try {
        $where = ['1 = 1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.username ILIKE :search OR u.email ILIKE :search OR gso.server_name ILIKE :search OR CAST(gso.id AS TEXT) = :exact_id)';
            $params['search'] = '%' . $search . '%';
            $params['exact_id'] = ctype_digit($search) ? $search : '0';
        }
        if ($paymentStatus !== '' && in_array($paymentStatus, ['unpaid', 'checkout_created', 'paid', 'failed', 'refunded', 'cancelled'], true)) {
            $where[] = 'gso.payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }
        $statement = $pdo->prepare(
            'SELECT gso.id, gso.server_name, gso.status, gso.payment_status,
                    gso.amount, gso.currency, gso.billing_period, gso.paid_at,
                    gso.next_payment_due_at, gso.created_at,
                    u.id AS user_id, u.username, u.email, gsp.name AS plan_name
             FROM game_server_orders gso
             LEFT JOIN users u ON u.id = gso.user_id
             LEFT JOIN game_server_plans gsp ON gsp.id = gso.plan_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY gso.created_at DESC, gso.id DESC LIMIT 100'
        );
        $statement->execute($params);
        $result['orders'] = $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        $result['errors'][] = '請求一覧を取得できませんでした。';
    }

    if ($selectedId <= 0 && $result['orders'] !== []) {
        $selectedId = (int) ($result['orders'][0]['id'] ?? 0);
    }
    if ($selectedId > 0) {
        try {
            $statement = $pdo->prepare(
                'SELECT gso.*, u.username, u.email, gsp.name AS plan_name
                 FROM game_server_orders gso
                 LEFT JOIN users u ON u.id = gso.user_id
                 LEFT JOIN game_server_plans gsp ON gsp.id = gso.plan_id
                 WHERE gso.id = :id LIMIT 1'
            );
            $statement->execute(['id' => $selectedId]);
            $selected = $statement->fetch();
            $result['selected'] = is_array($selected) ? $selected : null;

            $statement = $pdo->prepare(
                'SELECT id, event_type, payment_status, amount, currency,
                        provider, provider_object_id, message, created_at
                 FROM payment_events WHERE order_id = :id
                 ORDER BY created_at DESC, id DESC LIMIT 100'
            );
            $statement->execute(['id' => $selectedId]);
            $result['events'] = $statement->fetchAll() ?: [];
        } catch (Throwable $exception) {
            $result['errors'][] = '選択した請求の詳細を取得できませんでした。';
        }
    }

    return $result;
}

function staff_users_load(string $search = '', string $status = '', int $selectedId = 0): array
{
    $pdo = staff_db();
    $result = [
        'counts' => ['total' => 0, 'active' => 0, 'working' => 0, 'suspended' => 0],
        'users' => [], 'selected' => null, 'roles' => [], 'departments' => [], 'categories' => [], 'available_roles' => [], 'errors' => [],
    ];
    try {
        $row = $pdo->query(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE status = 'active') AS active,
                    COUNT(*) FILTER (WHERE work_status IN ('online', 'working', 'busy')) AS working,
                    COUNT(*) FILTER (WHERE status = 'suspended') AS suspended
             FROM staff_users"
        )->fetch();
        if (is_array($row)) {
            foreach (['total', 'active', 'working', 'suspended'] as $key) {
                $result['counts'][$key] = (int) ($row[$key] ?? 0);
            }
        }

        $where = ['1 = 1'];
        $params = [];
        if ($search !== '') {
            $where[] = "(COALESCE(su.display_name, u.username) ILIKE :search OR u.email ILIKE :search OR COALESCE(su.employee_code, '') ILIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== '' && in_array($status, ['active', 'inactive', 'suspended', 'temporary', 'external'], true)) {
            $where[] = 'su.status = :status';
            $params['status'] = $status;
        }
        $statement = $pdo->prepare(
            "SELECT su.id, su.account_id, su.employee_code, su.display_name,
                    su.status, su.work_status, su.last_seen_at, su.created_at,
                    u.username, u.email,
                    COALESCE(STRING_AGG(DISTINCT ar.name, ', '), '') AS role_names
             FROM staff_users su
             INNER JOIN users u ON u.id = su.account_id
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN account_roles ar ON ar.id = ur.role_id AND ar.is_staff_role = TRUE
             WHERE " . implode(' AND ', $where) . "
             GROUP BY su.id, u.id
             ORDER BY COALESCE(su.display_name, u.username), su.id
             LIMIT 100"
        );
        $statement->execute($params);
        $result['users'] = $statement->fetchAll() ?: [];

        $result['available_roles'] = $pdo->query(
            'SELECT id, name, slug, description, priority, color, icon FROM account_roles
             WHERE is_staff_role = TRUE ORDER BY priority DESC, name'
        )->fetchAll() ?: [];
    } catch (Throwable $exception) {
        $result['errors'][] = 'スタッフ一覧を取得できませんでした。';
    }

    if ($selectedId <= 0 && $result['users'] !== []) {
        $selectedId = (int) ($result['users'][0]['id'] ?? 0);
    }
    if ($selectedId > 0) {
        try {
            $statement = $pdo->prepare(
                'SELECT su.*, u.username, u.email, u.last_login
                 FROM staff_users su INNER JOIN users u ON u.id = su.account_id
                 WHERE su.id = :id LIMIT 1'
            );
            $statement->execute(['id' => $selectedId]);
            $selected = $statement->fetch();
            $result['selected'] = is_array($selected) ? $selected : null;
            if ($result['selected'] !== null) {
                $accountId = (int) $result['selected']['account_id'];
                $statement = $pdo->prepare(
                    'SELECT ar.id, ar.name, ar.slug, ar.description, ar.color, ar.icon
                     FROM user_roles ur INNER JOIN account_roles ar ON ar.id = ur.role_id
                     WHERE ur.user_id = :id AND ar.is_staff_role = TRUE
                     ORDER BY ar.priority DESC, ar.name'
                );
                $statement->execute(['id' => $accountId]);
                $result['roles'] = $statement->fetchAll() ?: [];
            }
            $statement = $pdo->prepare(
                'SELECT sd.name, sud.is_primary
                 FROM staff_user_departments sud INNER JOIN staff_departments sd ON sd.id = sud.department_id
                 WHERE sud.user_id = :id ORDER BY sud.is_primary DESC, sd.sort_order, sd.name'
            );
            $statement->execute(['id' => $selectedId]);
            $result['departments'] = $statement->fetchAll() ?: [];
            $statement = $pdo->prepare(
                'SELECT sc.name FROM staff_user_categories suc
                 INNER JOIN staff_categories sc ON sc.id = suc.category_id
                 WHERE suc.user_id = :id ORDER BY sc.sort_order, sc.name'
            );
            $statement->execute(['id' => $selectedId]);
            $result['categories'] = $statement->fetchAll() ?: [];
        } catch (Throwable $exception) {
            $result['errors'][] = '選択したスタッフの詳細を取得できませんでした。';
        }
    }
    return $result;
}
