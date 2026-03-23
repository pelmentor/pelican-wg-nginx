<?php

class ActivityController {
    /**
     * GET /activity — renders the activity log page.
     */
    public function index(): void {
        $page = 'activity';
        require __DIR__ . '/../View/layout.php';
    }

    /**
     * GET /api/activity?limit=50
     * Returns recent activity log entries as JSON.
     */
    public function recent(): void {
        header('Content-Type: application/json');
        $limit = (int) ($_GET['limit'] ?? 50);
        $limit = max(1, min($limit, 200));
        echo json_encode(['entries' => ActivityLog::getRecent($limit)]);
    }
}
