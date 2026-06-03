<?php

require_once __DIR__ . "/db.php";

function current_user(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION["user_id"])) {
        return null;
    }

    $stmt = db()->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([
        ":id" => $_SESSION["user_id"],
    ]);

    $user = $stmt->fetch();

    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        header("Location: /login/");
        exit;
    }

    return $user;
}

function login_user(int $userId): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    session_regenerate_id(true);
    $_SESSION["user_id"] = $userId;
}

function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}