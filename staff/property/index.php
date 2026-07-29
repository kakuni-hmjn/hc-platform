<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../components/layout.php';

staff_layout_start([
    'title' => 'HC物品管理センター',
    'heading' => '管理ダッシュボード',
    'eyebrow' => 'HC PROPERTY MANAGEMENT CENTER',
    'description' => '備品・商品・IT機器の登録状況と対応が必要な項目を確認します。',
    'active_navigation' => 'property',
]);

$hpmcActive = 'home';

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1785295375"
>

<div class="hpmc-shell">


    <div class="hpmc-content">
        <section class="hpmc-dashboard-header">
            <div>
                <p class="hpmc-dashboard-header__eyebrow">
                    INTERNAL OPERATIONS
                </p>

                <h3>
                    物品管理状況
                </h3>

                <p>
                    登録済みの備品・商品・IT機器と、
                    現在対応が必要な情報を表示しています。
                </p>
            </div>

            <div class="hpmc-dashboard-header__actions">
                <a
                    href="/staff/property/scan/"
                    class="hpmc-secondary-button"
                >
                    <span class="material-icons">
                        qr_code_scanner
                    </span>

                    QR読み取り
                </a>

                <a
                    href="/staff/property/register/"
                    class="hpmc-primary-button"
                >
                    <span class="material-icons">
                        add_box
                    </span>

                    備品・商品登録
                </a>
            </div>
        </section>

        <section
            class="hpmc-dashboard-summary"
            aria-label="管理状況"
        >
            <article class="hpmc-dashboard-widget">
                <div class="hpmc-dashboard-widget__top">
                    <span class="hpmc-dashboard-widget__icon">
                        <span class="material-icons">
                            inventory_2
                        </span>
                    </span>

                    <span class="hpmc-dashboard-widget__label">
                        登録総数
                    </span>
                </div>

                <strong
                    id="dashboardTotalAssets"
                    class="hpmc-dashboard-widget__value"
                >
                    －
                </strong>

                <p>
                    登録済みの全管理対象
                </p>
            </article>

            <article class="hpmc-dashboard-widget">
                <div class="hpmc-dashboard-widget__top">
                    <span
                        class="hpmc-dashboard-widget__icon
                               hpmc-dashboard-widget__icon--stock"
                    >
                        <span class="material-icons">
                            warehouse
                        </span>
                    </span>

                    <span class="hpmc-dashboard-widget__label">
                        商品在庫
                    </span>
                </div>

                <strong
                    id="dashboardProductStock"
                    class="hpmc-dashboard-widget__value"
                >
                    －
                </strong>

                <p>
                    登録商品の在庫合計
                </p>
            </article>

            <article class="hpmc-dashboard-widget">
                <div class="hpmc-dashboard-widget__top">
                    <span
                        class="hpmc-dashboard-widget__icon
                               hpmc-dashboard-widget__icon--active"
                    >
                        <span class="material-icons">
                            check_circle
                        </span>
                    </span>

                    <span class="hpmc-dashboard-widget__label">
                        稼働・使用中
                    </span>
                </div>

                <strong
                    id="dashboardActiveAssets"
                    class="hpmc-dashboard-widget__value"
                >
                    －
                </strong>

                <p>
                    現在利用中の管理対象
                </p>
            </article>

            <article class="hpmc-dashboard-widget">
                <div class="hpmc-dashboard-widget__top">
                    <span
                        class="hpmc-dashboard-widget__icon
                               hpmc-dashboard-widget__icon--warning"
                    >
                        <span class="material-icons">
                            build_circle
                        </span>
                    </span>

                    <span class="hpmc-dashboard-widget__label">
                        要対応
                    </span>
                </div>

                <strong
                    id="dashboardAttentionAssets"
                    class="hpmc-dashboard-widget__value"
                >
                    －
                </strong>

                <p>
                    修理・メンテナンス中
                </p>
            </article>
        </section>

        <section class="hpmc-dashboard-grid">
            <article class="hpmc-panel">
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>
                            分類別登録数
                        </h3>

                        <p>
                            現在登録されている管理対象の内訳
                        </p>
                    </div>
                </header>

                <div
                    id="dashboardCategoryList"
                    class="hpmc-dashboard-breakdown"
                >
                    <div class="hpmc-dashboard-loading">
                        読み込み中
                    </div>
                </div>
            </article>

            <article class="hpmc-panel">
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>
                            状態別件数
                        </h3>

                        <p>
                            使用状況・在庫・対応状況
                        </p>
                    </div>
                </header>

                <div
                    id="dashboardStatusList"
                    class="hpmc-dashboard-breakdown"
                >
                    <div class="hpmc-dashboard-loading">
                        読み込み中
                    </div>
                </div>
            </article>
        </section>

        <section class="hpmc-dashboard-grid">
            <article class="hpmc-panel">
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>
                            要確認項目
                        </h3>

                        <p>
                            情報不足や対応が必要な管理対象
                        </p>
                    </div>
                </header>

                <div class="hpmc-dashboard-alerts">
                    <button
                        type="button"
                        class="hpmc-dashboard-alert"
                        data-dashboard-filter="low_stock"
                    >
                        <span
                            class="material-icons
                                   hpmc-dashboard-alert__icon"
                        >
                            production_quantity_limits
                        </span>

                        <span class="hpmc-dashboard-alert__body">
                            <strong>
                                在庫が少ない商品
                            </strong>

                            <small>
                                在庫数が3以下の商品
                            </small>
                        </span>

                        <b id="dashboardLowStock">
                            －
                        </b>
                    </button>

                    <button
                        type="button"
                        class="hpmc-dashboard-alert"
                        data-dashboard-filter="missing_location"
                    >
                        <span
                            class="material-icons
                                   hpmc-dashboard-alert__icon"
                        >
                            location_off
                        </span>

                        <span class="hpmc-dashboard-alert__body">
                            <strong>
                                配置先未設定
                            </strong>

                            <small>
                                部屋・ラック・棚が未登録
                            </small>
                        </span>

                        <b id="dashboardMissingLocation">
                            －
                        </b>
                    </button>

                    <button
                        type="button"
                        class="hpmc-dashboard-alert"
                        data-dashboard-filter="missing_serial"
                    >
                        <span
                            class="material-icons
                                   hpmc-dashboard-alert__icon"
                        >
                            tag
                        </span>

                        <span class="hpmc-dashboard-alert__body">
                            <strong>
                                シリアル番号未登録
                            </strong>

                            <small>
                                個体識別情報が不足
                            </small>
                        </span>

                        <b id="dashboardMissingSerial">
                            －
                        </b>
                    </button>

                    <button
                        type="button"
                        class="hpmc-dashboard-alert"
                        data-dashboard-filter="maintenance"
                    >
                        <span
                            class="material-icons
                                   hpmc-dashboard-alert__icon"
                        >
                            construction
                        </span>

                        <span class="hpmc-dashboard-alert__body">
                            <strong>
                                メンテナンス中
                            </strong>

                            <small>
                                現在使用できない管理対象
                            </small>
                        </span>

                        <b id="dashboardMaintenance">
                            －
                        </b>
                    </button>
                </div>
            </article>

            <article class="hpmc-panel">
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>
                            よく使う操作
                        </h3>

                        <p>
                            日常業務で使用する管理操作
                        </p>
                    </div>
                </header>

                <div class="hpmc-dashboard-actions">
                    <a
                        href="/staff/property/register/"
                        class="hpmc-dashboard-action"
                    >
                        <span class="material-icons">
                            add_box
                        </span>

                        <span>
                            <strong>
                                備品・商品登録
                            </strong>

                            <small>
                                新しい管理対象を登録
                            </small>
                        </span>
                    </a>

                    <a
                        href="/staff/property/qr-issue/"
                        class="hpmc-dashboard-action"
                    >
                        <span class="material-icons">
                            qr_code_2
                        </span>

                        <span>
                            <strong>
                                QR発行
                            </strong>

                            <small>
                                登録済み物品のラベル発行
                            </small>
                        </span>
                    </a>

                    <a
                        href="/staff/property/scan/"
                        class="hpmc-dashboard-action"
                    >
                        <span class="material-icons">
                            qr_code_scanner
                        </span>

                        <span>
                            <strong>
                                QR読み取り
                            </strong>

                            <small>
                                管理対象をその場で確認
                            </small>
                        </span>
                    </a>

                    <a
                        href="/staff/property/qr-issue/"
                        class="hpmc-dashboard-action"
                    >
                        <span class="material-icons">
                            search
                        </span>

                        <span>
                            <strong>
                                管理対象を検索
                            </strong>

                            <small>
                                ID・型番・IPから検索
                            </small>
                        </span>
                    </a>
                </div>
            </article>
        </section>

        <section class="hpmc-panel">
            <header class="hpmc-panel__heading">
                <div>
                    <h3>
                        最近登録された備品・商品
                    </h3>

                    <p>
                        新しく登録された管理対象を表示
                    </p>
                </div>

                <a
                    href="/staff/property/qr-issue/"
                    class="hpmc-text-link"
                >
                    一覧を開く
                </a>
            </header>

            <div
                id="dashboardRecentAssets"
                class="hpmc-dashboard-recent"
            >
                <div class="hpmc-dashboard-loading">
                    読み込み中
                </div>
            </div>
        </section>

        <section
            id="dashboardFilteredPanel"
            class="hpmc-panel"
            hidden
        >
            <header class="hpmc-panel__heading">
                <div>
                    <h3 id="dashboardFilteredTitle">
                        要確認項目
                    </h3>

                    <p id="dashboardFilteredDescription">
                    </p>
                </div>

                <button
                    id="dashboardFilteredClose"
                    type="button"
                    class="hpmc-text-button"
                >
                    閉じる
                </button>
            </header>

            <div
                id="dashboardFilteredAssets"
                class="hpmc-dashboard-recent"
            ></div>
        </section>
    </div>
</div>

<script src="/staff/property/dashboard.js?v=1"></script>

<?php staff_layout_end(); ?>
