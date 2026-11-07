<?php
/**
 * HC Platform Phase 2o - non-public review draft.
 * HC_PHASE2X_APPROVED_DRAFT_20261107: pricing and disclosure decisions recorded; NOT PUBLIC.
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
    <p>Minecraft用ゲームサーバーを一部検証・提供中で、正式リリース時の新料金は未確定です。利用可能なサービスの状況は<a href="/services/rental/game-server/">サービス案内</a>で確認できます。現に申込み可能なプランの価格表示・購入条件は、正式リリース前でも正確に整備する必要があります。</p>
    <p><strong>【公開前必須確認】</strong>正式なプラン料金はリリース時に確定します。今後の表示方針は「税抜価格を主表示、隣接する位置に見やすい税込総額を併記」です。税込総額を極端に小さく・薄くせず、申込前の一覧・注文・最終確認画面で一目で分かるようにします。決済時に税込総額を表示するだけでは不十分です。金額・税区分・端数処理・Stripeの実際の請求総額を検証するまで、未確定価格を掲載しません。現行プランの課金額を、新料金の税抜金額と取り違えません。</p>
    <p>初期費用：なし。追加ポート：正式リリース時点での提供有無が未定です。提供する場合は、有料オプションとして税込総額・課金周期・提供時期・解約・返金条件を購入前に明示します。提供開始までは購入可能なサービスとして案内しません。</p>

    <h2>支払方法・支払時期</h2>
    <p>支払いにはStripeを使用します。実際に利用可能な決済手段は申込画面とStripe側の有効な手段を確認して確定します。</p>
    <p>設計方針は手動更新を初期選択とし、希望者のみ自動更新を明示選択する方式です。ただし現在は安全確認のため手動更新の新規申込みを一時停止しています。自動更新を選んだ契約では原則として毎月の更新日に請求します。再開前に、単発決済と継続課金の分離、解約期限・更新日・支払方法・請求失敗時処理を実際の注文画面・Stripe・契約管理と一致させます。未実装の支払い方法を利用可能と表示しません。</p>

    <h2>提供開始時期</h2>
    <p>決済完了後にサーバーの自動構築処理を開始する構成です。構築完了の所要時間と、構築に失敗した場合の扱いは実運用を確認して確定します。</p>

    <h2>解約・更新・返金</h2>
    <p>利用者は解約でき、原則として支払済みの契約期間終了まで利用できます。手動更新と自動更新の停止手順・期限は、実装を検証して公開前に明記します。</p>
    <p>返金は<a href="/legal/refunds/">返金・解約ポリシー</a>の原稿で検討中です。現時点のドラフトは利用者への正式な契約条件ではありません。利用者の法令上の権利を制限しません。</p>

    <h2>お問い合わせ・販売者情報の開示請求</h2>
    <p>連絡窓口：<a href="/contact/">HC Platformのお問い合わせフォーム</a>。</p>
    <p>氏名・実際に事業を行う住所・確実に連絡可能な電話番号について、請求を受けた場合には法令に従い遅滞なく開示する方針です。通常の問い合わせは原則2営業日以内の初回回答を目指しますが、販売者情報の開示請求には購入判断前に十分な余裕をもって情報を渡せる迅速な別対応が必要です。受付通知・担当者への即時通知・安全な回答手段・対応記録の運用を確立するまで表示省略の適法性は未確認です。個人情報はソースコードに保存しません。</p>

    <h2>関連情報</h2>
    <p><a href="/terms/">利用規約</a> ／ <a href="/privacy/">プライバシーポリシー</a> ／ <a href="/operator/">運営情報</a></p>
    <p><strong>ドラフトのまま公開しないでください。</strong>購入画面の表示内容・事業者情報の開示方法・返金方針が確定したあとに、別途公開用ページを作成・審査します。</p>
  </article></div></section>
</main>
<?php include __DIR__ . '/../../parts/footer/footer.php'; ?>
<script src="/common/base.js"></script>
</body>
</html>
