<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/contact-chat.php';

$currentUser = require_login();
$userId = (int) ($currentUser['id'] ?? 0);
$pdo = db();
$schemaReady = contact_chat_schema_ready($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactId = filter_input(
        INPUT_POST,
        'contact_id',
        FILTER_VALIDATE_INT
    );
    $contactId = is_int($contactId) ? $contactId : 0;

    try {
        if (!csrf_check($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException(
                'セッションを確認できませんでした。もう一度お試しください。'
            );
        }

        $contact = contact_chat_find_for_user(
            $pdo,
            $contactId,
            $userId
        );

        if ($contact === null) {
            throw new RuntimeException('サポートチャットが見つかりません。');
        }

        contact_chat_add_customer_message(
            $pdo,
            $contact,
            $userId,
            (string) ($_POST['body'] ?? '')
        );

        $_SESSION['contact_chat_flash'] = [
            'type' => 'success',
            'message' => 'メッセージを送信しました。',
        ];
    } catch (Throwable $error) {
        $message = $error instanceof InvalidArgumentException
            || $error instanceof RuntimeException
            ? $error->getMessage()
            : '送信中にエラーが発生しました。もう一度お試しください。';

        $_SESSION['contact_chat_flash'] = [
            'type' => 'error',
            'message' => $message,
        ];
    }

    header(
        'Location: /dashboard/support/?id=' . max(0, $contactId)
    );
    exit;
}

$contacts = contact_chat_list_for_user($pdo, $userId);
$requestedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$selectedId = is_int($requestedId)
    ? $requestedId
    : (int) ($contacts[0]['id'] ?? 0);
$selected = contact_chat_find_for_user(
    $pdo,
    $selectedId,
    $userId
);
$chatMessages = $selected !== null
    ? contact_chat_messages($pdo, $selected)
    : [];

if ($selected !== null) {
    contact_chat_mark_notifications_read(
        $pdo,
        $userId,
        (int) $selected['id']
    );
}

$flash = $_SESSION['contact_chat_flash'] ?? null;
unset($_SESSION['contact_chat_flash']);

if (isset($_GET['created']) && $selected !== null) {
    $flash = [
        'type' => 'success',
        'message' => 'お問い合わせを受け付けました。この画面で続けて会話できます。',
    ];
}

function contact_chat_page_date(mixed $value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable((string) $value))
            ->format('Y/m/d H:i');
    } catch (Throwable) {
        return (string) $value;
    }
}

function contact_chat_page_preview(mixed $value, int $length = 64): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string) $value));

    if (!is_string($text)) {
        return '';
    }

    return mb_strlen($text) > $length
        ? mb_substr($text, 0, $length) . '…'
        : $text;
}

$pageTitle = 'サポートチャット | HC Platform';
$pageDescription = 'HCサポートとの個別チャットです。';
$pageCss = '/dashboard/support/support.css?v=1';

require_once __DIR__ . '/../../parts/head.php';
?>
<body>
<?php include __DIR__ . '/../../parts/header/header.php'; ?>

<main class="contact-chat-page" data-contact-chat-root>
    <section class="contact-chat-hero">
        <div class="container contact-chat-hero__inner">
            <div>
                <p class="eyebrow">HC ACCOUNT SUPPORT</p>
                <h1>サポートチャット</h1>
                <p>
                    HCアカウントに紐づいた、サポートスタッフとの個別チャットです。
                </p>
            </div>

            <a href="/contact/" class="contact-chat-new">
                新しいお問い合わせ
            </a>
        </div>
    </section>

    <section class="section contact-chat-section">
        <div class="container">
            <?php if (is_array($flash)): ?>
                <div class="contact-chat-flash contact-chat-flash--<?= h(
                    ($flash['type'] ?? '') === 'error' ? 'error' : 'success'
                ) ?>" role="status">
                    <?= h($flash['message'] ?? '') ?>
                </div>
            <?php endif; ?>

            <?php if (!$schemaReady): ?>
                <div class="contact-chat-flash contact-chat-flash--error" role="status">
                    サポートチャットのDB更新がまだ適用されていません。
                </div>
            <?php endif; ?>

            <div class="contact-chat-shell<?= $selected === null
                ? ' contact-chat-shell--empty'
                : '' ?>">
                <aside class="contact-chat-list" aria-label="サポートチャット一覧">
                    <header>
                        <div>
                            <span>YOUR CHATS</span>
                            <h2>お問い合わせ</h2>
                        </div>
                        <strong><?= count($contacts) ?></strong>
                    </header>

                    <div class="contact-chat-list__items">
                        <?php if ($contacts === []): ?>
                            <div class="contact-chat-empty contact-chat-empty--list">
                                <strong>チャットはまだありません</strong>
                                <p>お問い合わせを送信すると、ここに表示されます。</p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($contacts as $contact): ?>
                            <?php $contactId = (int) ($contact['id'] ?? 0); ?>
                            <a
                                href="/dashboard/support/?id=<?= $contactId ?>"
                                class="contact-chat-list__item<?= $contactId === $selectedId
                                    ? ' is-active'
                                    : '' ?>"
                            >
                                <span class="contact-chat-list__icon">HC</span>
                                <span class="contact-chat-list__body">
                                    <span>
                                        <b><?= h($contact['subject'] ?? 'お問い合わせ') ?></b>
                                        <time><?= h(contact_chat_page_date(
                                            $contact['latest_at'] ?? null
                                        )) ?></time>
                                    </span>
                                    <small><?= h(contact_chat_ticket_number($contactId)) ?></small>
                                    <p><?= h(contact_chat_page_preview(
                                        $contact['latest_message'] ?? ''
                                    )) ?></p>
                                    <em class="contact-chat-status contact-chat-status--<?= h(
                                        (string) ($contact['status'] ?? 'open')
                                    ) ?>">
                                        <?= h(contact_chat_status_label(
                                            (string) ($contact['status'] ?? 'open')
                                        )) ?>
                                    </em>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <section class="contact-chat-thread">
                    <?php if ($selected === null): ?>
                        <div class="contact-chat-empty">
                            <span>HC</span>
                            <h2>サポートへ相談する</h2>
                            <p>
                                新しいお問い合わせを送信すると、スタッフとの会話が始まります。
                            </p>
                            <a href="/contact/">お問い合わせを作成</a>
                        </div>
                    <?php else: ?>
                        <header class="contact-chat-thread__header">
                            <a
                                href="/dashboard/support/"
                                class="contact-chat-back"
                                aria-label="チャット一覧へ戻る"
                            >
                                ←
                            </a>
                            <div>
                                <span>HCアカウント個別チャット</span>
                                <h2><?= h($selected['subject'] ?? 'お問い合わせ') ?></h2>
                                <p>
                                    <?= h(contact_chat_ticket_number((int) $selected['id'])) ?>
                                    ・
                                    <?= h(contact_chat_status_label(
                                        (string) ($selected['status'] ?? 'open')
                                    )) ?>
                                </p>
                            </div>
                        </header>

                        <div class="contact-chat-messages" data-contact-chat-messages>
                            <div class="contact-chat-date"><span>会話内容</span></div>

                            <?php foreach ($chatMessages as $chatMessage): ?>
                                <?php $isCustomer = ($chatMessage['author_type'] ?? '') === 'customer'; ?>
                                <article class="contact-chat-message contact-chat-message--<?= $isCustomer
                                    ? 'customer'
                                    : 'staff' ?>">
                                    <span class="contact-chat-message__avatar">
                                        <?= $isCustomer
                                            ? h(mb_strtoupper(mb_substr(
                                                (string) ($currentUser['username'] ?? 'U'),
                                                0,
                                                1,
                                                'UTF-8'
                                            ), 'UTF-8'))
                                            : 'HC' ?>
                                    </span>
                                    <div>
                                        <header>
                                            <strong><?= h(
                                                $isCustomer
                                                    ? 'あなた'
                                                    : ($chatMessage['author_name'] ?? 'HCサポート')
                                            ) ?></strong>
                                            <time><?= h(contact_chat_page_date(
                                                $chatMessage['created_at'] ?? null
                                            )) ?></time>
                                        </header>
                                        <p><?= nl2br(h($chatMessage['body'] ?? '')) ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <form
                            action="/dashboard/support/"
                            method="post"
                            class="contact-chat-composer"
                            data-contact-chat-composer
                        >
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="contact_id" value="<?= (int) $selected['id'] ?>">
                            <label for="contactChatBody">メッセージ</label>
                            <textarea
                                id="contactChatBody"
                                name="body"
                                maxlength="5000"
                                rows="4"
                                placeholder="サポートへのメッセージを入力…"
                                data-contact-chat-body
                                <?= !$schemaReady ? 'disabled' : '' ?>
                            ></textarea>
                            <footer>
                                <span data-contact-chat-count>0 / 5000</span>
                                <small>スタッフにのみ送信されます</small>
                                <button type="submit" <?= !$schemaReady ? 'disabled' : '' ?>>
                                    送信する
                                </button>
                            </footer>
                        </form>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../../parts/footer/footer.php'; ?>
<script src="/common/base.js"></script>
<script src="/dashboard/support/support.js?v=1"></script>
</body>
</html>
