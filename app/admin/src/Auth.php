<?php

class Auth {
    /** Session inactivity timeout in seconds (30 minutes). */
    private const SESSION_TIMEOUT = 1800;

    public static function check(): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (empty($_SESSION['authenticated'])) {
            return false;
        }

        // Enforce session inactivity timeout
        if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::SESSION_TIMEOUT) {
            self::logout();
            return false;
        }

        // Update last activity timestamp on every authenticated check
        $_SESSION['last_activity'] = time();
        return true;
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

    // ── CSRF Protection ──────────────────────────────────────────────

    /**
     * Return the current CSRF token, generating one if absent.
     */
    public static function csrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token on POST requests.
     * Checks the `_csrf` body field first, then the `X-CSRF-Token` header.
     * Aborts with 403 on mismatch.
     */
    public static function verifyCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $token = $_POST['_csrf']
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!is_string($token) || $token === '' || !hash_equals(self::csrfToken(), $token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF token mismatch']);
            exit;
        }
    }

    // ── Authentication ───────────────────────────────────────────────

    public static function login(string $password): bool {
        if (!hash_equals(ADMIN_PASSWORD, $password)) return false;

        if (session_status() === PHP_SESSION_NONE) session_start();
        // Regenerate session ID to prevent fixation attacks
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        // Generate a fresh CSRF token for the new session
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
                ActivityLog::log('auth.login', 'Successful login');
                header('Location: /');
                exit;
            }
            ActivityLog::log('auth.login_failed', 'Failed login attempt');
            $error = 'Invalid password';
        }
        require __DIR__ . '/View/login.php';
        exit;
    }

    public static function handleLogout(): void {
        ActivityLog::log('auth.logout', 'User logged out');
        self::logout();
        header('Location: /login');
        exit;
    }
}
