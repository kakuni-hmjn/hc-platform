<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

$pageTitle = 'QR読み取り';

require_once __DIR__ . '/../../components/layout.php';

staff_layout_start([
    'title' => 'QR読み取り',
    'heading' => 'QR読み取り',
    'eyebrow' => 'HPMC QR SCANNER',
    'description' => '物品のQRコードを読み取り、詳細ページを開きます。',
    'active_navigation' => 'property',
]);

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1785295375"
>

<?php $hpmcActive = 'scan'; ?>

<div class="hpmc-shell">


    <div class="hpmc-content">
<style>
.hpmc-scanner-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, .55fr);
    gap: 18px;
}

.hpmc-scanner-panel,
.hpmc-manual-panel {
    padding: 20px;
    border: 1px solid var(--staff-border, rgba(148, 163, 184, .22));
    border-radius: 22px;
    background: var(--staff-panel, rgba(255, 255, 255, .04));
}

.hpmc-camera {
    position: relative;
    min-height: 430px;
    overflow: hidden;
    display: grid;
    place-items: center;
    border-radius: 18px;
    background: #020617;
}

.hpmc-camera video {
    width: 100%;
    height: 100%;
    position: absolute;
    inset: 0;
    object-fit: cover;
}

.hpmc-camera__placeholder {
    z-index: 1;
    padding: 30px;
    color: #cbd5e1;
    text-align: center;
}

.hpmc-camera__placeholder strong {
    display: block;
    margin-bottom: 8px;
    color: #fff;
    font-size: 18px;
}

.hpmc-camera__frame {
    width: min(62%, 300px);
    aspect-ratio: 1;
    z-index: 2;
    position: absolute;
    border: 3px solid rgba(255, 255, 255, .9);
    border-radius: 24px;
    pointer-events: none;
}

.hpmc-actions {
    margin-top: 16px;
    display: flex;
    gap: 10px;
}

.hpmc-button {
    min-height: 42px;
    padding: 0 16px;
    border: 1px solid transparent;
    border-radius: 12px;
    background: #2563eb;
    color: #fff;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.hpmc-button--secondary {
    border-color: var(--staff-border, rgba(148, 163, 184, .22));
    background: transparent;
    color: inherit;
}

.hpmc-button:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.hpmc-message {
    min-height: 22px;
    margin: 13px 0 0;
    color: var(--staff-muted, #94a3b8);
    font-size: 13px;
    font-weight: 700;
}

.hpmc-message--error {
    color: #f87171;
}

.hpmc-message--success {
    color: #4ade80;
}

.hpmc-manual-panel h3 {
    margin: 0 0 8px;
}

.hpmc-manual-panel > p {
    margin: 0 0 20px;
    color: var(--staff-muted, #94a3b8);
    font-size: 13px;
    line-height: 1.7;
}

.hpmc-field {
    display: grid;
    gap: 8px;
}

.hpmc-field label {
    font-size: 12px;
    font-weight: 800;
}

.hpmc-field input {
    width: 100%;
    min-height: 44px;
    padding: 0 13px;
    border: 1px solid var(--staff-border, rgba(148, 163, 184, .22));
    border-radius: 12px;
    background: transparent;
    color: inherit;
    font: inherit;
}

.hpmc-manual-panel .hpmc-button {
    width: 100%;
    margin-top: 12px;
}

@media (max-width: 850px) {
    .hpmc-scanner-layout {
        grid-template-columns: 1fr;
    }

    .hpmc-camera {
        min-height: 360px;
    }
}

@media (max-width: 520px) {
    .hpmc-camera {
        min-height: 300px;
    }

    .hpmc-actions {
        flex-direction: column;
    }

    .hpmc-button {
        width: 100%;
    }
}
</style>

<div class="hpmc-scanner-layout">
    <section class="hpmc-scanner-panel">
        <div class="hpmc-camera">
            <video
                id="hpmcScannerVideo"
                playsinline
                muted
            ></video>

            <div
                id="hpmcScannerPlaceholder"
                class="hpmc-camera__placeholder"
            >
                <strong>カメラは停止中です</strong>

                「カメラを開始」を押して、
                QRコードを枠内に映してください。
            </div>

            <div
                class="hpmc-camera__frame"
                aria-hidden="true"
            ></div>
        </div>

        <div class="hpmc-actions">
            <button
                id="hpmcScannerStart"
                type="button"
                class="hpmc-button"
            >
                カメラを開始
            </button>

            <button
                id="hpmcScannerStop"
                type="button"
                class="hpmc-button hpmc-button--secondary"
                disabled
            >
                停止
            </button>
        </div>

        <p
            id="hpmcScannerMessage"
            class="hpmc-message"
            aria-live="polite"
        ></p>
    </section>

    <aside class="hpmc-manual-panel">
        <h3>管理番号を入力</h3>

        <p>
            カメラが使えない場合は、
            QRに記載された管理番号を入力してください。
        </p>

        <form id="hpmcManualForm">
            <div class="hpmc-field">
                <label for="hpmcManualCode">
                    管理番号
                </label>

                <input
                    id="hpmcManualCode"
                    type="text"
                    placeholder="HC-AST-JP-NGN-00001"
                    autocomplete="off"
                    required
                >
            </div>

            <button
                type="submit"
                class="hpmc-button"
            >
                物品詳細を開く
            </button>
        </form>
    </aside>
</div>

<script src="/staff/property/scan/zxing.min.js"></script>
<script src="/staff/property/scan/scanner.js?v=2"></script>

    </div>
</div>

<?php staff_layout_end(); ?>
