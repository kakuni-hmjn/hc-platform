<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "Minecraftのサーバーソフト比較 | HC Platform";
$pageDescription = "Vanilla、Paper、Fabric、Forge、NeoForgeの違いと選び方。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">SERVER SOFTWARE</p>
<h1>Vanilla・Paper・Fabricの違い</h1>
<p class="hc-guide-lead">「プラグインを入れたい」「MODを使いたい」を出発点に、互換性を確かめて選ぶ方法を解説します。</p>
<?php require __DIR__ . '/../../_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<article class="hc-guide-article"><p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / <a href="/guides/minecraft/">Minecraft</a> / サーバーソフトの選び方</p>
<section class="hc-guide-section"><h2>最初に決めるべきこと</h2><p>サーバーソフトを選ぶ前に、参加者がJava版・統合版のどちらで遊ぶのか、必要な機能がプラグインで実現できるのか、それともMODが必要なのかを整理します。後からソフトを変更するとワールドや設定の互換性確認が必要になるため、最初の選択が重要です。</p>
<div class="hc-guide-table-wrap"><table><thead><tr><th>種類</th><th>主な用途</th><th>注意点</th></tr></thead><tbody>
<tr><td>Vanilla（公式）</td><td>追加機能を使わない、標準のJava版マルチ</td><td>Paper用プラグインやFabric用MODはそのまま動きません。</td></tr>
<tr><td>Paper</td><td>Java版でプラグインを使った運用</td><td>プラグインごとの対応バージョンを確認。Vanillaとは一部挙動が異なる場合があります。</td></tr>
<tr><td>Fabric</td><td>Fabric向けMODで機能や処理を変更する構成</td><td>クライアント側にも同じMODが必要なものがあります。</td></tr>
<tr><td>Forge / NeoForge</td><td>各ローダーに対応するMODを利用</td><td>ローダーが異なるMODを混在させることは基本的にできず、版の互換性が重要です。</td></tr>
</tbody></table></div></section>
<section class="hc-guide-section"><h2>プラグインとMODは同じではない</h2><p>プラグインはサーバー側で機能を追加する方法で、Paper系では権限管理や保護、運営補助などに使われます。一方、MODはブロックやシステムの追加などゲーム内容を大きく変更できる反面、必要に応じて参加者のゲーム側にも導入が必要です。</p><p>「Paperを使えばすべてのMODが動く」「Fabric用MODはPaperのpluginsフォルダに入れれば動く」といった理解は誤りです。サーバーソフトと拡張機能の配布ページで、対応ローダー・ゲームバージョン・依存関係を確認してください。</p></section>
<section class="hc-guide-section"><h2>実際に選ぶ手順</h2><ol><li>参加者のエディション（Java版・統合版）とゲームバージョンを揃える。</li><li>導入したい機能を「標準」「プラグイン」「MOD」に分ける。</li><li>対応するサーバーソフトを選び、少人数で起動・接続・負荷を検証する。</li><li>サーバーソフト変更前にワールド、設定ファイル、プラグイン・MOD一式をバックアップする。</li></ol><div class="hc-guide-note"><p><strong>よくある混同：</strong>Java版のPaperサーバーに統合版プレイヤーを接続させたい場合、PaperだけではなくGeyser等による接続方式の追加検討が必要です。詳しくは<a href="/guides/minecraft/crossplay/">クロスプレイの解説</a>を参照してください。</p></div></section>
<section class="hc-guide-next"><h2>参考資料</h2><p><a href="https://docs.papermc.io/paper/getting-started/">PaperMC公式：Getting started</a>。実際のJavaバージョン要件や最新の構築方法は公式情報を優先してください。</p><p><a class="hc-guide-backlink" href="/guides/minecraft/">Minecraftガイド一覧へ戻る</a></p><p class="hc-guide-meta">編集：HC Platform ／ 2026年11月6日公開用原稿</p></section></article>
</div></main>
<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
