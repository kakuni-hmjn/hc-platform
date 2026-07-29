<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../../lib/bootstrap.php';

require_once __DIR__
    . '/../../lib/data.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' =>
            'POSTメソッドのみ利用できます。',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$input = json_decode(
    (string) file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' =>
            '設定内容を読み取れません。',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$code = static function (
    mixed $value,
    string $default
): string {
    $value = strtoupper(
        trim((string) $value)
    );

    $value = preg_replace(
        '/[^A-Z0-9]/',
        '',
        $value
    ) ?? '';

    return $value === ''
        ? $default
        : $value;
};

$settings = [
    'low_stock_threshold' => max(
        0,
        min(
            9999,
            (int) (
                $input['low_stock_threshold']
                ?? 3
            )
        )
    ),

    'default_country_code' =>
        $code(
            $input['default_country_code']
            ?? 'JP',
            'JP'
        ),

    'default_prefecture_code' =>
        $code(
            $input['default_prefecture_code']
            ?? 'NGN',
            'NGN'
        ),

    'default_site_code' =>
        $code(
            $input['default_site_code']
            ?? 'HCDC01',
            'HCDC01'
        ),

    'id_prefix' =>
        $code(
            $input['id_prefix']
            ?? 'HC',
            'HC'
        ),

    'id_sequence_digits' => max(
        4,
        min(
            10,
            (int) (
                $input['id_sequence_digits']
                ?? 6
            )
        )
    ),

    'default_label_size' =>
        (string) (
            $input['default_label_size']
            ?? '62x29'
        ),

    'default_label_layout' =>
        (string) (
            $input['default_label_layout']
            ?? 'visual_right'
        ),
];

try {
    hpmc_save_settings($settings);

    echo json_encode(
        [
            'success' => true,
            'message' => '設定を保存しました。',
            'settings' => $settings,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    error_log(
        '[HPMC settings] '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' =>
                '設定を保存できませんでした。',
        ],
        JSON_UNESCAPED_UNICODE
    );
}
