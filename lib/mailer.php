<?php

function send_verification_code(string $email, string $code): bool
{
    $config = require __DIR__ . "/../config/mail.php";

    if (($config["mode"] ?? "log") !== "smtp") {
        return log_verification_code($email, $code);
    }

    $subject = "HC Account 認証コード";

    $body = <<<TEXT
HC Account 認証コード

あなたの認証コードは以下です。

{$code}

このコードの有効期限は10分です。

このメールに心当たりがない場合は、このメールを破棄してください。
TEXT;

    return send_smtp_mail(
        $config["smtp_host"],
        (int)$config["smtp_port"],
        $config["smtp_user"],
        $config["smtp_password"],
        $config["from_email"],
        $config["from_name"],
        $email,
        $subject,
        $body
    );
}

function log_verification_code(string $email, string $code): bool
{
    $logDir = __DIR__ . "/../storage/logs";
    $logFile = $logDir . "/mail.log";

    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    cleanup_old_mail_log_codes($logFile, 10);

    $message = "[" . date("Y-m-d H:i:s") . "] {$email} 認証コード: {$code}\n";

    file_put_contents($logFile, $message, FILE_APPEND);

    return true;
}

function cleanup_old_mail_log_codes(string $logFile, int $minutes = 10): void
{
    if (!file_exists($logFile)) {
        return;
    }

    $lines = file($logFile, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        return;
    }

    $now = time();
    $keepLines = [];

    foreach ($lines as $line) {
        if (preg_match('/^\[(.*?)\]/', $line, $matches)) {
            $loggedTime = strtotime($matches[1]);

            if ($loggedTime !== false && ($now - $loggedTime) <= ($minutes * 60)) {
                $keepLines[] = $line;
            }

            continue;
        }

        $keepLines[] = $line;
    }

    $content = "";

    if (!empty($keepLines)) {
        $content = implode(PHP_EOL, $keepLines) . PHP_EOL;
    }

    file_put_contents($logFile, $content);
}

function send_smtp_mail(
    string $host,
    int $port,
    string $username,
    string $password,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $body
): bool {
    if ($host === "" || $username === "" || $password === "" || $fromEmail === "") {
        mail_error_log("SMTP設定が不足しています。");
        return false;
    }

    $headers = [];
    $headers[] = "From: " . mb_encode_mimeheader($fromName, "UTF-8") . " <{$fromEmail}>";
    $headers[] = "To: <{$toEmail}>";
    $headers[] = "Subject: " . mb_encode_mimeheader($subject, "UTF-8");
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "Content-Transfer-Encoding: 8bit";

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

    $socket = fsockopen($host, $port, $errno, $errstr, 20);

    if (!$socket) {
        mail_error_log("SMTP接続失敗: {$errno} {$errstr}");
        return false;
    }

    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, [220]);

        smtp_command($socket, "EHLO localhost", [250]);

        if ($port === 587 || $port === 2525) {
            smtp_command($socket, "STARTTLS", [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException("STARTTLSに失敗しました。");
            }

            smtp_command($socket, "EHLO localhost", [250]);
        }

        smtp_command($socket, "AUTH LOGIN", [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);

        smtp_command($socket, "MAIL FROM:<{$fromEmail}>", [250]);
        smtp_command($socket, "RCPT TO:<{$toEmail}>", [250, 251]);
        smtp_command($socket, "DATA", [354]);

        fwrite($socket, smtp_escape_message($message) . "\r\n.\r\n");

        smtp_expect($socket, [250]);

        smtp_command($socket, "QUIT", [221]);

        fclose($socket);
        return true;
    } catch (Throwable $e) {
        mail_error_log($e->getMessage());

        if (is_resource($socket)) {
            fclose($socket);
        }

        return false;
    }
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expectedCodes);
}

function smtp_expect($socket, array $expectedCodes): string
{
    $response = "";

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (strlen($line) >= 4 && $line[3] === " ") {
            break;
        }
    }

    if ($response === "") {
        throw new RuntimeException("SMTPサーバーから応答がありません。");
    }

    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP応答エラー: " . trim($response));
    }

    return $response;
}

function smtp_escape_message(string $message): string
{
    $message = str_replace(["\r\n", "\r"], "\n", $message);
    $lines = explode("\n", $message);

    foreach ($lines as &$line) {
        if (str_starts_with($line, ".")) {
            $line = "." . $line;
        }
    }

    return implode("\r\n", $lines);
}

function mail_error_log(string $message): void
{
    $logDir = __DIR__ . "/../storage/logs";

    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    file_put_contents(
        $logDir . "/mail-error.log",
        "[" . date("Y-m-d H:i:s") . "] " . $message . "\n",
        FILE_APPEND
    );
}

function send_password_reset_link(string $email, string $resetUrl): bool
{
    $config = require __DIR__ . "/../config/mail.php";

    if (($config["mode"] ?? "log") !== "smtp") {
        $logDir = __DIR__ . "/../storage/logs";

        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        file_put_contents(
            $logDir . "/mail.log",
            "[" . date("Y-m-d H:i:s") . "] {$email} パスワードリセットURL: {$resetUrl}\n",
            FILE_APPEND
        );

        return true;
    }

    $subject = "HC Account パスワード再設定";

    $body = <<<TEXT
HC Account パスワード再設定

以下のURLからパスワードを再設定してください。

{$resetUrl}

このURLの有効期限は30分です。

このメールに心当たりがない場合は、このメールを破棄してください。
TEXT;

    return send_smtp_mail(
        $config["smtp_host"],
        (int)$config["smtp_port"],
        $config["smtp_user"],
        $config["smtp_password"],
        $config["from_email"],
        $config["from_name"],
        $email,
        $subject,
        $body
    );
}