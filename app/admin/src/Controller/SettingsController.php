<?php

class SettingsController {
    private const CONFIG_MAP = [
        'nginx'     => USER_CONFIG_DIR . '/nginx-site.conf',
        'php'       => USER_CONFIG_DIR . '/php-fpm.conf',
        'wireguard' => USER_CONFIG_DIR . '/wg0.conf',
    ];

    public function index(): void {
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.view');
        $page = 'settings';
        require __DIR__ . '/../View/layout.php';
    }

    // ─── Nginx / PHP config (raw text) ───────────────────────────────

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

        // WireGuard config should be 600 (wg-quick warns if more permissive)
        if (($input['file'] ?? '') === 'wireguard') {
            chmod($file, 0600);
        }

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
     * Basic WireGuard config validation — checks required fields.
     * ListenPort is optional (only needed for server mode).
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
        if (!preg_match('/\[Peer\]/i', $content)) {
            $errors[] = '[Peer] section missing';
        }
        if (!preg_match('/PublicKey\s*=/', $content)) {
            $errors[] = 'PublicKey not set in [Peer]';
        }

        if (empty($errors)) {
            return 'WireGuard config syntax OK';
        }
        return 'WireGuard config errors: ' . implode('; ', $errors);
    }

    // ─── WireGuard structured form API ───────────────────────────────

    /**
     * GET /api/settings/wireguard — returns WG config parsed into form fields.
     * If no config exists, returns empty template fields.
     */
    public function getWireguard(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.view');

        $file = self::CONFIG_MAP['wireguard'];
        if (!file_exists($file)) {
            echo json_encode([
                'exists' => false,
                'interface' => [
                    'private_key' => '', 'address' => '', 'dns' => '', 'listen_port' => '',
                ],
                'peer' => [
                    'public_key' => '', 'preshared_key' => '', 'endpoint' => '',
                    'allowed_ips' => '', 'persistent_keepalive' => '25',
                ],
            ]);
            return;
        }

        $parsed = self::parseWgConfig(file_get_contents($file));
        $parsed['exists'] = true;
        echo json_encode($parsed);
    }

    /**
     * POST /api/settings/wireguard — saves WG config from form fields.
     * Generates INI-style config file from structured input.
     */
    public function saveWireguard(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'settings.write');

        $input = json_decode(file_get_contents('php://input'), true);
        $iface = $input['interface'] ?? [];
        $peer = $input['peer'] ?? [];

        // Validate required fields
        if (empty(trim($iface['private_key'] ?? ''))) {
            http_response_code(400);
            echo json_encode(['error' => 'Private Key is required']);
            return;
        }
        if (empty(trim($iface['address'] ?? ''))) {
            http_response_code(400);
            echo json_encode(['error' => 'Address is required']);
            return;
        }

        // Generate INI-style config from fields
        $config = "[Interface]\n";
        $config .= "PrivateKey = " . trim($iface['private_key']) . "\n";
        $config .= "Address = " . trim($iface['address']) . "\n";
        if (!empty(trim($iface['dns'] ?? ''))) {
            $config .= "DNS = " . trim($iface['dns']) . "\n";
        }
        if (!empty(trim($iface['listen_port'] ?? ''))) {
            $config .= "ListenPort = " . trim($iface['listen_port']) . "\n";
        }

        $config .= "\n[Peer]\n";
        if (!empty(trim($peer['public_key'] ?? ''))) {
            $config .= "PublicKey = " . trim($peer['public_key']) . "\n";
        }
        if (!empty(trim($peer['preshared_key'] ?? ''))) {
            $config .= "PresharedKey = " . trim($peer['preshared_key']) . "\n";
        }
        if (!empty(trim($peer['endpoint'] ?? ''))) {
            $config .= "Endpoint = " . trim($peer['endpoint']) . "\n";
        }
        if (!empty(trim($peer['allowed_ips'] ?? ''))) {
            $config .= "AllowedIPs = " . trim($peer['allowed_ips']) . "\n";
        }
        if (!empty(trim($peer['persistent_keepalive'] ?? ''))) {
            $config .= "PersistentKeepalive = " . trim($peer['persistent_keepalive']) . "\n";
        }

        $file = self::CONFIG_MAP['wireguard'];
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $tmpFile = $file . '.tmp';
        file_put_contents($tmpFile, $config, LOCK_EX);
        rename($tmpFile, $file);
        chmod($file, 0600);

        ActivityLog::log('config.save', 'wireguard');
        echo json_encode(['success' => true]);
    }

    /**
     * Parse WireGuard INI config into structured fields.
     */
    private static function parseWgConfig(string $content): array {
        $result = [
            'interface' => [
                'private_key' => '', 'address' => '', 'dns' => '', 'listen_port' => '',
            ],
            'peer' => [
                'public_key' => '', 'preshared_key' => '', 'endpoint' => '',
                'allowed_ips' => '', 'persistent_keepalive' => '',
            ],
        ];

        $section = '';
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            if (preg_match('/^\[(\w+)\]$/i', $line, $m)) {
                $section = strtolower($m[1]);
                continue;
            }
            if (!str_contains($line, '=')) continue;

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $normalKey = strtolower(str_replace(' ', '', $key));

            if ($section === 'interface') {
                match ($normalKey) {
                    'privatekey'  => $result['interface']['private_key'] = $value,
                    'address'     => $result['interface']['address'] = $value,
                    'dns'         => $result['interface']['dns'] = $value,
                    'listenport'  => $result['interface']['listen_port'] = $value,
                    default       => null,
                };
            } elseif ($section === 'peer') {
                match ($normalKey) {
                    'publickey'           => $result['peer']['public_key'] = $value,
                    'presharedkey'        => $result['peer']['preshared_key'] = $value,
                    'endpoint'            => $result['peer']['endpoint'] = $value,
                    'allowedips'          => $result['peer']['allowed_ips'] = $value,
                    'persistentkeepalive' => $result['peer']['persistent_keepalive'] = $value,
                    default               => null,
                };
            }
        }

        return $result;
    }

    // ─── Service controls ────────────────────────────────────────────

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
