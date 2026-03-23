<?php

class Auth {
    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return !empty($_SESSION['authenticated']);
    }

    public static function requireAuth(): void {
        if (self::check()) return;

        if (str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        header('Location: /login');
        exit;
    }

    public static function login(string $password): bool {
        if (!hash_equals(ADMIN_PASSWORD, $password)) return false;

        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        return true;
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_destroy();
    }

    public static function handleLogin(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            if (self::login($password)) {
                header('Location: /');
                exit;
            }
            $error = 'Invalid password';
        }
        require __DIR__ . '/View/login.php';
        exit;
    }

    public static function handleLogout(): void {
        self::logout();
        header('Location: /login');
        exit;
    }
}
