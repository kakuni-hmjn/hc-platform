<?php
session_start();

require_once __DIR__ . "/../lib/helpers.php";
require_once __DIR__ . "/../lib/db.php";
require_once __DIR__ . "/../lib/csrf.php";
require_once __DIR__ . "/../lib/auth.php";

$pageTitle = "お問い合わせ | HC Platform";
$pageDescription = "HC Platformへのお問い合わせページです。";
$pageCss = "/contact/contact.css";

$errors = [];
$messages = [];

$currentUser = current_user();

$name = $currentUser["username"] ?? "";
$email = $currentUser["email"] ?? "";
$category = "general";
$subject = "";
$message = "";

$categories = [
    "general" => "一般的なお問い合わせ",
    "account" => "アカウントについて",
    "service" => "サービスについて",
    "billing" => "契約・支払いについて",
    "bug" => "不具合報告",
    "other" => "その他",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $category = $_POST["category"] ?? "general";
    $subject = trim($_POST["subject"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if (!csrf_check($csrfToken)) {
        $errors[] = "不正なリクエストです。もう一度お試しください。";
    }

    if ($name === "") {
        $errors[] = "お名前を入力してください。";
    } elseif (mb_strlen($name) > 100) {
        $errors[] = "お名前は100文字以内で入力してください。";
    }

    if ($email === "") {
        $errors[] = "メールアドレスを入力してください。";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "メールアドレスの形式が正しくありません。";
    } elseif (mb_strlen($email) > 120) {
        $errors[] = "メールアドレスは120文字以内で入力してください。";
    }

    if (!array_key_exists($category, $categories)) {
        $errors[] = "お問い合わせ種別が正しくありません。";
    }

    if ($subject === "") {
        $errors[] = "件名を入力してください。";
    } elseif (mb_strlen($subject) > 160) {
        $errors[] = "件名は160文字以内で入力してください。";
    }

    if ($message === "") {
        $errors[] = "お問い合わせ内容を入力してください。";
    } elseif (mb_strlen($message) > 5000) {
        $errors[] = "お問い合わせ内容は5000文字以内で入力してください。";
    }

    if (!$errors) {
        try {
            $pdo = db();

            $stmt = $pdo->prepare("
                INSERT INTO contacts (
                    user_id,
                    name,
                    email,
                    category,
                    subject,
                    message,
                    status,
                    ip_address
                ) VALUES (
                    :user_id,
                    :name,
                    :email,
                    :category,
                    :subject,
                    :message,
                    'open',
                    :ip_address
                )
            ");

            $stmt->execute([
                ":user_id" => $currentUser["id"] ?? null,
                ":name" => $name,
                ":email" => $email,
                ":category" => $category,
                ":subject" => $subject,
                ":message" => $message,
                ":ip_address" => $_SERVER["REMOTE_ADDR"] ?? null,
            ]);

            $messages[] = "お問い合わせを送信しました。内容を確認後、必要に応じてご連絡します。";

            $subject = "";
            $message = "";
            $category = "general";
        } catch (Throwable $e) {
            $errors[] = "お問い合わせの送信中にエラーが発生しました。";
        }
    }
}

require_once __DIR__ . "/../parts/head.php";
?>
<body>
<?php include __DIR__ . "/../parts/header/header.php"; ?>

<main class="contact-page">

    <section class="contact-hero">
        <div class="container contact-grid">
            <div class="contact-copy reveal">
                <p class="eyebrow">Contact</p>
                <h1>お問い合わせ</h1>
                <p>
                    HC Platformへのご質問、不具合報告、サービスに関するお問い合わせはこちらから送信できます。
                </p>
            </div>

            <aside class="contact-side-card reveal">
                <span>Support</span>
                <h2>お問い合わせ前に</h2>
                <p>
                    アカウント関連、契約関連、不具合報告など、内容に近い種別を選んで送信してください。
                </p>
            </aside>
        </div>
    </section>

    <section class="section contact-section">
        <div class="container contact-layout">

            <section class="contact-panel reveal">
                <div class="panel-head">
                    <div>
                        <p class="eyebrow">Form</p>
                        <h2>お問い合わせフォーム</h2>
                    </div>
                </div>

                <?php if ($messages): ?>
                    <div class="contact-success">
                        <?php foreach ($messages as $msg): ?>
                            <p><?php echo h($msg); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="contact-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo h($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="/contact/" method="post" class="contact-form">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">お名前</label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="<?php echo h($name); ?>"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">メールアドレス</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="<?php echo h($email); ?>"
                                maxlength="120"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="category">お問い合わせ種別</label>
                        <select id="category" name="category" required>
                            <?php foreach ($categories as $key => $label): ?>
                                <option value="<?php echo h($key); ?>" <?php echo $category === $key ? "selected" : ""; ?>>
                                    <?php echo h($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject">件名</label>
                        <input
                            id="subject"
                            type="text"
                            name="subject"
                            value="<?php echo h($subject); ?>"
                            maxlength="160"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="message">お問い合わせ内容</label>
                        <textarea
                            id="message"
                            name="message"
                            rows="9"
                            maxlength="5000"
                            required
                        ><?php echo h($message); ?></textarea>
                        <small>5000文字以内で入力してください。</small>
                    </div>

                    <button class="contact-submit" type="submit">送信する</button>
                </form>
            </section>

            <aside class="contact-info reveal">
                <article class="info-card">
                    <h3>返信について</h3>
                    <p>
                        内容によっては返信まで時間がかかる場合があります。
                        返信が必要な場合は、正しいメールアドレスを入力してください。
                    </p>
                </article>

                <article class="info-card">
                    <h3>ログイン中の場合</h3>
                    <p>
                        ログイン中のユーザーは、アカウント情報とお問い合わせが紐づけられます。
                    </p>
                </article>
            </aside>

        </div>
    </section>

</main>

<?php include __DIR__ . "/../parts/footer/footer.php"; ?>

<script src="/common/base.js"></script>
<script src="/contact/contact.js"></script>
</body>
</html>