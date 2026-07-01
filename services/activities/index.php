<?php
session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";

$currentUser = current_user();

$pageTitle = "活動支援 | HC Platform";
$pageDescription = "HC Platformの活動支援ページです。ゲーム配信者、eSports選手、YouTuber、クリエイターの活動を支える支援サービスを準備しています。";
$pageCss = "/services/activities/activities.css";

include __DIR__ . "/../../parts/head.php";
?>
<body>
<?php require_once __DIR__ . "/../../parts/header/header.php"; ?>

<main class="activities-page">
    <section class="activities-hero reveal">
        <div class="container">
            <p class="eyebrow">Activities Support</p>

            <div class="status-badge">
                <span></span>
                企画準備中
            </div>

            <h1>
                クリエイターと選手の<br>
                活動を支える場所へ。
            </h1>

            <p class="hero-lead">
                ゲーム配信者、eSports選手、YouTuber、イベント関係者など、
                活動する人たちを支えるチーム・事務所型の支援サービスを準備しています。
            </p>

            <div class="hero-actions">
                <a href="/inquiry/" class="activity-button primary">
                    相談・問い合わせ
                </a>
                <a href="/services/" class="activity-button secondary">
                    サービス一覧へ
                </a>
            </div>
        </div>
    </section>

    <section class="activities-section reveal">
        <div class="container">
            <div class="section-heading">
                <p class="eyebrow">What We Support</p>
                <h2>活動の裏側を支える</h2>
                <p>
                    配信・大会・チーム運営・制作活動を、技術面と運営面の両方から支援する構想です。
                </p>
            </div>

            <div class="support-grid">
                <article class="support-card">
                    <span class="card-tag">Creator</span>
                    <h3>配信者・YouTuber支援</h3>
                    <p>
                        配信環境、企画、サムネイル、Webページ、告知など、
                        クリエイター活動に必要な土台を整えます。
                    </p>
                </article>

                <article class="support-card">
                    <span class="card-tag">eSports</span>
                    <h3>選手・チーム支援</h3>
                    <p>
                        eSportsチームの立ち上げ、選手活動、チームページ、
                        募集・告知などを支える仕組みを準備しています。
                    </p>
                </article>

                <article class="support-card">
                    <span class="card-tag">Event</span>
                    <h3>イベント・大会支援</h3>
                    <p>
                        コミュニティ大会、配信イベント、学校行事などの
                        運営・配信・告知をサポートします。
                    </p>
                </article>
            </div>
        </div>
    </section>

    <section class="activities-section reveal">
        <div class="container">
            <div class="concept-box">
                <div>
                    <p class="eyebrow">Concept</p>
                    <h2>HCの活動支援チームを準備中</h2>
                    <p>
                        ただのサービス提供ではなく、活動者と一緒に成長していく
                        クリエイター・eSportsチームのような形を目指しています。
                    </p>
                </div>

                <a href="/inquiry/" class="activity-button primary">
                    詳細を相談する
                </a>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
