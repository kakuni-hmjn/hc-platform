<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'ok' => false,
        'message' => 'POSTリクエストのみ利用できます。',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

try {
    $input = json_decode(
        file_get_contents('php://input') ?: '',
        true
    );

    if (!is_array($input)) {
        $input = $_POST;
    }

    $status = trim(
        (string) ($input['status'] ?? '')
    );

    $allowedStatuses = [
        'online',
        'working',
        'busy',
        'away',
        'break',
        'offline',
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        http_response_code(422);

        echo json_encode([
            'ok' => false,
            'message' => '選択された状態は利用できません。',
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $staffUserId = (int) (
        $staffContext['user']['id']
        ?? 0
    );

    if ($staffUserId <= 0) {
        throw new RuntimeException(
            'スタッフ情報を確認できませんでした。'
        );
    }

    $pdo = staff_db();

    $statement = $pdo->prepare(
        'UPDATE staff_users
         SET
             work_status = :work_status,
             last_seen_at = CURRENT_TIMESTAMP,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :user_id
         RETURNING work_status'
    );

    $statement->execute([
        'work_status' => $status,
        'user_id' => $staffUserId,
    ]);

    $updatedStatus = $statement->fetchColumn();

    if (!is_string($updatedStatus)) {
        throw new RuntimeException(
            '勤務状態を更新できませんでした。'
        );
    }

    $labels = [
        'online' => 'オンライン',
        'working' => '作業中',
        'busy' => '対応中',
        'away' => '離席中',
        'break' => '休憩中',
        'offline' => 'オフライン',
    ];

    echo json_encode([
        'ok' => true,
        'status' => $updatedStatus,
        'label' => $labels[$updatedStatus],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => '勤務状態の更新に失敗しました。',
    ], JSON_UNESCAPED_UNICODE);
}
