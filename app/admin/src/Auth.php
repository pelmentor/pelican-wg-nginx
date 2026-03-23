<?php

class Auth {
    /** Session inactivity timeout in seconds (30 minutes). */
    private const SESSION_TIMEOUT = 1800;

    /**
     * Start the session with secure cookie parameters if not already started.
     */
    // SECURITY: httponly prevents JS from reading the session cookie (XSS can't steal sessions).
    // SameSite=Strict prevents the browser from sending the cookie on cross-origin requests (CSRF).
    private static function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    /**
     * Check whether the current session is authenticated.
     * Enforces inactivity timeout and validates user still exists.
     */
    public static function check(): bool {
        self::ensureSession();

        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Enforce session inactivity timeout
        if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::SESSION_TIMEOUT) {
            self::logout();
            return false;
        }

        // Verify user still exists
        $user = UserManager::getById($_SESSION['user_id']);
        if ($user === null) {
            self::logout();
            return false;
        }

        // Update last activity timestamp on every authenticated check
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Require authentication — redirect to login or return 401 for API routes.
     */
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

    /**
     * Get the currently authenticated user, or null if not logged in.
     */
    public static function getCurrentUser(): ?array {
        self::ensureSession();

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        return UserManager::getById($_SESSION['user_id']);
    }

    /**
     * Get the role of the currently authenticated user.
     */
    public static function getCurrentRole(): string {
        $user = self::getCurrentUser();
        return $user['role'] ?? 'viewer';
    }

    // -- CSRF Protection ---------------------------------------------------

    /**
     * Return the current CSRF token, generating one if absent.
     */
    public static function csrfToken(): string {
        self::ensureSession();
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

        // SECURITY: hash_equals() is timing-safe — prevents attackers from guessing the token
        // byte-by-byte via response time differences. Do NOT replace with === or strcmp().
        if (!is_string($token) || $token === '' || !hash_equals(self::csrfToken(), $token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'CSRF token mismatch']);
            exit;
        }
    }

    // -- Authentication ----------------------------------------------------

    /**
     * Attempt to log in with username and password.
     * Looks up user via UserManager and verifies password hash.
     *
     * @return bool true on success, false on invalid credentials
     */
    public static function login(string $username, string $password): bool {
        $user = UserManager::getByUsername($username);
        if ($user === null) {
            return false;
        }

        if (!UserManager::verifyPassword($user, $password)) {
            return false;
        }

        self::ensureSession();
        // SECURITY: session_regenerate_id(true) prevents session fixation — an attacker who sets
        // a known session ID before login can't hijack the session after authentication succeeds.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        // Generate a fresh CSRF token for the new session
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }

    public static function logout(): void {
        self::ensureSession();
        session_destroy();
    }

    /**
     * Handle the login page (GET displays form, POST processes login).
     */
    public static function handleLogin(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            if (self::login($username, $password)) {
                ActivityLog::log('auth.login', 'Successful login for user: ' . $username);
                header('Location: /');
                exit;
            }
            ActivityLog::log('auth.login_failed', 'Failed login attempt for user: ' . $username);
            $error = 'Invalid username or password';
        }
        require __DIR__ . '/View/login.php';
        exit;
    }

    public static function handleLogout(): void {
        $user = self::getCurrentUser();
        $username = $user['username'] ?? 'unknown';
        ActivityLog::log('auth.logout', 'User logged out: ' . $username);
        self::logout();
        header('Location: /login');
        exit;
    }
}
