<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../components/layout.php';

staff_layout_start([
    'title' => '備品・商品登録',
    'heading' => '備品・商品登録',
    'eyebrow' => 'HPMC ITEM REGISTRATION',
    'description' => '分類を選択すると、登録内容に合わせた専用フォームへ切り替わります。',
    'active_navigation' => 'property',
]);

$hpmcActive = 'register';

$categories = [
    [
        'value' => 'product',
        'icon' => 'shopping_bag',
        'label' => '商品',
        'description' => '販売商品・パーツ・デバイス',
    ],
    [
        'value' => 'equipment',
        'icon' => 'inventory_2',
        'label' => '備品',
        'description' => '社内備品・貸出品・消耗品',
    ],
    [
        'value' => 'physical_server',
        'icon' => 'dns',
        'label' => '物理サーバー',
        'description' => 'ラック・タワー・ブレード',
    ],
    [
        'value' => 'network_device',
        'icon' => 'account_tree',
        'label' => 'ネットワーク機器',
        'description' => 'スイッチ・ルーター・FW・AP',
    ],
    [
        'value' => 'computer',
        'icon' => 'computer',
        'label' => 'PC・WS',
        'description' => 'PC・ノート・ワークステーション',
    ],
    [
        'value' => 'storage_device',
        'icon' => 'storage',
        'label' => 'ストレージ',
        'description' => 'NAS・SAN・JBOD',
    ],
    [
        'value' => 'rack',
        'icon' => 'view_stream',
        'label' => 'ラック',
        'description' => 'サーバーラック・収納棚',
    ],
    [
        'value' => 'other',
        'icon' => 'category',
        'label' => 'その他',
        'description' => '上記に含まれない物品',
    ],
];

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1785295375"
>

<div class="hpmc-shell">


    <div class="hpmc-content">
        <section class="hpmc-hero">
            <div>
                <p class="hpmc-hero__eyebrow">
                    ITEM REGISTRATION
                </p>

                <h3>登録する分類を選択</h3>

                <p class="hpmc-hero__description">
                    選択した分類に必要な情報だけを表示します。
                    登録時に分類別の管理IDを自動発行します。
                </p>
            </div>
        </section>

        <form id="itemRegisterForm" novalidate>
            <section class="hpmc-panel">
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>分類</h3>

                        <p>
                            登録対象に最も近い分類を選択してください。
                        </p>
                    </div>
                </header>

                <div class="hpmc-category-selector">
                    <?php foreach ($categories as $index => $category): ?>
                        <label class="hpmc-category-option">
                            <input
                                type="radio"
                                name="category"
                                value="<?= htmlspecialchars(
                                    $category['value'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                <?= $index === 0 ? 'checked' : '' ?>
                            >

                            <span class="hpmc-category-option__icon">
                                <span class="material-icons">
                                    <?= htmlspecialchars(
                                        $category['icon'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </span>

                            <span>
                                <strong>
                                    <?= htmlspecialchars(
                                        $category['label'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars(
                                        $category['description'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hpmc-panel">
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>基本情報</h3>

                        <p>
                            名称は選択した分類に合わせて変わります。
                        </p>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field hpmc-field--wide">
                        <label
                            id="itemNameLabel"
                            for="itemName"
                        >
                            商品名 *
                        </label>

                        <input
                            id="itemName"
                            name="name"
                            type="text"
                            placeholder="例：HC Gaming PC Entry"
                            required
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="itemStatus">
                            状態
                        </label>

                        <select
                            id="itemStatus"
                            name="status"
                        >
                            <option value="active">
                                使用中・販売中
                            </option>

                            <option value="stock">
                                在庫
                            </option>

                            <option value="reserved">
                                予約・確保済み
                            </option>

                            <option value="loaned">
                                貸出中
                            </option>

                            <option value="maintenance">
                                メンテナンス中
                            </option>

                            <option value="retired">
                                廃棄・販売終了
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="notes">
                            管理メモ
                        </label>

                        <input
                            id="notes"
                            name="notes"
                            type="text"
                            placeholder="任意"
                        >
                    </div>
                </div>
            </section>

            <!-- 商品 -->
            <section
                class="hpmc-panel"
                data-category-section="product"
            >
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>商品情報</h3>

                        <p>
                            販売、在庫、仕入れに使用する情報です。
                        </p>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="productCategory">
                            商品カテゴリ *
                        </label>

                        <select
                            id="productCategory"
                            name="product_category"
                            data-category-required
                        >
                            <option value="">
                                選択してください
                            </option>

                            <option value="gaming_pc">
                                ゲーミングPC
                            </option>

                            <option value="workstation">
                                ワークステーション
                            </option>

                            <option value="pc_parts">
                                PCパーツ
                            </option>

                            <option value="gaming_device">
                                ゲーミングデバイス
                            </option>

                            <option value="network_device">
                                ネットワーク製品
                            </option>

                            <option value="server">
                                サーバー製品
                            </option>

                            <option value="software">
                                ソフトウェア
                            </option>

                            <option value="other">
                                その他
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="productCondition">
                            商品状態
                        </label>

                        <select
                            id="productCondition"
                            name="product_condition"
                        >
                            <option value="new">
                                新品
                            </option>

                            <option value="used">
                                中古
                            </option>

                            <option value="refurbished">
                                整備済み
                            </option>

                            <option value="outlet">
                                アウトレット
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="productManufacturer">
                            メーカー
                        </label>

                        <input
                            id="productManufacturer"
                            name="manufacturer"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="productModel">
                            型番
                        </label>

                        <input
                            id="productModel"
                            name="model"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="productSku">
                            SKU・商品コード
                        </label>

                        <input
                            id="productSku"
                            name="sku"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="productBarcode">
                            JAN・バーコード
                        </label>

                        <input
                            id="productBarcode"
                            name="barcode"
                            type="text"
                            inputmode="numeric"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="purchasePrice">
                            仕入価格
                        </label>

                        <input
                            id="purchasePrice"
                            name="purchase_price"
                            type="number"
                            min="0"
                            step="1"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="sellingPrice">
                            販売価格 *
                        </label>

                        <input
                            id="sellingPrice"
                            name="selling_price"
                            type="number"
                            min="0"
                            step="1"
                            data-category-required
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="productQuantity">
                            在庫数
                        </label>

                        <input
                            id="productQuantity"
                            name="quantity"
                            type="number"
                            min="0"
                            value="1"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="taxCategory">
                            税区分
                        </label>

                        <select
                            id="taxCategory"
                            name="tax_category"
                        >
                            <option value="standard">
                                標準税率
                            </option>

                            <option value="reduced">
                                軽減税率
                            </option>

                            <option value="exempt">
                                非課税
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field hpmc-field--wide">
                        <label for="productDescription">
                            商品説明
                        </label>

                        <textarea
                            id="productDescription"
                            name="product_description"
                            placeholder="商品ページに掲載する説明や仕様"
                        ></textarea>
                    </div>
                </div>
            </section>

            <!-- 備品 -->
            <section
                class="hpmc-panel"
                data-category-section="equipment"
                hidden
            >
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>備品情報</h3>

                        <p>
                            社内利用、貸出、消耗品を登録します。
                        </p>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="equipmentCategory">
                            備品分類 *
                        </label>

                        <select
                            id="equipmentCategory"
                            name="equipment_category"
                            data-category-required
                        >
                            <option value="">
                                選択してください
                            </option>

                            <option value="office">
                                オフィス用品
                            </option>

                            <option value="tool">
                                工具
                            </option>

                            <option value="audio">
                                音響機器
                            </option>

                            <option value="video">
                                映像・撮影機器
                            </option>

                            <option value="event">
                                イベント用品
                            </option>

                            <option value="cable">
                                ケーブル
                            </option>

                            <option value="consumable">
                                消耗品
                            </option>

                            <option value="other">
                                その他
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="equipmentDepartment">
                            管理部署
                        </label>

                        <input
                            id="equipmentDepartment"
                            name="assigned_department"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="equipmentManufacturer">
                            メーカー
                        </label>

                        <input
                            id="equipmentManufacturer"
                            name="manufacturer"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="equipmentModel">
                            型番
                        </label>

                        <input
                            id="equipmentModel"
                            name="model"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="equipmentSerial">
                            シリアル番号
                        </label>

                        <input
                            id="equipmentSerial"
                            name="serial_number"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="equipmentQuantity">
                            数量
                        </label>

                        <input
                            id="equipmentQuantity"
                            name="quantity"
                            type="number"
                            min="0"
                            value="1"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="equipmentUnit">
                            単位
                        </label>

                        <select
                            id="equipmentUnit"
                            name="unit"
                        >
                            <option value="個">個</option>
                            <option value="台">台</option>
                            <option value="本">本</option>
                            <option value="箱">箱</option>
                            <option value="セット">セット</option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label class="hpmc-checkbox">
                            <input
                                name="loanable"
                                type="checkbox"
                                value="1"
                            >

                            <span>貸出可能な備品</span>
                        </label>
                    </div>
                </div>
            </section>

            <!-- 物理サーバー -->
            <section
                class="hpmc-panel"
                data-category-section="physical_server"
                hidden
            >
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>物理サーバー情報</h3>

                        <p>
                            ハードウェア構成と管理ネットワークを登録します。
                        </p>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="serverManufacturer">
                            メーカー *
                        </label>

                        <input
                            id="serverManufacturer"
                            name="manufacturer"
                            type="text"
                            placeholder="例：HPE"
                            data-category-required
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="serverModel">
                            型番・モデル *
                        </label>

                        <input
                            id="serverModel"
                            name="model"
                            type="text"
                            placeholder="例：ProLiant DL360 Gen9"
                            data-category-required
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="serverSerial">
                            シリアル番号
                        </label>

                        <input
                            id="serverSerial"
                            name="serial_number"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="serverRole">
                            サーバー用途
                        </label>

                        <select
                            id="serverRole"
                            name="server_role"
                        >
                            <option value="virtualization">
                                仮想化基盤
                            </option>

                            <option value="storage">
                                ストレージ
                            </option>

                            <option value="database">
                                データベース
                            </option>

                            <option value="web">
                                Web
                            </option>

                            <option value="game">
                                ゲームサーバー
                            </option>

                            <option value="backup">
                                バックアップ
                            </option>

                            <option value="monitoring">
                                監視
                            </option>

                            <option value="other">
                                その他
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="chassisType">
                            筐体形式
                        </label>

                        <select
                            id="chassisType"
                            name="chassis_type"
                        >
                            <option value="rack">
                                ラック型
                            </option>

                            <option value="tower">
                                タワー型
                            </option>

                            <option value="blade">
                                ブレード
                            </option>

                            <option value="mini">
                                ミニPC
                            </option>

                            <option value="custom">
                                自作
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="serverHeightU">
                            本体サイズ
                        </label>

                        <select
                            id="serverHeightU"
                            name="height_u"
                        >
                            <option value="1">1U</option>
                            <option value="2">2U</option>
                            <option value="3">3U</option>
                            <option value="4">4U</option>
                            <option value="0">ラック外</option>
                        </select>
                    </div>

                    <div class="hpmc-field hpmc-field--wide">
                        <label for="cpuModel">
                            CPUモデル
                        </label>

                        <input
                            id="cpuModel"
                            name="cpu_model"
                            type="text"
                            placeholder="例：Intel Xeon E5-2699 v4"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="cpuCount">
                            CPU搭載数
                        </label>

                        <input
                            id="cpuCount"
                            name="cpu_count"
                            type="number"
                            min="0"
                            value="2"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="cpuCoreCount">
                            合計コア数
                        </label>

                        <input
                            id="cpuCoreCount"
                            name="cpu_core_count"
                            type="number"
                            min="0"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="memoryGb">
                            メモリ容量 GB
                        </label>

                        <input
                            id="memoryGb"
                            name="memory_gb"
                            type="number"
                            min="0"
                            placeholder="256"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="memoryConfiguration">
                            メモリ構成
                        </label>

                        <input
                            id="memoryConfiguration"
                            name="memory_configuration"
                            type="text"
                            placeholder="例：32GB DDR4 × 8"
                        >
                    </div>

                    <div class="hpmc-field hpmc-field--wide">
                        <label for="storageSummary">
                            ストレージ構成
                        </label>

                        <textarea
                            id="storageSummary"
                            name="storage_summary"
                            placeholder="例：900GB SAS × 4 RAID10、1.92TB SSD × 2"
                        ></textarea>
                    </div>

                    <div class="hpmc-field">
                        <label for="raidController">
                            RAIDコントローラー
                        </label>

                        <input
                            id="raidController"
                            name="raid_controller"
                            type="text"
                            placeholder="例：HPE Smart Array P440ar"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="powerSupplyCount">
                            電源ユニット数
                        </label>

                        <input
                            id="powerSupplyCount"
                            name="power_supply_count"
                            type="number"
                            min="0"
                            value="2"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="networkInterfaces">
                            NIC構成
                        </label>

                        <input
                            id="networkInterfaces"
                            name="network_interfaces"
                            type="text"
                            placeholder="例：1GbE × 4、10GbE × 2"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="serverOs">
                            OS
                        </label>

                        <input
                            id="serverOs"
                            name="operating_system"
                            type="text"
                            placeholder="例：Proxmox VE"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="hypervisor">
                            ハイパーバイザー
                        </label>

                        <input
                            id="hypervisor"
                            name="hypervisor"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="clusterName">
                            クラスター名
                        </label>

                        <input
                            id="clusterName"
                            name="cluster_name"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="remoteManagementType">
                            リモート管理
                        </label>

                        <select
                            id="remoteManagementType"
                            name="remote_management_type"
                        >
                            <option value="">
                                なし
                            </option>

                            <option value="ilo">
                                HPE iLO
                            </option>

                            <option value="idrac">
                                Dell iDRAC
                            </option>

                            <option value="xcc">
                                Lenovo XCC
                            </option>

                            <option value="ipmi">
                                IPMI
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="remoteManagementIp">
                            iLO・iDRAC IP
                        </label>

                        <input
                            id="remoteManagementIp"
                            name="remote_management_ip"
                            type="text"
                            placeholder="10.0.10.101"
                        >
                    </div>
                </div>
            </section>

            <!-- ネットワーク機器 -->
            <section
                class="hpmc-panel"
                data-category-section="network_device"
                hidden
            >
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>ネットワーク機器情報</h3>

                        <p>
                            ポート、PoE、ファームウェア、役割を登録します。
                        </p>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="networkManufacturer">
                            メーカー *
                        </label>

                        <input
                            id="networkManufacturer"
                            name="manufacturer"
                            type="text"
                            placeholder="例：Allied Telesis"
                            data-category-required
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="networkModel">
                            型番 *
                        </label>

                        <input
                            id="networkModel"
                            name="model"
                            type="text"
                            placeholder="例：x510-52GTX"
                            data-category-required
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="networkSerial">
                            シリアル番号
                        </label>

                        <input
                            id="networkSerial"
                            name="serial_number"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="networkDeviceType">
                            機器種別 *
                        </label>

                        <select
                            id="networkDeviceType"
                            name="network_device_type"
                            data-category-required
                        >
                            <option value="">
                                選択してください
                            </option>

                            <option value="switch">
                                スイッチ
                            </option>

                            <option value="router">
                                ルーター
                            </option>

                            <option value="firewall">
                                ファイアウォール
                            </option>

                            <option value="wireless_ap">
                                無線AP
                            </option>

                            <option value="wireless_controller">
                                無線コントローラー
                            </option>

                            <option value="load_balancer">
                                ロードバランサー
                            </option>

                            <option value="other">
                                その他
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="networkRole">
                            ネットワーク上の役割
                        </label>

                        <select
                            id="networkRole"
                            name="network_role"
                        >
                            <option value="core">
                                コア
                            </option>

                            <option value="distribution">
                                ディストリビューション
                            </option>

                            <option value="access">
                                アクセス
                            </option>

                            <option value="edge">
                                エッジ
                            </option>

                            <option value="transit">
                                トランジット
                            </option>

                            <option value="management">
                                管理用
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="portCount">
                            通常ポート数
                        </label>

                        <input
                            id="portCount"
                            name="port_count"
                            type="number"
                            min="0"
                            placeholder="48"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="portSpeed">
                            通常ポート速度
                        </label>

                        <select
                            id="portSpeed"
                            name="port_speed"
                        >
                            <option value="100M">100Mbps</option>
                            <option value="1G" selected>1GbE</option>
                            <option value="2.5G">2.5GbE</option>
                            <option value="5G">5GbE</option>
                            <option value="10G">10GbE</option>
                            <option value="25G">25GbE</option>
                            <option value="40G">40GbE</option>
                            <option value="100G">100GbE</option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="uplinkSpecification">
                            アップリンク構成
                        </label>

                        <input
                            id="uplinkSpecification"
                            name="uplink_specification"
                            type="text"
                            placeholder="例：10G SFP+ × 4"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label class="hpmc-checkbox">
                            <input
                                id="poeSupported"
                                name="poe_supported"
                                type="checkbox"
                                value="1"
                            >

                            <span>PoE対応</span>
                        </label>
                    </div>

                    <div class="hpmc-field">
                        <label for="poeBudgetWatts">
                            PoE電力容量 W
                        </label>

                        <input
                            id="poeBudgetWatts"
                            name="poe_budget_watts"
                            type="number"
                            min="0"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="firmwareVersion">
                            OS・ファームウェア
                        </label>

                        <input
                            id="firmwareVersion"
                            name="firmware_version"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="stackName">
                            スタック名
                        </label>

                        <input
                            id="stackName"
                            name="stack_name"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="stackMemberNumber">
                            スタック番号
                        </label>

                        <input
                            id="stackMemberNumber"
                            name="stack_member_number"
                            type="number"
                            min="0"
                        >
                    </div>

                    <div class="hpmc-field hpmc-field--wide">
                        <label for="supportedVlans">
                            VLAN・ネットワーク用途
                        </label>

                        <textarea
                            id="supportedVlans"
                            name="supported_vlans"
                            placeholder="例：VLAN10 MGMT、VLAN20 Server、VLAN40 Cluster"
                        ></textarea>
                    </div>
                </div>
            </section>

            <!-- PC -->
            <section
                class="hpmc-panel"
                data-category-section="computer"
                hidden
            >
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>PC・ワークステーション情報</h3>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="computerManufacturer">
                            メーカー
                        </label>

                        <input
                            id="computerManufacturer"
                            name="manufacturer"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="computerModel">
                            型番
                        </label>

                        <input
                            id="computerModel"
                            name="model"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="computerType">
                            端末種別
                        </label>

                        <select
                            id="computerType"
                            name="device_type"
                        >
                            <option value="desktop">
                                デスクトップ
                            </option>

                            <option value="laptop">
                                ノートPC
                            </option>

                            <option value="workstation">
                                ワークステーション
                            </option>

                            <option value="mini_pc">
                                ミニPC
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="processor">
                            CPU
                        </label>

                        <input
                            id="processor"
                            name="processor"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="computerMemory">
                            メモリ GB
                        </label>

                        <input
                            id="computerMemory"
                            name="memory_gb"
                            type="number"
                            min="0"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="gpu">
                            GPU
                        </label>

                        <input
                            id="gpu"
                            name="gpu"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="computerStorage">
                            ストレージ
                        </label>

                        <input
                            id="computerStorage"
                            name="storage_capacity"
                            type="text"
                            placeholder="例：NVMe SSD 1TB"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="computerOs">
                            OS
                        </label>

                        <input
                            id="computerOs"
                            name="operating_system"
                            type="text"
                        >
                    </div>
                </div>
            </section>

            <!-- ストレージ -->
            <section
                class="hpmc-panel"
                data-category-section="storage_device"
                hidden
            >
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>ストレージ機器情報</h3>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="storageManufacturer">
                            メーカー
                        </label>

                        <input
                            id="storageManufacturer"
                            name="manufacturer"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="storageModel">
                            型番
                        </label>

                        <input
                            id="storageModel"
                            name="model"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="storageDeviceType">
                            種別
                        </label>

                        <select
                            id="storageDeviceType"
                            name="storage_device_type"
                        >
                            <option value="nas">NAS</option>
                            <option value="san">SAN</option>
                            <option value="jbod">JBOD</option>
                            <option value="disk_enclosure">
                                ディスクエンクロージャー
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="storageCapacity">
                            総容量
                        </label>

                        <input
                            id="storageCapacity"
                            name="storage_capacity"
                            type="text"
                            placeholder="例：120TB"
                        >
                    </div>

                    <div class="hpmc-field hpmc-field--wide">
                        <label for="diskConfiguration">
                            ディスク構成
                        </label>

                        <input
                            id="diskConfiguration"
                            name="disk_configuration"
                            type="text"
                            placeholder="例：12TB HDD × 12"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="storageRaidLevel">
                            RAID
                        </label>

                        <input
                            id="storageRaidLevel"
                            name="raid_level"
                            type="text"
                            placeholder="例：RAID6"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="storageProtocol">
                            接続方式
                        </label>

                        <input
                            id="storageProtocol"
                            name="storage_protocol"
                            type="text"
                            placeholder="例：NFS、iSCSI、SAS"
                        >
                    </div>
                </div>
            </section>

            <!-- ラック -->
            <section
                class="hpmc-panel"
                data-category-section="rack"
                hidden
            >
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>ラック情報</h3>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="rackCodeInput">
                            ラックコード *
                        </label>

                        <input
                            id="rackCodeInput"
                            name="rack_code"
                            type="text"
                            placeholder="例：RACK-A01"
                            data-category-required
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="rackMaxU">
                            最大U数
                        </label>

                        <input
                            id="rackMaxU"
                            name="rack_max_u"
                            type="number"
                            min="1"
                            value="42"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="rackDepth">
                            奥行き mm
                        </label>

                        <input
                            id="rackDepth"
                            name="rack_depth_mm"
                            type="number"
                            min="0"
                            placeholder="1000"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="rackPower">
                            電源構成
                        </label>

                        <input
                            id="rackPower"
                            name="rack_power"
                            type="text"
                            placeholder="例：100V 20A × 2"
                        >
                    </div>
                </div>
            </section>

            <!-- その他 -->
            <section
                class="hpmc-panel"
                data-category-section="other"
                hidden
            >
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>その他の物品情報</h3>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="otherCategoryName">
                            独自分類
                        </label>

                        <input
                            id="otherCategoryName"
                            name="custom_category"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="otherQuantity">
                            数量
                        </label>

                        <input
                            id="otherQuantity"
                            name="quantity"
                            type="number"
                            min="0"
                            value="1"
                        >
                    </div>
                </div>
            </section>

            <section class="hpmc-panel">
                <header class="hpmc-panel__heading">
                    <div>
                        <h3>配置・管理情報</h3>
                    </div>
                </header>

                <div class="hpmc-form-grid">
                    <div class="hpmc-field">
                        <label for="countryCode">
                            国
                        </label>

                        <select
                            id="countryCode"
                            name="country_code"
                        >
                            <option value="JP">
                                日本 / JP
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="prefectureCode">
                            都道府県
                        </label>

                        <select
                            id="prefectureCode"
                            name="prefecture_code"
                        >
                            <option value="NGN">
                                長野県 / NGN
                            </option>

                            <option value="TKY">
                                東京都 / TKY
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="siteCode">
                            拠点
                        </label>

                        <select
                            id="siteCode"
                            name="site_code"
                        >
                            <option value="HCDC01">
                                HC DC 01
                            </option>

                            <option value="HCHQ01">
                                HC本社
                            </option>

                            <option value="HCWH01">
                                HC倉庫01
                            </option>

                            <option value="HOME01">
                                自宅拠点
                            </option>
                        </select>
                    </div>

                    <div class="hpmc-field">
                        <label for="building">
                            建物
                        </label>

                        <input
                            id="building"
                            name="building"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="floor">
                            階
                        </label>

                        <input
                            id="floor"
                            name="floor"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="room">
                            部屋
                        </label>

                        <input
                            id="room"
                            name="room"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="rackCode">
                            配置ラック
                        </label>

                        <input
                            id="rackCode"
                            name="placement_rack_code"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="startU">
                            開始U
                        </label>

                        <input
                            id="startU"
                            name="start_u"
                            type="number"
                            min="0"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="hostname">
                            ホスト名
                        </label>

                        <input
                            id="hostname"
                            name="hostname"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="managementIp">
                            管理IP
                        </label>

                        <input
                            id="managementIp"
                            name="management_ip"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="managementVlan">
                            管理VLAN
                        </label>

                        <input
                            id="managementVlan"
                            name="management_vlan"
                            type="text"
                        >
                    </div>

                    <div class="hpmc-field">
                        <label for="macAddress">
                            MACアドレス
                        </label>

                        <input
                            id="macAddress"
                            name="mac_address"
                            type="text"
                        >
                    </div>
                </div>
            </section>

            <section class="hpmc-register-footer">
                <div>
                    <strong>
                        登録時に管理IDを自動発行
                    </strong>

                    <p id="registerMessage"></p>
                </div>

                <button
                    id="registerButton"
                    type="submit"
                    class="hpmc-primary-button"
                >
                    <span class="material-icons">
                        add_box
                    </span>

                    登録する
                </button>
            </section>
        </form>

        <section
            id="registrationResult"
            class="hpmc-panel hpmc-registration-result"
            hidden
        >
            <span class="material-icons">
                check_circle
            </span>

            <div>
                <strong>登録しました</strong>

                <p id="registeredManagementId"></p>
            </div>

            <a
                id="registeredQrLink"
                href="#"
                class="hpmc-secondary-button"
            >
                QR発行
            </a>

            <a
                id="registeredDetailLink"
                href="#"
                class="hpmc-primary-button"
            >
                詳細を開く
            </a>
        </section>
    </div>
</div>

<script src="/staff/property/register/register.js?v=3"></script>

<?php staff_layout_end(); ?>
