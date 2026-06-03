<?php
session_start();

require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/csrf.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/turnstile.php";
require_once __DIR__ . "/../lib/mailer.php";
require_once __DIR__ . "/../lib/rate-limit.php";
require_once __DIR__ . "/../lib/auth.php";

if (current_user()) {
    redirect("/dashboard/");
}

$security = require __DIR__ . "/../config/security.php";

$pageTitle = "新規登録 | HC Platform";
$pageDescription = "HC Platformの新規登録ページです。";
$pageCss = "/register/register.css";

$errors = [];
$old = [
    "username" => "",
    "email" => "",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $passwordConfirm = $_POST["password_confirm"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";
    $turnstileToken = $_POST["cf-turnstile-response"] ?? "";
    $termsAccepted = isset($_POST["terms_accepted"]) && $_POST["terms_accepted"] === "1";
    $ip = client_ip();

    $old["username"] = $username;
    $old["email"] = $email;

    if (!csrf_check($csrfToken)) {
        $errors[] = "不正なリクエストです。もう一度お試しください。";
    }

    if ($username === "" || mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        $errors[] = "ユーザー名は3文字以上50文字以内で入力してください。";
    }

    if ($username !== "" && !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = "ユーザー名に使用できる文字は、英数字・ドット・ハイフン・アンダーバーのみです。";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "メールアドレスの形式が正しくありません。";
    }

    if (strlen($password) < (int)$security["password_min_length"]) {
        $errors[] = "パスワードは" . (int)$security["password_min_length"] . "文字以上で入力してください。";
    }

    if ($password !== $passwordConfirm) {
        $errors[] = "確認用パスワードが一致しません。";
    }

    if (!$termsAccepted) {
        $errors[] = "利用規約とプライバシーポリシーへの同意が必要です。";
    }

    if (!$errors) {
        $recentCount = count_recent_registration_attempts(
            $ip,
            (int)$security["register_ip_limit_minutes"]
        );

        if ($recentCount >= (int)$security["register_ip_limit_count"]) {
            $errors[] = "短時間に登録操作が多すぎます。時間をおいて再度お試しください。";
        }
    }

    if (!$errors && !verify_turnstile($turnstileToken, $ip)) {
        $errors[] = "Bot確認に失敗しました。ページを更新して再度お試しください。";
    }

    if (!$errors) {
        try {
            $pdo = db();

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = :email OR username = :username
                LIMIT 1
            ");

            $stmt->execute([
                ":email" => $email,
                ":username" => $username,
            ]);

            if ($stmt->fetch()) {
                $errors[] = "このメールアドレスまたはユーザー名は既に使用されています。";
            }
        } catch (Throwable $e) {
            $errors[] = "ユーザー確認中にエラーが発生しました。";
        }
    }

    if (!$errors) {
        try {
            $pdo = db();

            $code = (string) random_int(100000, 999999);
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $expiresMinutes = (int)$security["verification_code_minutes"];

            $pdo->beginTransaction();

            $delete = $pdo->prepare("
                DELETE FROM pending_registrations
                WHERE email = :email OR username = :username
            ");

            $delete->execute([
                ":email" => $email,
                ":username" => $username,
            ]);

            $insert = $pdo->prepare("
                INSERT INTO pending_registrations
                    (
                        username,
                        email,
                        password,
                        verification_code_hash,
                        ip_address,
                        expires_at,
                        terms_accepted,
                        terms_accepted_at,
                        resend_count,
                        last_sent_at
                    )
                VALUES
                    (
                        :username,
                        :email,
                        :password,
                        :verification_code_hash,
                        :ip_address,
                        NOW() + (:minutes || ' minutes')::interval,
                        true,
                        NOW(),
                        0,
                        NOW()
                    )
            ");

            $insert->execute([
                ":username" => $username,
                ":email" => $email,
                ":password" => $passwordHash,
                ":verification_code_hash" => $codeHash,
                ":ip_address" => $ip,
                ":minutes" => $expiresMinutes,
            ]);

            log_registration_attempt(
                $email,
                $username,
                $ip,
                "pending",
                "verification code sent"
            );

            $pdo->commit();

            if (!send_verification_code($email, $code)) {
                $errors[] = "認証メールの送信に失敗しました。";

                try {
                    log_registration_attempt(
                        $email,
                        $username,
                        $ip,
                        "failed",
                        "mail send failed"
                    );
                } catch (Throwable $e) {
                    // ログ失敗は画面に出さない
                }
            } else {
                $_SESSION["pending_email"] = $email;
                redirect("/verify-code/");
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = "登録処理中にエラーが発生しました。";
        }
    }

    if ($errors) {
        try {
            log_registration_attempt(
                $email ?: null,
                $username ?: null,
                $ip,
                "failed",
                implode(" / ", $errors)
            );
        } catch (Throwable $e) {
            // ログ失敗は画面に出さない
        }
    }
}

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="auth-page register-page">
    <div class="container auth-grid">
        <div class="auth-copy reveal">
            <p class="eyebrow">新規登録</p>
            <h1>HCアカウントを作成</h1>
            <p>
                ゲームサーバーレンタル開始に向けて、HC Accountを準備しています。
                登録後、メールに届く6桁の認証コードを入力して本登録を完了します。
            </p>
        </div>

        <section class="auth-card reveal">
            <h2>新規登録</h2>
            <p>必要情報を入力して、認証コードを受け取ります。</p>

            <?php if ($errors): ?>
                <div class="auth-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="/register/" method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                <div class="form-group">
                    <label for="username">ユーザー名</label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="<?php echo h($old["username"]); ?>"
                        autocomplete="username"
                        minlength="3"
                        maxlength="50"
                        required
                    >
                    <small>英数字・ドット・ハイフン・アンダーバーが使用できます。</small>
                </div>

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

                <div class="form-group">
                    <label for="password">パスワード</label>

                    <div class="password-field">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            minlength="<?php echo (int)$security["password_min_length"]; ?>"
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-target="password"
                            aria-label="パスワードを表示"
                        >
                            表示
                        </button>
                    </div>

                    <small><?php echo (int)$security["password_min_length"]; ?>文字以上で入力してください。</small>
                </div>

                <div class="form-group">
                    <label for="password_confirm">パスワード確認</label>

                    <div class="password-field">
                        <input
                            id="password_confirm"
                            type="password"
                            name="password_confirm"
                            autocomplete="new-password"
                            minlength="<?php echo (int)$security["password_min_length"]; ?>"
                            required
                        >
                        <button
                            class="password-toggle"
                            type="button"
                            data-target="password_confirm"
                            aria-label="確認用パスワードを表示"
                        >
                            表示
                        </button>
                    </div>

                    <small>確認のため、同じパスワードをもう一度入力してください。</small>
                </div>

                <div class="form-check">
                    <label>
                        <input
                            type="checkbox"
                            name="terms_accepted"
                            value="1"
                            required
                        >
                        <span>
                            <a href="/terms/" target="_blank" rel="noopener">利用規約</a> と
                            <a href="/privacy/" target="_blank" rel="noopener">プライバシーポリシー</a> に同意します
                        </span>
                    </label>
                </div>

                <div class="turnstile-box">
                    <div
                        class="cf-turnstile"
                        data-sitekey="<?php echo h($security["turnstile_site_key"]); ?>"
                    ></div>
                </div>

                <button class="auth-submit" type="submit">認証コードを送信</button>
            </form>

            <p class="auth-switch">
                すでにアカウントをお持ちの場合は <a href="/login/">ログイン</a>
            </p>
        </section>
    </div>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<script src="/common/base.js"></script>
<script src="/register/register.js"></script>
</body>
</html>