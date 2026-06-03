<?php
session_start();

require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/csrf.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/mailer.php";

$security = require __DIR__ . "/../config/security.php";
$app = require __DIR__ . "/../config/app.php";

$pageTitle = "パスワード再設定 | HC Platform";
$pageDescription = "HC Accountのパスワード再設定ページです。";
$pageCss = "/password-reset/password-reset.css";

$errors = [];
$messages = [];
$old = [
    "email" => "",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    $old["email"] = $email;

    if (!csrf_check($csrfToken)) {
        $errors[] = "不正なリクエストです。もう一度お試しください。";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "メールアドレスの形式が正しくありません。";
    }

    if (!$errors) {
        try {
            $pdo = db();

            $stmt = $pdo->prepare("
                SELECT id, email, status
                FROM users
                WHERE email = :email
                LIMIT 1
            ");

            $stmt->execute([
                ":email" => $email,
            ]);

            $user = $stmt->fetch();

            /*
             * メールアドレスの存在確認を外部に漏らさないため、
             * ユーザーが存在しない場合も同じ成功メッセージを表示します。
             */
            if ($user && $user["status"] === "active") {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash("sha256", $rawToken);
                $expiresMinutes = (int)$security["password_reset_minutes"];

                $pdo->beginTransaction();

                $delete = $pdo->prepare("
                    DELETE FROM password_reset_tokens
                    WHERE user_id = :user_id
                      AND used = false
                ");

                $delete->execute([
                    ":user_id" => $user["id"],
                ]);

                $insert = $pdo->prepare("
                    INSERT INTO password_reset_tokens
                        (user_id, email, token_hash, expires_at)
                    VALUES
                        (:user_id, :email, :token_hash, NOW() + (:minutes || ' minutes')::interval)
                ");

                $insert->execute([
                    ":user_id" => $user["id"],
                    ":email" => $user["email"],
                    ":token_hash" => $tokenHash,
                    ":minutes" => $expiresMinutes,
                ]);

                $pdo->commit();

                $resetUrl = $app["app_url"] . "/password-reset/confirm/?token=" . urlencode($rawToken);

                send_password_reset_link($user["email"], $resetUrl);
            }

            $messages[] = "入力されたメールアドレス宛に、パスワード再設定用の案内を送信しました。";
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = "パスワード再設定処理中にエラーが発生しました。";
        }
    }
}

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="auth-page password-reset-page">
    <div class="container auth-grid">
        <div class="auth-copy reveal">
            <p class="eyebrow">Password Reset</p>
            <h1>パスワード再設定</h1>
            <p>
                登録済みのメールアドレスを入力してください。
                パスワード再設定用のURLをメールで送信します。
            </p>
        </div>

        <section class="auth-card reveal">
            <h2>再設定メール送信</h2>
            <p>登録済みメールアドレス宛に再設定用リンクを送信します。</p>

            <?php if ($messages): ?>
                <div class="auth-success">
                    <?php foreach ($messages as $message): ?>
                        <p><?php echo h($message); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="auth-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="/password-reset/" method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                <div class="form-group">
                    <label for="email">メールアドレス</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="<?php echo h($old["email"]); ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <button class="auth-submit" type="submit">再設定メールを送信</button>
            </form>

            <p class="auth-switch">
                ログイン画面へ戻る場合は <a href="/login/">ログイン</a>
            </p>
        </section>
    </div>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/password-reset/password-reset.js"></script>
</body>
</html>