<?php

class ServiceManager {
    public function control(string $service, string $action): array {
        return match ($service) {
            'nginx' => $this->controlNginx($action),
            'php-fpm' => $this->controlPhpFpm($action),
            'wireguard' => $this->controlWg($action),
            default => ['success' => false, 'output' => 'Unknown service'],
        };
    }

    public function getAllStatus(): array {
        return [
            'nginx' => $this->isRunning('nginx'),
            'php-fpm' => $this->isRunning('php-fpm'),
            'wireguard' => file_exists('/sys/class/net/wg0'),
        ];
    }

    // SECURITY: All service commands use sudo because PHP-FPM runs as www-data
    // but these operations need root. Sudoers whitelist in /etc/sudoers.d/www-data-services
    // restricts www-data to only these specific binaries.

    private function controlNginx(string $action): array {
        return match ($action) {
            'reload' => $this->run('sudo nginx -t 2>&1 && sudo nginx -s reload 2>&1'),
            'test' => $this->run('sudo nginx -c /data/admin/nginx/nginx.conf -t 2>&1'),
            'restart' => $this->run('sudo nginx -s quit 2>&1; sleep 1; sudo nginx -c /data/admin/nginx/nginx.conf 2>&1'),
            default => ['success' => false, 'output' => "Unknown action: $action"],
        };
    }

    private function controlPhpFpm(string $action): array {
        return match ($action) {
            'restart' => $this->run("sudo pkill -SIGUSR2 php-fpm 2>&1"),
            default => ['success' => false, 'output' => "Unknown action: $action"],
        };
    }

    private function controlWg(string $action): array {
        return match ($action) {
            'up' => $this->run('sudo wg-quick up wg0 2>&1'),
            'down' => $this->run('sudo wg-quick down wg0 2>&1'),
            'restart' => $this->run('sudo wg-quick down wg0 2>&1; sudo wg-quick up wg0 2>&1'),
            'show' => $this->run('sudo wg show 2>&1'),
            default => ['success' => false, 'output' => "Unknown action: $action"],
        };
    }

    private function run(string $cmd): array {
        exec($cmd, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);
        return ['success' => $exitCode === 0, 'output' => trim($output)];
    }

    private function isRunning(string $name): bool {
        // Use pgrep -f for php-fpm because the actual binary is versioned
        // (e.g. "php-fpm8.1", "php-fpm8.2") and pgrep -x requires exact match.
        if ($name === 'php-fpm') {
            exec("pgrep -f 'php-fpm: master'", $out, $code);
            return $code === 0;
        }
        exec("pgrep -x " . escapeshellarg($name), $out, $code);
        return $code === 0;
    }
}
