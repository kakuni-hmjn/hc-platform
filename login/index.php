<?php
session_start();

require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/csrf.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";

if (current_user()) {
    redirect("/dashboard/");
}

$security = require __DIR__ . "/../config/security.php";

$pageTitle = "ログイン | HC Platform";
$pageDescription = "HC Accountのログインページです。";
$pageCss = "/login/login.css";

$errors = [];
$old = [
    "email" => "",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";
    $ip = client_ip();

    $old["email"] = $email;

    if (!csrf_check($csrfToken)) {
        $errors[] = "不正なリクエストです。もう一度お試しください。";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "メールアドレスの形式が正しくありません。";
    }

    if ($password === "") {
        $errors[] = "パスワードを入力してください。";
    }

    if (!$errors) {
        try {
            $pdo = db();

            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE email = :email
                LIMIT 1
            ");

            $stmt->execute([
                ":email" => $email,
            ]);

            $user = $stmt->fetch();

            if (!$user) {
                $errors[] = "メールアドレスまたはパスワードが正しくありません。";

                $log = $pdo->prepare("
                    INSERT INTO login_logs
                        (user_id, email, ip_address, result, message)
                    VALUES
                        (NULL, :email, :ip_address, :result, :message)
                ");

                $log->execute([
                    ":email" => $email,
                    ":ip_address" => $ip,
                    ":result" => "failed",
                    ":message" => "user not found",
                ]);
            } else {
                if (!empty($user["locked_until"]) && strtotime($user["locked_until"]) > time()) {
                    $lockedUntil = date("Y/m/d H:i", strtotime($user["locked_until"]));
                    $errors[] = "ログイン失敗が多いため、一時的にロックされています。{$lockedUntil} 以降に再度お試しください。";

                    $log = $pdo->prepare("
                        INSERT INTO login_logs
                            (user_id, email, ip_address, result, message)
                        VALUES
                            (:user_id, :email, :ip_address, :result, :message)
                    ");

                    $log->execute([
                        ":user_id" => $user["id"],
                        ":email" => $email,
                        ":ip_address" => $ip,
                        ":result" => "locked",
                        ":message" => "account locked",
                    ]);
                } elseif (!password_verify($password, $user["password"])) {
                    $failedCount = (int)$user["login_failed_count"] + 1;
                    $limit = (int)$security["login_failed_limit"];
                    $lockMinutes = (int)$security["login_lock_minutes"];

                    if ($failedCount >= $limit) {
                        $update = $pdo->prepare("
                            UPDATE users
                            SET
                                login_failed_count = :failed_count,
                                locked_until = NOW() + (:lock_minutes || ' minutes')::interval
                            WHERE id = :id
                        ");

                        $update->execute([
                            ":failed_count" => $failedCount,
                            ":lock_minutes" => $lockMinutes,
                            ":id" => $user["id"],
                        ]);

                        $errors[] = "ログイン失敗が多いため、{$lockMinutes}分間アカウントをロックしました。";
                    } else {
                        $update = $pdo->prepare("
                            UPDATE users
                            SET login_failed_count = :failed_count
                            WHERE id = :id
                        ");

                        $update->execute([
                            ":failed_count" => $failedCount,
                            ":id" => $user["id"],
                        ]);

                        $remaining = $limit - $failedCount;
                        $errors[] = "メールアドレスまたはパスワードが正しくありません。残り{$remaining}回失敗すると一時ロックされます。";
                    }

                    $log = $pdo->prepare("
                        INSERT INTO login_logs
                            (user_id, email, ip_address, result, message)
                        VALUES
                            (:user_id, :email, :ip_address, :result, :message)
                    ");

                    $log->execute([
                        ":user_id" => $user["id"],
                        ":email" => $email,
                        ":ip_address" => $ip,
                        ":result" => "failed",
                        ":message" => "invalid password",
                    ]);
                } elseif ($user["status"] !== "active") {
                    $errors[] = "このアカウントは現在利用できません。";

                    $log = $pdo->prepare("
                        INSERT INTO login_logs
                            (user_id, email, ip_address, result, message)
                        VALUES
                            (:user_id, :email, :ip_address, :result, :message)
                    ");

                    $log->execute([
                        ":user_id" => $user["id"],
                        ":email" => $email,
                        ":ip_address" => $ip,
                        ":result" => "blocked",
                        ":message" => "inactive account",
                    ]);
                } elseif (!$user["email_verified"]) {
                    $errors[] = "メール認証が完了していません。もう一度登録してください。";

                    $log = $pdo->prepare("
                        INSERT INTO login_logs
                            (user_id, email, ip_address, result, message)
                        VALUES
                            (:user_id, :email, :ip_address, :result, :message)
                    ");

                    $log->execute([
                        ":user_id" => $user["id"],
                        ":email" => $email,
                        ":ip_address" => $ip,
                        ":result" => "failed",
                        ":message" => "email not verified",
                    ]);
                } else {
                    login_user((int)$user["id"]);

                    $update = $pdo->prepare("
                        UPDATE users
                        SET
                            last_login = NOW(),
                            login_failed_count = 0,
                            locked_until = NULL
                        WHERE id = :id
                    ");

                    $update->execute([
                        ":id" => $user["id"],
                    ]);

                    $log = $pdo->prepare("
                        INSERT INTO login_logs
                            (user_id, email, ip_address, result, message)
                        VALUES
                            (:user_id, :email, :ip_address, :result, :message)
                    ");

                    $log->execute([
                        ":user_id" => $user["id"],
                        ":email" => $email,
                        ":ip_address" => $ip,
                        ":result" => "success",
                        ":message" => "login success",
                    ]);

                    redirect("/dashboard/");
                }
            }
        } catch (Throwable $e) {
            $errors[] = "ログイン処理中にエラーが発生しました。";
        }
    }
}

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="auth-page login-page">
    <div class="container auth-grid">
        <div class="auth-copy reveal">
            <p class="eyebrow">ログイン</p>
            <h1>HC Accountにログイン</h1>
            <p>
                登録済みのメールアドレスとパスワードでログインできます。
                サービス公開後は、ここからプラン選択やサーバー管理へ進みます。
            </p>
        </div>

        <section class="auth-card reveal">
            <h2>ログイン</h2>
            <p>HC Accountへログインします。</p>

            <?php if ($errors): ?>
                <div class="auth-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo h($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="/login/" method="post" class="auth-form">
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

                <div class="form-group">
                    <label for="password">パスワード</label>

                    <div class="password-field">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
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
                </div>

                <button class="auth-submit" type="submit">ログイン</button>
            </form>

            <p class="auth-switch">
                アカウントをお持ちでない場合は <a href="/register/">新規登録</a>
            </p>

            <p class="auth-switch auth-reset-link">
                パスワードを忘れた場合は <a href="/password-reset/">パスワード再設定</a>
            </p>
        </section>
    </div>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/login/login.js"></script>
</body>
</html>