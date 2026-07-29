<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../../lib/bootstrap.php';

require_once __DIR__
    . '/../../lib/data.php';

require_once __DIR__
    . '/../../lib/id-generator.php';

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
            '送信内容を読み取れませんでした。',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$string = static function (
    mixed $value,
    int $maximum = 1000
): string {
    return mb_substr(
        trim((string) $value),
        0,
        $maximum
    );
};

$nullable = static function (
    mixed $value,
    int $maximum = 1000
) use ($string): ?string {
    $value = $string(
        $value,
        $maximum
    );

    return $value === ''
        ? null
        : $value;
};

$categoryKey = $string(
    $input['category'] ?? '',
    50
);

$name = $string(
    $input['name'] ?? '',
    255
);

$countryCode = hpmc_code(
    $input['country_code'] ?? 'JP',
    3
);

$prefectureCode = hpmc_code(
    $input['prefecture_code'] ?? '',
    10
);

$siteCode = hpmc_code(
    $input['site_code'] ?? '',
    30
);

if (
    $categoryKey === ''
    || $name === ''
    || $countryCode === ''
    || $prefectureCode === ''
    || $siteCode === ''
) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' =>
            '必須項目を入力してください。',
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$pdo = hpmc_db();

try {
    $pdo->beginTransaction();

    $categoryStatement = $pdo->prepare(
        <<<'SQL'
        SELECT
            id,
            category_code
        FROM hpmc_categories
        WHERE category_key = :category_key
          AND is_active = TRUE
        LIMIT 1
        SQL
    );

    $categoryStatement->execute([
        'category_key' => $categoryKey,
    ]);

    $category = $categoryStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!is_array($category)) {
        throw new RuntimeException(
            '選択した分類は利用できません。'
        );
    }

    $managementId =
        hpmc_generate_management_id(
            $pdo,
            (string) $category['category_code'],
            $countryCode,
            $prefectureCode,
            $siteCode
        );

    $commonKeys = [
        'category',
        'name',
        'status',
        'manufacturer',
        'model',
        'serial_number',
        'barcode',
        'sku',
        'quantity',
        'unit',
        'purchase_price',
        'selling_price',
        'country_code',
        'prefecture_code',
        'site_code',
        'building',
        'floor',
        'room',
        'rack_code',
        'placement_rack_code',
        'shelf_code',
        'start_u',
        'height_u',
        'hostname',
        'management_ip',
        'management_vlan',
        'mac_address',
        'purchase_date',
        'warranty_expiry',
        'supplier',
        'notes',
    ];

    $specifications = array_diff_key(
        $input,
        array_flip($commonKeys)
    );

    $statement = $pdo->prepare(
        <<<'SQL'
        INSERT INTO hpmc_assets (
            management_id,
            category_id,
            name,
            status,

            manufacturer,
            model,
            serial_number,
            barcode,
            sku,

            quantity,
            unit,
            purchase_price,
            selling_price,

            country_code,
            prefecture_code,
            site_code,

            building,
            floor,
            room,
            rack_code,
            shelf_code,
            start_u,
            height_u,

            hostname,
            management_ip,
            management_vlan,
            mac_address,

            purchase_date,
            warranty_expiry,
            supplier,

            specifications,
            notes,

            created_at,
            updated_at
        )
        VALUES (
            :management_id,
            :category_id,
            :name,
            :status,

            :manufacturer,
            :model,
            :serial_number,
            :barcode,
            :sku,

            :quantity,
            :unit,
            :purchase_price,
            :selling_price,

            :country_code,
            :prefecture_code,
            :site_code,

            :building,
            :floor,
            :room,
            :rack_code,
            :shelf_code,
            :start_u,
            :height_u,

            :hostname,
            CAST(NULLIF(:management_ip, '') AS INET),
            CAST(NULLIF(:management_vlan, '') AS INTEGER),
            CAST(NULLIF(:mac_address, '') AS MACADDR),

            CAST(NULLIF(:purchase_date, '') AS DATE),
            CAST(NULLIF(:warranty_expiry, '') AS DATE),
            :supplier,

            CAST(:specifications AS JSONB),
            :notes,

            NOW(),
            NOW()
        )
        RETURNING id
        SQL
    );

    $statement->execute([
        'management_id' => $managementId,
        'category_id' =>
            (int) $category['id'],

        'name' => $name,
        'status' => $string(
            $input['status'] ?? 'active',
            30
        ),

        'manufacturer' =>
            $nullable(
                $input['manufacturer'] ?? null,
                150
            ),

        'model' =>
            $nullable(
                $input['model'] ?? null,
                200
            ),

        'serial_number' =>
            $nullable(
                $input['serial_number'] ?? null,
                200
            ),

        'barcode' =>
            $nullable(
                $input['barcode'] ?? null,
                200
            ),

        'sku' =>
            $nullable(
                $input['sku'] ?? null,
                150
            ),

        'quantity' => max(
            0,
            (int) ($input['quantity'] ?? 1)
        ),

        'unit' => $string(
            $input['unit'] ?? '個',
            30
        ),

        'purchase_price' => max(
            0,
            (float) (
                $input['purchase_price']
                ?? 0
            )
        ),

        'selling_price' => max(
            0,
            (float) (
                $input['selling_price']
                ?? 0
            )
        ),

        'country_code' =>
            $countryCode,

        'prefecture_code' =>
            $prefectureCode,

        'site_code' =>
            $siteCode,

        'building' =>
            $nullable(
                $input['building'] ?? null,
                150
            ),

        'floor' =>
            $nullable(
                $input['floor'] ?? null,
                50
            ),

        'room' =>
            $nullable(
                $input['room'] ?? null,
                150
            ),

        'rack_code' =>
            $nullable(
                $input['rack_code']
                ?? $input['placement_rack_code']
                ?? null,
                100
            ),

        'shelf_code' =>
            $nullable(
                $input['shelf_code'] ?? null,
                100
            ),

        'start_u' => (
            isset($input['start_u'])
            && $input['start_u'] !== ''
                ? max(
                    0,
                    (int) $input['start_u']
                )
                : null
        ),

        'height_u' => (
            isset($input['height_u'])
            && $input['height_u'] !== ''
                ? max(
                    0,
                    (int) $input['height_u']
                )
                : null
        ),

        'hostname' =>
            $nullable(
                $input['hostname'] ?? null,
                255
            ),

        'management_ip' => $string(
            $input['management_ip'] ?? '',
            100
        ),

        'management_vlan' => $string(
            $input['management_vlan'] ?? '',
            20
        ),

        'mac_address' => $string(
            $input['mac_address'] ?? '',
            50
        ),

        'purchase_date' => $string(
            $input['purchase_date'] ?? '',
            20
        ),

        'warranty_expiry' => $string(
            $input['warranty_expiry'] ?? '',
            20
        ),

        'supplier' =>
            $nullable(
                $input['supplier'] ?? null,
                200
            ),

        'specifications' => json_encode(
            $specifications,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ),

        'notes' =>
            $nullable(
                $input['notes'] ?? null,
                5000
            ),
    ]);

    $assetId = (int) $statement->fetchColumn();

    $pdo->commit();

    echo json_encode(
        [
            'success' => true,
            'message' => '登録しました。',

            'asset' => [
                'id' => $assetId,
                'management_id' =>
                    $managementId,
                'category' =>
                    $categoryKey,
                'name' =>
                    $name,
            ],

            'detail_url' =>
                '/staff/property/detail/?id='
                . rawurlencode($managementId),

            'qr_url' =>
                '/staff/property/qr-issue/?id='
                . rawurlencode($managementId),
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        '[HPMC create] '
        . $exception->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' =>
                '登録処理中にエラーが発生しました。',
        ],
        JSON_UNESCAPED_UNICODE
    );
}
