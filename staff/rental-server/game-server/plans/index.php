<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 3) . '/components/layout.php';

staff_layout_start([
    'title' => 'プラン管理',
    'heading' => 'プラン管理',
    'eyebrow' => 'GAME SERVER PLANS',
    'description' => '提供プラン、価格、リソース、Stripe連携を管理します。',
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
