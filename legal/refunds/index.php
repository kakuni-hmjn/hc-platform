<?php
/**
 * HC Platform Phase 2o - refund policy PROPOSAL ONLY.
 * Non-public by construction. Requires separate operator acceptance and legal review.
 */
if (PHP_SAPI !== 'cli' || !defined('HC_LEGAL_DRAFT_RENDER') || HC_LEGAL_DRAFT_RENDER !== true) {
    http_response_code(404);
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: no-store');
    }
    exit;
}

$pageTitle = '【非公開・提案】返金・解約ポリシー | HC Platform';
$pageDescription = '校閲用の返金・解約案。正式な契約条件ではありません。';
$pageCss = '/terms/terms.css';
require_once __DIR__ . '/../../parts/head.php';
?>
<body>
<?php include __DIR__ . '/../../parts/header/header.php'; ?>
<main class="legal-page">
  <section class="legal-hero"><div class="container"><div class="legal-hero-content">
    <p class="eyebrow">PROPOSAL — NOT EFFECTIVE</p>
    <h1>返金・解約ポリシー（非公開・検討案）</h1>
    <p>この内容は利用者に適用される規約ではなく、HC Platform運営者が承認するための提案です。</p>
  </div></div></section>
  <section class="section legal-section"><div class="container legal-container"><article class="legal-card">
    <h2>1. 支払済み期間と解約</h2>
    <p>解約手続後も、原則として支払済みの契約期間終了までサーバーを利用できます。手動更新では利用者が次期料金を支払わなければ更新しない設計を確認します。自動更新では、更新前に解約が成立した場合、次回以降の継続課金を停止します。次回課金停止の締切・画面操作を実装と照合して明示します。</p>

    <h2>2. 利用者都合の途中解約（提案）</h2>
    <p>サーバーの提供開始後、利用者の都合で途中解約する場合、原則として残り期間の日割返金は行わず、支払済み期間の末日まで利用できる方式を提案します。ただし、個別に認める場合、契約内容の不適合、法令上の権利や義務がある場合はそれらを優先します。提供開始前に解約の申し出があった場合の扱いは次項に従って検討します。</p>

    <h2>3. 提供前・自動構築失敗（提案）</h2>
    <p>決済済みでサーバーの提供が開始されていない場合、利用者からの申し出により全額返金を検討します。自動構築失敗等で提供できないときは、状況を確認し、再構築か全額返金を利用者と相談して対応します。具体的な復旧期限・申請受付方法は公開前に確定します。</p>

    <h2>4. HC側の障害・提供内容の不備（提案）</h2>
    <p>HC側の問題で契約に沿った提供ができなかった場合、障害の範囲・時間・契約条件に応じて全額または一部の返金、あるいは利用者が同意する場合の利用期間延長等を検討します。対応方法は実際の損害・法令上の権利を踏まえ個別に決め、運営者の故意・重過失その他法令上の責任を一律に排除しません。</p>

    <h2>5. 二重請求・追加ポート・更新（提案）</h2>
    <p>重複して徴収した料金が判明した場合は調査して過剰分の返金を行います。追加ポート等の有料オプションは、本体サービスと別に価格・課金周期・開始日・終了日・解約および返金方法を公開前に確定します。自動更新の解約と過去の決済に対する返金は異なる操作です。</p>

    <h2>6. 返金の申請と処理（提案）</h2>
    <p>依頼は<a href="/contact/">お問い合わせフォーム</a>で受け付け、注文情報・決済状態・二重返金の有無を照合して運営者が判断します。返金処理は原則としてStripe上で元の支払いに対して行い、必要に応じて全額・一部返金を使い分けます。カード明細への反映時期は支払方法や金融機関によって異なります。</p>
    <p>返金実施前には、対象決済の環境（LIVE/TEST）、決済ID、既返金額、契約状態、オプション分を読み取りで確認します。初期運用ではWebhook・返金API・サーバー削除処理と自動連動させません。</p>

    <h2>7. 既存契約への適用</h2>
    <p>本案の内容を、すでに成立した契約に遡って一方的に適用することはしません。既存利用者の注文時点での合意内容と法令を確認して個別に対応します。</p>

    <p><strong>レビュー必須：</strong>料金・購入確認画面・返金例外・自動更新の締切・データ保管期間・追加ポートの返金単位が確定するまで、正式な契約条件として公開できません。</p>
    <p><a href="/legal/commerce/">特定商取引法表示の原稿</a> ／ <a href="/terms/">現行利用規約</a></p>
  </article></div></section>
</main>
<?php include __DIR__ . '/../../parts/footer/footer.php'; ?>
<script src="/common/base.js"></script>
</body>
</html>
