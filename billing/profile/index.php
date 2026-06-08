<?php

session_start();

require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";
require_once __DIR__ . "/../../lib/db.php";

$currentUser = require_login();

$pageTitle = "請求先情報 | HC Platform";
$pageDescription = "HC Platformの請求先情報ページです。";
$pageCss = "/billing/profile/profile.css";

$pdo = db();

$userDetail = null;
$errors = [];

function bp_datetime(?string $value): string
{
    if (!$value) {
        return "-";
    }

    try {
        return (new DateTime($value))->format("Y/m/d H:i");
    } catch (Throwable $e) {
        return $value;
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            email,
            role,
            status,
            email_verified,
            created_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        "id" => (int)$currentUser["id"],
    ]);

    $userDetail = $stmt->fetch();

    if (!$userDetail) {
        $errors[] = "ユーザー情報を取得できませんでした。";
    }
} catch (Throwable $e) {
    $errors[] = "請求先情報の取得中にエラーが発生しました。";
}

require_once __DIR__ . "/../../parts/head.php";
?>

<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="billing-profile-page">
    <section class="billing-profile-hero">
        <div class="container billing-profile-hero-grid">
            <div class="billing-profile-copy reveal">
                <p class="eyebrow">Billing / Profile</p>
                <h1>請求先情報</h1>
                <p>
                    請求書・領収書・決済情報に使う請求先情報を確認します。
                    現在は決済連携前のため、変更機能は準備中です。
                </p>
            </div>

            <aside class="billing-profile-status-card reveal">
                <span>Billing Profile</span>
                <h2>準備中</h2>
                <p>Stripe Customer連携後に編集機能を有効化します。</p>
            </aside>
        </div>
    </section>

    <section class="section billing-profile-section">
        <div class="container">
            <div class="billing-profile-toolbar">
                <a href="/billing/" class="back-button">請求・支払いへ戻る</a>
                <a href="/dashboard/" class="sub-button">マイページへ戻る</a>
            </div>

            <?php if ($errors): ?>
                <div class="billing-profile-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($userDetail): ?>
                <div class="billing-profile-grid reveal">
                    <section class="billing-profile-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Account</p>
                                <h2>アカウント情報</h2>
                            </div>
                        </div>

                        <dl class="profile-detail-list">
                            <div>
                                <dt>ユーザーID</dt>
                                <dd>#<?php echo h((string)$userDetail["id"]); ?></dd>
                            </div>

                            <div>
                                <dt>ユーザー名</dt>
                                <dd><?php echo h((string)$userDetail["username"]); ?></dd>
                            </div>

                            <div>
                                <dt>メールアドレス</dt>
                                <dd><?php echo h((string)$userDetail["email"]); ?></dd>
                            </div>

                            <div>
                                <dt>メール認証</dt>
                                <dd><?php echo !empty($userDetail["email_verified"]) ? "認証済み" : "未認証"; ?></dd>
                            </div>

                            <div>
                                <dt>アカウント状態</dt>
                                <dd><?php echo h((string)$userDetail["status"]); ?></dd>
                            </div>

                            <div>
                                <dt>登録日時</dt>
                                <dd><?php echo h(bp_datetime((string)$userDetail["created_at"])); ?></dd>
                            </div>
                        </dl>
                    </section>

                    <section class="billing-profile-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Billing Address</p>
                                <h2>請求先情報</h2>
                            </div>
                        </div>

                        <div class="profile-placeholder">
                            <div class="profile-icon">badge</div>
                            <h3>請求先情報は未設定です</h3>
                            <p>
                                決済連携後、氏名・住所・請求先メール・法人名などを登録できるようにします。
                                現在はアカウントのメールアドレスを請求先候補として扱います。
                            </p>

                            <button type="button" disabled>
                                請求先情報を編集 準備中
                            </button>
                        </div>
                    </section>

                    <section class="billing-profile-panel wide-panel">
                        <div class="panel-head">
                            <div>
                                <p class="eyebrow">Future</p>
                                <h2>今後追加する項目</h2>
                            </div>
                        </div>

                        <div class="future-profile-grid">
                            <article>
                                <span>person</span>
                                <strong>氏名・法人名</strong>
                                <p>個人名または法人名を請求先として登録できるようにします。</p>
                            </article>

                            <article>
                                <span>mail</span>
                                <strong>請求先メール</strong>
                                <p>請求書や支払い通知の送信先メールを設定できるようにします。</p>
                            </article>

                            <article>
                                <span>location_on</span>
                                <strong>住所情報</strong>
                                <p>必要に応じて住所や郵便番号を登録できるようにします。</p>
                            </article>

                            <article>
                                <span>receipt_long</span>
                                <strong>請求書情報</strong>
                                <p>請求書番号、領収書、税区分などを表示できるようにします。</p>
                            </article>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
</body>
</html>
