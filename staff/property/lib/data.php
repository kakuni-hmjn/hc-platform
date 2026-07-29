<?php

declare(strict_types=1);

function hpmc_db(): PDO
{
    if (!function_exists('db')) {
        throw new RuntimeException(
            'DB接続が初期化されていません。'
        );
    }

    return db();
}

function hpmc_escape(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function hpmc_decode_json(
    mixed $value
): array {
    if (is_array($value)) {
        return $value;
    }

    if (
        !is_string($value)
        || trim($value) === ''
    ) {
        return [];
    }

    $decoded = json_decode(
        $value,
        true
    );

    return is_array($decoded)
        ? $decoded
        : [];
}

function hpmc_load_assets(): array
{
    $statement = hpmc_db()->query(
        <<<'SQL'
        SELECT
            a.id,
            a.management_id,

            c.category_key AS category,
            c.category_code,
            c.display_name AS category_label,
            c.icon_name AS category_icon,

            a.name,
            a.status,

            a.manufacturer,
            a.model,
            a.serial_number,
            a.barcode,
            a.sku,

            a.quantity,
            a.unit,
            a.purchase_price,
            a.selling_price,

            a.country_code,
            a.prefecture_code,
            a.site_code,
            a.location_id,

            a.building,
            a.floor,
            a.room,
            a.rack_code,
            a.shelf_code,
            a.start_u,
            a.height_u,

            a.hostname,

            CASE
                WHEN a.management_ip IS NULL
                THEN NULL
                ELSE host(a.management_ip)
            END AS management_ip,

            a.management_vlan,

            CASE
                WHEN a.mac_address IS NULL
                THEN NULL
                ELSE a.mac_address::text
            END AS mac_address,

            a.purchase_date,
            a.warranty_expiry,
            a.supplier,

            a.specifications,
            a.notes,

            a.qr_issued_at,
            a.created_by,
            a.updated_by,
            a.created_at,
            a.updated_at

        FROM hpmc_assets a

        INNER JOIN hpmc_categories c
            ON c.id = a.category_id

        WHERE a.deleted_at IS NULL

        ORDER BY
            a.created_at DESC,
            a.id DESC
        SQL
    );

    $assets = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    foreach ($assets as &$asset) {
        $asset['id'] =
            (int) ($asset['id'] ?? 0);

        $asset['quantity'] =
            (int) ($asset['quantity'] ?? 0);

        $asset['purchase_price'] =
            (float) ($asset['purchase_price'] ?? 0);

        $asset['selling_price'] =
            (float) ($asset['selling_price'] ?? 0);

        $asset['start_u'] =
            $asset['start_u'] === null
                ? null
                : (int) $asset['start_u'];

        $asset['height_u'] =
            $asset['height_u'] === null
                ? null
                : (int) $asset['height_u'];

        $asset['management_vlan'] =
            $asset['management_vlan'] === null
                ? null
                : (int) $asset['management_vlan'];

        $asset['specifications'] =
            hpmc_decode_json(
                $asset['specifications']
                ?? null
            );
    }

    unset($asset);

    return $assets;
}

function hpmc_find_asset_by_management_id(
    string $managementId
): ?array {
    $statement = hpmc_db()->prepare(
        <<<'SQL'
        SELECT
            a.id,
            a.management_id,

            c.category_key AS category,
            c.category_code,
            c.display_name AS category_label,
            c.icon_name AS category_icon,

            a.name,
            a.status,

            a.manufacturer,
            a.model,
            a.serial_number,
            a.barcode,
            a.sku,

            a.quantity,
            a.unit,
            a.purchase_price,
            a.selling_price,

            a.country_code,
            a.prefecture_code,
            a.site_code,
            a.location_id,

            a.building,
            a.floor,
            a.room,
            a.rack_code,
            a.shelf_code,
            a.start_u,
            a.height_u,

            a.hostname,

            CASE
                WHEN a.management_ip IS NULL
                THEN NULL
                ELSE host(a.management_ip)
            END AS management_ip,

            a.management_vlan,

            CASE
                WHEN a.mac_address IS NULL
                THEN NULL
                ELSE a.mac_address::text
            END AS mac_address,

            a.purchase_date,
            a.warranty_expiry,
            a.supplier,

            a.specifications,
            a.notes,

            a.qr_issued_at,
            a.created_by,
            a.updated_by,
            a.created_at,
            a.updated_at

        FROM hpmc_assets a

        INNER JOIN hpmc_categories c
            ON c.id = a.category_id

        WHERE a.management_id = :management_id
          AND a.deleted_at IS NULL

        LIMIT 1
        SQL
    );

    $statement->execute([
        'management_id' => $managementId,
    ]);

    $asset = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    if (!is_array($asset)) {
        return null;
    }

    $asset['specifications'] =
        hpmc_decode_json(
            $asset['specifications']
            ?? null
        );

    return $asset;
}

function hpmc_category_definitions(): array
{
    $statement = hpmc_db()->query(
        <<<'SQL'
        SELECT
            category_key,
            category_code,
            display_name,
            icon_name
        FROM hpmc_categories
        WHERE is_active = TRUE
        ORDER BY sort_order, id
        SQL
    );

    $categories = [];

    foreach (
        $statement->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $categories[
            (string) $row['category_key']
        ] = [
            'code' =>
                (string) $row['category_code'],

            'label' =>
                (string) $row['display_name'],

            'icon' =>
                (string) (
                    $row['icon_name']
                    ?: 'category'
                ),
        ];
    }

    return $categories;
}

function hpmc_status_definitions(): array
{
    return [
        'active' => '使用中・販売中',
        'stock' => '在庫',
        'reserved' => '予約・確保済み',
        'loaned' => '貸出中',
        'maintenance' => 'メンテナンス中',
        'repair' => '修理中',
        'retired' => '廃棄・販売終了',
    ];
}

function hpmc_load_settings(): array
{
    $defaults = [
        'low_stock_threshold' => 3,
        'default_country_code' => 'JP',
        'default_prefecture_code' => 'NGN',
        'default_site_code' => 'HCDC01',
        'id_prefix' => 'HC',
        'id_sequence_digits' => 6,
        'default_label_size' => '62x29',
        'default_label_layout' => 'visual_right',
    ];

    $statement = hpmc_db()->query(
        <<<'SQL'
        SELECT
            setting_key,
            setting_value
        FROM hpmc_settings
        SQL
    );

    foreach (
        $statement->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $value = json_decode(
            (string) $row['setting_value'],
            true
        );

        $defaults[
            (string) $row['setting_key']
        ] = $value;
    }

    return $defaults;
}

function hpmc_save_settings(
    array $settings,
    ?int $userId = null
): void {
    $statement = hpmc_db()->prepare(
        <<<'SQL'
        INSERT INTO hpmc_settings (
            setting_key,
            setting_value,
            updated_by,
            updated_at
        )
        VALUES (
            :setting_key,
            CAST(:setting_value AS JSONB),
            :updated_by,
            NOW()
        )
        ON CONFLICT (setting_key)
        DO UPDATE SET
            setting_value =
                EXCLUDED.setting_value,
            updated_by =
                EXCLUDED.updated_by,
            updated_at = NOW()
        SQL
    );

    foreach ($settings as $key => $value) {
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            'updated_by' => $userId,
        ]);
    }
}

function hpmc_asset_location(
    array $asset
): string {
    $parts = [
        $asset['site_code'] ?? '',
        $asset['building'] ?? '',
        $asset['floor'] ?? '',
        $asset['room'] ?? '',
        $asset['rack_code'] ?? '',
        $asset['shelf_code'] ?? '',
    ];

    $parts = array_values(
        array_filter(
            array_map(
                static fn (
                    mixed $value
                ): string => trim(
                    (string) $value
                ),
                $parts
            ),
            static fn (
                string $value
            ): bool => $value !== ''
        )
    );

    return implode(' / ', $parts);
}
