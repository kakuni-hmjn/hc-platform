<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

function notification_json(array $payload, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

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
        }
    }

    return null;
}

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

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    notification_json([
        'ok' => false,
        'error' => 'method_not_allowed',
        'message' => 'POSTメソッドのみ利用できます。',
    ], 405);
}

$pdo = notification_resolve_pdo();
$staffUserId = notification_resolve_staff_user_id();

if (!$pdo instanceof PDO) {
    notification_json([
        'ok' => false,
        'error' => 'database_connection_not_found',
        'message' => 'データベース接続を取得できませんでした。',
    ], 500);
}

if ($staffUserId === null) {
    notification_json([
        'ok' => false,
        'error' => 'unauthorized',
        'message' => 'ログイン中のスタッフを確認できませんでした。',
    ], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$notificationId = $payload['id'] ?? null;

if (filter_var($notificationId, FILTER_VALIDATE_INT) === false) {
    notification_json([
        'ok' => false,
        'error' => 'invalid_notification_id',
        'message' => '通知IDが正しくありません。',
    ], 422);
}

$notificationId = (int) $notificationId;

try {
    $statement = $pdo->prepare(
        <<<'SQL'
        UPDATE staff_notifications
        SET
            is_read = TRUE,
            read_at = COALESCE(read_at, CURRENT_TIMESTAMP),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND user_id = :user_id
        RETURNING id
        SQL
    );

    $statement->execute([
        ':id' => $notificationId,
        ':user_id' => $staffUserId,
    ]);

    $updatedId = $statement->fetchColumn();

    if ($updatedId === false) {
        notification_json([
            'ok' => false,
            'error' => 'notification_not_found',
            'message' => '対象の通知が見つかりませんでした。',
        ], 404);
    }

    notification_json([
        'ok' => true,
        'id' => (int) $updatedId,
        'is_read' => true,
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
        '[staff notifications read API] '
        . $exception->getMessage()
    );

    notification_json([
        'ok' => false,
        'error' => 'notification_update_failed',
        'message' => '通知の既読処理に失敗しました。',
    ], 500);
}
