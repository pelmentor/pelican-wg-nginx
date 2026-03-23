<?php

class StatsService {
    public function getAll(): array {
        return [
            'cpu' => $this->getCpu(),
            'memory' => $this->getMemory(),
            'disk' => $this->getDisk(),
            'network' => $this->getNetwork(),
            'uptime' => $this->getUptime(),
            'services' => [
                'nginx' => $this->isRunning('nginx'),
                'php-fpm' => $this->isRunning('php-fpm'),
                'wireguard' => file_exists('/sys/class/net/wg0'),
            ],
            'wireguard' => $this->getWireguardDetails(),
        ];
    }

    /**
     * Get detailed WireGuard info: address, endpoint, last handshake, transfer.
     * Returns null if WG is not active.
     */
    private function getWireguardDetails(): ?array {
        if (!file_exists('/sys/class/net/wg0')) return null;

        $output = @shell_exec('sudo wg show wg0 2>&1') ?? '';
        if (empty($output) || str_contains($output, 'not found')) return null;

        $details = [
            'address'        => '',
            'endpoint'       => '',
            'last_handshake' => '',
            'transfer_rx'    => 0,
            'transfer_tx'    => 0,
            'peer'           => '',
        ];

        // Address from config file
        $conf = @file_get_contents(USER_CONFIG_DIR . '/wg0.conf');
        if ($conf && preg_match('/Address\s*=\s*(.+)/i', $conf, $m)) {
            $details['address'] = trim($m[1]);
        }

        // Parse wg show output
        if (preg_match('/endpoint:\s*(\S+)/i', $output, $m)) {
            $details['endpoint'] = $m[1];
        }
        if (preg_match('/latest handshake:\s*(.+)/i', $output, $m)) {
            $details['last_handshake'] = trim($m[1]);
        }
        if (preg_match('/transfer:\s*([\d.]+\s*\S+)\s+received,\s*([\d.]+\s*\S+)\s+sent/i', $output, $m)) {
            $details['transfer_rx'] = $m[1];
            $details['transfer_tx'] = $m[2];
        }
        if (preg_match('/peer:\s*(\S+)/i', $output, $m)) {
            $details['peer'] = substr($m[1], 0, 8) . '...';
        }

        return $details;
    }

    private function getCpu(): array {
        $load = sys_getloadavg();
        $cores = (int) trim(shell_exec('nproc') ?? '1');
        return [
            'percent' => round(($load[0] / $cores) * 100, 1),
            'load' => $load[0],
            'cores' => $cores,
        ];
    }

    private function getMemory(): array {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $avail);
        $totalKb = (int)($total[1] ?? 0);
        $availKb = (int)($avail[1] ?? 0);
        $usedKb = $totalKb - $availKb;
        return [
            'used_mb' => round($usedKb / 1024, 1),
            'total_mb' => round($totalKb / 1024, 1),
            'percent' => $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0,
        ];
    }

    private function getDisk(): array {
        $total = disk_total_space(DATA_DIR);
        $free = disk_free_space(DATA_DIR);
        $used = $total - $free;
        return [
            'used_gb' => round($used / 1073741824, 2),
            'total_gb' => round($total / 1073741824, 2),
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
        ];
    }

    private function getNetwork(): array {
        $stats = [];
        $dev = @file_get_contents('/proc/net/dev');
        if (!$dev) return $stats;

        foreach (explode("\n", $dev) as $line) {
            if (preg_match('/^\s*(eth0|wg0):\s*(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $m)) {
                $stats[$m[1]] = [
                    'rx_bytes' => (int)$m[2],
                    'tx_bytes' => (int)$m[3],
                ];
            }
        }
        return $stats;
    }

    private function getUptime(): int {
        return (int)(float)explode(' ', file_get_contents('/proc/uptime'))[0];
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
