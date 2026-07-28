<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 1) . '/components/layout.php';

staff_layout_start([
    'title' => '顧客管理',
    'heading' => '顧客管理',
    'eyebrow' => 'CUSTOMER MANAGEMENT',
    'description' => 'HC Account、契約、利用サービスを横断して確認します。',
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
