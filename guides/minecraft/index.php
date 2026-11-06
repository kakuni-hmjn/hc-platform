<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "Minecraftサーバー技術ガイド | HC Platform";
$pageDescription = "Minecraftのサーバーソフト、メモリ、TPS・MSPT、クロスプレイを解説。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">MINECRAFT GUIDES</p>
<h1>Minecraftサーバー</h1>
<p class="hc-guide-lead">サーバーソフトの選び方から負荷の測定、クロスプレイまで、目的ごとに技術記事を探せます。</p>
<?php require __DIR__ . '/../_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / Minecraft</p><section class="hc-guide-section"><h2>Minecraftの技術情報</h2><p>マルチサーバーではソフトの選択、メモリやCPUの負荷、参加者のエディションなどが快適性に影響します。サーバー構築前に構成を整理し、運用中は計測結果から設定を見直しましょう。</p><div class="hc-guide-grid">
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/minecraft/server-types/">サーバーソフト比較</a></h3><p>Vanilla、Paper、Fabric、Forge、NeoForgeとプラグイン・MODの違い。</p></article>
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/minecraft/memory/">メモリ・TPS・MSPT</a></h3><p>プレイヤー人数だけに頼らず、実際の処理負荷から容量を考える。</p></article>
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/minecraft/crossplay/">Java版・統合版クロスプレイ</a></h3><p>Geyserを使う接続方式と、ポート・認証の注意点。</p></article>
</div></section><section class="hc-guide-section"><h2>運用前の確認</h2><ol><li>遊ぶエディションとバージョンを統一する。</li><li>導入したいMOD・プラグインの対応状況を確認する。</li><li>ワールドデータと設定をバックアップする。</li><li>TPS・MSPT、リソース使用量、接続品質を必要に応じて観測する。</li></ol><p>Minecraftについての個別の質問は<a href="/faq/minecraft/">Minecraft FAQ</a>を確認してください。</p></section><section class="hc-guide-section"><h2>HCの提供状況について</h2><p>このカテゴリの内容は一般的な技術解説です。HCのレンタルサーバーで掲載した構成をそのまま利用できるという案内ではありません。最新の提供状況は<a href="/services/">サービス一覧</a>でご確認ください。</p></section>
</div></main>
<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
