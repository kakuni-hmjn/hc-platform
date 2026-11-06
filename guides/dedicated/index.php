<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "専用サーバー技術ガイド | HC Platform";
$pageDescription = "専用サーバーのハードウェア構成、冗長化、保守・監視の基本。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">DEDICATED SERVER</p>
<h1>専用サーバーの設計と運用</h1>
<p class="hc-guide-lead">物理機器の性能だけでなく、障害時の復旧や管理範囲を含めて考えるための基礎ガイドです。</p>
<?php require __DIR__ . '/../_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / 専用サーバー</p><article class="hc-guide-article"><section class="hc-guide-section"><h2>専用サーバーとVPSの主な違い</h2><p>専用サーバーは原則として特定の利用者が物理マシンを占有する方式です。CPUやメモリ、ストレージ、ネットワークカードの構成を重視する用途で選択肢になります。ただし、機器交換、保守作業、リモート管理機能、障害時の対応範囲はサービス契約によって異なります。</p><p>VPSと異なり物理ハードウェア単位の構成が重要です。CPUの世代やコア数だけでなく、メモリの冗長性、ストレージ構成、NICや接続帯域も確認しましょう。</p></section>
<section class="hc-guide-section"><h2>設計前のチェック項目</h2><div class="hc-guide-table-wrap"><table><thead><tr><th>項目</th><th>確認ポイント</th></tr></thead><tbody><tr><td>CPU・メモリ</td><td>必要な処理スレッド数、ピーク時使用量、ECCの要否</td></tr><tr><td>ストレージ</td><td>容量・I/O性能・冗長化方式・故障時の交換手順</td></tr><tr><td>ネットワーク</td><td>ポート速度、外部帯域、IPアドレス、保護機能</td></tr><tr><td>電源・保守</td><td>電源冗長化、交換部品、障害時の作業窓口</td></tr><tr><td>遠隔管理</td><td>BMC/IPMI等の利用条件、アクセス制限、ログ</td></tr></tbody></table></div></section>
<section class="hc-guide-section"><h2>RAIDとバックアップは役割が違う</h2><p>RAIDなどの冗長化は、一定の故障に対して継続運用を助ける方法です。誤削除、マルウェア、論理破損、火災などに備えるバックアップの代わりにはなりません。別の障害ドメインへ保存し、復旧手順を確認してください。</p></section>
<section class="hc-guide-section"><h2>監視と保守計画</h2><p>ディスクSMART、ECCエラー、温度、ファン、電源、ネットワークエラー、OS・アプリのログなどを確認します。障害検知から連絡、予備機への切り替え、復旧確認までの手順を決めることも大切です。</p><p>専用サーバーの提供内容は事業者ごとに異なります。本ページは一般的な運用知識であり、HCでの提供開始を意味するものではありません。現在の状況は<a href="/services/">サービス一覧</a>で確認してください。</p></section>
<section class="hc-guide-next"><h2>ほかの技術ガイド</h2><p><a href="/guides/basics/">サーバー選びの基礎</a> ／ <a href="/guides/vps/">VPS・Linux</a></p></section></article>
</div></main>
<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
