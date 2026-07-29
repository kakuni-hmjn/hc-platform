<?php

declare(strict_types=1);

require_once __DIR__ . '/../../lib/bootstrap.php';

$propertyId = trim(
    (string) (
        $_GET['id']
        ?? $_GET['code']
        ?? ''
    )
);

$pageTitle = '物品詳細';

require_once __DIR__ . '/../../components/layout.php';

staff_layout_start([
    'title' => '物品詳細',
    'heading' => '物品詳細',
    'eyebrow' => 'HPMC PROPERTY DETAIL',
    'description' => '物品の登録情報と現在地を確認します。',
    'active_navigation' => 'property',
]);

?>
<style>
.hpmc-detail-card,
.hpmc-empty {
    padding: 22px;
    border: 1px solid var(--staff-border, rgba(148, 163, 184, .22));
    border-radius: 22px;
    background: var(--staff-panel, rgba(255, 255, 255, .04));
}

.hpmc-detail-head {
    padding-bottom: 20px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    border-bottom: 1px solid var(--staff-border, rgba(148, 163, 184, .22));
}

.hpmc-detail-head span {
    display: block;
    margin-bottom: 6px;
    color: var(--staff-muted, #94a3b8);
    font-size: 11px;
    font-weight: 800;
}

.hpmc-detail-head h2 {
    margin: 0;
    font-size: clamp(22px, 3vw, 34px);
    word-break: break-all;
}

.hpmc-detail-status {
    padding: 7px 11px;
    border-radius: 999px;
    background: rgba(245, 158, 11, .12);
    color: #fbbf24;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.hpmc-detail-grid {
    margin: 20px 0 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.hpmc-detail-grid > div {
    padding: 15px;
    border: 1px solid var(--staff-border, rgba(148, 163, 184, .18));
    border-radius: 16px;
    background: rgba(148, 163, 184, .04);
}

.hpmc-detail-grid dt {
    margin-bottom: 6px;
    color: var(--staff-muted, #94a3b8);
    font-size: 11px;
    font-weight: 800;
}

.hpmc-detail-grid dd {
    margin: 0;
    font-size: 14px;
    line-height: 1.7;
    font-weight: 700;
}

.hpmc-notice {
    margin-top: 16px;
    padding: 16px 18px;
    border: 1px solid rgba(59, 130, 246, .24);
    border-radius: 16px;
    background: rgba(59, 130, 246, .07);
}

.hpmc-notice strong {
    display: block;
    margin-bottom: 5px;
}

.hpmc-notice p,
.hpmc-empty p {
    margin: 0;
    color: var(--staff-muted, #94a3b8);
    font-size: 13px;
    line-height: 1.7;
}

.hpmc-button {
    min-height: 42px;
    margin-top: 18px;
    padding: 0 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #2563eb;
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
}

@media (max-width: 650px) {
    .hpmc-detail-grid {
        grid-template-columns: 1fr;
    }

    .hpmc-detail-head {
        flex-direction: column;
    }

    .hpmc-button {
        width: 100%;
    }
}
</style>

<?php if ($propertyId === ''): ?>
    <section class="hpmc-empty">
        <h2>管理番号が指定されていません</h2>

        <p>
            QRコードを読み取るか、
            管理番号を入力してください。
        </p>

        <a
            href="/staff/property/scan/"
            class="hpmc-button"
        >
            QR読み取りへ
        </a>
    </section>
<?php else: ?>
    <article class="hpmc-detail-card">
        <header class="hpmc-detail-head">
            <div>
                <span>管理番号</span>

                <h2>
                    <?= htmlspecialchars(
                        $propertyId,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h2>
            </div>

            <strong class="hpmc-detail-status">
                未登録
            </strong>
        </header>

        <dl class="hpmc-detail-grid">
            <?php
            $fields = [
                '物品名' => '未登録',
                'カテゴリ' => '未登録',
                '状態' => '未登録',
                '現在のロケーション' => '未登録',
                'メーカー' => '未登録',
                '型番' => '未登録',
                'シリアル番号' => '未登録',
                '購入日' => '未登録',
                '保証期限' => '未登録',
                '備考' => '未登録',
            ];
            ?>

            <?php foreach ($fields as $label => $value): ?>
                <div>
                    <dt>
                        <?= htmlspecialchars(
                            $label,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </dt>

                    <dd>
                        <?= htmlspecialchars(
                            $value,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </article>

    <section class="hpmc-notice">
        <strong>物品DB接続前の初期画面です</strong>

        <p>
            現在はQRから取得した管理番号のみを表示します。
            次の段階で登録、編集、写真、ロケーション、
            移動履歴を接続します。
        </p>
    </section>
<?php endif; ?>

<?php staff_layout_end(); ?>
