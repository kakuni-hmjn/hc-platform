<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "サーバーの選び方と運用の基礎 | HC Platform";
$pageDescription = "ゲームサーバー、VPS、専用サーバーの違いと選ぶときの確認事項。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">GENERAL BASICS</p>
<h1>サーバーの選び方と運用の基礎</h1>
<p class="hc-guide-lead">何を動かすか、どこまで自分で管理するか。用途・保守・セキュリティの観点から選び方を整理します。</p>
<?php require __DIR__ . '/../_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / 共通ガイド</p><article class="hc-guide-article">
<section class="hc-guide-section"><h2>用途から必要な管理範囲を決める</h2><p>ゲームのマルチプレイならゲームサーバー、Linuxを自分で設定してWebやBotを運用するならVPS、専用の物理性能やハードウェア制御が必要なら専用サーバーなど、用途によって適した方式が変わります。</p><div class="hc-guide-table-wrap"><table><thead><tr><th>種類</th><th>向いている用途</th><th>先に確認すること</th></tr></thead><tbody><tr><td>ゲームサーバー</td><td>特定ゲームの共同プレイやコミュニティ運用</td><td>対応エディション、MOD・プラグイン、バックアップ</td></tr><tr><td>VPS</td><td>Webサイト、Bot、テスト環境、個別アプリ</td><td>OS管理、SSH、更新、ポート、サーバー監視</td></tr><tr><td>専用サーバー</td><td>高い専有性や構成自由度が必要な用途</td><td>ハード管理範囲、障害対応、冗長化、電源・回線</td></tr></tbody></table></div></section>
<section class="hc-guide-section"><h2>スペックだけで選ばない</h2><p>CPUのコア数とクロック、メモリ容量、SSDのI/O性能、通信帯域はそれぞれ異なる負荷に影響します。ゲームはメインの処理スレッドが支配的な場合があり、Webアプリはデータベースや同時リクエストの数が支配的になる場合があります。用途に合った計測方法で必要資源を見積もります。</p><p>サービスの比較では、実際の利用条件・共有資源・保守対応・バックアップ方式・リソース超過時の挙動を確認しましょう。数値が大きいだけで必ず速いとは限りません。</p></section>
<section class="hc-guide-section"><h2>データの保護は方式を問わず必要</h2><ol><li><strong>バックアップ：</strong>データと設定を分け、復旧テストまで実施する。</li><li><strong>認証：</strong>管理者権限と一般利用者権限を分け、長期利用する鍵・パスワードを保護する。</li><li><strong>更新：</strong>OSやゲーム・アプリの脆弱性情報を確認し、変更前に復旧手段を用意する。</li><li><strong>監視：</strong>CPU・メモリ・ディスク空き容量・サービス死活・エラーを継続的に把握する。</li></ol><div class="hc-guide-note"><p>サービスごとに運用者と利用者の責任範囲が異なります。HCの提供条件については<a href="/services/">サービス一覧</a>や各利用規約を確認してください。本記事は特定サービスの提供や機能を保証するものではありません。</p></div></section>
<section class="hc-guide-next"><h2>次に読む</h2><div class="hc-guide-grid">
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/minecraft/">Minecraft</a></h3><p>ゲームの設定と負荷を確認</p></article>
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/vps/">VPS・Linux</a></h3><p>OSの初期設定とセキュリティ</p></article>
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/dedicated/">専用サーバー</a></h3><p>物理機器の運用と保守</p></article>
</div></section></article>
</div></main>
<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
