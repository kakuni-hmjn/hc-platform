<?php
require_once __DIR__ . "/data/site-data.php";

$pageTitle = "HC Platform | HCと共にある生活";
$pageDescription = "HC Platformは、ゲームコミュニティ、クリエイター活動、配信活動などを支えるためのインフラサービスブランドです。";
$pageCss = "/index.css";
$enableAdsense = true;

require_once __DIR__ . "/parts/head.php";
?>
<body>
<?php include __DIR__ . "/parts/header/header.php"; ?>

<main class="home-page">

    <section class="home-hero">
        <div class="container home-hero-grid">

            <div class="home-hero-copy reveal">
                <p class="eyebrow">Game / Create / Stream / Infrastructure</p>

                <h1 class="home-title">
                    <span>HCと共にある生活。</span>
                    <strong>遊ぶ、作る、配信する。</strong>
                </h1>

                <p class="home-lead">
                    HC Platformは、ゲームコミュニティ、クリエイター活動、配信活動などを支えるための
                    インフラサービスブランドです。まずはゲームサーバーレンタルを中心に、
                    活動の裏側で必要になる仕組みを一つずつ整えていきます。
                </p>

                <div class="home-actions">
                    <a href="/register/" class="button primary">HC Accountを作成</a>
                    <a href="#plans" class="button ghost">提供予定プランを見る</a>
                </div>

                <div class="home-points">
                    <div>
                        <strong>HC Account</strong>
                        <span>メール認証対応</span>
                    </div>
                    <div>
                        <strong>Game Server</strong>
                        <span>レンタル事業準備中</span>
                    </div>
                    <div>
                        <strong>Creator Infra</strong>
                        <span>今後展開予定</span>
                    </div>
                </div>
            </div>

            <div class="home-hero-visual reveal">
                <div class="platform-panel">
                    <div class="platform-panel-head">
                        <div>
                            <span>HC Platform</span>
                            <h2>公開準備ステータス</h2>
                        </div>
                        <strong>準備中</strong>
                    </div>

                    <div class="platform-logo-box">
                        <img src="/assets/logo.png" alt="HC Platform ロゴ">
                        <div>
                            <h3>HC Platform</h3>
                            <p>ゲーム・制作・配信を支えるサービス基盤</p>
                        </div>
                    </div>

                    <div class="platform-status-list">
                        <div>
                            <span>アカウント登録</span>
                            <strong>対応済み</strong>
                        </div>
                        <div>
                            <span>メール認証</span>
                            <strong>対応済み</strong>
                        </div>
                        <div>
                            <span>プラン申込み</span>
                            <strong>準備中</strong>
                        </div>
                        <div>
                            <span>決済機能</span>
                            <strong>準備中</strong>
                        </div>
                        <div>
                            <span>サーバー自動作成</span>
                            <strong>準備中</strong>
                        </div>
                    </div>

                    <div class="platform-progress">
                        <div>
                            <span>Current Phase</span>
                            <strong>Phase 01</strong>
                        </div>
                        <div class="progress-bar">
                            <span></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <section id="concept" class="section concept-section">
        <div class="container">
            <div class="section-heading reveal">
                <p class="eyebrow">Concept</p>
                <h2>活動の裏側に、扱いやすいインフラを。</h2>
                <p>
                    遊ぶ場所を用意する。作ったものを動かす。配信やコミュニティを支える。
                    HC Platformは、そうした活動の裏側で必要になる仕組みを整えていくことを目指します。
                </p>
            </div>

            <div class="concept-grid">
                <article class="concept-card reveal">
                    <span class="concept-icon"></span>
                    <h3>ゲームコミュニティ向け</h3>
                    <p>
                        友達同士の小規模サーバーから、コミュニティ運営まで使いやすい環境を目指します。
                    </p>
                </article>

                <article class="concept-card reveal">
                    <span class="concept-icon"></span>
                    <h3>クリエイター活動の基盤</h3>
                    <p>
                        配信者やクリエイターが、活動の裏側で必要とするサーバー環境を整えていきます。
                    </p>
                </article>

                <article class="concept-card reveal">
                    <span class="concept-icon"></span>
                    <h3>始めやすい価格帯</h3>
                    <p>
                        月額300円から始められる、手に取りやすい価格帯のサービス展開を予定しています。
                    </p>
                </article>
            </div>
        </div>
    </section>

    <section id="service" class="section service-overview">
        <div class="container service-overview-grid">

            <div class="service-overview-copy reveal">
                <p class="eyebrow">First Service</p>
                <h2>
                    <span>最初の展開は</span>
                    <span>ゲームサーバーレンタル。</span>
                </h2>
                <p>
                    HC Platformの最初のサービスとして、ゲームサーバーレンタル事業を準備しています。
                    小規模に始めたい人から、高性能な環境を求めるコミュニティまで、
                    用途に合わせたプランを提供できるよう整備しています。
                </p>
            </div>

            <div class="service-overview-card reveal">
                <div class="service-number">01</div>
                <h3>ゲームサーバーレンタル</h3>
                <p>
                    Minecraftなどのゲームサーバーを、より気軽に扱えるように。
                    アカウント登録、メール認証、契約管理、支払い後のサーバー作成までを順番に整えていきます。
                </p>

                <ul>
                    <li>HC Accountによるログイン管理</li>
                    <li>Cloudflare TurnstileによるBot対策</li>
                    <li>メール認証コードによる本人確認</li>
                    <li>支払い後にサーバーを作成する設計</li>
                    <li>管理画面はPterodactyl Panelを使用予定</li>
                </ul>
            </div>

        </div>
    </section>

    <section id="plans" class="section plan-preview-section">
        <div class="container">
            <div class="section-heading reveal">
                <p class="eyebrow">Plans</p>
                <h2>提供予定プラン</h2>
                <p>
                    正式提供前の予定プランです。サービス開始時には、価格・仕様・提供内容が変更される場合があります。
                </p>
            </div>

            <div class="home-plan-grid">
                <?php foreach ($plans as $index => $plan): ?>
                    <article class="home-plan-card <?php echo isset($plan["type"]) && $plan["type"] === "dedicated" ? "dedicated-home-plan" : ""; ?> reveal">
                        <div class="home-plan-top">
                            <span><?php echo htmlspecialchars($plan["tag"], ENT_QUOTES, "UTF-8"); ?></span>

                            <?php if ($index === 1): ?>
                                <small>おすすめ</small>
                            <?php endif; ?>
                        </div>

                        <h3><?php echo htmlspecialchars($plan["name"], ENT_QUOTES, "UTF-8"); ?></h3>

                        <p class="home-plan-price">
                            <?php echo htmlspecialchars($plan["price"], ENT_QUOTES, "UTF-8"); ?>
                            <?php if ($plan["price"] !== "要相談"): ?>
                                <span>/ 月</span>
                            <?php endif; ?>
                        </p>

                        <p class="home-plan-spec">
                            <?php echo htmlspecialchars($plan["spec"], ENT_QUOTES, "UTF-8"); ?>
                        </p>

                        <p class="home-plan-desc">
                            <?php echo htmlspecialchars($plan["desc"], ENT_QUOTES, "UTF-8"); ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="plan-preview-action reveal">
                <a href="/order/" class="button primary">新規プラン契約ページへ</a>
            </div>
        </div>
    </section>

    <section id="status" class="section status-section">
        <div class="container">
            <div class="status-panel reveal">
                <div>
                    <p class="eyebrow">Status</p>
                    <h2>正式公開に向けて準備中</h2>
                    <p>
                        HC Platformは現在、アカウント機能、メール認証、契約ページ、決済連携などを順番に整備しています。
                        サービス公開時には、利用規約、プライバシーポリシー、運営情報、サポート情報も含めて提供します。
                    </p>
                </div>

                <div class="status-actions">
                    <a href="/register/" class="button primary">新規登録</a>
                    <a href="/operator/" class="button ghost">運営情報を見る</a>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . "/parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/index.js"></script>
</body>
</html>