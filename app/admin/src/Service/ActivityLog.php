<?php

class ActivityLog {
    private const LOG_FILE = '/data/logs/activity.json';
    private const MAX_ENTRIES = 500;

    /**
     * Append an activity entry to the JSON log file.
     *
     * Uses flock(LOCK_EX) around the entire read-modify-write cycle
     * to prevent race conditions when concurrent requests log simultaneously.
     */
    public static function log(string $action, string $detail = ''): void {
        $dir = dirname(self::LOG_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $fh = fopen(self::LOG_FILE, 'c+');
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
        if (!file_exists(self::LOG_FILE)) {
            return [];
        }

        $json = @file_get_contents(self::LOG_FILE);
        if ($json === false || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
