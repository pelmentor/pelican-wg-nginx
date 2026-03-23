<?php

class FileManager {
    private string $root;

    public function __construct(string $root = USER_DIR) {
        $this->root = realpath($root) ?: $root;
    }

    /**
     * Resolve and validate path — prevents directory traversal attacks.
     */
    // SECURITY: Path traversal protection — realpath() resolves symlinks and ".." segments,
    // then str_starts_with() confirms the result is still inside $this->root.
    private function resolve(string $path): string {
        $full = realpath($this->root . '/' . ltrim($path, '/'));
        if (!$full) {
            throw new RuntimeException('Path not found: file or directory does not exist');
        }
        if (!str_starts_with($full, $this->root)) {
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
            // Hide sensitive entries from file browser
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
        // TRAP: LOCK_EX is essential — without it, concurrent writes corrupt the file.
        file_put_contents($full, $content, LOCK_EX);
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

    /**
     * Upload files to the target directory.
     *
     * SECURITY NOTE: Uploading .php, .phtml, or .phar files to the webroot
     * allows arbitrary PHP code execution via the user-facing Nginx vhost.
     * This is intentionally permitted because the admin explicitly chose the
     * upload destination, but operators should be aware of the risk.
     */
    // WARNING: Uploading .php files to webroot = arbitrary code execution via the shared FPM pool.
    // This is intentional (admin chose the destination), but be aware of the risk.
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

    public function copy(string $from): void {
        $full = $this->resolve($from);
        $dir = dirname($full);
        $basename = pathinfo($full, PATHINFO_FILENAME);
        $ext = pathinfo($full, PATHINFO_EXTENSION);
        $suffix = $ext !== '' ? '.' . $ext : '';
        $dest = $dir . '/' . $basename . ' - Copy' . $suffix;

        // Ensure unique name
        $i = 2;
        while (file_exists($dest)) {
            $dest = $dir . '/' . $basename . ' - Copy (' . $i . ')' . $suffix;
            $i++;
        }

        if (is_dir($full)) {
            $this->copyDir($full, $dest);
        } else {
            copy($full, $dest);
        }
    }

    public function compress(string $dir, array $files, string $archiveName): void {
        $baseDir = $dir === '/' || $dir === '' ? $this->root : $this->resolve($dir);
        if (!is_dir($baseDir)) throw new RuntimeException('Not a directory');

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension not available');
        }

        // Sanitize archive name
        $archiveName = preg_replace('/[^\w.\-]/', '_', $archiveName);
        if (!str_ends_with($archiveName, '.zip')) {
            $archiveName .= '.zip';
        }
        $zipPath = $baseDir . '/' . $archiveName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create zip archive');
        }

        foreach ($files as $file) {
            $filePath = $baseDir . '/' . basename($file);
            if (!file_exists($filePath) || !str_starts_with(realpath($filePath), $this->root)) {
                continue;
            }
            if (is_dir($filePath)) {
                $this->addDirToZip($zip, $filePath, basename($file));
            } else {
                $zip->addFile($filePath, basename($file));
            }
        }

        $zip->close();
    }

    // SECURITY: Zip Slip protection below — every archive entry is checked for ".." traversal
    // before extraction. Without this, a malicious zip could write files outside the target dir.
    public function decompress(string $path): void {
        $full = $this->resolve($path);
        if (!is_file($full)) throw new RuntimeException('Not a file');
        if (!str_ends_with(strtolower($full), '.zip')) {
            throw new RuntimeException('Only .zip files are supported');
        }

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension not available');
        }

        $zip = new \ZipArchive();
        if ($zip->open($full) !== true) {
            throw new RuntimeException('Cannot open zip archive');
        }

        $extractDir = dirname($full);
        $realExtractDir = realpath($extractDir);

        // Zip Slip protection: validate every entry stays inside the extraction directory
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            // Reject any path containing directory traversal sequences
            if (str_contains($entryName, '..')) {
                $zip->close();
                throw new RuntimeException('Zip archive contains path traversal entry: ' . $entryName);
            }
            // If the parent directory already exists, verify it resolves inside the target
            $canonicalDir = realpath(dirname($realExtractDir . '/' . $entryName));
            if ($canonicalDir !== false && !str_starts_with($canonicalDir, $realExtractDir)) {
                $zip->close();
                throw new RuntimeException('Zip archive contains entry that escapes target directory: ' . $entryName);
            }
        }

        $zip->extractTo($extractDir);
        $zip->close();
    }

    public function chmod(string $path, string $mode): void {
        $full = $this->resolve($path);
        $octal = octdec($mode);
        if ($octal < 0 || $octal > 07777) {
            throw new RuntimeException('Invalid permissions mode');
        }
        if (!chmod($full, $octal)) {
            throw new RuntimeException('Failed to change permissions');
        }
    }

    public function search(string $dir, string $query): array {
        $baseDir = $dir === '/' || $dir === '' ? $this->root : $this->resolve($dir);
        if (!is_dir($baseDir)) throw new RuntimeException('Not a directory');

        $results = [];
        $this->searchRecursive($baseDir, strtolower($query), $baseDir, $results);
        return $results;
    }

    public function createFile(string $path): void {
        $full = $this->resolveNew($path);
        if (file_exists($full)) {
            throw new RuntimeException('File already exists');
        }
        file_put_contents($full, '');
    }

    private function deleteDir(string $dir): void {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function copyDir(string $src, string $dst): void {
        mkdir($dst, 0755, true);
        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') continue;
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;
            is_dir($s) ? $this->copyDir($s, $d) : copy($s, $d);
        }
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $prefix): void {
        $zip->addEmptyDir($prefix);
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $dir . '/' . $item;
            $zipPath = $prefix . '/' . $item;
            if (is_dir($fullPath)) {
                $this->addDirToZip($zip, $fullPath, $zipPath);
            } else {
                $zip->addFile($fullPath, $zipPath);
            }
        }
    }

    private function searchRecursive(string $dir, string $query, string $baseDir, array &$results, int $limit = 100): void {
        if (count($results) >= $limit) return;

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            if (count($results) >= $limit) return;

            $fullPath = $dir . '/' . $item;
            $relativePath = '/' . ltrim(str_replace($this->root, '', $fullPath), '/');

            if (str_contains(strtolower($item), $query)) {
                $results[] = [
                    'name' => $item,
                    'path' => $relativePath,
                    'type' => is_dir($fullPath) ? 'directory' : 'file',
                    'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                    'modified' => filemtime($fullPath),
                ];
            }

            if (is_dir($fullPath)) {
                $this->searchRecursive($fullPath, $query, $baseDir, $results, $limit);
            }
        }
    }
}
