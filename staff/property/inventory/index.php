<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../components/layout.php';
require_once __DIR__ . '/../lib/data.php';

$assets = hpmc_load_assets();
$settings = hpmc_load_settings();

$products = array_values(
    array_filter(
        $assets,
        static fn (
            array $asset
        ): bool => (
            $asset['category']
            ?? ''
        ) === 'product'
    )
);

$totalQuantity = array_sum(
    array_map(
        static fn (
            array $asset
        ): int => max(
            0,
            (int) (
                $asset['quantity']
                ?? 0
            )
        ),
        $products
    )
);

$purchaseValue = array_sum(
    array_map(
        static fn (
            array $asset
        ): float => (
            (float) (
                $asset['purchase_price']
                ?? 0
            )
            * (int) (
                $asset['quantity']
                ?? 0
            )
        ),
        $products
    )
);

$salesValue = array_sum(
    array_map(
        static fn (
            array $asset
        ): float => (
            (float) (
                $asset['selling_price']
                ?? 0
            )
            * (int) (
                $asset['quantity']
                ?? 0
            )
        ),
        $products
    )
);

$lowStockThreshold = max(
    0,
    (int) (
        $settings['low_stock_threshold']
        ?? 3
    )
);

staff_layout_start([
    'title' => '在庫管理',
    'heading' => '在庫管理',
    'eyebrow' => 'HPMC INVENTORY',
    'description' => '商品在庫、仕入金額、販売予定金額を確認します。',
    'active_navigation' => 'property',
]);

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1785295375"
>

<div class="hpmc-content">
    <section class="hpmc-dashboard-summary">
        <article class="hpmc-dashboard-widget">
            <span class="hpmc-dashboard-widget__label">
                商品種類
            </span>

            <strong class="hpmc-dashboard-widget__value">
                <?= count($products) ?>
            </strong>

            <p>登録済み商品</p>
        </article>

        <article class="hpmc-dashboard-widget">
            <span class="hpmc-dashboard-widget__label">
                在庫総数
            </span>

            <strong class="hpmc-dashboard-widget__value">
                <?= number_format($totalQuantity) ?>
            </strong>

            <p>全商品の数量合計</p>
        </article>

        <article class="hpmc-dashboard-widget">
            <span class="hpmc-dashboard-widget__label">
                在庫原価
            </span>

            <strong class="hpmc-dashboard-widget__value">
                ¥<?= number_format($purchaseValue) ?>
            </strong>

            <p>仕入価格ベース</p>
        </article>

        <article class="hpmc-dashboard-widget">
            <span class="hpmc-dashboard-widget__label">
                販売予定金額
            </span>

            <strong class="hpmc-dashboard-widget__value">
                ¥<?= number_format($salesValue) ?>
            </strong>

            <p>販売価格ベース</p>
        </article>
    </section>

    <section class="hpmc-panel">
        <header class="hpmc-panel__heading">
            <div>
                <h3>商品在庫</h3>

                <p>
                    在庫数<?= $lowStockThreshold ?>以下は
                    要確認として表示します。
                </p>
            </div>

            <a
                href="/staff/property/register/"
                class="hpmc-primary-button"
            >
                商品登録
            </a>
        </header>

        <div class="hpmc-items-table-wrap">
            <table class="hpmc-items-table">
                <thead>
                    <tr>
                        <th>商品</th>
                        <th>SKU</th>
                        <th>在庫数</th>
                        <th>仕入価格</th>
                        <th>販売価格</th>
                        <th>在庫金額</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $quantity = max(
                            0,
                            (int) (
                                $product['quantity']
                                ?? 0
                            )
                        );

                        $lowStock =
                            $quantity
                            <= $lowStockThreshold;
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= hpmc_escape(
                                        $product['name']
                                        ?? '名称未設定'
                                    ) ?>
                                </strong>

                                <small>
                                    <?= hpmc_escape(
                                        $product['management_id']
                                        ?? ''
                                    ) ?>
                                </small>
                            </td>

                            <td>
                                <?= hpmc_escape(
                                    $product['sku']
                                    ?? '－'
                                ) ?>
                            </td>

                            <td>
                                <span class="<?= (
                                    $lowStock
                                        ? 'hpmc-stock-low'
                                        : 'hpmc-stock-normal'
                                ) ?>">
                                    <?= number_format($quantity) ?>
                                </span>
                            </td>

                            <td>
                                ¥<?= number_format(
                                    (float) (
                                        $product['purchase_price']
                                        ?? 0
                                    )
                                ) ?>
                            </td>

                            <td>
                                ¥<?= number_format(
                                    (float) (
                                        $product['selling_price']
                                        ?? 0
                                    )
                                ) ?>
                            </td>

                            <td>
                                ¥<?= number_format(
                                    (
                                        (float) (
                                            $product['purchase_price']
                                            ?? 0
                                        )
                                        * $quantity
                                    )
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php staff_layout_end(); ?>
