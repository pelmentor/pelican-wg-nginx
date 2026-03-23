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

    private function controlNginx(string $action): array {
        return match ($action) {
            'reload' => $this->run('nginx -t 2>&1 && nginx -s reload 2>&1'),
            'test' => $this->run('nginx -c /data/admin/nginx/nginx.conf -t 2>&1'),
            'restart' => $this->run('nginx -s quit 2>&1; sleep 1; nginx -c /data/admin/nginx/nginx.conf 2>&1'),
            default => ['success' => false, 'output' => "Unknown action: $action"],
        };
    }

    private function controlPhpFpm(string $action): array {
        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        return match ($action) {
            'restart' => $this->run("pkill -SIGUSR2 php-fpm 2>&1"),
            default => ['success' => false, 'output' => "Unknown action: $action"],
        };
    }

    private function controlWg(string $action): array {
        return match ($action) {
            'up' => $this->run('wg-quick up wg0 2>&1'),
            'down' => $this->run('wg-quick down wg0 2>&1'),
            'restart' => $this->run('wg-quick down wg0 2>&1; wg-quick up wg0 2>&1'),
            'show' => $this->run('wg show 2>&1'),
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
