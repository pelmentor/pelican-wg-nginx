<?php

class DashboardController {
    public function index(): void {
        Permission::requirePerm(Auth::getCurrentRole(), 'dashboard.view');
        $page = 'dashboard';
        require __DIR__ . '/../View/layout.php';
    }

    public function stats(): void {
        header('Content-Type: application/json');
        Permission::requirePerm(Auth::getCurrentRole(), 'dashboard.view');
        $stats = new StatsService();
        echo json_encode($stats->getAll());
    }

    public function health(): void {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'time' => time()]);
    }
}
