<?php
session_start();

require_once __DIR__ . "/../../lib/db.php";
require_once __DIR__ . "/../../lib/csrf.php";
require_once __DIR__ . "/../../lib/helpers.php";
require_once __DIR__ . "/../../lib/auth.php";

// ログイン済みユーザーもプロフィール設定から利用できるようにするため、
// ここでは dashboard へリダイレクトしない。
// if (current_user()) {
//     redirect("/dashboard/");
// }

$security = require __DIR__ . "/../../config/security.php";

$pageTitle = "新しいパスワード設定 | HC Platform";
$pageDescription = "HC Accountの新しいパスワード設定ページです。";
$pageCss = "/password-reset/password-reset.css";

$errors = [];
$messages = [];

$token = $_GET["token"] ?? $_POST["token"] ?? "";
$tokenHash = $token !== "" ? hash("sha256", $token) : "";

if ($token === "") {
    $errors[] = "パスワード再設定トークンが見つかりません。";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST["password"] ?? "";
    $passwordConfirm = $_POST["password_confirm"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!csrf_check($csrfToken)) {
        $errors[] = "不正なリクエストです。もう一度お試しください。";
    }

    if (strlen($password) < (int)$security["password_min_length"]) {
        $errors[] = "パスワードは" . (int)$security["password_min_length"] . "文字以上で入力してください。";
    }

    if ($password !== $passwordConfirm) {
        $errors[] = "確認用パスワードが一致しません。";
    }

    if (!$errors) {
        try {
            $pdo = db();

            $stmt = $pdo->prepare("
                SELECT *
                FROM password_reset_tokens
                WHERE token_hash = :token_hash
                  AND used = false
                LIMIT 1
            ");

            $stmt->execute([
                ":token_hash" => $tokenHash,
            ]);

            $reset = $stmt->fetch();

            if (!$reset) {
                $errors[] = "この再設定URLは無効です。";
            } elseif (strtotime($reset["expires_at"]) < time()) {
                $errors[] = "この再設定URLの有効期限が切れています。";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $updateUser = $pdo->prepare("
                    UPDATE users
                    SET
                        password = :password,
                        login_failed_count = 0,
                        locked_until = NULL
                    WHERE id = :user_id
                ");

                $updateUser->execute([
                    ":password" => $passwordHash,
                    ":user_id" => $reset["user_id"],
                ]);

                $updateToken = $pdo->prepare("
                    UPDATE password_reset_tokens
                    SET used = true
                    WHERE id = :id
                ");

                $updateToken->execute([
                    ":id" => $reset["id"],
                ]);

                $pdo->commit();

                $messages[] = "パスワードを変更しました。新しいパスワードでログインしてください。";
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = "パスワード変更処理中にエラーが発生しました。";
        }
    }
}

require_once __DIR__ . "/../../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../../parts/header/header.php"; ?>

<main class="auth-page password-reset-page">
    <div class="container auth-grid">
        <div class="auth-copy reveal">
            <p class="eyebrow">New Password</p>
            <h1>新しいパスワードを設定</h1>
            <p>
                新しいパスワードを入力してください。
                設定後はログイン画面から再度ログインできます。
            </p>
        </div>

        <section class="auth-card reveal">
            <h2>パスワード変更</h2>
            <p>新しいパスワードを設定します。</p>

            <?php if ($messages): ?>
                <div class="auth-success">
                    <?php foreach ($messages as $message): ?>
                        <p><?php echo h($message); ?></p>
                    <?php endforeach; ?>
                </div>

                <p class="auth-switch">
                    <a href="/login/">ログイン画面へ移動</a>
                </p>
            <?php else: ?>
                <?php if ($errors): ?>
                    <div class="auth-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="/password-reset/confirm/" method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="token" value="<?php echo h($token); ?>">

                    <div class="form-group">
                        <label for="password">新しいパスワード</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            minlength="<?php echo (int)$security["password_min_length"]; ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">新しいパスワード確認</label>
                        <input
                            id="password_confirm"
                            type="password"
                            name="password_confirm"
                            autocomplete="new-password"
                            minlength="<?php echo (int)$security["password_min_length"]; ?>"
                            required
                        >
                    </div>

                    <button class="auth-submit" type="submit">パスワードを変更</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php include __DIR__ . "/../../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/password-reset/password-reset.js"></script>
</body>
</html>