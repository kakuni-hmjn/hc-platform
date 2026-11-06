<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "技術ガイド | HC Platform";
$pageDescription = "HC Platformの技術ガイド一覧。Minecraft、VPS、専用サーバー、サーバー運用の基礎をカテゴリ別に紹介。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">HC TECH GUIDES</p>
<h1>技術ガイド</h1>
<p class="hc-guide-lead">サーバーやネットワークの基礎からMinecraft、VPS、専用サーバーの運用まで。目的別に探せるHCの技術情報です。</p>
<?php require __DIR__ . '/_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<section class="hc-guide-section"><h2>カテゴリから探す</h2><p>HC Platformの技術情報を、利用目的やサーバーの種類に分けて掲載しています。Minecraftだけでなく、Linux、VPS、物理サーバーなどの基礎も扱います。</p><div class="hc-guide-grid">
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/basics/">共通ガイド｜サーバーの選び方</a></h3><p>ゲーム・Web・Bot・検証環境など、用途から必要な管理範囲を整理します。</p></article>
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/minecraft/">Minecraftサーバー</a></h3><p>PaperとMOD、メモリ・TPS・MSPT、Java版と統合版の接続を解説します。</p></article>
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/vps/">VPS・Linux</a></h3><p>仮想サーバーの仕組み、SSH、ファイアウォール、バックアップの基本を紹介します。</p></article>
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/guides/dedicated/">専用サーバー</a></h3><p>物理機器の管理、冗長化、電源、ストレージ、監視の考え方を整理します。</p></article>
</div></section>
<section class="hc-guide-section"><h2>最初に知っておきたいこと</h2><p>サーバーを選ぶときは、価格やCPU・メモリの数字だけでなく、誰がOSを管理するか、障害時にどこまで自分で対応するか、バックアップをどう確保するかが重要です。<a href="/guides/basics/">共通ガイド</a>で比較できます。</p><p>技術記事は一般的な構築・運用知識を説明するものです。HCが各サービスを提供中であることを示すものではありません。現在の提供状況は<a href="/services/">サービス一覧</a>を参照してください。</p></section>
<section class="hc-guide-section"><h2>疑問から探す</h2><p>契約・お問い合わせに関する一般的な案内と、Minecraft・VPS別の質問は<a href="/faq/">FAQ</a>にまとめています。</p></section>
</div></main>
<?php include __DIR__ . "/../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
