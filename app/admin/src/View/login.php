<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — WG-Nginx</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-sm">
        <div class="bg-gray-800 rounded-xl border border-gray-700 p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-white">WG-Nginx</h1>
                <p class="text-gray-400 text-sm mt-1">Admin Panel</p>
            </div>

            <?php if (!empty($error)): ?>
            <div class="bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded-lg mb-4 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="/login">
                <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                <input
                    type="password"
                    name="password"
                    autofocus
                    required
                    class="w-full px-4 py-2.5 bg-gray-900 border border-gray-600 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Enter admin password"
                >
                <button
                    type="submit"
                    class="w-full mt-4 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition"
                >
                    Login
                </button>
            </form>

            <p class="text-xs text-gray-500 text-center mt-6">
                Password is shown in container logs at startup
            </p>
        </div>
    </div>
</body>
</html>
