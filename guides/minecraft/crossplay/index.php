<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "MinecraftのJava版・統合版クロスプレイ | HC Platform";
$pageDescription = "Geyserの構成、認証、ポートの注意点。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">CROSSPLAY</p>
<h1>Java版と統合版のクロスプレイ</h1>
<p class="hc-guide-lead">Geyserが何をしているか、必要な通信と認証を整理します。</p>
<?php require __DIR__ . '/../../_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<article class="hc-guide-article"><p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / <a href="/guides/minecraft/">Minecraft</a> / クロスプレイ</p>
<section class="hc-guide-section"><h2>Java版と統合版は、そのままでは同じサーバーに入れない</h2><p>Minecraft Java Edition向けのサーバーとBedrock Edition（統合版）向けのクライアントは通信方式が異なります。両者で同じワールドに参加できる構成の一つがGeyserです。Geyserは統合版クライアントからの通信をJava版サーバー向けに変換する仕組みで、Paperやプロキシと組み合わせて使用します。</p><p>導入するだけで完全に同一のゲーム体験になるわけではありません。ゲーム機などの接続条件、両エディションの仕様差、ゲームバージョン、プラグインとの相性を検証する必要があります。</p></section>
<section class="hc-guide-section"><h2>何を用意するか</h2><ol><li><strong>Java版サーバー：</strong>Paperなど、使う構成に合わせて用意する。</li><li><strong>Geyser：</strong>使用するサーバーソフトやプロキシに対応するビルドを選ぶ。</li><li><strong>接続用ポート：</strong>統合版の接続に使うポートと通信方式を確認する。Java版の標準ポートとは別の設定が必要になる場合がある。</li><li><strong>認証方式：</strong>統合版ユーザーの認証方法を決める。必要に応じてFloodgateを検討する。</li><li><strong>接続テスト：</strong>Java版と統合版の両方から接続し、移動・インベントリ・プラグイン動作まで確認する。</li></ol></section>
<section class="hc-guide-section"><h2>ネットワーク設定でつまずきやすい点</h2><p>ゲームサーバーの管理画面でポートが割り当てられていても、ファイアウォールやポート転送、プロキシの設定が一致しなければ外部から接続できません。またGeyserの利用ポートを別のサービスと重複させないことが重要です。最新のGeyserはバージョンや構成により通信要件が変わる場合があるため、具体的な設定値は公式セットアップガイドを参照してください。</p><div class="hc-guide-note"><p><strong>HCサービスとの関係：</strong>本ページは一般的な構築手順の説明です。HCレンタルサーバーでクロスプレイが標準対応していることを意味しません。利用可能な構成・追加ポート・提供時期はサービスページの公開情報を確認してください。</p></div></section>
<!-- HC_PHASE2AE_ARTICLE_UPDATE_20261107 -->
<section class="hc-guide-section"><h2>接続できない場合の切り分け表</h2><div class="hc-guide-table-wrap"><table><thead><tr><th>症状</th><th>最初の確認</th><th>次に試すこと</th></tr></thead><tbody>
<tr><td>Java版も接続できない</td><td>Javaサーバーの起動ログ、接続先、ファイアウォール</td><td>Geyserを疑う前にJava版サーバー単体の接続を復旧する。</td></tr>
<tr><td>Java版は接続できるが統合版だけ接続できない</td><td>Geyserの起動ログ、統合版の接続ポート・通信方式</td><td>外部ポート、ホスト側の許可設定、プロキシとの対応を確認する。</td></tr>
<tr><td>サーバーは見えるがログインできない</td><td>認証モード、Floodgateの有無、バージョン対応</td><td>Geyser公式ドキュメントの認証・互換性要件を確認する。</td></tr>
<tr><td>接続後に一部機能が使えない</td><td>プラグイン・MODとエディション間の仕様差</td><td>最小構成で再現し、追加機能を一つずつ戻す。</td></tr>
</tbody></table></div><p>統合版の通信方式やGeyserの設定項目は更新されることがあります。固定のポート設定例を機械的に流用せず、導入するバージョンの<a href="https://geysermc.org/wiki/geyser/setup/">公式セットアップ資料</a>を確認してください。</p></section>
<section class="hc-guide-next"><h2>参考資料</h2><p><a href="https://geysermc.org/wiki/geyser/setup/">GeyserMC公式：セットアップ</a> ／ <a href="https://geysermc.org/">GeyserMC公式：概要</a></p><p><a class="hc-guide-backlink" href="/guides/minecraft/">Minecraftガイド一覧へ戻る</a></p><p class="hc-guide-meta">編集：HC Platform ／ 最終確認：2026年11月7日</p></section></article>
</div></main>
<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
