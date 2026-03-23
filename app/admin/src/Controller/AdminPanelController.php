<?php

class AdminPanelController {
    /**
     * GET /admin/panel — render the panel settings page (admin only).
     */
    public function index(): void {
        Permission::requirePerm(Auth::getCurrentRole(), 'admin.*');
        $page = 'admin_panel';
        require __DIR__ . '/../View/layout.php';
    }

    /**
     * GET /api/admin/panel/info — return system information as JSON.
     */
    public function info(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'admin.*');

        $info = [];

        // Container info
        $info['container'] = [
            'hostname'  => gethostname() ?: 'unknown',
            'uptime'    => $this->getUptime(),
            'os'        => php_uname('s') . ' ' . php_uname('r'),
            'arch'      => php_uname('m'),
        ];

        // PHP info
        $info['php'] = [
            'version'     => PHP_VERSION,
            'sapi'        => PHP_SAPI,
            'extensions'  => get_loaded_extensions(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'opcache'     => function_exists('opcache_get_status') ? (opcache_get_status(false) ?: null) : null,
        ];

        // Nginx info
        $nginxVersion = 'unknown';
        $nginxOutput = @shell_exec('nginx -v 2>&1');
        if ($nginxOutput && preg_match('/nginx\/([\d.]+)/', $nginxOutput, $m)) {
            $nginxVersion = $m[1];
        }
        $nginxWorkers = 'unknown';
        $nginxConf = @file_get_contents('/etc/nginx/nginx.conf');
        if ($nginxConf && preg_match('/worker_processes\s+(\S+);/', $nginxConf, $m)) {
            $nginxWorkers = $m[1];
        }
        $info['nginx'] = [
            'version' => $nginxVersion,
            'workers' => $nginxWorkers,
            'running' => $this->isProcessRunning('nginx'),
        ];

        // WireGuard info
        $wgStatus = 'inactive';
        $wgInterface = 'wg0';
        $wgPeers = 0;
        $wgOutput = @shell_exec('wg show all 2>&1');
        if ($wgOutput && !str_contains($wgOutput, 'Unable') && !str_contains($wgOutput, 'not found')) {
            $wgStatus = 'active';
            if (preg_match('/interface:\s*(\S+)/', $wgOutput, $m)) {
                $wgInterface = $m[1];
            }
            $wgPeers = substr_count($wgOutput, 'peer:');
        }
        $info['wireguard'] = [
            'status'    => $wgStatus,
            'interface' => $wgInterface,
            'peers'     => $wgPeers,
        ];

        // Environment variables (sanitized)
        $env = [];
        $sensitiveKeys = ['PASSWORD', 'SECRET', 'KEY', 'TOKEN', 'PASS', 'CREDENTIAL'];
        foreach ($_ENV as $key => $value) {
            $isSensitive = false;
            foreach ($sensitiveKeys as $sk) {
                if (stripos($key, $sk) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            $env[$key] = $isSensitive ? '********' : $value;
        }
        // Also get from getenv for systems that don't populate $_ENV
        foreach (getenv() as $key => $value) {
            if (isset($env[$key])) continue;
            $isSensitive = false;
            foreach ($sensitiveKeys as $sk) {
                if (stripos($key, $sk) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            $env[$key] = $isSensitive ? '********' : $value;
        }
        ksort($env);
        $info['environment'] = $env;

        // Disk usage
        $info['disk'] = [
            'total' => disk_total_space('/'),
            'free'  => disk_free_space('/'),
            'used'  => disk_total_space('/') - disk_free_space('/'),
        ];

        echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get system uptime in seconds.
     */
    private function getUptime(): int {
        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime !== false) {
            return (int) floatval(explode(' ', trim($uptime))[0]);
        }
        return 0;
    }

    /**
     * Check if a process is running.
     */
    private function isProcessRunning(string $name): bool {
        $output = @shell_exec("pgrep -x {$name} 2>/dev/null");
        return !empty(trim($output ?? ''));
    }
}
