<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';

staff_layout_start([
    'title' => 'ゲームサーバー設定',
    'heading' => 'ゲームサーバー設定',
    'eyebrow' => 'GAME SERVER SETTINGS',
    'description' => '作成ルール、自動化、承認、通知などを設定します。',
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
