<?php

declare(strict_types=1);

$currentRequestPath = parse_url(
    (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    PHP_URL_PATH
);

if (!is_string($currentRequestPath)) {
    $currentRequestPath = '/';
}

$isHpmcPage = str_starts_with(
    $currentRequestPath,
    '/staff/property/'
);

$hpmcSidebarItems = [
    [
        'href' => '/staff/property/',
        'label' => '概要',
        'icon' => 'space_dashboard',
        'match' => [
            '/staff/property/',
        ],
        'exact' => true,
    ],
    [
        'href' => '/staff/property/register/',
        'label' => '備品・商品登録',
        'icon' => 'add_box',
        'match' => [
            '/staff/property/register/',
        ],
    ],
    [
        'href' => '/staff/property/scan/',
        'label' => 'QR読み取り',
        'icon' => 'qr_code_scanner',
        'match' => [
            '/staff/property/scan/',
        ],
    ],
    [
        'href' => '/staff/property/qr-issue/',
        'label' => 'QR発行',
        'icon' => 'qr_code_2',
        'match' => [
            '/staff/property/qr-issue/',
        ],
    ],
    [
        'href' => '/staff/property/items/',
        'label' => '物品一覧',
        'icon' => 'inventory_2',
        'match' => [
            '/staff/property/items/',
            '/staff/property/detail/',
        ],
    ],
    [
        'href' => '/staff/property/inventory/',
        'label' => '在庫管理',
        'icon' => 'warehouse',
        'match' => [
            '/staff/property/inventory/',
        ],
    ],
    [
        'href' => '/staff/property/locations/',
        'label' => 'ロケーション',
        'icon' => 'location_on',
        'match' => [
            '/staff/property/locations/',
        ],
    ],
    [
        'href' => '/staff/property/racks/',
        'label' => 'ラック管理',
        'icon' => 'dns',
        'match' => [
            '/staff/property/racks/',
            '/staff/property/rack/',
        ],
    ],
    [
        'href' => '/staff/property/settings/',
        'label' => '設定',
        'icon' => 'settings',
        'match' => [
            '/staff/property/settings/',
        ],
    ],
];

$hpmcItemIsActive = static function (
    array $item,
    string $requestPath
): bool {
    $matches = $item['match'] ?? [];
    $exact = (bool) ($item['exact'] ?? false);

    foreach ($matches as $match) {
        $match = (string) $match;

        if ($exact) {
            if ($requestPath === $match) {
                return true;
            }

            continue;
        }

        if (str_starts_with($requestPath, $match)) {
            return true;
        }
    }

    return false;
};

?>
<details
    class="staff-sidebar-group staff-sidebar-group--hpmc"
    data-staff-sidebar-group="hpmc"
    <?= $isHpmcPage ? 'open' : '' ?>
>
    <summary
        class="staff-sidebar-group__summary<?= (
            $isHpmcPage
                ? ' is-active'
                : ''
        ) ?>"
    >
        <span class="staff-sidebar-group__main">
            <span class="staff-sidebar-group__icon">
                <span class="material-icons">
                    inventory_2
                </span>
            </span>

            <span class="staff-sidebar-group__text">
                <strong>
                    物品管理センター
                </strong>

                <small>
                    HPMC
                </small>
            </span>
        </span>

        <span
            class="material-icons
                   staff-sidebar-group__arrow"
            aria-hidden="true"
        >
            expand_more
        </span>
    </summary>

    <nav
        class="staff-sidebar-submenu"
        aria-label="HC物品管理センター"
    >
        <?php foreach ($hpmcSidebarItems as $item): ?>
            <?php
            $itemActive = $hpmcItemIsActive(
                $item,
                $currentRequestPath
            );
            ?>

            <a
                href="<?= htmlspecialchars(
                    (string) $item['href'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="staff-sidebar-submenu__item<?= (
                    $itemActive
                        ? ' is-active'
                        : ''
                ) ?>"
                data-staff-full-navigation
                <?= $itemActive
                    ? 'aria-current="page"'
                    : '' ?>
            >
                <span class="material-icons">
                    <?= htmlspecialchars(
                        (string) $item['icon'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>

                <span>
                    <?= htmlspecialchars(
                        (string) $item['label'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>
</details>
