<?php

class SettingsController {
    private const CONFIG_MAP = [
        'nginx'     => USER_CONFIG_DIR . '/nginx-site.conf',
        'php'       => USER_CONFIG_DIR . '/php-fpm.conf',
        'wireguard' => '/etc/wireguard/wg0.conf',
    ];

    public function index(): void {
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.view');
        $page = 'settings';
        require __DIR__ . '/../View/layout.php';
    }

    public function getConfig(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.view');

        $file = self::CONFIG_MAP[$_GET['file'] ?? ''] ?? null;
        if (!$file || !file_exists($file)) {
            http_response_code(404);
            echo json_encode(['error' => 'Config not found']);
            return;
        }
        echo json_encode(['content' => file_get_contents($file), 'file' => $_GET['file']]);
    }

    public function saveConfig(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.write');

        $input = json_decode(file_get_contents('php://input'), true);
        $file = self::CONFIG_MAP[$input['file'] ?? ''] ?? null;
        if (!$file) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid config file']);
            return;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // SECURITY: Atomic write via tmp + rename — prevents readers from seeing a half-written
        // config file. rename() is atomic on POSIX filesystems; a crash mid-write won't corrupt.
        $tmpFile = $file . '.tmp';
        file_put_contents($tmpFile, $input['content'], LOCK_EX);
        rename($tmpFile, $file);
        ActivityLog::log('config.save', $input['file']);
        echo json_encode(['success' => true]);
    }

    public function validateConfig(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.view');

        $input = json_decode(file_get_contents('php://input'), true);
        $file = $input['file'] ?? '';
        $content = $input['content'] ?? '';

        if (!isset(self::CONFIG_MAP[$file])) {
            http_response_code(400);
            echo json_encode(['valid' => false, 'output' => 'Unknown config type']);
            return;
        }

        $tmpFile = '/tmp/validate_' . basename(self::CONFIG_MAP[$file]);
        file_put_contents($tmpFile, $content);

        $result = match ($file) {
            'nginx' => $this->validateNginxConfig($tmpFile),
            'php' => 'PHP-FPM config validation not available via CLI',
            'wireguard' => $this->validateWgConfig($content),
            default => 'Unknown config type',
        };

        @unlink($tmpFile);

        $valid = !str_contains($result, 'failed') && !str_contains($result, 'error');
        echo json_encode(['valid' => $valid, 'output' => $result]);
    }

    /**
     * Validate Nginx config by wrapping the user.conf snippet in a minimal
     * http{} block so that `nginx -t` receives a full, valid config file.
     */
    private function validateNginxConfig(string $snippetPath): string {
        $wrapperPath = '/tmp/validate_nginx_wrapper.conf';
        $wrapper = "events {}\nhttp { include /etc/nginx/mime.types; include " . $snippetPath . "; }\n";
        file_put_contents($wrapperPath, $wrapper);

        // SECURITY: escapeshellarg() is critical — without it, a crafted file path could inject
        // arbitrary shell commands. Never remove or replace with manual quoting.
        $output = shell_exec("nginx -t -c " . escapeshellarg($wrapperPath) . " 2>&1") ?? '';
        @unlink($wrapperPath);

        return $output;
    }

    /**
     * Basic WireGuard config validation — checks required keys are present.
     */
    private function validateWgConfig(string $content): string {
        $errors = [];

        if (!preg_match('/\[Interface\]/i', $content)) {
            $errors[] = '[Interface] section missing';
        }
        if (!preg_match('/PrivateKey\s*=/', $content)) {
            $errors[] = 'PrivateKey not set in [Interface]';
        }
        if (!preg_match('/Address\s*=/', $content)) {
            $errors[] = 'Address not set in [Interface]';
        }
        if (!preg_match('/ListenPort\s*=/', $content)) {
            $errors[] = 'ListenPort not set in [Interface]';
        }

        if (empty($errors)) {
            return 'WireGuard config syntax OK';
        }
        return 'WireGuard config errors: ' . implode('; ', $errors);
    }

    public function serviceAction(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.write');

        $input = json_decode(file_get_contents('php://input'), true);
        $mgr = new ServiceManager();
        $result = $mgr->control($input['service'] ?? '', $input['action'] ?? '');
        ActivityLog::log('service.' . ($input['action'] ?? 'unknown'), $input['service'] ?? 'unknown');
        echo json_encode($result);
    }

    public function serviceStatus(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.view');

        $mgr = new ServiceManager();
        echo json_encode($mgr->getAllStatus());
    }
}
