<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

$pageTitle = 'QR発行';

require_once __DIR__ . '/../../components/layout.php';

staff_layout_start([
    'title' => 'QR発行',
    'heading' => 'QR発行',
    'eyebrow' => 'HPMC QR LABEL STUDIO',
    'description' => '登録済みの備品・商品を選択してQRを発行します。',
    'active_navigation' => 'property',
]);

$hpmcActive = 'qr_issue';

$requestedId = trim(
    (string) ($_GET['id'] ?? '')
);

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1786500000"
>

<div class="hpmc-shell">


    <div class="hpmc-content">
        <section class="hpmc-hero">
            <div>
                <p class="hpmc-hero__eyebrow">
                    QR LABEL STUDIO
                </p>

                <h3>登録済みの備品・商品から選択</h3>

                <p class="hpmc-hero__description">
                    管理ID、名称、型番、シリアル番号、
                    IPアドレスなどから検索できます。
                </p>
            </div>
        </section>

        <section class="hpmc-panel">
            <div class="hpmc-search-toolbar">
                <div class="hpmc-search-input">
                    <span class="material-icons">
                        search
                    </span>

                    <input
                        id="assetSearch"
                        type="search"
                        placeholder="管理ID、名称、型番、シリアル、IP、ホスト名で検索"
                        autocomplete="off"
                    >
                </div>

                <select id="categoryFilter">
                    <option value="">
                        すべての分類
                    </option>

                    <option value="product">
                        商品
                    </option>

                    <option value="equipment">
                        備品
                    </option>

                    <option value="physical_server">
                        物理サーバー
                    </option>

                    <option value="network_device">
                        ネットワーク機器
                    </option>

                    <option value="computer">
                        PC・ワークステーション
                    </option>

                    <option value="storage_device">
                        ストレージ機器
                    </option>

                    <option value="rack">
                        ラック
                    </option>

                    <option value="other">
                        その他
                    </option>
                </select>

                <select id="statusFilter">
                    <option value="">
                        すべての状態
                    </option>

                    <option value="active">
                        使用中・販売中
                    </option>

                    <option value="stock">
                        在庫
                    </option>

                    <option value="reserved">
                        予約済み
                    </option>

                    <option value="loaned">
                        貸出中
                    </option>

                    <option value="maintenance">
                        メンテナンス
                    </option>

                    <option value="retired">
                        廃棄・終了
                    </option>
                </select>
            </div>

            <div class="hpmc-search-summary">
                <span id="assetCount">
                    読み込み中
                </span>

                <button
                    id="clearSearch"
                    type="button"
                    class="hpmc-text-button"
                >
                    検索条件をクリア
                </button>
            </div>

            <div
                id="assetList"
                class="hpmc-asset-list"
            ></div>

            <div
                id="assetEmpty"
                class="hpmc-empty-state"
                hidden
            >
                <span class="material-icons">
                    search_off
                </span>

                <strong>
                    該当する備品・商品がありません
                </strong>

                <p>
                    検索条件を変えるか、
                    備品・商品を先に登録してください。
                </p>
            </div>
        </section>

        <section
            id="qrEditor"
            class="hpmc-panel"
            hidden
        >
            <header class="hpmc-panel__heading">
                <div>
                    <h3>QRラベル設定</h3>

                    <p id="selectedAssetSummary"></p>
                </div>
            </header>

            <div class="hpmc-qr-layout">
                <div>
                    <div class="hpmc-form-grid">
                        <div class="hpmc-field">
                            <label for="selectedManagementId">
                                管理ID
                            </label>

                            <input
                                id="selectedManagementId"
                                type="text"
                                readonly
                            >
                        </div>

                        <div class="hpmc-field">
                            <label for="selectedName">
                                表示名
                            </label>

                            <input
                                id="selectedName"
                                type="text"
                            >
                        </div>

                        <div class="hpmc-field">
                            <label for="printerProfile">
                                印刷機プロファイル
                            </label>

                            <select id="printerProfile">
                                <option value="browser">
                                    通常プリンター
                                </option>

                                <option value="brother_62">
                                    Brother 62mm
                                </option>

                                <option value="brother_29">
                                    Brother 29mm
                                </option>

                                <option value="dymo_54_25">
                                    DYMO 54×25mm
                                </option>
                            </select>
                        </div>

                        <div class="hpmc-field">
                            <label for="labelSize">
                                ラベルサイズ
                            </label>

                            <select id="labelSize">
                                <option value="62x29">
                                    62 × 29 mm
                                </option>

                                <option value="62x40">
                                    62 × 40 mm
                                </option>

                                <option value="54x25">
                                    54 × 25 mm
                                </option>

                                <option value="40x30">
                                    40 × 30 mm
                                </option>

                                <option value="29x20">
                                    29 × 20 mm
                                </option>
                            </select>
                        </div>

                        <div class="hpmc-field">
                            <label for="printMode">
                                印刷形式
                            </label>

                            <select id="printMode">
                                <option value="label">
                                    ラベル付き
                                </option>

                                <option value="qr_only">
                                    QRコードのみ
                                </option>
                            </select>
                        </div>

                        <div class="hpmc-field">
                            <label for="labelLayout">
                                ラベルレイアウト
                            </label>

                            <select id="labelLayout">
                                <option value="visual_right">
                                    横型・見た目重視
                                </option>

                                <option value="identify_bottom">
                                    下部識別型
                                </option>

                                <option value="compact_right">
                                    コンパクト横型
                                </option>

                                <option value="id_focus">
                                    管理ID強調型
                                </option>
                            </select>
                        </div>

                        <div class="hpmc-field">
                            <label for="printCopies">
                                枚数
                            </label>

                            <input
                                id="printCopies"
                                type="number"
                                min="1"
                                max="100"
                                value="1"
                            >
                        </div>

                        <div class="hpmc-field">
                            <label for="labelWidth">
                                幅 mm
                            </label>

                            <input
                                id="labelWidth"
                                type="number"
                                value="62"
                            >
                        </div>

                        <div class="hpmc-field">
                            <label for="labelHeight">
                                高さ mm
                            </label>

                            <input
                                id="labelHeight"
                                type="number"
                                value="29"
                            >
                        </div>
                    </div>

                    <div class="hpmc-form-actions">
                        <button
                            id="downloadQrButton"
                            type="button"
                            class="hpmc-secondary-button"
                        >
                            PNG保存
                        </button>

                        <button
                            id="printQrButton"
                            type="button"
                            class="hpmc-primary-button"
                        >
                            印刷
                        </button>
                    </div>
                </div>

                <aside class="hpmc-qr-preview">
                    <div class="hpmc-label-preview">
                        <canvas
                            id="qrCanvas"
                            width="320"
                            height="320"
                        ></canvas>

                        <div class="hpmc-label-preview__info">
                            <div class="hpmc-label-preview__brand">
                                HC PROPERTY MANAGEMENT
                            </div>

                            <div
                                id="previewName"
                                class="hpmc-label-preview__name"
                            ></div>

                            <div
                                id="previewId"
                                class="hpmc-label-preview__id"
                            ></div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</div>

<div
    id="qrRenderSource"
    hidden
    aria-hidden="true"
></div>

<div
    id="printArea"
    class="hpmc-print-only"
></div>

<script>
window.hpmcRequestedId = <?= json_encode(
    $requestedId,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
) ?>;
</script>

<script src="/staff/property/qr-issue/qrcode.js"></script>
<script src="/staff/property/qr-issue/qr-issue.js?v=1785302951"></script>

<?php staff_layout_end(); ?>
