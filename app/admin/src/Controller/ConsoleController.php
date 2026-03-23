<?php

class ConsoleController {
    private const LOG_FILES = [
        'nginx-error'   => USER_LOGS_DIR . '/nginx-error.log',
        'php-fpm-error' => USER_LOGS_DIR . '/php-fpm-error.log',
        'nginx-access'  => USER_LOGS_DIR . '/nginx-access.log',
        'wireguard'     => USER_LOGS_DIR . '/wireguard.log',
        'admin-error'   => ADMIN_LOGS_DIR . '/admin-error.log',
    ];

    public function index(): void {
        Permission::requirePerm(Auth::getCurrentRole(), 'console.read');
        $page = 'console';
        require __DIR__ . '/../View/layout.php';
    }

    /**
     * GET /api/console/poll?positions={"nginx-error":1234,...}
     *
     * Returns new log lines since the given byte positions.
     * Client polls every 2 seconds. Each request takes ~50ms
     * and releases the PHP-FPM worker immediately.
     */
    public function poll(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'console.read');

        $positions = json_decode($_GET['positions'] ?? '{}', true) ?: [];

        $streamer = new LogStreamer();
        $result = $streamer->poll(self::LOG_FILES, $positions);

        echo json_encode($result);
    }

    /**
     * POST /api/console/command
     * Body: {"command": "wg show"}
     */
    public function command(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'console.write');

        $input = json_decode(file_get_contents('php://input'), true);
        $cmd = trim($input['command'] ?? '');

        if ($cmd === '' || $cmd === 'help') {
            echo json_encode(['output' => $this->help()]);
            return;
        }

        // Client-side 'clear' is handled in JS, but respond gracefully if sent
        if ($cmd === 'clear') {
            echo json_encode(['output' => 'Terminal cleared.']);
            return;
        }

        // Ping — validate host to prevent injection
        if ($cmd === 'ping') {
            echo json_encode(['output' => "Usage: ping <host>\nExample: ping 10.0.0.1\n         ping google.com"]);
            return;
        }
        if (preg_match('/^ping\s+([\w.\-:]+)$/', $cmd, $m)) {
            ActivityLog::log('console.command', $cmd);
            $output = shell_exec('ping -c 4 -W 3 ' . escapeshellarg($m[1]) . ' 2>&1');
            echo json_encode(['output' => $output]);
            return;
        }
        if (str_starts_with($cmd, 'ping ')) {
            echo json_encode(['output' => "Invalid host. Usage: ping <host>\nHost can contain letters, numbers, dots, hyphens."]);
            return;
        }

        // SECURITY: sudo required — PHP-FPM runs as www-data, these commands need root.
        // Allowed by /etc/sudoers.d/www-data-services (scoped whitelist).
        $result = match ($cmd) {
            'status' => $this->statusOutput(),
            'wg show' => shell_exec('sudo wg show 2>&1') ?: 'WireGuard not active',
            'wg peers' => shell_exec('sudo wg show wg0 peers 2>&1; sudo wg show wg0 endpoints 2>&1; sudo wg show wg0 latest-handshakes 2>&1; sudo wg show wg0 transfer 2>&1') ?: 'No peers',
            'nginx reload' => shell_exec('sudo nginx -t 2>&1 && sudo nginx -s reload 2>&1') ?: 'Reloaded',
            'nginx test' => shell_exec('sudo nginx -c /data/admin/nginx/nginx.conf -t 2>&1') ?: 'OK',
            'logs access' => shell_exec('tail -n 30 ' . escapeshellarg(USER_LOGS_DIR . '/nginx-access.log') . ' 2>&1') ?: 'No log yet',
            'logs error' => shell_exec('tail -n 30 ' . escapeshellarg(USER_LOGS_DIR . '/nginx-error.log') . ' 2>&1') ?: 'No errors',
            'phpinfo' => shell_exec('php -v 2>&1') . "\n\nExtensions:\n" . shell_exec('php -m 2>&1'),
            default => null,
        };

        ActivityLog::log('console.command', $cmd);

        if ($result === null) {
            echo json_encode(['output' => "Unknown command: $cmd\nType 'help' for available commands."]);
        } else {
            echo json_encode(['output' => $result]);
        }
    }

    private function statusOutput(): string {
        $mgr = new ServiceManager();
        $status = $mgr->getAllStatus();
        $lines = ["=== Service Status ==="];
        foreach ($status as $svc => $running) {
            $lines[] = "  $svc: " . ($running ? 'RUNNING' : 'STOPPED');
        }
        return implode("\n", $lines);
    }

    private function help(): string {
        return <<<HELP
Available commands:
  help              — show this message
  status            — show all service statuses
  wg show           — WireGuard interface status
  wg peers          — peer details (endpoints, handshakes, transfer)
  ping <host>       — ping a host (e.g. ping 10.0.0.1)
  nginx reload      — reload Nginx config (tests first)
  nginx test        — test Nginx config for errors
  logs access       — last 30 lines of access log
  logs error        — last 30 lines of error log
  phpinfo           — PHP version and extensions
  clear             — clear the terminal screen
HELP;
    }
}
