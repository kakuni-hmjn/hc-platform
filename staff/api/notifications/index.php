<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

/**
 * JSONレスポンスを返して終了する。
 *
 * @param array<string, mixed> $payload
 */
function notification_json(array $payload, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * bootstrap.phpからPDO接続を取得する。
 */
function notification_resolve_pdo(): ?PDO
{
    global $pdo, $db;

    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }

    if (isset($db) && $db instanceof PDO) {
        return $db;
    }

    foreach ([
        'db',
        'database',
        'get_db',
        'get_database',
        'staff_db',
        'app_db',
    ] as $functionName) {
        if (!function_exists($functionName)) {
            continue;
        }

        try {
            $connection = $functionName();

            if ($connection instanceof PDO) {
                return $connection;
            }
        } catch (Throwable) {
            // 次の候補を確認する
        }
    }

    return null;
}

/**
 * ログイン中スタッフのIDを取得する。
 */
function notification_resolve_staff_user_id(): ?int
{
    global $currentStaffUser, $staffUser, $currentUser;

    $candidates = [
        $currentStaffUser ?? null,
        $staffUser ?? null,
        $currentUser ?? null,
        $_SESSION['staff_user'] ?? null,
        $_SESSION['current_staff_user'] ?? null,
        $_SESSION['user'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        foreach (['id', 'staff_user_id', 'user_id'] as $key) {
            if (
                isset($candidate[$key])
                && filter_var($candidate[$key], FILTER_VALIDATE_INT) !== false
            ) {
                $id = (int) $candidate[$key];

                if ($id > 0) {
                    return $id;
                }
            }
        }
    }

    foreach ([
        $_SESSION['staff_user_id'] ?? null,
        $_SESSION['staff_id'] ?? null,
        $_SESSION['user_id'] ?? null,
    ] as $candidateId) {
        if (filter_var($candidateId, FILTER_VALIDATE_INT) !== false) {
            $id = (int) $candidateId;

            if ($id > 0) {
                return $id;
            }
        }
    }

    foreach ([
        'current_staff_user_id',
        'staff_user_id',
        'authenticated_staff_user_id',
    ] as $functionName) {
        if (!function_exists($functionName)) {
            continue;
        }

        try {
            $id = (int) $functionName();

            if ($id > 0) {
                return $id;
            }
        } catch (Throwable) {
            // 次の候補を確認する
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    notification_json([
        'ok' => false,
        'error' => 'method_not_allowed',
        'message' => 'GETメソッドのみ利用できます。',
    ], 405);
}

$pdo = notification_resolve_pdo();

if (!$pdo instanceof PDO) {
    notification_json([
        'ok' => false,
        'error' => 'database_connection_not_found',
        'message' => 'データベース接続を取得できませんでした。',
    ], 500);
}

$staffUserId = notification_resolve_staff_user_id();

if ($staffUserId === null) {
    notification_json([
        'ok' => false,
        'error' => 'unauthorized',
        'message' => 'ログイン中のスタッフを確認できませんでした。',
    ], 401);
}

$allowedCategories = [
    'all',
    'system',
    'order',
    'user',
    'discord',
    'github',
    'development',
];

$category = strtolower(trim((string) ($_GET['category'] ?? 'all')));

if (!in_array($category, $allowedCategories, true)) {
    notification_json([
        'ok' => false,
        'error' => 'invalid_category',
        'message' => '指定された通知カテゴリは利用できません。',
    ], 422);
}

$limit = filter_input(
    INPUT_GET,
    'limit',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'default' => 20,
            'min_range' => 1,
            'max_range' => 50,
        ],
    ]
);

if (!is_int($limit)) {
    $limit = 20;
}

try {
    $unreadStatement = $pdo->prepare(
        <<<'SQL'
        SELECT COUNT(*)
        FROM staff_notifications
        WHERE user_id = :user_id
          AND is_read = FALSE
        SQL
    );

    $unreadStatement->execute([
        ':user_id' => $staffUserId,
    ]);

    $unreadCount = (int) $unreadStatement->fetchColumn();

    $whereCategory = '';
    $parameters = [
        ':user_id' => $staffUserId,
    ];

    if ($category !== 'all') {
        $whereCategory = ' AND category = :category';
        $parameters[':category'] = $category;
    }

    $query = <<<SQL
        SELECT
            id,
            type,
            category,
            level,
            title,
            body,
            action_url,
            icon,
            source,
            metadata,
            is_read,
            read_at,
            created_at,
            updated_at
        FROM staff_notifications
        WHERE user_id = :user_id
        {$whereCategory}
        ORDER BY created_at DESC, id DESC
        LIMIT {$limit}
        SQL;

    $statement = $pdo->prepare($query);
    $statement->execute($parameters);

    $items = [];

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $metadata = $row['metadata'] ?? [];

        if (is_string($metadata)) {
            $decodedMetadata = json_decode($metadata, true);
            $metadata = is_array($decodedMetadata) ? $decodedMetadata : [];
        }

        $items[] = [
            'id' => (int) $row['id'],
            'type' => (string) $row['type'],
            'category' => (string) ($row['category'] ?? 'system'),
            'level' => (string) ($row['level'] ?? 'info'),
            'title' => (string) $row['title'],
            'message' => (string) ($row['body'] ?? ''),
            'url' => $row['action_url'] !== null
                ? (string) $row['action_url']
                : null,
            'icon' => $row['icon'] !== null
                ? (string) $row['icon']
                : null,
            'source' => (string) ($row['source'] ?? 'hc-platform'),
            'metadata' => $metadata,
            'is_read' => (bool) $row['is_read'],
            'read_at' => $row['read_at'],
            'created_at' => $row['created_at'],
        ];
    }

    notification_json([
        'ok' => true,
        'unread' => $unreadCount,
        'category' => $category,
        'items' => $items,
    ]);
} catch (Throwable $exception) {
    $notificationLogDirectory = dirname(__DIR__, 3)
        . '/storage/logs';

    if (!is_dir($notificationLogDirectory)) {
        @mkdir(
            $notificationLogDirectory,
            0775,
            true
        );
    }

    $notificationLogMessage = sprintf(
        "[%s] %s\n%s: %s\nFile: %s:%d\nTrace:\n%s\n\n",
        date('c'),
        __FILE__,
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    @file_put_contents(
        $notificationLogDirectory
            . '/notification-api-error.log',
        $notificationLogMessage,
        FILE_APPEND | LOCK_EX
    );

    error_log(
        '[staff notifications API] '
        . $exception->getMessage()
    );

    notification_json([
        'ok' => false,
        'error' => 'notification_fetch_failed',
        'message' => '通知の取得に失敗しました。',
    ], 500);
}
