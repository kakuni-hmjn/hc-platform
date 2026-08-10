<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function staff_system_env_enabled(string $key): bool
{
    $value = strtolower(trim((string) (getenv($key) ?: '')));
    return in_array($value, ['1', 'true', 'yes', 'on', 'enabled'], true);
}

function staff_system_environment(): string
{
    $value = strtolower(trim((string) (getenv('APP_ENV') ?: 'development')));
    return match ($value) {
        'prod', 'production' => '本番環境',
        'staging', 'stage' => 'ステージング環境',
        'test', 'testing' => 'テスト環境',
        default => '開発環境',
    };
}

function staff_system_revision(): string
{
    $root = dirname(__DIR__, 2);
    $headPath = $root . '/.git/HEAD';
    if (!is_file($headPath) || !is_readable($headPath)) {
        return '取得不可';
    }
    $head = trim((string) file_get_contents($headPath));
    if (str_starts_with($head, 'ref: ')) {
        $ref = trim(substr($head, 5));
        $refPath = $root . '/.git/' . $ref;
        if (is_file($refPath) && is_readable($refPath)) {
            $head = trim((string) file_get_contents($refPath));
        }
    }
    return preg_match('/^[a-f0-9]{7,40}$/i', $head) === 1 ? substr($head, 0, 8) : '取得不可';
}

function staff_system_health(): array
{
    $root = dirname(__DIR__, 2);
    $checks = [
        'database' => ['label' => 'データベース', 'ok' => false, 'detail' => '未確認'],
        'storage' => ['label' => 'ログ保存先', 'ok' => false, 'detail' => '未確認'],
        'stripe' => ['label' => 'Stripe', 'ok' => false, 'detail' => '設定なし'],
        'pterodactyl' => ['label' => 'Pterodactyl', 'ok' => false, 'detail' => '設定なし'],
        'mail' => ['label' => 'メール送信', 'ok' => false, 'detail' => '設定なし'],
    ];
    try {
        staff_db()->query('SELECT 1')->fetchColumn();
        $checks['database'] = ['label' => 'データベース', 'ok' => true, 'detail' => '接続正常'];
    } catch (Throwable $exception) {
        $checks['database']['detail'] = '接続エラー';
    }
    $storagePath = $root . '/storage/logs';
    $checks['storage'] = [
        'label' => 'ログ保存先',
        'ok' => is_dir($storagePath) && is_writable($storagePath),
        'detail' => is_dir($storagePath) && is_writable($storagePath) ? '書き込み可能' : '要確認',
    ];
    $stripeConfigured = trim((string) (getenv('STRIPE_SECRET_KEY') ?: '')) !== '';
    $checks['stripe'] = ['label' => 'Stripe', 'ok' => $stripeConfigured, 'detail' => $stripeConfigured ? '接続情報あり' : '接続情報なし'];
    $pteroConfigured = staff_system_env_enabled('PTERO_ENABLED') || staff_system_env_enabled('PTERO_MOCK');
    $checks['pterodactyl'] = ['label' => 'Pterodactyl', 'ok' => $pteroConfigured, 'detail' => staff_system_env_enabled('PTERO_MOCK') ? 'モック接続' : ($pteroConfigured ? '有効' : '無効')];
    $mailConfigured = trim((string) (getenv('SMTP_HOST') ?: getenv('MAIL_HOST') ?: '')) !== ''
        || staff_system_env_enabled('MAIL_ENABLED');
    $checks['mail'] = ['label' => 'メール送信', 'ok' => $mailConfigured, 'detail' => $mailConfigured ? '送信設定あり' : '送信設定なし'];
    return $checks;
}

function staff_development_overview_load(): array
{
    $result = [
        'counts' => ['services' => 0, 'published' => 0, 'planned' => 0, 'news' => 0],
        'services' => [],
        'errors' => [],
    ];
    try {
        $row = staff_db()->query(
            "SELECT COUNT(*) AS services,
                    COUNT(*) FILTER (WHERE status = 'published') AS published,
                    COUNT(*) FILTER (WHERE service_phase = 'planned') AS planned
             FROM services"
        )->fetch();
        if (is_array($row)) {
            $result['counts']['services'] = (int) ($row['services'] ?? 0);
            $result['counts']['published'] = (int) ($row['published'] ?? 0);
            $result['counts']['planned'] = (int) ($row['planned'] ?? 0);
        }
        $result['counts']['news'] = (int) (staff_db()->query("SELECT COUNT(*) FROM news WHERE status = 'published'")->fetchColumn() ?: 0);
        $result['services'] = staff_db()->query(
            'SELECT id, title, slug, label, summary, service_phase, has_detail_page, detail_url, status, updated_at, created_at
             FROM services ORDER BY sort_order, id'
        )->fetchAll() ?: [];
    } catch (Throwable $exception) {
        $result['errors'][] = 'プロジェクト情報を取得できませんでした。';
    }
    return $result;
}

function staff_infrastructure_load(string $search = '', string $status = '', int $selectedId = 0): array
{
    $result = [
        'counts' => ['nodes' => 0, 'active_nodes' => 0, 'servers' => 0, 'active_servers' => 0, 'memory_mb' => 0, 'disk_mb' => 0],
        'nodes' => [], 'servers' => [], 'selected' => null, 'errors' => [],
    ];
    try {
        $row = staff_db()->query(
            "SELECT COUNT(*) AS nodes,
                    COUNT(*) FILTER (WHERE status = 'active') AS active_nodes,
                    COALESCE(SUM(memory_mb), 0) AS memory_mb,
                    COALESCE(SUM(disk_mb), 0) AS disk_mb
             FROM ptero_nodes"
        )->fetch();
        if (is_array($row)) {
            foreach (['nodes', 'active_nodes', 'memory_mb', 'disk_mb'] as $key) {
                $result['counts'][$key] = (int) ($row[$key] ?? 0);
            }
        }
        $row = staff_db()->query(
            "SELECT COUNT(*) AS servers, COUNT(*) FILTER (WHERE status = 'active') AS active_servers
             FROM ptero_servers WHERE deleted_at IS NULL"
        )->fetch();
        if (is_array($row)) {
            $result['counts']['servers'] = (int) ($row['servers'] ?? 0);
            $result['counts']['active_servers'] = (int) ($row['active_servers'] ?? 0);
        }
        $result['nodes'] = staff_db()->query(
            "SELECT pn.*, COUNT(ps.id) FILTER (WHERE ps.deleted_at IS NULL) AS server_count
             FROM ptero_nodes pn LEFT JOIN ptero_servers ps ON ps.node_id = pn.id
             GROUP BY pn.id ORDER BY pn.sort_order, pn.id"
        )->fetchAll() ?: [];

        $where = ['ps.deleted_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $where[] = '(ps.name ILIKE :search OR ps.ptero_identifier ILIKE :search OR u.username ILIKE :search OR u.email ILIKE :search OR pn.label ILIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== '') {
            $where[] = 'ps.status = :status';
            $params['status'] = $status;
        }
        $statement = staff_db()->prepare(
            'SELECT ps.*, u.username, u.email, pn.label AS node_label, pn.fqdn,
                    gsp.name AS plan_name, gsp.memory_mb, gsp.cpu_limit, gsp.disk_mb
             FROM ptero_servers ps
             LEFT JOIN users u ON u.id = ps.user_id
             LEFT JOIN ptero_nodes pn ON pn.id = ps.node_id
             LEFT JOIN game_server_plans gsp ON gsp.id = ps.plan_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ps.created_at DESC, ps.id DESC LIMIT 200'
        );
        $statement->execute($params);
        $result['servers'] = $statement->fetchAll() ?: [];
        if ($selectedId <= 0 && $result['servers'] !== []) {
            $selectedId = (int) ($result['servers'][0]['id'] ?? 0);
        }
        foreach ($result['servers'] as $server) {
            if ((int) ($server['id'] ?? 0) === $selectedId) {
                $result['selected'] = $server;
                break;
            }
        }
    } catch (Throwable $exception) {
        $result['errors'][] = 'インフラ情報を取得できませんでした。';
    }
    return $result;
}
