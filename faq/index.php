<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "よくある質問 | HC Platform";
$pageDescription = "HC Platformの共通FAQ。サービスの提供状況、問い合わせ先、MinecraftやVPSに関する質問。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-faq">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">HC HELP CENTER</p>
<h1>よくある質問（FAQ）</h1>
<p class="hc-guide-lead">HC Platform全般の案内と、Minecraft・VPSなどの技術分野別FAQをまとめています。</p>
<?php require __DIR__ . '/../guides/_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<section class="hc-guide-section"><h2>よくある質問をカテゴリ別に探す</h2><p>HC Platformのサービスに関する共通の案内と、技術分野ごとの質問を分けて掲載しています。</p><div class="hc-guide-grid">
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/faq/minecraft/">Minecraft FAQ</a></h3><p>メモリ・TPS・MOD・クロスプレイ・接続トラブル。</p></article>
<article class="hc-guide-card"><h3><a class="hc-guide-title-link" href="/faq/vps/">VPS FAQ</a></h3><p>SSH、セキュリティ、スナップショット、バックアップ。</p></article>
</div></section>
<article class="hc-guide-article"><section class="hc-guide-section"><h2>HC Platformについて</h2><p>HC Platformは、サーバー関連サービスや制作・コミュニティ支援などの取り組みを案内するサイトです。サービスごとに提供状況が異なるため、<a href="/services/">事業・サービス一覧</a>に表示される状態と最新の詳細ページを確認してください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. 掲載されている技術ガイドの内容はHCの全サービスで利用できますか？</h2><p>技術ガイドは一般的な説明です。特定の構成やソフトウェアがHCのプランに標準搭載されることを保証しません。提供条件は各サービスの公開情報をご確認ください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. 開発中・計画中のサービスは申し込めますか？</h2><p>「開発中」「計画中」のサービスは、正式に申込み可能だという意味ではありません。提供開始と申込方法は該当するサービスページで案内されます。</p></section>
<section class="hc-guide-faq-item"><h2>Q. 技術的な質問はどこから送れますか？</h2><p><a href="/contact/">お問い合わせフォーム</a>から相談できます。契約者向け機能が利用できる場合は、アカウント内の案内もご確認ください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. 運営情報と利用規約はどこにありますか？</h2><p><a href="/operator/">運営情報</a>、<a href="/terms/">利用規約</a>、<a href="/privacy/">プライバシーポリシー</a>をご確認ください。</p></section>
<section class="hc-guide-next"><h2>技術情報も確認する</h2><p><a href="/guides/">技術ガイド一覧</a>からMinecraft、VPS、専用サーバーなどのカテゴリを選べます。</p></section></article>
</div></main>
<?php include __DIR__ . "/../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
