<?php
/**
 * HC Platform Phase 2o - non-public review draft.
 * DO NOT remove this fail-closed guard without a separate legal/content review.
 * No query string, cookie, HTTP header, or production environment flag enables this page.
 */
if (PHP_SAPI !== 'cli' || !defined('HC_LEGAL_DRAFT_RENDER') || HC_LEGAL_DRAFT_RENDER !== true) {
    http_response_code(404);
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: no-store');
    }
    exit;
}

$pageTitle = '【非公開・校閲用】特定商取引法に基づく表記 | HC Platform';
$pageDescription = '校閲用ドラフト。公開・販売条件の確定前につき未発効です。';
$pageCss = '/terms/terms.css';
require_once __DIR__ . '/../../parts/head.php';
?>
<body>
<?php include __DIR__ . '/../../parts/header/header.php'; ?>
<main class="legal-page">
  <section class="legal-hero"><div class="container"><div class="legal-hero-content">
    <p class="eyebrow">INTERNAL REVIEW DRAFT — NOT PUBLIC</p>
    <h1>特定商取引法に基づく表記（校閲用ドラフト）</h1>
    <p>このページは公開前の原稿です。販売条件や情報開示体制の検証が完了するまで、公開・運用しません。</p>
  </div></div></section>
  <section class="section legal-section"><div class="container legal-container"><article class="legal-card">
    <h2>事業者</h2>
    <p>運営名称：HMJn Company ／ 運営形態：個人事業（法人登記なし）</p>
    <p><strong>【公開前必須確認】</strong>個人事業者本人の法的氏名・事業上の住所・連絡可能な電話番号について、法定要件を満たす表示または表示省略と遅滞ない開示体制を整備します。屋号は法的氏名の代わりになりません。本人情報は本リポジトリに記録しません。</p>
    <p>データセンター所在地：長野県（事業者住所の表示ではありません）</p>

    <h2>販売する役務・価格</h2>
    <p>Minecraft用ゲームサーバーの月額提供を予定しています。サービスの実際の提供状況は<a href="/services/rental/game-server/">サービス案内</a>で確認できます。</p>
    <p><strong>【公開前必須確認】</strong>各プラン・追加ポートの消費税込みの販売総額、選択可能な構成、追加料金を販売画面・最終確認画面・Stripeで一致させます。過去の料金案は未確定のため、この原稿に金額を掲載しません。</p>
    <p>初期費用：なし。追加ポート：有料（単価・課金周期・返金条件は未確定）。</p>

    <h2>支払方法・支払時期</h2>
    <p>支払いにはStripeを使用します。実際に利用可能な決済手段は申込画面とStripe側の有効な手段を確認して確定します。</p>
    <p>手動更新（初期選択）と月額自動更新を選択できます。手動更新では毎期利用者が支払い、自動更新では原則として翌月の契約更新日に次回の支払いが行われます。決済に使う保存手段・失敗時処理・請求確定時点・次回請求予定日は公開前に実装と照合します。</p>

    <h2>提供開始時期</h2>
    <p>決済完了後にサーバーの自動構築処理を開始する構成です。構築完了の所要時間と、構築に失敗した場合の扱いは実運用を確認して確定します。</p>

    <h2>解約・更新・返金</h2>
    <p>利用者は解約でき、原則として支払済みの契約期間終了まで利用できます。手動更新と自動更新の停止手順・期限は、実装を検証して公開前に明記します。</p>
    <p>返金は<a href="/legal/refunds/">返金・解約ポリシー</a>の原稿で検討中です。現時点のドラフトは利用者への正式な契約条件ではありません。利用者の法令上の権利を制限しません。</p>

    <h2>お問い合わせ・販売者情報の開示請求</h2>
    <p>連絡窓口：<a href="/contact/">HC Platformのお問い合わせフォーム</a>。</p>
    <p>運用目標は原則2営業日以内の初回回答です。ただし、特定商取引法の「遅滞なく」は固定の2営業日基準ではなく、購入を決める前に十分な余裕を持って情報を提供できることが必要です。販売者情報の開示請求には契約前に適切に対応できる体制と実績を確認します。</p>

    <h2>関連情報</h2>
    <p><a href="/terms/">利用規約</a> ／ <a href="/privacy/">プライバシーポリシー</a> ／ <a href="/operator/">運営情報</a></p>
    <p><strong>ドラフトのまま公開しないでください。</strong>購入画面の表示内容・事業者情報の開示方法・返金方針が確定したあとに、別途公開用ページを作成・審査します。</p>
  </article></div></section>
</main>
<?php include __DIR__ . '/../../parts/footer/footer.php'; ?>
<script src="/common/base.js"></script>
</body>
</html>
