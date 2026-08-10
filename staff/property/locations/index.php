<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../components/layout.php';
require_once __DIR__ . '/../lib/data.php';

$assets = hpmc_load_assets();

$locations = [];

foreach ($assets as $asset) {
    $siteCode = trim(
        (string) (
            $asset['site_code']
            ?? '未設定'
        )
    );

    if ($siteCode === '') {
        $siteCode = '未設定';
    }

    if (!isset($locations[$siteCode])) {
        $locations[$siteCode] = [
            'count' => 0,
            'rooms' => [],
            'racks' => [],
            'categories' => [],
        ];
    }

    $locations[$siteCode]['count']++;

    $room = trim(
        (string) (
            $asset['room']
            ?? ''
        )
    );

    if ($room !== '') {
        $locations[$siteCode]['rooms'][$room] =
            (
                $locations[$siteCode]['rooms'][$room]
                ?? 0
            ) + 1;
    }

    $rack = trim(
        (string) (
            $asset['rack_code']
            ?? ''
        )
    );

    if ($rack !== '') {
        $locations[$siteCode]['racks'][$rack] =
            (
                $locations[$siteCode]['racks'][$rack]
                ?? 0
            ) + 1;
    }

    $category = trim(
        (string) (
            $asset['category_label']
            ?? 'その他'
        )
    );

    $locations[$siteCode]['categories'][$category] =
        (
            $locations[$siteCode]['categories'][$category]
            ?? 0
        ) + 1;
}

ksort($locations);

staff_layout_start([
    'title' => 'ロケーション',
    'heading' => 'ロケーション',
    'eyebrow' => 'HPMC LOCATIONS',
    'description' => '拠点、部屋、ラックごとの管理対象を確認します。',
    'active_navigation' => 'property',
]);

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1786500000"
>

<div class="hpmc-content">
    <section class="hpmc-location-grid">
        <?php foreach ($locations as $siteCode => $location): ?>
            <article class="hpmc-panel">
                <header class="hpmc-location-header">
                    <span class="hpmc-location-icon">
                        <span class="material-icons">
                            domain
                        </span>
                    </span>

                    <div>
                        <p>拠点コード</p>

                        <h3>
                            <?= hpmc_escape($siteCode) ?>
                        </h3>
                    </div>

                    <strong>
                        <?= number_format(
                            $location['count']
                        ) ?>件
                    </strong>
                </header>

                <div class="hpmc-location-section">
                    <h4>部屋</h4>

                    <?php if ($location['rooms'] === []): ?>
                        <p class="hpmc-muted-text">
                            部屋情報なし
                        </p>
                    <?php else: ?>
                        <?php foreach (
                            $location['rooms']
                            as $room => $count
                        ): ?>
                            <div class="hpmc-location-row">
                                <span><?= hpmc_escape($room) ?></span>
                                <strong><?= $count ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="hpmc-location-section">
                    <h4>ラック</h4>

                    <?php if ($location['racks'] === []): ?>
                        <p class="hpmc-muted-text">
                            ラック情報なし
                        </p>
                    <?php else: ?>
                        <?php foreach (
                            $location['racks']
                            as $rack => $count
                        ): ?>
                            <div class="hpmc-location-row">
                                <span><?= hpmc_escape($rack) ?></span>
                                <strong><?= $count ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>

<?php staff_layout_end(); ?>
