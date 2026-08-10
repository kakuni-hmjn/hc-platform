<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';
require_once __DIR__ . '/../../components/layout.php';
require_once __DIR__ . '/../lib/data.php';

$settings = hpmc_load_settings();

staff_layout_start([
    'title' => 'HPMC設定',
    'heading' => '設定',
    'eyebrow' => 'HPMC SETTINGS',
    'description' => '管理ID、在庫基準、既定ロケーション、印刷設定を変更します。',
    'active_navigation' => 'property',
]);

?>
<link
    rel="stylesheet"
    href="/staff/property/assets/property.css?v=1786500000"
>

<div class="hpmc-content">
    <form id="hpmcSettingsForm">
        <section class="hpmc-panel">
            <header class="hpmc-panel__heading">
                <div>
                    <h3>在庫管理</h3>

                    <p>
                        ダッシュボードと在庫管理で使用します。
                    </p>
                </div>
            </header>

            <div class="hpmc-form-grid">
                <div class="hpmc-field">
                    <label for="lowStockThreshold">
                        低在庫と判定する数量
                    </label>

                    <input
                        id="lowStockThreshold"
                        name="low_stock_threshold"
                        type="number"
                        min="0"
                        value="<?= hpmc_escape(
                            $settings[
                                'low_stock_threshold'
                            ]
                        ) ?>"
                    >
                </div>
            </div>
        </section>

        <section class="hpmc-panel">
            <header class="hpmc-panel__heading">
                <div>
                    <h3>管理ID</h3>
                </div>
            </header>

            <div class="hpmc-form-grid">
                <div class="hpmc-field">
                    <label for="idPrefix">
                        ID接頭辞
                    </label>

                    <input
                        id="idPrefix"
                        name="id_prefix"
                        type="text"
                        value="<?= hpmc_escape(
                            $settings['id_prefix']
                        ) ?>"
                    >
                </div>

                <div class="hpmc-field">
                    <label for="idSequenceDigits">
                        連番桁数
                    </label>

                    <input
                        id="idSequenceDigits"
                        name="id_sequence_digits"
                        type="number"
                        min="4"
                        max="10"
                        value="<?= hpmc_escape(
                            $settings[
                                'id_sequence_digits'
                            ]
                        ) ?>"
                    >
                </div>
            </div>
        </section>

        <section class="hpmc-panel">
            <header class="hpmc-panel__heading">
                <div>
                    <h3>既定ロケーション</h3>
                </div>
            </header>

            <div class="hpmc-form-grid">
                <div class="hpmc-field">
                    <label for="defaultCountryCode">
                        国コード
                    </label>

                    <input
                        id="defaultCountryCode"
                        name="default_country_code"
                        type="text"
                        value="<?= hpmc_escape(
                            $settings[
                                'default_country_code'
                            ]
                        ) ?>"
                    >
                </div>

                <div class="hpmc-field">
                    <label for="defaultPrefectureCode">
                        都道府県コード
                    </label>

                    <input
                        id="defaultPrefectureCode"
                        name="default_prefecture_code"
                        type="text"
                        value="<?= hpmc_escape(
                            $settings[
                                'default_prefecture_code'
                            ]
                        ) ?>"
                    >
                </div>

                <div class="hpmc-field">
                    <label for="defaultSiteCode">
                        拠点コード
                    </label>

                    <input
                        id="defaultSiteCode"
                        name="default_site_code"
                        type="text"
                        value="<?= hpmc_escape(
                            $settings[
                                'default_site_code'
                            ]
                        ) ?>"
                    >
                </div>
            </div>
        </section>

        <section class="hpmc-panel">
            <header class="hpmc-panel__heading">
                <div>
                    <h3>QRラベル</h3>
                </div>
            </header>

            <div class="hpmc-form-grid">
                <div class="hpmc-field">
                    <label for="defaultLabelSize">
                        既定ラベルサイズ
                    </label>

                    <select
                        id="defaultLabelSize"
                        name="default_label_size"
                    >
                        <?php foreach (
                            [
                                '62x29' => '62 × 29 mm',
                                '62x40' => '62 × 40 mm',
                                '54x25' => '54 × 25 mm',
                                '40x30' => '40 × 30 mm',
                                '29x20' => '29 × 20 mm',
                            ]
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= hpmc_escape($value) ?>"
                                <?= (
                                    $settings[
                                        'default_label_size'
                                    ] === $value
                                        ? 'selected'
                                        : ''
                                ) ?>
                            >
                                <?= hpmc_escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="hpmc-field">
                    <label for="defaultLabelLayout">
                        既定レイアウト
                    </label>

                    <select
                        id="defaultLabelLayout"
                        name="default_label_layout"
                    >
                        <?php foreach (
                            [
                                'visual_right' =>
                                    '横型・見た目重視',
                                'identify_bottom' =>
                                    '下部識別型',
                                'compact_right' =>
                                    'コンパクト横型',
                                'id_focus' =>
                                    '管理ID強調型',
                            ]
                            as $value => $label
                        ): ?>
                            <option
                                value="<?= hpmc_escape($value) ?>"
                                <?= (
                                    $settings[
                                        'default_label_layout'
                                    ] === $value
                                        ? 'selected'
                                        : ''
                                ) ?>
                            >
                                <?= hpmc_escape($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="hpmc-register-footer">
            <div>
                <strong>HPMC設定</strong>

                <p id="settingsMessage"></p>
            </div>

            <button
                id="settingsSaveButton"
                type="submit"
                class="hpmc-primary-button"
            >
                <span class="material-icons">
                    save
                </span>

                設定を保存
            </button>
        </section>
    </form>
</div>

<script src="/staff/property/settings/settings.js?v=1"></script>

<?php staff_layout_end(); ?>
