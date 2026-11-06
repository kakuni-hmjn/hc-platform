<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "Linux VPS初期セキュリティ | HC Platform";
$pageDescription = "VPS導入後のSSH鍵、ユーザー権限、ファイアウォール、更新とバックアップ。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">SECURITY BASICS</p>
<h1>Linux VPSの初期セキュリティ</h1>
<p class="hc-guide-lead">SSHの復旧手段を確保しながら、鍵認証・公開ポート・更新・バックアップを順に整備します。</p>
<?php require __DIR__ . '/../../_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / <a href="/guides/vps/">VPS</a> / 初期セキュリティ</p><article class="hc-guide-article"><section class="hc-guide-section"><h2>1. まず復旧経路を確保する</h2><p>初期設定はOSイメージや事業者の標準設定によって異なります。管理用コンソールなどSSHが使えなくなった際の復旧手段を先に確認します。リモートでファイアウォールやSSHの設定を一括変更すると、自分の接続を遮断することがあります。</p></section>
<section class="hc-guide-section"><h2>2. SSH鍵と管理ユーザー</h2><p>SSHは暗号化された管理接続です。可能なら秘密鍵にパスフレーズを設定して保護し、公開鍵をサーバーのログイン先に登録します。Ubuntuの公式資料では、Ed25519鍵の作成例として次のコマンドを紹介しています。</p><pre><code>ssh-keygen -t ed25519</code></pre><p>秘密鍵は公開したり、リポジトリに登録したりしないでください。鍵で接続できることを別の端末から確かめてから、必要に応じてパスワード認証やrootの直接ログインの設定を見直します。ログイン可能なセッションを残したまま作業すると復旧しやすくなります。</p><p><a href="https://ubuntu.com/server/docs/how-to/security/openssh-server/">公式：OpenSSH server</a></p></section>
<section class="hc-guide-section"><h2>3. 公開ポートを最小限にする</h2><p>Web公開なら80/443、管理アクセスには通常SSHなど、必要な通信だけを許可します。ファイアウォールはOS内だけでなく、VPS事業者のネットワーク側にもある場合があります。どちらが許可・遮断しているか確認しましょう。</p><div class="hc-guide-note"><p><strong>接続遮断に注意：</strong>遠隔からファイアウォールを有効化する前に、SSHの接続先ポートを許可し、管理コンソール等で復旧できることを確認してください。</p></div><p><a href="https://ubuntu.com/server/docs/firewalls/">公式：Firewall</a></p></section>
<section class="hc-guide-section"><h2>4. 更新・ログ・監視の運用</h2><p>OSのサポート期限を確認し、パッケージのセキュリティ更新を継続します。アプリ更新の前には変更点と互換性を確認し、必要に応じてバックアップを取ります。認証失敗、ディスク容量、サービスの再起動回数、CPUやメモリの変化を監視しておくと、障害の切り分けがしやすくなります。</p></section>
<section class="hc-guide-section"><h2>5. バックアップを復元まで試す</h2><p>スナップショットと、アプリケーションの整合性を保ったデータバックアップは役割が異なります。定期取得に加え、別の場所に保持し、復元できるか検証してください。データベースを稼働状態のままコピーするだけでは整合性を保証できないことがあります。</p></section>
<section class="hc-guide-next"><h2>関連ページ</h2><p><a class="hc-guide-backlink" href="/guides/vps/">VPSガイドへ戻る</a> ／ <a href="/faq/vps/">VPS FAQ</a></p></section></article>
</div></main>
<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
