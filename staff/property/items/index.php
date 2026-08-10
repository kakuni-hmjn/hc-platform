<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../components/layout.php';
require_once __DIR__ . '/../lib/data.php';

$assets = hpmc_load_assets();

usort(
    $assets,
    static fn (
        array $left,
        array $right
    ): int => strcmp(
        (string) ($right['created_at'] ?? ''),
        (string) ($left['created_at'] ?? '')
    )
);

$categories = hpmc_category_definitions();
$statuses = hpmc_status_definitions();

staff_layout_start([
    'title' => '物品一覧',
    'heading' => '物品一覧',
    'eyebrow' => 'HPMC ITEMS',
    'description' => '登録済みの備品・商品・IT機器を検索・確認します。',
    'active_navigation' => 'property',
]);

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1786500000"
>

<div class="hpmc-content">
    <section class="hpmc-panel">
        <div class="hpmc-search-toolbar">
            <div class="hpmc-search-input">
                <span class="material-icons">
                    search
                </span>

                <input
                    id="itemsSearch"
                    type="search"
                    placeholder="管理ID、名称、型番、IP、シリアル番号で検索"
                    autocomplete="off"
                >
            </div>

            <select id="itemsCategory">
                <option value="">
                    すべての分類
                </option>

                <?php foreach ($categories as $key => $category): ?>
                    <option value="<?= hpmc_escape($key) ?>">
                        <?= hpmc_escape($category['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="itemsStatus">
                <option value="">
                    すべての状態
                </option>

                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= hpmc_escape($key) ?>">
                        <?= hpmc_escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="hpmc-items-toolbar">
            <span id="itemsResultCount">
                <?= count($assets) ?>件
            </span>

            <div>
                <a
                    href="/staff/property/register/"
                    class="hpmc-primary-button"
                >
                    <span class="material-icons">
                        add_box
                    </span>

                    新規登録
                </a>
            </div>
        </div>

        <div class="hpmc-items-table-wrap">
            <table class="hpmc-items-table">
                <thead>
                    <tr>
                        <th>管理対象</th>
                        <th>分類</th>
                        <th>状態</th>
                        <th>メーカー・型番</th>
                        <th>配置先</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody id="itemsTableBody">
                    <?php foreach ($assets as $asset): ?>
                        <?php
                        $categoryKey = (string) (
                            $asset['category']
                            ?? 'other'
                        );

                        $category = $categories[
                            $categoryKey
                        ] ?? $categories['other'];

                        $searchText = implode(
                            ' ',
                            [
                                $asset['management_id'] ?? '',
                                $asset['name'] ?? '',
                                $asset['category_label'] ?? '',
                                $asset['manufacturer'] ?? '',
                                $asset['model'] ?? '',
                                $asset['serial_number'] ?? '',
                                $asset['barcode'] ?? '',
                                $asset['sku'] ?? '',
                                $asset['hostname'] ?? '',
                                $asset['management_ip'] ?? '',
                                $asset['mac_address'] ?? '',
                                hpmc_asset_location($asset),
                            ]
                        );
                        ?>

                        <tr
                            data-item-row
                            data-category="<?= hpmc_escape(
                                $categoryKey
                            ) ?>"
                            data-status="<?= hpmc_escape(
                                $asset['status'] ?? ''
                            ) ?>"
                            data-search="<?= hpmc_escape(
                                mb_strtolower($searchText)
                            ) ?>"
                        >
                            <td>
                                <div class="hpmc-item-cell">
                                    <span class="hpmc-item-cell__icon">
                                        <span class="material-icons">
                                            <?= hpmc_escape(
                                                $category['icon']
                                            ) ?>
                                        </span>
                                    </span>

                                    <span>
                                        <strong>
                                            <?= hpmc_escape(
                                                $asset['name']
                                                ?? '名称未設定'
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= hpmc_escape(
                                                $asset['management_id']
                                                ?? ''
                                            ) ?>
                                        </small>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <?= hpmc_escape(
                                    $category['label']
                                ) ?>
                            </td>

                            <td>
                                <span class="hpmc-item-status">
                                    <?= hpmc_escape(
                                        $statuses[
                                            $asset['status']
                                            ?? ''
                                        ]
                                        ?? (
                                            $asset['status']
                                            ?? '未設定'
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <strong>
                                    <?= hpmc_escape(
                                        $asset['manufacturer']
                                        ?? '－'
                                    ) ?>
                                </strong>

                                <small>
                                    <?= hpmc_escape(
                                        $asset['model']
                                        ?? ''
                                    ) ?>
                                </small>
                            </td>

                            <td>
                                <?= hpmc_escape(
                                    hpmc_asset_location($asset)
                                    ?: '未設定'
                                ) ?>
                            </td>

                            <td>
                                <a
                                    href="/staff/property/detail/?id=<?= rawurlencode(
                                        (string) (
                                            $asset['management_id']
                                            ?? ''
                                        )
                                    ) ?>"
                                    class="hpmc-table-action"
                                >
                                    詳細
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div
            id="itemsEmpty"
            class="hpmc-empty-state"
            hidden
        >
            <span class="material-icons">
                search_off
            </span>

            <strong>
                該当する管理対象がありません
            </strong>

            <p>
                検索条件を変更してください。
            </p>
        </div>
    </section>
</div>

<script src="/staff/property/items/items.js?v=1"></script>

<?php staff_layout_end(); ?>
