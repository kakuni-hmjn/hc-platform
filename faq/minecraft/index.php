<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "MinecraftサーバーFAQ | HC Platform";
$pageDescription = "Minecraftサーバーのメモリ、Paper、MOD、TPS、クロスプレイのよくある質問。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-faq">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">MINECRAFT FAQ</p>
<h1>Minecraft よくある質問</h1>
<p class="hc-guide-lead">Minecraftサーバーの導入や運用でよくある疑問を、技術的な観点からまとめました。</p>
<?php require __DIR__ . '/../../guides/_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / Minecraft FAQ</p><article class="hc-guide-article"><section class="hc-guide-section"><h2>構築・運用について</h2><p>各回答は一般的なMinecraftサーバー運用の知識です。HC Platformの特定プラン・機能がすでに提供中であることを意味しません。</p></section>
<section class="hc-guide-faq-item"><h2>Q. 4GBや8GBのサーバーで何人まで遊べますか？</h2><p>メモリ容量だけでは決まりません。CPU性能、ゲームバージョン、プラグインやMOD、描画距離、参加者の行動で大きく変わります。負荷を実測し、<a href="/guides/minecraft/memory/">TPS・MSPTも含めて</a>余裕のある構成を選んでください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. VanillaとPaperはどちらを選ぶべきですか？</h2><p>拡張を使わず標準の動作を重視するならVanilla、Paper向けプラグインを利用するならPaperが選択肢です。既存ワールド移行では必ずバックアップを取り、互換性を確認してください。<a href="/guides/minecraft/server-types/">比較ガイド</a>に詳細があります。</p></section>
<section class="hc-guide-faq-item"><h2>Q. PaperでMODを使えますか？</h2><p>Fabric・Forge・NeoForge向けのMODを、そのままPaperのpluginsフォルダに入れて動かすことはできません。各ソフト向けの拡張形式を確認してください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. Java版と統合版は一緒に遊べますか？</h2><p>Geyserなどを使えばJava版サーバーに統合版から接続できる構成があります。ただし追加設定やポート、認証対応が必要で、すべての環境で無条件に動くわけではありません。<a href="/guides/minecraft/crossplay/">クロスプレイガイド</a>を参照してください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. TPSが20なのにブロック破壊が遅れて見えるのはなぜですか？</h2><p>平均TPSが正常でも、一時的な処理遅延やネットワークの遅れが原因になる場合があります。MSPT・プロファイリング・パケットロス・クライアントの状態を分けて確認します。</p></section>
<section class="hc-guide-faq-item"><h2>Q. Javaの-Xmxを契約メモリ上限と同じにしてよいですか？</h2><p>推奨しません。Javaヒープ以外のメモリも必要なため、OS・コンテナ・プロセスの動作に余裕を確保し、実際の使用量を監視してください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. ワールドデータのバックアップは必要ですか？</h2><p>必要です。アップデートやソフト変更、MOD入れ替えの前にワールドと設定を保存し、実際に復元できるか確かめてください。稼働中のデータコピーが不整合を生むこともあるため、停止や安全なバックアップ方法を検討します。</p></section>
<section class="hc-guide-faq-item"><h2>Q. サーバーは起動しているのに接続できません。</h2><p>使用エディションとバージョン、接続先アドレスとポート、ファイアウォール、ホワイトリスト、認証設定を順に確認してください。原因を断定せず、サーバーログのエラーを起点に調べます。</p></section>
<section class="hc-guide-faq-item"><h2>Q. HCのレンタルサーバーは現在申込できますか？</h2><p>サービスごとの提供状況は<a href="/services/">公式サービス一覧</a>を確認してください。このFAQでは、決済や自動構築など開発中の機能について、利用可能であるとの案内は行っていません。</p></section>
<section class="hc-guide-faq-item"><h2>Q. 問い合わせはどこから送れますか？</h2><p><a href="/contact/">お問い合わせフォーム</a>をご利用ください。契約中のサポート機能がある場合は、ログイン後の案内をご確認ください。</p></section>
<section class="hc-guide-next"><h2>さらに詳しく</h2><p>まずは<a href="/guides/minecraft/">Minecraft技術ガイド一覧</a>から、構築目的に合う記事を読んでください。</p><p class="hc-guide-meta">編集：HC Platform ／ 2026年11月6日公開用原稿</p></section></article>
</div></main>
<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
