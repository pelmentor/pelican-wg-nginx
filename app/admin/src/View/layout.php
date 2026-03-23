<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WG-Nginx Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        panel: {
                            bg: '#111827',
                            sidebar: '#1f2937',
                            card: '#1f2937',
                            border: '#374151',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if ($page === 'console'): ?>
    <link rel="stylesheet" href="/assets/vendor/xterm.css">
    <?php endif; ?>
</head>
<body class="bg-panel-bg text-gray-100 min-h-screen flex">

    <!-- Sidebar -->
    <aside class="w-64 bg-panel-sidebar border-r border-panel-border flex flex-col min-h-screen fixed">
        <!-- Logo -->
        <div class="p-4 border-b border-panel-border">
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                </svg>
                WG-Nginx
            </h1>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 p-3 space-y-1">
            <a href="/" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition <?= $page === 'dashboard' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                Dashboard
            </a>
            <a href="/console" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition <?= $page === 'console' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Console
            </a>
            <a href="/files" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition <?= $page === 'files' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                Files
            </a>
            <a href="/settings" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition <?= $page === 'settings' ? 'bg-blue-600 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
        </nav>

        <!-- Footer -->
        <div class="p-4 border-t border-panel-border">
            <a href="/logout" class="flex items-center gap-2 text-sm text-gray-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 ml-64">
        <div class="p-6">
            <?php require __DIR__ . "/{$page}.php"; ?>
        </div>
    </main>

    <script src="/assets/js/app.js"></script>
    <?php if ($page === 'console'): ?>
    <script src="/assets/vendor/xterm.js"></script>
    <script src="/assets/vendor/xterm-addon-fit.js"></script>
    <script src="/assets/js/console.js"></script>
    <?php elseif ($page === 'dashboard'): ?>
    <script src="/assets/js/dashboard.js"></script>
    <?php elseif ($page === 'files'): ?>
    <script src="/assets/js/files.js"></script>
    <?php elseif ($page === 'settings'): ?>
    <script src="/assets/js/settings.js"></script>
    <?php endif; ?>
</body>
</html>
