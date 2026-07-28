<?php
session_start();

require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/csrf.php";
require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/mailer.php";

$security = require __DIR__ . "/../config/security.php";

$pageTitle = "認証コード入力 | HC Platform";
$pageDescription = "HC Accountのメール認証ページです。";
$pageCss = "/verify-code/verify-code.css";


$redirect = $_GET["redirect"] ?? $_POST["redirect"] ?? ($_SESSION["pending_redirect"] ?? "/dashboard/");
$redirect = safe_redirect_path($redirect);
$_SESSION["pending_redirect"] = $redirect;

$errors = [];
$messages = [];

$email = $_SESSION["pending_email"] ?? "";

if ($email === "") {
    $errors[] = "認証待ちのメールアドレスが見つかりません。もう一度新規登録してください。";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "verify";
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!csrf_check($csrfToken)) {
        $errors[] = "不正なリクエストです。もう一度お試しください。";
    }

    if ($email === "") {
        $errors[] = "認証情報が見つかりません。もう一度新規登録してください。";
    }

    if (!$errors && $action === "resend") {
        try {
            $pdo = db();

            $stmt = $pdo->prepare("
                SELECT *
                FROM pending_registrations
                WHERE email = :email
                LIMIT 1
            ");

            $stmt->execute([
                ":email" => $email,
            ]);

            $pending = $stmt->fetch();

            if (!$pending) {
                $errors[] = "仮登録情報が見つかりません。もう一度新規登録してください。";
            } elseif ((int)$pending["resend_count"] >= (int)$security["max_verification_resends"]) {
                $errors[] = "認証コードの再送回数が上限に達しました。もう一度新規登録してください。";
            } elseif (!empty($pending["last_sent_at"])) {
                $waitSeconds = (int)$security["verification_resend_wait_seconds"];

                $waitStmt = $pdo->prepare("
                    SELECT GREATEST(
                        0,
                        :wait_seconds - EXTRACT(EPOCH FROM (NOW() - last_sent_at))
                    )::integer AS remaining_seconds
                    FROM pending_registrations
                    WHERE id = :id
                ");

                $waitStmt->execute([
                    ":wait_seconds" => $waitSeconds,
                    ":id" => $pending["id"],
                ]);

                $remaining = (int)($waitStmt->fetchColumn() ?: 0);

                if ($remaining > 0) {
                    $errors[] = "認証コードの再送はあと" . $remaining . "秒後にお試しください。";
                }
            }

            if (!$errors) {
                $code = (string) random_int(100000, 999999);
                $codeHash = password_hash($code, PASSWORD_DEFAULT);
                $expiresMinutes = (int)$security["verification_code_minutes"];

                $update = $pdo->prepare("
                    UPDATE pending_registrations
                    SET
                        verification_code_hash = :verification_code_hash,
                        failed_attempts = 0,
                        expires_at = NOW() + (:minutes || ' minutes')::interval,
                        resend_count = resend_count + 1,
                        last_sent_at = NOW()
                    WHERE id = :id
                ");

                $update->execute([
                    ":verification_code_hash" => $codeHash,
                    ":minutes" => $expiresMinutes,
                    ":id" => $pending["id"],
                ]);

                if (send_verification_code($email, $code)) {
                    $messages[] = "認証コードを再送しました。メールをご確認ください。";
                } else {
                    $errors[] = "認証メールの再送に失敗しました。時間をおいて再度お試しください。";
                }
            }
        } catch (Throwable $e) {
            $errors[] = "認証コード再送中にエラーが発生しました。";
        }
    }

    if (!$errors && $action === "verify") {
        $code = trim($_POST["code"] ?? "");

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            $errors[] = "認証コードは6桁の数字で入力してください。";
        }

        if (!$errors) {
            try {
                $pdo = db();

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM pending_registrations
                    WHERE email = :email
                    LIMIT 1
                ");

                $stmt->execute([
                    ":email" => $email,
                ]);

                $pending = $stmt->fetch();

                if (!$pending) {
                    $errors[] = "仮登録情報が見つかりません。もう一度新規登録してください。";
                } elseif (strtotime($pending["expires_at"]) < time()) {
                    $errors[] = "認証コードの有効期限が切れています。認証コードを再送してください。";
                } elseif ((int)$pending["failed_attempts"] >= (int)$security["max_verify_attempts"]) {
                    $errors[] = "認証コードの入力回数が上限に達しました。もう一度新規登録してください。";
                } elseif (!password_verify($code, $pending["verification_code_hash"])) {
                    $update = $pdo->prepare("
                        UPDATE pending_registrations
                        SET failed_attempts = failed_attempts + 1
                        WHERE id = :id
                    ");

                    $update->execute([
                        ":id" => $pending["id"],
                    ]);

                    $errors[] = "認証コードが正しくありません。";
                } else {
                    $pdo->beginTransaction();

                    $insert = $pdo->prepare("
                        INSERT INTO users
                            (
                                username,
                                email,
                                password,
                                role,
                                status,
                                email_verified,
                                email_verified_at,
                                register_ip,
                                terms_accepted,
                                terms_accepted_at
                            )
                        VALUES
                            (
                                :username,
                                :email,
                                :password,
                                'user',
                                'active',
                                true,
                                NOW(),
                                :register_ip,
                                :terms_accepted,
                                :terms_accepted_at
                            )
                        RETURNING id
                    ");

                    $insert->execute([
                        ":username" => $pending["username"],
                        ":email" => $pending["email"],
                        ":password" => $pending["password"],
                        ":register_ip" => $pending["ip_address"],
                        ":terms_accepted" => $pending["terms_accepted"],
                        ":terms_accepted_at" => $pending["terms_accepted_at"],
                    ]);

                    $user = $insert->fetch();

                    $delete = $pdo->prepare("
                        DELETE FROM pending_registrations
                        WHERE id = :id
                    ");

                    $delete->execute([
                        ":id" => $pending["id"],
                    ]);

                    $pdo->commit();

                    unset($_SESSION["pending_email"]);

                    session_regenerate_id(true);
                    $_SESSION["user_id"] = $user["id"];

                    redirect($redirect);
                }
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = "認証処理中にエラーが発生しました。";
            }
        }
    }
}

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="auth-page verify-page">
    <div class="container auth-grid">
        <div class="auth-copy reveal">
            <p class="eyebrow">メール認証</p>
            <h1>認証コードを入力</h1>
            <p>
                登録したメールアドレスに届いた6桁の認証コードを入力してください。
                コードの有効期限は<?php echo h((string)$security["verification_code_minutes"]); ?>分です。
            </p>
        </div>

        <section class="auth-card reveal">
            <h2>認証コード</h2>

            <?php if ($email): ?>
                <p>
                    <strong><?php echo h($email); ?></strong> に送信されたコードを入力してください。
                </p>
            <?php else: ?>
                <p>認証待ちのメールアドレスがありません。</p>
            <?php endif; ?>

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

            <form action="/verify-code/" method="post" class="auth-form">
        <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="verify">

                <div class="form-group">
                    <label for="code">6桁の認証コード</label>
                    <input
                        id="code"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        placeholder="123456"
                        required
                    >
                </div>

                <button class="auth-submit" type="submit">認証して登録完了</button>
            </form>

            <form action="/verify-code/" method="post" class="resend-form">
                <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="resend">

                <button class="resend-button" type="submit">
                    認証コードを再送
                </button>
            </form>

            <p class="auth-switch">
                メールアドレスを変更する場合は <a href="/register/?redirect=<?= rawurlencode($redirect) ?>">新規登録に戻る</a>
            </p>
        </section>
    </div>
</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/verify-code/verify-code.js"></script>
</body>
</html>