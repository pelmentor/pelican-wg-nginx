<?php

class LogStreamer {
    /**
     * Stream log file changes via Server-Sent Events (SSE).
     * This is a long-lived connection — the function never returns.
     * Nginx must have fastcgi_buffering off for this endpoint.
     */
    public function stream(array $logFiles): void {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        while (ob_get_level()) ob_end_clean();
        set_time_limit(0);

        // Open files and seek to end
        $handles = [];
        foreach ($logFiles as $file) {
            if (file_exists($file)) {
                $h = fopen($file, 'r');
                fseek($h, 0, SEEK_END);
                $handles[$file] = $h;
            }
        }

        echo "event: connected\ndata: " . json_encode(['files' => array_keys($handles)]) . "\n\n";
        flush();

        $lastPing = time();

        while (!connection_aborted()) {
            $hasData = false;

            foreach ($handles as $file => $h) {
                $line = fgets($h);
                if ($line !== false) {
                    $hasData = true;
                    $source = basename($file, '.log');
                    echo "data: " . json_encode([
                        'source' => $source,
                        'line' => rtrim($line),
                        'time' => date('H:i:s'),
                    ]) . "\n\n";
                }
            }

            if ($hasData) {
                flush();
            }

            // Send keepalive ping every 15 seconds
            if (time() - $lastPing >= 15) {
                echo ": ping\n\n";
                flush();
                $lastPing = time();
            }

            // Don't spin CPU — sleep 200ms between checks
            usleep(200000);
        }

        foreach ($handles as $h) fclose($h);
    }
}
