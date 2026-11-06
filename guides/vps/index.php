<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "VPS・Linux技術ガイド | HC Platform";
$pageDescription = "VPSの用途、Linuxの運用、SSH、ネットワーク、バックアップの基礎。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">VPS / LINUX</p>
<h1>VPS・Linuxの運用</h1>
<p class="hc-guide-lead">Linux VPSを利用するときに必要な初期設定、アクセス制御、運用監視の考え方をまとめました。</p>
<?php require __DIR__ . '/../_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / VPS</p><article class="hc-guide-article"><section class="hc-guide-section"><h2>VPSは何を自分で管理するのか</h2><p>VPS（Virtual Private Server）は、物理サーバー上のリソースを仮想マシンとして利用する仕組みです。一般的なLinux VPSでは、OSの設定、ログイン方法、パッケージ更新、アプリの運用を利用者が管理します。提供事業者が担当する範囲は契約条件で異なります。</p><p>Webサイト、Bot、検証環境など用途の自由度は高い一方、初期設定を済ませただけでは安全な運用は完成しません。</p></section>
<section class="hc-guide-section"><h2>はじめに確認する5項目</h2><ol><li><strong>ログイン：</strong>SSH鍵で接続できるか、管理者を誰にするか。</li><li><strong>アクセス制御：</strong>必要なポートだけ公開し、SSHの接続手段を失わない設定順にする。</li><li><strong>OS更新：</strong>サポート期間とセキュリティ更新の適用方法を把握する。</li><li><strong>バックアップ：</strong>自動スナップショットがある場合も、必要なデータの復旧方法を検証する。</li><li><strong>監視：</strong>ディスク空き容量、CPU、メモリ、ログ、外部からの死活を確認する。</li></ol><p>設定例は<a href="/guides/vps/initial-security/">Linux VPSの初期セキュリティガイド</a>を参照してください。</p></section>
<section class="hc-guide-section"><h2>用途別の設計ポイント</h2><div class="hc-guide-table-wrap"><table><thead><tr><th>用途</th><th>重点項目</th></tr></thead><tbody><tr><td>Webサイト</td><td>HTTP/HTTPS、TLS、更新手順、データベースのバックアップ</td></tr><tr><td>Bot・常駐アプリ</td><td>プロセスの自動起動・再起動、ログ、APIキーの保管</td></tr><tr><td>開発・テスト</td><td>本番との分離、復元可能な環境構築、アクセス制限</td></tr><tr><td>VPN・社内向けツール</td><td>認証、ネットワークルール、公開範囲とルートの理解</td></tr></tbody></table></div></section>
<section class="hc-guide-section"><h2>参考資料・注意事項</h2><p><a href="https://ubuntu.com/server/docs/">Ubuntu Server公式ドキュメント</a> ／ <a href="https://ubuntu.com/server/docs/how-to/security/">Ubuntu Serverのセキュリティ</a></p><p>このページは一般的なVPSの説明であり、HCのVPSが現在契約可能なことを意味しません。提供状況は<a href="/services/">サービス一覧</a>でご確認ください。よくある疑問は<a href="/faq/vps/">VPS FAQ</a>にまとめています。</p></section></article>
</div></main>
<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
