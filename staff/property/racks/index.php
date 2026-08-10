<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../components/layout.php';
require_once __DIR__ . '/../lib/data.php';

$assets = hpmc_load_assets();

$racks = [];

foreach ($assets as $asset) {
    if (
        ($asset['category'] ?? '')
        !== 'rack'
    ) {
        continue;
    }

    $rackCode = trim(
        (string) (
            $asset['rack_code']
            ?? $asset['name']
            ?? ''
        )
    );

    if ($rackCode === '') {
        continue;
    }

    $racks[$rackCode] = [
        'asset' => $asset,
        'devices' => [],
    ];
}

foreach ($assets as $asset) {
    if (
        ($asset['category'] ?? '')
        === 'rack'
    ) {
        continue;
    }

    $rackCode = trim(
        (string) (
            $asset['rack_code']
            ?? ''
        )
    );

    if ($rackCode === '') {
        continue;
    }

    if (!isset($racks[$rackCode])) {
        $racks[$rackCode] = [
            'asset' => [
                'name' => $rackCode,
                'rack_code' => $rackCode,
                'site_code' =>
                    $asset['site_code'] ?? '',
                'room' =>
                    $asset['room'] ?? '',
                'specifications' => [
                    'rack_max_u' => 42,
                ],
            ],
            'devices' => [],
        ];
    }

    $racks[$rackCode]['devices'][] = $asset;
}

ksort($racks);

foreach ($racks as &$rack) {
    usort(
        $rack['devices'],
        static fn (
            array $left,
            array $right
        ): int => (
            (int) (
                $right['start_u']
                ?? 0
            )
        ) <=> (
            (int) (
                $left['start_u']
                ?? 0
            )
        )
    );
}

unset($rack);

staff_layout_start([
    'title' => 'ラック管理',
    'heading' => 'ラック管理',
    'eyebrow' => 'HPMC RACK MANAGEMENT',
    'description' => 'ラック別に搭載機器と使用中のU位置を確認します。',
    'active_navigation' => 'property',
]);

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1786500000"
>

<div class="hpmc-content">
    <section class="hpmc-rack-grid">
        <?php foreach ($racks as $rackCode => $rack): ?>
            <?php
            $rackAsset = $rack['asset'];
            $maxU = max(
                1,
                (int) (
                    $rackAsset['specifications'][
                        'rack_max_u'
                    ]
                    ?? $rackAsset['height_u']
                    ?? 42
                )
            );

            $usedU = array_sum(
                array_map(
                    static fn (
                        array $device
                    ): int => max(
                        1,
                        (int) (
                            $device['height_u']
                            ?? 1
                        )
                    ),
                    $rack['devices']
                )
            );
            ?>

            <article class="hpmc-panel">
                <header class="hpmc-rack-header">
                    <div>
                        <p>
                            <?= hpmc_escape(
                                $rackAsset['site_code']
                                ?? ''
                            ) ?>
                        </p>

                        <h3>
                            <?= hpmc_escape($rackCode) ?>
                        </h3>

                        <small>
                            <?= hpmc_escape(
                                $rackAsset['room']
                                ?? ''
                            ) ?>
                        </small>
                    </div>

                    <div class="hpmc-rack-usage">
                        <strong>
                            <?= $usedU ?>U
                        </strong>

                        <span>
                            / <?= $maxU ?>U
                        </span>
                    </div>
                </header>

                <div class="hpmc-rack-progress">
                    <span
                        style="width: <?= min(
                            100,
                            ($usedU / $maxU) * 100
                        ) ?>%"
                    ></span>
                </div>

                <div class="hpmc-rack-devices">
                    <?php if ($rack['devices'] === []): ?>
                        <div class="hpmc-dashboard-empty">
                            搭載機器はありません。
                        </div>
                    <?php else: ?>
                        <?php foreach ($rack['devices'] as $device): ?>
                            <a
                                href="/staff/property/detail/?id=<?= rawurlencode(
                                    (string) (
                                        $device['management_id']
                                        ?? ''
                                    )
                                ) ?>"
                                class="hpmc-rack-device"
                            >
                                <span class="hpmc-rack-device__u">
                                    U<?= hpmc_escape(
                                        $device['start_u']
                                        ?? '－'
                                    ) ?>
                                </span>

                                <span>
                                    <strong>
                                        <?= hpmc_escape(
                                            $device['name']
                                            ?? '名称未設定'
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= hpmc_escape(
                                            $device['model']
                                            ?? ''
                                        ) ?>
                                    </small>
                                </span>

                                <em>
                                    <?= max(
                                        1,
                                        (int) (
                                            $device['height_u']
                                            ?? 1
                                        )
                                    ) ?>U
                                </em>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</div>

<?php staff_layout_end(); ?>
