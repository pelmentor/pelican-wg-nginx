<?php

class LogStreamer {
    /**
     * Return recent log lines since a given position.
     *
     * Why polling instead of SSE:
     * SSE (Server-Sent Events) holds a PHP-FPM worker for the entire
     * connection lifetime. With ondemand pool, this permanently consumes
     * a worker, starving other requests (file manager, dashboard, etc.).
     * Polling with 2s interval uses a worker for ~50ms per request.
     *
     * The client sends the last known file positions (byte offsets).
     * We read new data from those positions and return it with updated offsets.
     * This is efficient — no re-reading old data.
     */
    public function poll(array $logFiles, array $positions): array {
        $lines = [];
        $newPositions = $positions;

        foreach ($logFiles as $key => $file) {
            if (!file_exists($file)) continue;

            $size = filesize($file);
            $pos = $positions[$key] ?? $size; // Default: start from end (no old data)

            // File was truncated (log rotation) — reset to beginning
            if ($pos > $size) $pos = 0;

            if ($pos < $size) {
                $h = fopen($file, 'r');
                if ($h === false) {
                    $newPositions[$key] = $size;
                    continue;
                }
                fseek($h, $pos);
                $chunk = fread($h, min($size - $pos, 65536)); // Max 64KB per poll
                fclose($h);

                if ($chunk) {
                    $source = basename($file, '.log');
                    foreach (explode("\n", rtrim($chunk)) as $line) {
                        if ($line === '') continue;
                        $lines[] = [
                            'source' => $source,
                            'line' => $line,
                            'time' => date('H:i:s'),
                        ];
                    }
                }
                $newPositions[$key] = $size;
            } else {
                $newPositions[$key] = $size;
            }
        }

        return ['lines' => $lines, 'positions' => $newPositions];
    }
}
