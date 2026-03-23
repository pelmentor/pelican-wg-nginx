<?php
/**
 * Front Controller — all admin panel requests route through here.
 * Nginx rewrites everything to /index.php via try_files.
 */

// Bootstrap
require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Router.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Service/StatsService.php';
require __DIR__ . '/../src/Service/LogStreamer.php';
require __DIR__ . '/../src/Service/FileManager.php';
require __DIR__ . '/../src/Service/ServiceManager.php';
require __DIR__ . '/../src/Controller/DashboardController.php';
require __DIR__ . '/../src/Controller/ConsoleController.php';
require __DIR__ . '/../src/Controller/FilesController.php';
require __DIR__ . '/../src/Controller/SettingsController.php';

$router = new Router();

// --- Public routes (no auth) ---
$router->get('/login', fn() => Auth::handleLogin());
$router->post('/login', fn() => Auth::handleLogin());
$router->get('/logout', fn() => Auth::handleLogout());
$router->get('/api/health', fn() => (new DashboardController())->health());

// --- Protected routes (require auth) ---
$uri = strtok($_SERVER['REQUEST_URI'], '?');
if ($uri !== '/login' && $uri !== '/api/health') {
    Auth::requireAuth();
}

// Pages
$router->get('/', [DashboardController::class, 'index']);
$router->get('/console', [ConsoleController::class, 'index']);
$router->get('/files', [FilesController::class, 'index']);
$router->get('/settings', [SettingsController::class, 'index']);

// API — Dashboard
$router->get('/api/stats', [DashboardController::class, 'stats']);

// API — Console
$router->get('/api/console/poll', [ConsoleController::class, 'poll']);
$router->post('/api/console/command', [ConsoleController::class, 'command']);

// API — Files
$router->get('/api/files/list', [FilesController::class, 'listDir']);
$router->get('/api/files/read', [FilesController::class, 'read']);
$router->post('/api/files/write', [FilesController::class, 'write']);
$router->post('/api/files/upload', [FilesController::class, 'upload']);
$router->post('/api/files/delete', [FilesController::class, 'delete']);
$router->post('/api/files/rename', [FilesController::class, 'renamePath']);
$router->post('/api/files/mkdir', [FilesController::class, 'mkdirPath']);
$router->get('/api/files/download', [FilesController::class, 'download']);
$router->post('/api/files/copy', [FilesController::class, 'copy']);
$router->post('/api/files/compress', [FilesController::class, 'compress']);
$router->post('/api/files/decompress', [FilesController::class, 'decompress']);
$router->post('/api/files/chmod', [FilesController::class, 'chmodPath']);
$router->get('/api/files/search', [FilesController::class, 'search']);
$router->post('/api/files/create', [FilesController::class, 'createFile']);

// API — Settings
$router->get('/api/settings/config', [SettingsController::class, 'getConfig']);
$router->post('/api/settings/config', [SettingsController::class, 'saveConfig']);
$router->post('/api/settings/validate', [SettingsController::class, 'validateConfig']);
$router->post('/api/settings/service', [SettingsController::class, 'serviceAction']);
$router->get('/api/settings/status', [SettingsController::class, 'serviceStatus']);

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
