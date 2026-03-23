<?php

class AdminLogsController {
    /**
     * GET /admin/logs — render the system logs page (admin only).
     */
    public function index(): void {
        Permission::requirePerm(Auth::getCurrentRole(), 'admin.*');
        $page = 'admin_logs';
        require __DIR__ . '/../View/layout.php';
    }

    /**
     * GET /api/admin/logs?type=access|error|activity&lines=100
     * Returns log file content as JSON.
     */
    public function getLogs(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'admin.*');

        $type = $_GET['type'] ?? 'access';
        $lines = (int) ($_GET['lines'] ?? 100);
        $lines = max(1, min($lines, 500));
        $search = $_GET['search'] ?? '';

        $logFiles = [
            'access'   => ADMIN_LOGS_DIR . '/admin-access.log',
            'error'    => ADMIN_LOGS_DIR . '/admin-error.log',
            'activity' => ADMIN_LOGS_DIR . '/activity.json',
        ];

        if (!isset($logFiles[$type])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid log type']);
            return;
        }

        $file = $logFiles[$type];

        if (!file_exists($file)) {
            echo json_encode([
                'type'    => $type,
                'lines'   => [],
                'total'   => 0,
                'file'    => basename($file),
            ]);
            return;
        }

        if ($type === 'activity') {
            // Parse activity.json specially
            $json = @file_get_contents($file);
            $entries = [];
            if ($json !== false && $json !== '') {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    $entries = $data;
                }
            }

            // Reverse to show newest first
            $entries = array_reverse($entries);

            // Apply search filter
            if ($search !== '') {
                $searchLower = strtolower($search);
                $entries = array_filter($entries, function ($entry) use ($searchLower) {
                    return str_contains(strtolower($entry['action'] ?? ''), $searchLower)
                        || str_contains(strtolower($entry['detail'] ?? ''), $searchLower)
                        || str_contains(strtolower($entry['ip'] ?? ''), $searchLower);
                });
                $entries = array_values($entries);
            }

            $total = count($entries);
            $entries = array_slice($entries, 0, $lines);

            echo json_encode([
                'type'    => $type,
                'entries' => $entries,
                'total'   => $total,
                'file'    => basename($file),
            ]);
            return;
        }

        // For text-based log files, read last N lines
        $allLines = $this->tailFile($file, $lines + 100); // read extra for search filtering

        // Apply search filter
        if ($search !== '') {
            $searchLower = strtolower($search);
            $allLines = array_filter($allLines, function ($line) use ($searchLower) {
                return str_contains(strtolower($line), $searchLower);
            });
            $allLines = array_values($allLines);
        }

        $total = count($allLines);
        $resultLines = array_slice($allLines, -$lines);

        echo json_encode([
            'type'  => $type,
            'lines' => $resultLines,
            'total' => $total,
            'file'  => basename($file),
        ]);
    }

    /**
     * Read the last N lines of a file efficiently.
     */
    private function tailFile(string $file, int $lines): array {
        if (!is_readable($file)) {
            return [];
        }

        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return [];
        }

        $allLines = explode("\n", rtrim($content, "\n"));
        return array_slice($allLines, -$lines);
    }
}
