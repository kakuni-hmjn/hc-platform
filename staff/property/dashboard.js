(() => {
    'use strict';

    const state = {
        assets: [],
    };

    const el = {
        total:
            document.getElementById(
                'dashboardTotalAssets'
            ),
        productStock:
            document.getElementById(
                'dashboardProductStock'
            ),
        active:
            document.getElementById(
                'dashboardActiveAssets'
            ),
        attention:
            document.getElementById(
                'dashboardAttentionAssets'
            ),
        categoryList:
            document.getElementById(
                'dashboardCategoryList'
            ),
        statusList:
            document.getElementById(
                'dashboardStatusList'
            ),
        lowStock:
            document.getElementById(
                'dashboardLowStock'
            ),
        missingLocation:
            document.getElementById(
                'dashboardMissingLocation'
            ),
        missingSerial:
            document.getElementById(
                'dashboardMissingSerial'
            ),
        maintenance:
            document.getElementById(
                'dashboardMaintenance'
            ),
        recent:
            document.getElementById(
                'dashboardRecentAssets'
            ),
        filteredPanel:
            document.getElementById(
                'dashboardFilteredPanel'
            ),
        filteredTitle:
            document.getElementById(
                'dashboardFilteredTitle'
            ),
        filteredDescription:
            document.getElementById(
                'dashboardFilteredDescription'
            ),
        filteredAssets:
            document.getElementById(
                'dashboardFilteredAssets'
            ),
        filteredClose:
            document.getElementById(
                'dashboardFilteredClose'
            ),
    };

    const categoryDefinitions = {
        product: {
            label: '商品',
            icon: 'shopping_bag',
        },
        equipment: {
            label: '備品',
            icon: 'inventory_2',
        },
        physical_server: {
            label: '物理サーバー',
            icon: 'dns',
        },
        network_device: {
            label: 'ネットワーク機器',
            icon: 'account_tree',
        },
        computer: {
            label: 'PC・ワークステーション',
            icon: 'computer',
        },
        storage_device: {
            label: 'ストレージ機器',
            icon: 'storage',
        },
        rack: {
            label: 'ラック',
            icon: 'view_stream',
        },
        other: {
            label: 'その他',
            icon: 'category',
        },
    };

    const statusDefinitions = {
        active: {
            label: '使用中・販売中',
            className: 'is-active',
        },
        stock: {
            label: '在庫',
            className: 'is-stock',
        },
        reserved: {
            label: '予約・確保済み',
            className: 'is-reserved',
        },
        loaned: {
            label: '貸出中',
            className: 'is-loaned',
        },
        maintenance: {
            label: 'メンテナンス中',
            className: 'is-maintenance',
        },
        repair: {
            label: '修理中',
            className: 'is-maintenance',
        },
        retired: {
            label: '廃棄・販売終了',
            className: 'is-retired',
        },
    };

    function value(input) {
        return String(input ?? '').trim();
    }

    function numberValue(input) {
        const number = Number(input);

        return Number.isFinite(number)
            ? number
            : 0;
    }

    function escapeHtml(input) {
        return value(input)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function locationText(asset) {
        return [
            asset.site_code,
            asset.building,
            asset.floor,
            asset.room,
            asset.rack_code,
            asset.shelf_code,
        ]
            .map(value)
            .filter(Boolean)
            .join(' / ');
    }

    function assetDetail(asset) {
        return [
            asset.manufacturer,
            asset.model,
            asset.hostname,
            asset.management_ip,
        ]
            .map(value)
            .filter(Boolean)
            .join(' / ');
    }

    function countBy(field) {
        return state.assets.reduce(
            (counts, asset) => {
                const key =
                    value(asset[field]) || 'unknown';

                counts[key] =
                    (counts[key] || 0) + 1;

                return counts;
            },
            {}
        );
    }

    function lowStockAssets() {
        return state.assets.filter((asset) => {
            return (
                asset.category === 'product'
                && numberValue(asset.quantity) <= 3
            );
        });
    }

    function missingLocationAssets() {
        return state.assets.filter((asset) => {
            return ![
                asset.room,
                asset.rack_code,
                asset.shelf_code,
            ].some((item) => value(item) !== '');
        });
    }

    function missingSerialAssets() {
        return state.assets.filter((asset) => {
            if (
                asset.category === 'product'
                || asset.category === 'rack'
            ) {
                return false;
            }

            return value(asset.serial_number) === '';
        });
    }

    function maintenanceAssets() {
        return state.assets.filter((asset) => {
            return [
                'maintenance',
                'repair',
            ].includes(asset.status);
        });
    }

    function renderSummary() {
        const total = state.assets.length;

        const productStock = state.assets
            .filter(
                (asset) =>
                    asset.category === 'product'
            )
            .reduce(
                (sum, asset) =>
                    sum + numberValue(asset.quantity),
                0
            );

        const active = state.assets.filter(
            (asset) =>
                asset.status === 'active'
        ).length;

        const attention =
            maintenanceAssets().length;

        el.total.textContent =
            total.toLocaleString('ja-JP');

        el.productStock.textContent =
            productStock.toLocaleString('ja-JP');

        el.active.textContent =
            active.toLocaleString('ja-JP');

        el.attention.textContent =
            attention.toLocaleString('ja-JP');

        el.lowStock.textContent =
            lowStockAssets()
                .length
                .toLocaleString('ja-JP');

        el.missingLocation.textContent =
            missingLocationAssets()
                .length
                .toLocaleString('ja-JP');

        el.missingSerial.textContent =
            missingSerialAssets()
                .length
                .toLocaleString('ja-JP');

        el.maintenance.textContent =
            maintenanceAssets()
                .length
                .toLocaleString('ja-JP');
    }

    function renderBreakdown(
        container,
        counts,
        definitions
    ) {
        const maximum = Math.max(
            1,
            ...Object.values(counts)
        );

        const rows = Object.entries(definitions)
            .map(([key, definition]) => {
                const count = counts[key] || 0;
                const percentage =
                    Math.round(
                        (count / maximum) * 100
                    );

                return `
                    <div class="hpmc-dashboard-breakdown-row">
                        <div class="hpmc-dashboard-breakdown-row__header">
                            <span>
                                ${
                                    definition.icon
                                        ? `
                                            <span class="material-icons">
                                                ${escapeHtml(
                                                    definition.icon
                                                )}
                                            </span>
                                        `
                                        : `
                                            <i class="${
                                                escapeHtml(
                                                    definition.className
                                                    || ''
                                                )
                                            }"></i>
                                        `
                                }

                                ${escapeHtml(
                                    definition.label
                                )}
                            </span>

                            <strong>
                                ${count.toLocaleString(
                                    'ja-JP'
                                )}
                            </strong>
                        </div>

                        <div class="hpmc-dashboard-progress">
                            <span
                                style="width: ${percentage}%"
                            ></span>
                        </div>
                    </div>
                `;
            })
            .join('');

        container.innerHTML = rows;
    }

    function assetRow(asset) {
        const category =
            categoryDefinitions[asset.category]
            || categoryDefinitions.other;

        const location =
            locationText(asset);

        return `
            <a
                href="/staff/property/detail/?id=${
                    encodeURIComponent(
                        asset.management_id
                    )
                }"
                class="hpmc-dashboard-recent-row"
            >
                <span class="hpmc-dashboard-recent-row__icon">
                    <span class="material-icons">
                        ${escapeHtml(category.icon)}
                    </span>
                </span>

                <span class="hpmc-dashboard-recent-row__main">
                    <strong>
                        ${escapeHtml(asset.name)}
                    </strong>

                    <small>
                        ${escapeHtml(
                            asset.management_id
                        )}
                    </small>

                    <em>
                        ${escapeHtml(
                            assetDetail(asset)
                        )}
                    </em>
                </span>

                <span class="hpmc-dashboard-recent-row__location">
                    ${escapeHtml(
                        location || '配置先未設定'
                    )}
                </span>

                <span class="material-icons">
                    chevron_right
                </span>
            </a>
        `;
    }

    function renderRecent() {
        const assets = [...state.assets]
            .sort((left, right) => {
                return value(right.created_at)
                    .localeCompare(
                        value(left.created_at)
                    );
            })
            .slice(0, 6);

        if (assets.length === 0) {
            el.recent.innerHTML = `
                <div class="hpmc-dashboard-empty">
                    登録された備品・商品はありません。
                </div>
            `;

            return;
        }

        el.recent.innerHTML =
            assets.map(assetRow).join('');
    }

    function filterDefinition(type) {
        const definitions = {
            low_stock: {
                title: '在庫が少ない商品',
                description:
                    '在庫数が3以下の商品を表示しています。',
                assets: lowStockAssets(),
            },
            missing_location: {
                title: '配置先未設定',
                description:
                    '部屋・ラック・棚が設定されていない管理対象です。',
                assets: missingLocationAssets(),
            },
            missing_serial: {
                title: 'シリアル番号未登録',
                description:
                    '個体識別用のシリアル番号が登録されていません。',
                assets: missingSerialAssets(),
            },
            maintenance: {
                title: 'メンテナンス・修理中',
                description:
                    '現在、通常利用できない管理対象です。',
                assets: maintenanceAssets(),
            },
        };

        return definitions[type] || null;
    }

    function showFilteredAssets(type) {
        const definition =
            filterDefinition(type);

        if (!definition) {
            return;
        }

        el.filteredTitle.textContent =
            definition.title;

        el.filteredDescription.textContent =
            definition.description;

        if (definition.assets.length === 0) {
            el.filteredAssets.innerHTML = `
                <div class="hpmc-dashboard-empty">
                    該当する管理対象はありません。
                </div>
            `;
        } else {
            el.filteredAssets.innerHTML =
                definition.assets
                    .map(assetRow)
                    .join('');
        }

        el.filteredPanel.hidden = false;

        el.filteredPanel.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }

    function renderDashboard() {
        renderSummary();

        renderBreakdown(
            el.categoryList,
            countBy('category'),
            categoryDefinitions
        );

        renderBreakdown(
            el.statusList,
            countBy('status'),
            statusDefinitions
        );

        renderRecent();
    }

    function renderError() {
        [
            el.total,
            el.productStock,
            el.active,
            el.attention,
            el.lowStock,
            el.missingLocation,
            el.missingSerial,
            el.maintenance,
        ].forEach((element) => {
            element.textContent = '－';
        });

        const message = `
            <div class="hpmc-dashboard-empty">
                管理情報を取得できませんでした。
            </div>
        `;

        el.categoryList.innerHTML = message;
        el.statusList.innerHTML = message;
        el.recent.innerHTML = message;
    }

    async function loadAssets() {
        try {
            const response = await fetch(
                '/staff/property/api/assets/list.php',
                {
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With':
                            'XMLHttpRequest',
                    },
                }
            );

            const data = await response.json();

            if (
                !response.ok
                || !data.success
                || !Array.isArray(data.assets)
            ) {
                throw new Error(
                    '管理情報を取得できませんでした。'
                );
            }

            state.assets = data.assets;

            renderDashboard();
        } catch (error) {
            renderError();
        }
    }

    document
        .querySelectorAll(
            '[data-dashboard-filter]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    showFilteredAssets(
                        button.dataset.dashboardFilter
                    );
                }
            );
        });

    el.filteredClose.addEventListener(
        'click',
        () => {
            el.filteredPanel.hidden = true;
        }
    );

    loadAssets();
})();
