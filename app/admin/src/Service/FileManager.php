<?php

class FileManager {
    private string $root;

    public function __construct(string $root = DATA_DIR) {
        $this->root = realpath($root) ?: $root;
    }

    /**
     * Resolve and validate path — prevents directory traversal attacks.
     */
    private function resolve(string $path): string {
        $full = realpath($this->root . '/' . ltrim($path, '/'));
        if (!$full || !str_starts_with($full, $this->root)) {
            throw new RuntimeException('Access denied: path outside sandbox');
        }
        return $full;
    }

    /**
     * Resolve path that may not exist yet (for create operations).
     */
    private function resolveNew(string $path): string {
        $dir = dirname($this->root . '/' . ltrim($path, '/'));
        $realDir = realpath($dir);
        if (!$realDir || !str_starts_with($realDir, $this->root)) {
            throw new RuntimeException('Access denied: path outside sandbox');
        }
        return $realDir . '/' . basename($path);
    }

    public function listDirectory(string $path): array {
        $dir = $path === '/' || $path === '' ? $this->root : $this->resolve($path);
        if (!is_dir($dir)) throw new RuntimeException('Not a directory');

        $entries = [];
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            // Hide .admin_password
            if ($name === '.admin_password' && $dir === $this->root) continue;

            $full = $dir . '/' . $name;
            $entries[] = [
                'name' => $name,
                'type' => is_dir($full) ? 'directory' : 'file',
                'size' => is_file($full) ? filesize($full) : 0,
                'modified' => filemtime($full),
                'permissions' => substr(sprintf('%o', fileperms($full)), -4),
            ];
        }

        // Sort: directories first, then alphabetical
        usort($entries, function ($a, $b) {
            if ($a['type'] !== $b['type']) return $a['type'] === 'directory' ? -1 : 1;
            return strcasecmp($a['name'], $b['name']);
        });

        return $entries;
    }

    public function readFile(string $path): string {
        $full = $this->resolve($path);
        if (!is_file($full)) throw new RuntimeException('Not a file');
        if (filesize($full) > 10 * 1024 * 1024) throw new RuntimeException('File too large to edit (>10MB)');
        return file_get_contents($full);
    }

    public function writeFile(string $path, string $content): void {
        $full = $this->resolve($path);
        file_put_contents($full, $content);
    }

    public function delete(string $path): void {
        $full = $this->resolve($path);
        if (is_dir($full)) {
            $this->deleteDir($full);
        } else {
            unlink($full);
        }
    }

    public function rename(string $from, string $to): void {
        $fullFrom = $this->resolve($from);
        $fullTo = $this->resolveNew($to);
        rename($fullFrom, $fullTo);
    }

    public function mkdir(string $path): void {
        $full = $this->resolveNew($path);
        if (!is_dir($full)) {
            mkdir($full, 0755, true);
        }
    }

    public function upload(string $targetDir, array $files): void {
        $dir = $targetDir === '/' || $targetDir === '' ? $this->root : $this->resolve($targetDir);
        if (!is_dir($dir)) throw new RuntimeException('Target directory does not exist');

        foreach ($files['tmp_name'] as $i => $tmpFile) {
            $name = basename($files['name'][$i]);
            // Sanitize filename
            $name = preg_replace('/[^\w.\-]/', '_', $name);
            move_uploaded_file($tmpFile, $dir . '/' . $name);
        }
    }

    public function download(string $path): void {
        $full = $this->resolve($path);
        if (!is_file($full)) throw new RuntimeException('Not a file');

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($full) . '"');
        header('Content-Length: ' . filesize($full));
        readfile($full);
        exit;
    }

    private function deleteDir(string $dir): void {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
