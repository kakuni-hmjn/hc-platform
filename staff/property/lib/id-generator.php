<?php

declare(strict_types=1);

function hpmc_code(
    mixed $value,
    int $maximumLength
): string {
    $value = strtoupper(
        trim((string) $value)
    );

    $value = preg_replace(
        '/[^A-Z0-9]/',
        '',
        $value
    ) ?? '';

    return substr(
        $value,
        0,
        $maximumLength
    );
}

function hpmc_generate_management_id(
    PDO $pdo,
    string $categoryCode,
    string $countryCode,
    string $prefectureCode,
    string $siteCode
): string {
    $categoryCode =
        hpmc_code($categoryCode, 10);

    $countryCode =
        hpmc_code($countryCode, 3);

    $prefectureCode =
        hpmc_code($prefectureCode, 10);

    $siteCode =
        hpmc_code($siteCode, 30);

    if (
        $categoryCode === ''
        || $countryCode === ''
        || $prefectureCode === ''
        || $siteCode === ''
    ) {
        throw new InvalidArgumentException(
            '管理IDのコード情報が不足しています。'
        );
    }

    $statement = $pdo->prepare(
        <<<'SQL'
        INSERT INTO hpmc_id_counters (
            category_code,
            country_code,
            prefecture_code,
            site_code,
            current_value,
            updated_at
        )
        VALUES (
            :category_code,
            :country_code,
            :prefecture_code,
            :site_code,
            1,
            NOW()
        )
        ON CONFLICT (
            category_code,
            country_code,
            prefecture_code,
            site_code
        )
        DO UPDATE SET
            current_value =
                hpmc_id_counters.current_value + 1,
            updated_at = NOW()
        RETURNING current_value
        SQL
    );

    $statement->execute([
        'category_code' => $categoryCode,
        'country_code' => $countryCode,
        'prefecture_code' => $prefectureCode,
        'site_code' => $siteCode,
    ]);

    $sequence = $statement->fetchColumn();

    if ($sequence === false) {
        throw new RuntimeException(
            '管理IDを採番できませんでした。'
        );
    }

    return sprintf(
        'HC-%s-%s-%s-%s-%06d',
        $categoryCode,
        $countryCode,
        $prefectureCode,
        $siteCode,
        (int) $sequence
    );
}
