<?php

declare(strict_types=1);

$hpmcActive = (string) ($hpmcActive ?? 'home');

$hpmcNavigation = [
    [
        'id' => 'home',
        'href' => '/staff/property/',
        'icon' => 'space_dashboard',
        'label' => '概要',
    ],
    [
        'id' => 'register',
        'href' => '/staff/property/register/',
        'icon' => 'add_box',
        'label' => '備品・商品登録',
    ],
    [
        'id' => 'scan',
        'href' => '/staff/property/scan/',
        'icon' => 'qr_code_scanner',
        'label' => 'QR読み取り',
    ],
    [
        'id' => 'qr_issue',
        'href' => '/staff/property/qr-issue/',
        'icon' => 'qr_code_2',
        'label' => 'QR発行',
    ],
    [
        'id' => 'items',
        'href' => '#',
        'icon' => 'inventory_2',
        'label' => '物品一覧',
    ],
    [
        'id' => 'inventory',
        'href' => '#',
        'icon' => 'warehouse',
        'label' => '在庫管理',
    ],
    [
        'id' => 'locations',
        'href' => '#',
        'icon' => 'location_on',
        'label' => 'ロケーション',
    ],
    [
        'id' => 'racks',
        'href' => '#',
        'icon' => 'dns',
        'label' => 'ラック管理',
    ],
    [
        'id' => 'settings',
        'href' => '#',
        'icon' => 'settings',
        'label' => '設定',
    ],
];

?>
<aside class="hpmc-navigation">
    <div class="hpmc-navigation__header">
        <span>HPMC</span>
        <strong>物品管理センター</strong>
    </div>

    <nav
        class="hpmc-navigation__items"
        aria-label="物品管理メニュー"
    >
        <?php foreach ($hpmcNavigation as $item): ?>
            <a
                href="<?= htmlspecialchars(
                    $item['href'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="hpmc-navigation__item<?= (
                    $hpmcActive === $item['id']
                        ? ' is-active'
                        : ''
                ) ?>"
            >
                <span class="material-icons">
                    <?= htmlspecialchars(
                        $item['icon'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

                <span>
                    <?= htmlspecialchars(
                        $item['label'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
