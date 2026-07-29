<?php

declare(strict_types=1);

require_once dirname(__DIR__, 1) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 1) . '/components/layout.php';

staff_layout_start([
    'title' => 'レンタルサーバー事業',
    'heading' => 'レンタルサーバー事業',
    'eyebrow' => 'RENTAL SERVER BUSINESS',
    'description' => 'レンタルサーバー事業全体の契約、稼働状況、売上、対応状況を確認します。',
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
