<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';

staff_layout_start([
    'title' => 'Node管理',
    'heading' => 'Node管理',
    'eyebrow' => 'GAME SERVER NODES',
    'description' => 'ゲームサーバーNodeの容量と稼働状態を管理します。',
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
