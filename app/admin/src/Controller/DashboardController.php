<?php

class DashboardController {
    public function index(): void {
        $page = 'dashboard';
        require __DIR__ . '/../View/layout.php';
    }

    public function stats(): void {
        header('Content-Type: application/json');
        $stats = new StatsService();
        echo json_encode($stats->getAll());
    }

    public function health(): void {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'time' => time()]);
    }
}
