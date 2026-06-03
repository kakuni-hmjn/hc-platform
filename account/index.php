<?php
session_start();

require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/helpers.php";

$user = require_login();

$pageTitle = "プロフィール設定 | HC Platform";
$pageDescription = "HC Accountのプロフィール設定ページです。";
$pageCss = "/account/account.css";

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="account-page">

    <section class="account-hero">
        <div class="container account-grid">

            <div class="account-copy reveal">
                <p class="eyebrow">Account</p>
                <h1>プロフィール設定</h1>
                <p>
                    HC Accountの基本情報を確認できます。
                    パスワード変更は、登録済みメールアドレスへの再設定リンク送信で行います。
                </p>
            </div>

            <aside class="account-profile-card reveal">
                <div class="profile-avatar">
                    <?php echo h(mb_substr($user["username"], 0, 1)); ?>
                </div>

                <h2><?php echo h($user["username"]); ?></h2>
                <p><?php echo h($user["email"]); ?></p>

                <div class="profile-status">
                    <?php if ($user["status"] === "active"): ?>
                        <span class="active">アカウント有効</span>
                    <?php else: ?>
                        <span class="inactive">アカウント停止中</span>
                    <?php endif; ?>

                    <?php if (!empty($user["email_verified"])): ?>
                        <span class="verified">メール認証済み</span>
                    <?php else: ?>
                        <span class="unverified">メール未認証</span>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </section>

    <section class="section account-section">
        <div class="container account-layout">

            <section class="account-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Profile</p>
                        <h2>基本情報</h2>
                    </div>
                    <span>確認用</span>
                </div>

                <div class="account-info-list">
                    <div>
                        <span>ユーザー名</span>
                        <strong><?php echo h($user["username"]); ?></strong>
                    </div>

                    <div>
                        <span>メールアドレス</span>
                        <strong><?php echo h($user["email"]); ?></strong>
                    </div>

                    <div>
                        <span>権限</span>
                        <strong><?php echo h($user["role"]); ?></strong>
                    </div>

                    <div>
                        <span>アカウント状態</span>
                        <strong><?php echo h($user["status"]); ?></strong>
                    </div>

                    <div>
                        <span>メール認証</span>
                        <strong><?php echo !empty($user["email_verified"]) ? "認証済み" : "未認証"; ?></strong>
                    </div>

                    <div>
                        <span>登録日</span>
                        <strong><?php echo h(date("Y/m/d H:i", strtotime($user["created_at"]))); ?></strong>
                    </div>

                    <div>
                        <span>最終ログイン</span>
                        <strong>
                            <?php echo !empty($user["last_login"]) ? h(date("Y/m/d H:i", strtotime($user["last_login"]))) : "未記録"; ?>
                        </strong>
                    </div>
                </div>
            </section>

            <aside class="account-side reveal">
                <article class="side-card">
                    <h3>設定メニュー</h3>

                    <div class="setting-links">
                        <a href="#profile">プロフィール情報</a>
                        <a href="#email">メールアドレス変更</a>
                        <a href="#password">パスワード変更</a>
                        <a href="/dashboard/">ダッシュボードへ戻る</a>
                    </div>
                </article>

                <article class="side-card warning-card">
                    <h3>現在の状態</h3>
                    <p>
                        プロフィール情報の変更機能は準備中です。
                        パスワード変更は、メール認証リンクを使った再設定方式で利用できます。
                    </p>
                </article>
            </aside>

        </div>
    </section>

    <section class="section account-edit-section">
        <div class="container">

            <div class="section-heading reveal">
                <p class="eyebrow">Settings</p>
                <h2>アカウント設定</h2>
                <p>
                    アカウント情報の変更には、本人確認やメール認証を入れて安全に実装していきます。
                </p>
            </div>

            <div class="settings-grid">
                <article id="profile" class="setting-card reveal">
                    <span>01</span>
                    <h3>プロフィール変更</h3>
                    <p>
                        表示名やユーザー名の変更機能を追加予定です。
                        変更履歴や重複確認を入れて実装します。
                    </p>
                    <button type="button" disabled>準備中</button>
                </article>

                <article id="email" class="setting-card reveal">
                    <span>02</span>
                    <h3>メールアドレス変更</h3>
                    <p>
                        変更後のメールアドレスに確認コードを送信する方式で実装予定です。
                    </p>
                    <button type="button" disabled>準備中</button>
                </article>

                <article id="password" class="setting-card reveal">
                    <span>03</span>
                    <h3>パスワード変更</h3>
                    <p>
                        登録済みメールアドレスへ再設定リンクを送信し、
                        新しいパスワードを設定できます。
                    </p>
                    <a href="/password-reset/" class="setting-button">パスワードを変更</a>
                </article>
            </div>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/account/account.js"></script>
</body>
</html>