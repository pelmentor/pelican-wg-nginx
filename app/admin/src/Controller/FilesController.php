<?php

class FilesController {
    private FileManager $fm;

    public function __construct() {
        $this->fm = new FileManager(DATA_DIR);
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
            $input = json_decode(file_get_contents('php://input'), true);
            $this->fm->writeFile($input['path'], $input['content']);
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
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function delete(): void {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $this->fm->delete($input['path']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function renamePath(): void {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $this->fm->rename($input['from'], $input['to']);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function mkdirPath(): void {
        header('Content-Type: application/json');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
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
}
