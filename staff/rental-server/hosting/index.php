<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/components/layout.php';

staff_layout_start([
    'title' => 'ホスティング',
    'heading' => 'ホスティング',
    'eyebrow' => 'HOSTING SERVICE',
    'description' => 'ホスティング事業の管理機能を追加する予定です。',
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
