<?php

class SettingsController {
    private const CONFIG_MAP = [
        'wireguard' => '/data/wg/wg0.conf',
        'nginx' => '/data/nginx/user.conf',
        'php' => '/data/php/php-fpm.conf',
    ];

    public function index(): void {
        $page = 'settings';
        require __DIR__ . '/../View/layout.php';
    }

    public function getConfig(): void {
        header('Content-Type: application/json');
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
        $input = json_decode(file_get_contents('php://input'), true);
        $file = self::CONFIG_MAP[$input['file'] ?? ''] ?? null;
        if (!$file) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid config file']);
            return;
        }
        file_put_contents($file, $input['content']);
        ActivityLog::log('config.save', $input['file']);
        echo json_encode(['success' => true]);
    }

    public function validateConfig(): void {
        header('Content-Type: application/json');
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
            'nginx' => shell_exec("nginx -t -c $tmpFile 2>&1"),
            'php' => 'PHP-FPM config validation not available via CLI',
            'wireguard' => $this->validateWgConfig($content),
            default => 'Unknown config type',
        };

        @unlink($tmpFile);

        $valid = !str_contains($result, 'failed') && !str_contains($result, 'error');
        echo json_encode(['valid' => $valid, 'output' => $result]);
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
        $input = json_decode(file_get_contents('php://input'), true);
        $mgr = new ServiceManager();
        $result = $mgr->control($input['service'] ?? '', $input['action'] ?? '');
        ActivityLog::log('service.' . ($input['action'] ?? 'unknown'), $input['service'] ?? 'unknown');
        echo json_encode($result);
    }

    public function serviceStatus(): void {
        header('Content-Type: application/json');
        $mgr = new ServiceManager();
        echo json_encode($mgr->getAllStatus());
    }
}
