<?php
// HC AdSense Phase 1j: public documentation on HC shared layout.
$pageTitle = "Minecraftのメモリ・TPS・MSPT | HC Platform";
$pageDescription = "Minecraftサーバー負荷の測り方とメモリ容量の判断方法。";
$pageCss = "/guides/assets/guide.css";
require_once __DIR__ . "/../../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../../parts/header/header.php"; ?>
<main id="main" class="hc-guide-page hc-guide-docs">
<section class="hc-guide-hero"><div class="container">
<p class="eyebrow">PERFORMANCE</p>
<h1>メモリ・TPS・MSPTの読み方</h1>
<p class="hc-guide-lead">「人数だけで容量を決めない」ために、指標の見方とラグ調査の優先順位を整理します。</p>
<?php require __DIR__ . '/../../_subnav.php'; ?>
</div></section>
<div class="container hc-guide-body">
<article class="hc-guide-article"><p class="hc-guide-crumbs"><a href="/guides/">技術ガイド</a> / <a href="/guides/minecraft/">Minecraft</a> / 負荷とメモリの基礎</p>
<section class="hc-guide-section"><h2>メモリ容量だけで最大人数は決まらない</h2><p>同じ8GBのサーバーでも、ゲームバージョン、使用するサーバーソフト、描画距離・シミュレーション距離、ワールド生成、導入プラグイン、プレイヤーの集まり方によって負荷は変わります。CPUの処理能力、ストレージの応答速度、ネットワークも関係するため「〇GBなら必ず〇人」とは言い切れません。</p><p>初めは少人数で開始して、実際にプレイヤーが探索・建築・レッドストーン装置を使う場面で指標を測るのが確実です。余裕を残して契約容量や各種設定を決めましょう。</p></section>
<section class="hc-guide-section"><h2>TPSとMSPTの意味</h2><p>TPS（Ticks Per Second）はゲーム世界の更新回数で、通常の目標は20です。MSPT（Milliseconds Per Tick）は1回の更新処理にかかる時間で、20 TPSを維持するための目安は平均50ms未満です。ただし平均だけでは瞬間的な遅延を見落とすため、重くなる場面と合わせて確認します。</p>
<div class="hc-guide-table-wrap"><table><thead><tr><th>観測値</th><th>何が分かるか</th><th>次に見るポイント</th></tr></thead><tbody>
<tr><td>TPSが20付近で安定</td><td>通常の更新ペースを維持</td><td>短い遅延や接続品質は別に確認</td></tr>
<tr><td>MSPTが高くなる</td><td>1tickの処理に時間がかかる</td><td>エンティティ、プラグイン、チャンク生成など</td></tr>
<tr><td>メモリ上限付近で停滞</td><td>メモリ圧迫の可能性</td><td>ヒープとコンテナ上限、GC、MOD構成</td></tr>
<tr><td>TPS良好でも接続が遅い</td><td>通信経路など別要因の可能性</td><td>回線、無線、DNS、パケットロス、地域</td></tr>
</tbody></table></div>
<p>Paperでは公式ドキュメントが示すとおり、より詳細な負荷分析には <code>/spark</code> を使えます。必要な権限・バージョンを確認し、問題が発生している時間帯に計測してください。</p></section>
<section class="hc-guide-section"><h2>メモリの割り当てと計測</h2><p>Javaの <code>-Xmx</code> はJavaヒープの上限で、サーバーコンテナ全体のメモリ上限と同じではありません。Javaプロセスにはヒープ以外の領域も必要です。ホストやコンテナが許容する容量すべてを <code>-Xmx</code> に指定せず、環境に余裕を設けます。</p><p>プラグインやMODを導入する場合、起動時の使用量だけでなく、ワールド読み込みや多数の参加者が集まったときのピークを確認してください。スワップによる激しい待ち時間、ガベージコレクションの停止、ディスク負荷が重なると体感の遅延につながります。</p></section>
<section class="hc-guide-section"><h2>ラグを切り分ける4ステップ</h2><ol><li><strong>再現条件を控える：</strong>人数、時刻、ワールド、使用MOD・プラグイン、起こる操作を記録する。</li><li><strong>サーバー処理を見る：</strong>TPS・MSPTとsparkのレポートで処理の偏りを調べる。</li><li><strong>資源使用量を見る：</strong>CPUのコアごとの負荷、メモリ、GC、ディスクI/Oを併せて確認する。</li><li><strong>ネットワークとクライアントを分ける：</strong>全員に起こるか、一部の人だけか、FPS低下なのかを確認する。</li></ol><div class="hc-guide-note"><p><strong>HCでの推奨：</strong>人数を根拠なく保証するのではなく、使用する実機ノードでTPS・MSPT・メモリ使用量を計測してから安全側の収容目安を決める方法が適切です。本記事に未実施の性能測定結果は掲載していません。</p></div></section>
<section class="hc-guide-next"><h2>参考資料</h2><p><a href="https://docs.papermc.io/paper/reference/commands/">PaperMC公式：TPS・MSPT・spark</a> ／ <a href="https://docs.papermc.io/paper/profiling/">PaperMC公式：Profiling</a></p><p><a class="hc-guide-backlink" href="/guides/minecraft/">Minecraftガイド一覧へ戻る</a></p><p class="hc-guide-meta">編集：HC Platform ／ 2026年11月6日公開用原稿</p></section></article>
</div></main>
<?php include __DIR__ . "/../../../parts/footer/footer.php"; ?>
<script src="/common/base.js"></script>
</body>
</html>
