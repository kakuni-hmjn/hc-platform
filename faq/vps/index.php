<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "VPSのよくある質問 | HC Platform";
$pageDescription = "Linux VPSの接続、SSH鍵、ファイアウォール、スナップショットのFAQ。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-faq">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">VPS FAQ</p>
<h1>VPS よくある質問</h1>
<p class="hc-guide-lead">SSH、バックアップ、セキュリティなど、VPSの利用前後に確認したいポイントです。</p>
<?php require __DIR__ . '/../../guides/_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / VPS FAQ</p><article class="hc-guide-article"><section class="hc-guide-section"><h2>VPS運用について</h2><p>以下は一般的なLinux VPSの技術解説です。HCのVPS提供状況を示すものではありません。サービスの提供状況は<a href="/services/">サービス一覧</a>から確認してください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. VPSとゲームサーバーの違いは何ですか？</h2><p>ゲームサーバーサービスは対象ゲームの実行環境を中心に提供する形態です。VPSではLinuxなどのOS上で利用者自身がアプリや設定を管理する構成が一般的です。管理できる範囲は契約条件によって異なります。</p></section>
<section class="hc-guide-faq-item"><h2>Q. SSHで接続できないときは何を確認しますか？</h2><p>接続先IP、ポート、ユーザー名、秘密鍵、事業者側とOS側のファイアウォール、サーバーの起動状態を確認します。設定を変更する前に、管理コンソールなど復旧手段の有無を調べてください。</p></section>
<section class="hc-guide-faq-item"><h2>Q. パスワード認証をすぐに無効化してよいですか？</h2><p>鍵認証で新しいセッションから接続できることを確認し、代替の復旧経路を確保してから判断してください。手順を誤ると管理接続を失う可能性があります。</p></section>
<section class="hc-guide-faq-item"><h2>Q. スナップショットがあればバックアップは不要ですか？</h2><p>不要にはなりません。スナップショットの保持場所や障害時の利用条件を確認し、重要データは別の障害に耐えられる場所にバックアップして復元を検証しましょう。</p></section>
<section class="hc-guide-faq-item"><h2>Q. ファイアウォールはどう設定すればよいですか？</h2><p>利用するアプリに必要なポートとアクセス元を洗い出し、必要最小限の通信を許可します。SSHなどの管理経路を遮断しないこと、OSと事業者側のルールを両方確認することが大切です。</p></section>
<section class="hc-guide-faq-item"><h2>Q. CPUやメモリの増設だけで解決しますか？</h2><p>必ずしも解決しません。アプリの処理待ち、ディスクI/O、ネットワーク、データベースなどのどこが遅いかを先に特定してください。</p></section>
<section class="hc-guide-next"><h2>関連情報</h2><p><a href="/guides/vps/">VPSガイド</a> ／ <a href="/guides/vps/initial-security/">初期セキュリティ</a> ／ <a href="/faq/">共通FAQ</a></p></section></article>
</div></main>
<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
