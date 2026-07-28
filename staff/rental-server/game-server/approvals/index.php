<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';

staff_layout_start([
    'title' => '作成承認',
    'heading' => '作成承認',
    'eyebrow' => 'GAME SERVER APPROVALS',
    'description' => '作成済みサーバーの確認と利用開始承認を行います。',
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
