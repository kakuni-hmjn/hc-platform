<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../../lib/bootstrap.php';

require_once __DIR__
    . '/../../lib/data.php';

header(
    'Content-Type: application/json; charset=UTF-8'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);

try {
    $assets = hpmc_load_assets();

    echo json_encode(
        [
            'success' => true,
            'count' => count($assets),
            'assets' => $assets,
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Throwable $exception) {
    error_log(
        '[HPMC list] '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' =>
                '物品情報を取得できませんでした。',
        ],
        JSON_UNESCAPED_UNICODE
    );
}
