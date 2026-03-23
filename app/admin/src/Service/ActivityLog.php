<?php

class ActivityLog {
    private const MAX_ENTRIES = 500;

    /**
     * Get the log file path.
     */
    private static function logFile(): string {
        return ADMIN_LOGS_DIR . '/activity.json';
    }

    /**
     * Append an activity entry to the JSON log file.
     *
     * Uses flock(LOCK_EX) around the entire read-modify-write cycle
     * to prevent race conditions when concurrent requests log simultaneously.
     */
    // TRAP: flock(LOCK_EX) wraps the entire read-modify-write cycle below. Without it,
    // concurrent requests would read the same state and one write would silently overwrite the other.
    public static function log(string $action, string $detail = ''): void {
        $file = self::logFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $fh = fopen($file, 'c+');
        if ($fh === false) {
            return;
        }

        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            return;
        }

        // Read existing entries under the exclusive lock
        $json = stream_get_contents($fh);
        $entries = [];
        if ($json !== false && $json !== '') {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $entries = $data;
            }
        }

        $entries[] = [
            'time'   => time(),
            'action' => $action,
            'detail' => $detail,
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ];

        // Trim to max entries (keep newest)
        if (count($entries) > self::MAX_ENTRIES) {
            $entries = array_slice($entries, -self::MAX_ENTRIES);
        }

        // Truncate and write under the same lock
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fflush($fh);

        flock($fh, LOCK_UN);
        fclose($fh);
    }

    /**
     * Return the most recent N entries (newest first).
     */
    public static function getRecent(int $limit = 50): array {
        $entries = self::readAll();
        $entries = array_reverse($entries);
        return array_slice($entries, 0, $limit);
    }

    /**
     * Read all entries from the log file.
     */
    private static function readAll(): array {
        $file = self::logFile();
        if (!file_exists($file)) {
            return [];
        }

        $json = @file_get_contents($file);
        if ($json === false || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
