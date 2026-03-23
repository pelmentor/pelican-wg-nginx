<?php

class FilesController {
    private FileManager $fm;

    public function __construct() {
        $this->fm = new FileManager(DATA_DIR);
    }

    /**
     * Decode JSON input and validate that all required keys exist.
     * Returns the decoded array, or null (after sending a 400 response) on failure.
     */
    private function jsonInput(array $requiredKeys = []): ?array {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return null;
        }
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $input)) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $key"]);
                return null;
            }
        }
        return $input;
    }

    public function index(): void {
        $page = 'files';
        require __DIR__ . '/../View/layout.php';
    }

    public function listDir(): void {
        header('Content-Type: application/json');
        try {
            $path = $_GET['path'] ?? '/';
            echo json_encode(['entries' => $this->fm->listDirectory($path)]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function read(): void {
        header('Content-Type: application/json');
        try {
            $path = $_GET['path'] ?? '';
            echo json_encode(['content' => $this->fm->readFile($path), 'path' => $path]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function write(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput(['path', 'content']);
            if ($input === null) return;
            $this->fm->writeFile($input['path'], $input['content']);
            ActivityLog::log('file.write', $input['path']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function upload(): void {
        header('Content-Type: application/json');
        try {
            $targetDir = $_POST['path'] ?? '/webroot';
            $this->fm->upload($targetDir, $_FILES['files']);
            ActivityLog::log('file.upload', $targetDir);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function delete(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput(['path']);
            if ($input === null) return;
            $this->fm->delete($input['path']);
            ActivityLog::log('file.delete', $input['path']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function renamePath(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput(['from', 'to']);
            if ($input === null) return;
            $this->fm->rename($input['from'], $input['to']);
            ActivityLog::log('file.rename', $input['from'] . ' -> ' . $input['to']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function mkdirPath(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput(['path']);
            if ($input === null) return;
            $this->fm->mkdir($input['path']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function download(): void {
        try {
            $path = $_GET['path'] ?? '';
            $this->fm->download($path);
        } catch (Throwable $e) {
            http_response_code(400);
            echo $e->getMessage();
        }
    }

    public function copy(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput(['path']);
            if ($input === null) return;
            $this->fm->copy($input['path']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function compress(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput([]);
            if ($input === null) return;
            $files = $input['files'] ?? [$input['path'] ?? ''];
            $name = $input['name'] ?? 'archive';
            $dir = $input['path'] ?? '/';
            $this->fm->compress($dir, $files, $name);
            ActivityLog::log('file.compress', $name . '.tar.gz in ' . $dir);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function decompress(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput(['path']);
            if ($input === null) return;
            $this->fm->decompress($input['path']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function chmodPath(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput(['path', 'mode']);
            if ($input === null) return;
            $this->fm->chmod($input['path'], $input['mode']);
            ActivityLog::log('file.chmod', $input['path'] . ' -> ' . $input['mode']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function search(): void {
        header('Content-Type: application/json');
        try {
            $path = $_GET['path'] ?? '/';
            $query = $_GET['query'] ?? '';
            if (strlen($query) < 1) {
                echo json_encode(['results' => []]);
                return;
            }
            echo json_encode(['results' => $this->fm->search($path, $query)]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function createFile(): void {
        header('Content-Type: application/json');
        try {
            $input = $this->jsonInput(['path']);
            if ($input === null) return;
            $this->fm->createFile($input['path']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
