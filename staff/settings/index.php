<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 1) . '/components/layout.php';

staff_layout_start([
    'title' => 'システム設定',
    'heading' => 'システム設定',
    'eyebrow' => 'SYSTEM SETTINGS',
    'description' => 'Staff ConsoleとHC Platformの共通設定を管理します。',
]);

?>
<section class="staff-panel">
    <div class="staff-empty-state">
        <strong>この管理機能は現在構築中です</strong>
        <p>
            Staff Consoleの共通レイアウトと
            ナビゲーションへの接続は完了しています。
        </p>
    </div>
</section>
<?php

staff_layout_end();
