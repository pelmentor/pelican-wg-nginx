<?php

class SettingsController {
    private const CONFIG_MAP = [
        'wireguard' => '/data/wg/wg0.conf',
        'nginx' => '/data/nginx/user.conf',
        'php' => '/data/php/php-fpm.conf',
    ];

    public function index(): void {
        $page = 'settings';
        require __DIR__ . '/../View/layout.php';
    }

    public function getConfig(): void {
        header('Content-Type: application/json');
        $file = self::CONFIG_MAP[$_GET['file'] ?? ''] ?? null;
        if (!$file || !file_exists($file)) {
            http_response_code(404);
            echo json_encode(['error' => 'Config not found']);
            return;
        }
        echo json_encode(['content' => file_get_contents($file), 'file' => $_GET['file']]);
    }

    public function saveConfig(): void {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $file = self::CONFIG_MAP[$input['file'] ?? ''] ?? null;
        if (!$file) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid config file']);
            return;
        }
        file_put_contents($file, $input['content']);
        echo json_encode(['success' => true]);
    }

    public function serviceAction(): void {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $mgr = new ServiceManager();
        $result = $mgr->control($input['service'] ?? '', $input['action'] ?? '');
        echo json_encode($result);
    }

    public function serviceStatus(): void {
        header('Content-Type: application/json');
        $mgr = new ServiceManager();
        echo json_encode($mgr->getAllStatus());
    }
}
