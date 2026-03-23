<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — WG-Nginx</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex items-center justify-center">

    <!-- Background pattern -->
    <div class="fixed inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-gray-900 via-gray-950 to-gray-950"></div>

    <div class="relative w-full max-w-sm mx-4">
        <!-- Branding -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600/10 border border-blue-500/20 mb-4">
                <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">WG-Nginx</h1>
            <p class="text-sm text-gray-500 mt-1">Admin Panel</p>
        </div>

        <!-- Login card -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
            <?php if (!empty($error)): ?>
            <div class="flex items-center gap-2 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-4 text-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="/login">
                <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
                <label class="block text-sm font-medium text-gray-400 mb-2">Password</label>
                <input
                    type="password"
                    name="password"
                    autofocus
                    required
                    class="w-full px-4 py-2.5 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition"
                    placeholder="Enter admin password"
                >
                <button
                    type="submit"
                    class="w-full mt-4 px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition"
                >
                    Sign in
                </button>
            </form>
        </div>

        <!-- Hint -->
        <p class="text-xs text-gray-600 text-center mt-6">
            Password is shown in container logs at startup
        </p>
    </div>
</body>
</html>
