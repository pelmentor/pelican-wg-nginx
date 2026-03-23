<?php

/**
 * Simple file-based rate limiter.
 * Stores attempt counts per key in /data/tmp/ as flat files.
 */
class RateLimit {
    private const DIR = '/data/tmp/ratelimit';

    /**
     * Check whether the caller is within the allowed rate.
     *
     * @param  string $key          Unique key (e.g. "login:<ip>" or "api:<ip>")
     * @param  int    $maxAttempts  Maximum number of requests allowed in the window
     * @param  int    $windowSeconds  Time window in seconds
     * @return bool   true if the request is allowed, false if rate-limited
     */
    public static function check(string $key, int $maxAttempts, int $windowSeconds): bool {
        $dir = self::DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Sanitise key for filename safety
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $file = "{$dir}/{$safeKey}.json";

        $now = time();
        $attempts = [];

        // Read existing attempts
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    // Keep only attempts within the current window
                    $attempts = array_values(array_filter($decoded, fn(int $ts) => ($now - $ts) < $windowSeconds));
                }
            }
        }

        // Check if limit would be exceeded
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        // Record this attempt
        $attempts[] = $now;
        @file_put_contents($file, json_encode($attempts), LOCK_EX);

        return true;
    }

    /**
     * Abort with a 429 response if rate limit is exceeded.
     *
     * @param  string $key
     * @param  int    $maxAttempts
     * @param  int    $windowSeconds
     */
    public static function enforce(string $key, int $maxAttempts, int $windowSeconds): void {
        if (!self::check($key, $maxAttempts, $windowSeconds)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Too many requests. Please try again later.']);
            exit;
        }
    }

    /**
     * Periodically clean up stale rate-limit files (older than 5 minutes).
     * Called opportunistically — not on every request.
     */
    public static function cleanup(): void {
        $dir = self::DIR;
        if (!is_dir($dir)) return;

        // Run cleanup roughly 1 in 100 requests
        if (mt_rand(1, 100) !== 1) return;

        $cutoff = time() - 300;
        foreach (glob("{$dir}/*.json") as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
