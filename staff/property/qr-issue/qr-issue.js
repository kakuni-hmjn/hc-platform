(() => {
    'use strict';

    const storageKey = 'hc_hpmc_qr_print_v4';

    const state = {
        assets: [],
        selected: null,
        qrReady: false,
    };

    const el = {
        search:
            document.getElementById('assetSearch'),
        category:
            document.getElementById('categoryFilter'),
        status:
            document.getElementById('statusFilter'),
        clear:
            document.getElementById('clearSearch'),
        count:
            document.getElementById('assetCount'),
        list:
            document.getElementById('assetList'),
        empty:
            document.getElementById('assetEmpty'),
        editor:
            document.getElementById('qrEditor'),
        summary:
            document.getElementById(
                'selectedAssetSummary'
            ),
        managementId:
            document.getElementById(
                'selectedManagementId'
            ),
        name:
            document.getElementById('selectedName'),
        printer:
            document.getElementById(
                'printerProfile'
            ),
        size:
            document.getElementById('labelSize'),
        printMode:
            document.getElementById('printMode'),
        layout:
            document.getElementById('labelLayout'),
        copies:
            document.getElementById('printCopies'),
        width:
            document.getElementById('labelWidth'),
        height:
            document.getElementById('labelHeight'),
        canvas:
            document.getElementById('qrCanvas'),
        renderSource:
            document.getElementById('qrRenderSource'),
        preview:
            document.querySelector(
                '.hpmc-label-preview'
            ),
        previewInfo:
            document.querySelector(
                '.hpmc-label-preview__info'
            ),
        previewName:
            document.getElementById('previewName'),
        previewId:
            document.getElementById('previewId'),
        download:
            document.getElementById(
                'downloadQrButton'
            ),
        print:
            document.getElementById('printQrButton'),
        printArea:
            document.getElementById('printArea'),
    };

    const sizes = {
        '62x29': [62, 29],
        '62x40': [62, 40],
        '54x25': [54, 25],
        '40x30': [40, 30],
        '29x20': [29, 20],
    };

    const profiles = {
        browser: '62x29',
        brother_62: '62x29',
        brother_29: '29x20',
        dymo_54_25: '54x25',
    };

    function text(value) {
        return String(value ?? '').trim();
    }

    function escapeHtml(value) {
        return text(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function searchableText(asset) {
        return [
            asset.management_id,
            asset.category_label,
            asset.name,
            asset.manufacturer,
            asset.model,
            asset.serial_number,
            asset.barcode,
            asset.sku,
            asset.hostname,
            asset.management_ip,
            asset.management_vlan,
            asset.mac_address,
            asset.site_code,
            asset.building,
            asset.floor,
            asset.room,
            asset.rack_code,
            asset.shelf_code,
        ]
            .map(text)
            .join(' ')
            .toLowerCase();
    }

    function filteredAssets() {
        const keywords = el.search.value
            .trim()
            .toLowerCase()
            .split(/\s+/)
            .filter(Boolean);

        return state.assets.filter((asset) => {
            if (
                el.category.value
                && asset.category !== el.category.value
            ) {
                return false;
            }

            if (
                el.status.value
                && asset.status !== el.status.value
            ) {
                return false;
            }

            const target = searchableText(asset);

            return keywords.every(
                (keyword) => target.includes(keyword)
            );
        });
    }

    function categoryIcon(category) {
        const icons = {
            product: 'shopping_bag',
            equipment: 'inventory_2',
            physical_server: 'dns',
            network_device: 'account_tree',
            computer: 'computer',
            storage_device: 'storage',
            rack: 'view_stream',
            other: 'category',
        };

        return icons[category] || 'inventory_2';
    }

    function renderList() {
        const assets = filteredAssets();

        el.count.textContent =
            `${assets.length}件 / 全${state.assets.length}件`;

        el.empty.hidden = assets.length > 0;
        el.list.hidden = assets.length === 0;

        el.list.innerHTML = assets
            .map((asset) => {
                const details = [
                    asset.manufacturer,
                    asset.model,
                    asset.hostname,
                    asset.management_ip,
                ]
                    .map(text)
                    .filter(Boolean)
                    .join(' / ');

                return `
                    <button
                        type="button"
                        class="hpmc-asset-row"
                        data-management-id="${
                            escapeHtml(
                                asset.management_id
                            )
                        }"
                    >
                        <span class="hpmc-asset-row__icon">
                            <span class="material-icons">
                                ${categoryIcon(
                                    asset.category
                                )}
                            </span>
                        </span>

                        <span class="hpmc-asset-row__main">
                            <strong>
                                ${escapeHtml(asset.name)}
                            </strong>

                            <small>
                                ${escapeHtml(
                                    asset.management_id
                                )}
                            </small>

                            <em>
                                ${escapeHtml(details)}
                            </em>
                        </span>

                        <span class="hpmc-asset-row__category">
                            ${escapeHtml(
                                asset.category_label
                            )}
                        </span>

                        <span class="material-icons">
                            arrow_forward
                        </span>
                    </button>
                `;
            })
            .join('');
    }

    function qrTargetUrl() {
        if (!state.selected) {
            return '';
        }

        const url = new URL(
            '/staff/property/detail/',
            window.location.origin
        );

        url.searchParams.set(
            'id',
            state.selected.management_id
        );

        return url.toString();
    }

    function clearCanvas() {
        const context =
            el.canvas.getContext('2d');

        if (!context) {
            return;
        }

        context.clearRect(
            0,
            0,
            el.canvas.width,
            el.canvas.height
        );

        context.fillStyle = '#ffffff';

        context.fillRect(
            0,
            0,
            el.canvas.width,
            el.canvas.height
        );
    }

    function waitForQrCanvas() {
        return new Promise((resolve, reject) => {
            let attempts = 0;

            const check = () => {
                const sourceCanvas =
                    el.renderSource.querySelector('canvas');

                const sourceImage =
                    el.renderSource.querySelector('img');

                if (sourceCanvas) {
                    resolve(sourceCanvas);
                    return;
                }

                if (
                    sourceImage
                    && sourceImage.complete
                    && sourceImage.naturalWidth > 0
                ) {
                    resolve(sourceImage);
                    return;
                }

                attempts += 1;

                if (attempts >= 50) {
                    reject(
                        new Error(
                            'QRコードの描画に失敗しました。'
                        )
                    );

                    return;
                }

                window.setTimeout(check, 20);
            };

            check();
        });
    }

    async function renderQr() {
        state.qrReady = false;

        if (!state.selected) {
            clearCanvas();
            return;
        }

        if (typeof QRCode === 'undefined') {
            clearCanvas();

            throw new Error(
                'QR生成ライブラリが'
                + '読み込まれていません。'
            );
        }

        const targetUrl = qrTargetUrl();

        el.renderSource.replaceChildren();

        new QRCode(
            el.renderSource,
            {
                text: targetUrl,
                width: 640,
                height: 640,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel:
                    QRCode.CorrectLevel.M,
            }
        );

        const source = await waitForQrCanvas();

        const context =
            el.canvas.getContext('2d');

        if (!context) {
            throw new Error(
                'QR描画用キャンバスを'
                + '利用できません。'
            );
        }

        el.canvas.width = 640;
        el.canvas.height = 640;

        context.clearRect(0, 0, 640, 640);
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, 640, 640);

        context.imageSmoothingEnabled = false;

        context.drawImage(
            source,
            0,
            0,
            640,
            640
        );

        const pixel =
            context.getImageData(
                320,
                320,
                1,
                1
            ).data;

        if (pixel[3] === 0) {
            throw new Error(
                'QRコードが透明な状態で'
                + '生成されました。'
            );
        }

        el.previewId.textContent =
            state.selected.management_id;

        el.previewName.textContent =
            el.name.value.trim()
            || state.selected.name;

        state.qrReady = true;

        updatePreviewLayout();
    }

    function updatePreviewLayout() {
        const mode = el.printMode.value;
        const layout = el.layout.value;

        el.layout.disabled = mode === 'qr_only';

        el.preview.classList.remove(
            'is-qr-only',
            'is-visual-right',
            'is-identify-bottom',
            'is-compact-right',
            'is-id-focus'
        );

        if (mode === 'qr_only') {
            el.preview.classList.add('is-qr-only');
        } else {
            el.preview.classList.add(
                `is-${layout.replaceAll('_', '-')}`
            );
        }
    }

    async function selectAsset(asset) {
        state.selected = asset;

        el.managementId.value =
            asset.management_id;

        el.name.value = asset.name;

        el.summary.textContent = [
            asset.category_label,
            asset.manufacturer,
            asset.model,
        ]
            .map(text)
            .filter(Boolean)
            .join(' / ');

        el.editor.hidden = false;

        try {
            await renderQr();
        } catch (error) {
            window.alert(
                error instanceof Error
                    ? error.message
                    : 'QRコードを生成できませんでした。'
            );
        }

        el.editor.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }

    function applySize() {
        const size = sizes[el.size.value];

        if (!size) {
            return;
        }

        el.width.value = String(size[0]);
        el.height.value = String(size[1]);
    }

    function currentSettings() {
        return {
            printer: el.printer.value,
            size: el.size.value,
            printMode: el.printMode.value,
            layout: el.layout.value,
            copies: Math.max(
                1,
                Number(el.copies.value) || 1
            ),
            width: Math.max(
                10,
                Number(el.width.value) || 62
            ),
            height: Math.max(
                10,
                Number(el.height.value) || 29
            ),
        };
    }

    function saveSettings() {
        localStorage.setItem(
            storageKey,
            JSON.stringify(currentSettings())
        );
    }

    function loadSettings() {
        try {
            const settings = JSON.parse(
                localStorage.getItem(storageKey)
                || '{}'
            );

            if (settings.printer) {
                el.printer.value =
                    settings.printer;
            }

            if (settings.size) {
                el.size.value = settings.size;
            }

            if (settings.printMode) {
                el.printMode.value =
                    settings.printMode;
            }

            if (settings.layout) {
                el.layout.value =
                    settings.layout;
            }

            if (settings.copies) {
                el.copies.value =
                    String(settings.copies);
            }

            if (settings.width) {
                el.width.value =
                    String(settings.width);
            }

            if (settings.height) {
                el.height.value =
                    String(settings.height);
            }
        } catch (error) {
            localStorage.removeItem(storageKey);
        }

        updatePreviewLayout();
    }

    function createQrImage() {
        const image = document.createElement('img');

        image.src =
            el.canvas.toDataURL(
                'image/png',
                1
            );

        image.alt = 'QRコード';

        return image;
    }

    function createInfoBlock(layout) {
        const info = document.createElement('div');

        info.className =
            'hpmc-print-label__information';

        const brand =
            document.createElement('small');

        brand.className =
            'hpmc-print-label__brand';

        brand.textContent =
            'HC PROPERTY MANAGEMENT';

        const name =
            document.createElement('strong');

        name.className =
            'hpmc-print-label__name';

        name.textContent =
            el.name.value.trim()
            || state.selected.name;

        const id =
            document.createElement('span');

        id.className =
            'hpmc-print-label__id';

        id.textContent =
            state.selected.management_id;

        const category =
            document.createElement('em');

        category.className =
            'hpmc-print-label__category';

        category.textContent =
            state.selected.category_label || '';

        if (layout === 'id_focus') {
            info.append(
                brand,
                id,
                name,
                category
            );
        } else {
            info.append(
                brand,
                name,
                id,
                category
            );
        }

        return info;
    }

    function createPrintLabel(settings) {
        const label =
            document.createElement('article');

        label.style.width =
            `${settings.width}mm`;

        label.style.height =
            `${settings.height}mm`;

        if (settings.printMode === 'qr_only') {
            label.className =
                'hpmc-print-label '
                + 'hpmc-print-label--qr-only';

            label.append(createQrImage());

            return label;
        }

        label.className =
            'hpmc-print-label '
            + `hpmc-print-label--${settings.layout}`;

        const image = createQrImage();
        const info =
            createInfoBlock(settings.layout);

        if (
            settings.layout
            === 'identify_bottom'
        ) {
            label.append(image, info);
        } else {
            label.append(image, info);
        }

        return label;
    }

    function preparePrintArea() {
        const settings = currentSettings();

        el.printArea.replaceChildren();

        for (
            let index = 0;
            index < settings.copies;
            index += 1
        ) {
            el.printArea.append(
                createPrintLabel(settings)
            );
        }

        let style =
            document.getElementById(
                'hpmcDynamicPrintStyle'
            );

        if (!style) {
            style = document.createElement('style');
            style.id = 'hpmcDynamicPrintStyle';
            document.head.append(style);
        }

        style.textContent = `
            @page {
                size:
                    ${settings.width}mm
                    ${settings.height}mm;
                margin: 0;
            }

            @media print {
                .hpmc-print-only {
                    grid-template-columns:
                        repeat(
                            auto-fit,
                            ${settings.width}mm
                        );
                }
            }
        `;
    }

    function downloadQr() {
        if (
            !state.selected
            || !state.qrReady
        ) {
            window.alert(
                'QRコードがまだ生成されていません。'
            );

            return;
        }

        const link =
            document.createElement('a');

        link.download =
            `${state.selected.management_id}.png`;

        link.href =
            el.canvas.toDataURL(
                'image/png',
                1
            );

        link.click();
    }

    function escapePrintText(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function printLabelMarkup(settings) {
        if (!state.selected) {
            return '';
        }

        const qrDataUrl = el.canvas.toDataURL(
            'image/png',
            1
        );

        const assetName =
            el.name.value.trim()
            || state.selected.name
            || '名称未設定';

        const managementId =
            state.selected.management_id
            || '';

        const category =
            state.selected.category_label
            || '';

        if (settings.printMode === 'qr_only') {
            return `
                <article class="print-label print-label--qr-only">
                    <img
                        class="print-label__qr"
                        src="${qrDataUrl}"
                        alt="QRコード"
                    >
                </article>
            `;
        }

        const layoutClass =
            `print-label--${settings.layout}`;

        return `
            <article class="print-label ${layoutClass}">
                <img
                    class="print-label__qr"
                    src="${qrDataUrl}"
                    alt="QRコード"
                >

                <div class="print-label__information">
                    <small class="print-label__brand">
                        HC PROPERTY MANAGEMENT
                    </small>

                    ${
                        settings.layout === 'id_focus'
                            ? `
                                <span class="print-label__id">
                                    ${escapePrintText(
                                        managementId
                                    )}
                                </span>

                                <strong class="print-label__name">
                                    ${escapePrintText(
                                        assetName
                                    )}
                                </strong>
                            `
                            : `
                                <strong class="print-label__name">
                                    ${escapePrintText(
                                        assetName
                                    )}
                                </strong>

                                <span class="print-label__id">
                                    ${escapePrintText(
                                        managementId
                                    )}
                                </span>
                            `
                    }

                    <em class="print-label__category">
                        ${escapePrintText(category)}
                    </em>
                </div>
            </article>
        `;
    }

    function printDocumentHtml(settings) {
        const labels = [];

        for (
            let index = 0;
            index < settings.copies;
            index += 1
        ) {
            labels.push(
                printLabelMarkup(settings)
            );
        }

        const width =
            Number(settings.width) || 62;

        const height =
            Number(settings.height) || 29;

        return `<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title></title>

    <style>
        @page {
            size: ${width}mm ${height}mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: ${width}mm;
            min-width: ${width}mm;
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff;
            color: #000000;
        }

        body {
            overflow: visible;
            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                "Hiragino Sans",
                "Yu Gothic",
                sans-serif;
        }

        .print-label {
            width: ${width}mm;
            height: ${height}mm;
            margin: 0;
            padding: 2mm;
            overflow: hidden;
            break-after: page;
            page-break-after: always;
            background: #ffffff;
            color: #000000;
        }

        .print-label:last-child {
            break-after: auto;
            page-break-after: auto;
        }

        .print-label__qr {
            display: block;
            object-fit: contain;
            image-rendering: pixelated;
        }

        .print-label__information {
            min-width: 0;
            overflow: hidden;
        }

        .print-label__brand,
        .print-label__name,
        .print-label__id,
        .print-label__category {
            display: block;
            overflow-wrap: anywhere;
        }

        .print-label__brand {
            margin: 0 0 .8mm;
            color: #155bd7;
            font-size: 4.5pt;
            font-weight: 900;
            line-height: 1.15;
            letter-spacing: .06em;
        }

        .print-label__name {
            margin: 0 0 .8mm;
            color: #000000;
            font-size: 8.5pt;
            font-weight: 800;
            line-height: 1.12;
        }

        .print-label__id {
            color: #000000;
            font-family:
                ui-monospace,
                SFMono-Regular,
                Menlo,
                Monaco,
                Consolas,
                monospace;
            font-size: 5.4pt;
            font-weight: 800;
            line-height: 1.15;
        }

        .print-label__category {
            margin-top: .8mm;
            color: #475569;
            font-size: 5pt;
            font-style: normal;
            line-height: 1.1;
        }

        .print-label--qr-only {
            display: grid;
            place-items: center;
            padding: 1.5mm;
        }

        .print-label--qr-only
        .print-label__qr {
            width: min(
                calc(${width}mm - 3mm),
                calc(${height}mm - 3mm)
            );
            height: min(
                calc(${width}mm - 3mm),
                calc(${height}mm - 3mm)
            );
        }

        .print-label--visual_right {
            display: grid;
            grid-template-columns:
                minmax(0, 42%)
                minmax(0, 1fr);
            align-items: center;
            gap: 2mm;
        }

        .print-label--visual_right
        .print-label__qr {
            width: 100%;
            aspect-ratio: 1;
        }

        .print-label--identify_bottom {
            display: grid;
            grid-template-rows:
                minmax(0, 1fr)
                auto;
            align-items: center;
            justify-items: center;
            gap: 1mm;
        }

        .print-label--identify_bottom
        .print-label__qr {
            width: auto;
            height: 100%;
            max-width: 80%;
            aspect-ratio: 1;
        }

        .print-label--identify_bottom
        .print-label__information {
            width: 100%;
            text-align: center;
        }

        .print-label--identify_bottom
        .print-label__brand {
            display: none;
        }

        .print-label--identify_bottom
        .print-label__name {
            margin-bottom: .4mm;
            font-size: 6.5pt;
        }

        .print-label--compact_right {
            display: grid;
            grid-template-columns:
                minmax(0, 38%)
                minmax(0, 1fr);
            align-items: center;
            gap: 1.4mm;
            padding: 1.5mm;
        }

        .print-label--compact_right
        .print-label__qr {
            width: 100%;
            aspect-ratio: 1;
        }

        .print-label--compact_right
        .print-label__brand,
        .print-label--compact_right
        .print-label__category {
            display: none;
        }

        .print-label--compact_right
        .print-label__name {
            font-size: 6.5pt;
        }

        .print-label--compact_right
        .print-label__id {
            font-size: 4.7pt;
        }

        .print-label--id_focus {
            display: grid;
            grid-template-columns:
                minmax(0, 40%)
                minmax(0, 1fr);
            align-items: center;
            gap: 2mm;
        }

        .print-label--id_focus
        .print-label__qr {
            width: 100%;
            aspect-ratio: 1;
        }

        .print-label--id_focus
        .print-label__id {
            margin-bottom: 1mm;
            padding: 1mm;
            border-radius: .8mm;
            background: #000000;
            color: #ffffff;
            font-size: 5.5pt;
        }

        @media screen {
            body {
                background: #dfe3e8;
            }

            .print-label {
                box-shadow:
                    0 4px 16px
                    rgba(0, 0, 0, .18);
            }
        }

        @media print {
            html,
            body {
                width: ${width}mm !important;
                min-width: ${width}mm !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #ffffff !important;
            }

            .print-label {
                width: ${width}mm !important;
                height: ${height}mm !important;
                margin: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    ${labels.join('\n')}
</body>
</html>`;
    }

    function waitForPrintImages(
        printWindow
    ) {
        const images = Array.from(
            printWindow.document.images
        );

        if (images.length === 0) {
            return Promise.resolve();
        }

        return Promise.all(
            images.map((image) => {
                if (
                    image.complete
                    && image.naturalWidth > 0
                ) {
                    return Promise.resolve();
                }

                return new Promise((resolve) => {
                    image.addEventListener(
                        'load',
                        resolve,
                        {
                            once: true,
                        }
                    );

                    image.addEventListener(
                        'error',
                        resolve,
                        {
                            once: true,
                        }
                    );
                });
            })
        );
    }

    async function printQr() {
        if (
            !state.selected
            || !state.qrReady
        ) {
            window.alert(
                'QRコードがまだ生成されていません。'
            );

            return;
        }

        saveSettings();

        const settings = currentSettings();

        const iframe =
            document.createElement('iframe');

        iframe.setAttribute(
            'aria-hidden',
            'true'
        );

        iframe.tabIndex = -1;

        iframe.style.position = 'fixed';
        iframe.style.width = '1px';
        iframe.style.height = '1px';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.border = '0';
        iframe.style.opacity = '0';
        iframe.style.pointerEvents = 'none';

        document.body.append(iframe);

        const printWindow =
            iframe.contentWindow;

        const printDocument =
            iframe.contentDocument;

        if (
            !printWindow
            || !printDocument
        ) {
            iframe.remove();

            window.alert(
                '印刷画面を作成できませんでした。'
            );

            return;
        }

        const cleanup = () => {
            window.setTimeout(
                () => iframe.remove(),
                1000
            );
        };

        printWindow.addEventListener(
            'afterprint',
            cleanup,
            {
                once: true,
            }
        );

        printDocument.open();
        printDocument.write(
            printDocumentHtml(settings)
        );
        printDocument.close();

        try {
            await waitForPrintImages(
                printWindow
            );

            await new Promise((resolve) => {
                window.setTimeout(
                    resolve,
                    250
                );
            });

            printWindow.focus();
            printWindow.print();

            window.setTimeout(
                cleanup,
                30000
            );
        } catch (error) {
            cleanup();

            window.alert(
                '印刷データを作成できませんでした。'
            );
        }
    }

    async function loadAssets() {
        el.count.textContent =
            '読み込み中...';

        el.list.hidden = true;
        el.empty.hidden = true;

        try {
            const response = await fetch(
                '/staff/property/api/assets/list.php',
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                    },
                }
            );

            const responseText =
                await response.text();

            let data;

            try {
                data = JSON.parse(
                    responseText
                );
            } catch (error) {
                console.error(
                    'HPMC API response:',
                    responseText
                );

                throw new Error(
                    '一覧APIがJSON以外を返しました。'
                );
            }

            if (
                !response.ok
                || data.success !== true
            ) {
                throw new Error(
                    data.message
                    || '一覧を取得できませんでした。'
                );
            }

            state.assets = Array.isArray(
                data.assets
            )
                ? data.assets
                : [];

            renderList();

            const parameters =
                new URLSearchParams(
                    window.location.search
                );

            const requestedId =
                parameters.get('id');

            if (requestedId) {
                const asset =
                    state.assets.find(
                        (item) =>
                            item.management_id
                            === requestedId
                    );

                if (asset) {
                    selectAsset(asset);

                    const row =
                        el.list.querySelector(
                            `[data-management-id="${CSS.escape(
                                requestedId
                            )}"]`
                        );

                    if (row) {
                        row.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                        });
                    }
                }
            }
        } catch (error) {
            console.error(
                'HPMC asset loading error:',
                error
            );

            state.assets = [];

            el.list.replaceChildren();
            el.list.hidden = true;
            el.empty.hidden = false;

            el.count.textContent =
                '読み込みに失敗しました';

            const title =
                el.empty.querySelector(
                    'strong'
                );

            const description =
                el.empty.querySelector(
                    'p'
                );

            if (title) {
                title.textContent =
                    '備品・商品を読み込めませんでした';
            }

            if (description) {
                description.textContent =
                    error instanceof Error
                        ? error.message
                        : '一覧APIを確認してください。';
            }
        }
    }

    el.search.addEventListener(
        'input',
        renderList
    );

    el.category.addEventListener(
        'change',
        renderList
    );

    el.status.addEventListener(
        'change',
        renderList
    );

    el.clear.addEventListener(
        'click',
        () => {
            el.search.value = '';
            el.category.value = '';
            el.status.value = '';

            renderList();
            el.search.focus();
        }
    );

    el.list.addEventListener(
        'click',
        (event) => {
            const row = event.target.closest(
                '[data-management-id]'
            );

            if (!row) {
                return;
            }

            const asset = state.assets.find(
                (item) =>
                    item.management_id
                    === row.dataset.managementId
            );

            if (asset) {
                selectAsset(asset);
            }
        }
    );

    el.name.addEventListener(
        'input',
        () => {
            if (state.selected) {
                el.previewName.textContent =
                    el.name.value.trim()
                    || state.selected.name;
            }
        }
    );

    el.printMode.addEventListener(
        'change',
        updatePreviewLayout
    );

    el.layout.addEventListener(
        'change',
        updatePreviewLayout
    );

    el.size.addEventListener(
        'change',
        applySize
    );

    el.printer.addEventListener(
        'change',
        () => {
            const size =
                profiles[el.printer.value];

            if (size) {
                el.size.value = size;
                applySize();
            }
        }
    );

    el.download.addEventListener(
        'click',
        downloadQr
    );

    el.print.addEventListener(
        'click',
        printQr
    );

    clearCanvas();
    loadSettings();
    loadAssets();
})();
