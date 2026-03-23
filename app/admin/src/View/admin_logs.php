<!-- System Logs — Admin Area -->

<div class="max-w-5xl mx-auto space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-semibold text-white">System Logs</h2>
                <p class="text-xs text-gray-500">Admin access, error, and activity logs</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <!-- Auto-refresh toggle -->
            <label class="flex items-center gap-2 cursor-pointer">
                <span class="text-xs text-gray-500">Auto-refresh</span>
                <div class="relative">
                    <input type="checkbox" id="auto-refresh-toggle" class="sr-only" checked onchange="AdminLogs.toggleAutoRefresh()">
                    <div class="w-8 h-4 bg-gray-700 rounded-full transition-colors peer-checked:bg-green-600"></div>
                    <div id="auto-refresh-dot" class="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full transition-transform translate-x-4"></div>
                </div>
            </label>
            <!-- Refresh button -->
            <button onclick="AdminLogs.refresh()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/>
                </svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Search bar -->
    <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
        </svg>
        <input type="text" id="log-search" placeholder="Search logs..." oninput="AdminLogs.onSearchChange()"
               class="w-full pl-10 pr-4 py-2.5 bg-gray-900 border border-gray-800 rounded-lg text-sm text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-red-500/40 focus:border-red-500/40 transition">
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-gray-900 border border-gray-800 rounded-lg p-1">
        <button id="tab-access" onclick="AdminLogs.switchTab('access')"
                class="flex-1 px-4 py-2 text-xs font-medium rounded-md transition-colors duration-150 bg-red-600 text-white">
            Admin Access
        </button>
        <button id="tab-error" onclick="AdminLogs.switchTab('error')"
                class="flex-1 px-4 py-2 text-xs font-medium rounded-md transition-colors duration-150 text-gray-400 hover:text-white hover:bg-white/5">
            Admin Errors
        </button>
        <button id="tab-activity" onclick="AdminLogs.switchTab('activity')"
                class="flex-1 px-4 py-2 text-xs font-medium rounded-md transition-colors duration-150 text-gray-400 hover:text-white hover:bg-white/5">
            Activity Log
        </button>
    </div>

    <!-- Log content -->
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <!-- Log info bar -->
        <div class="px-5 py-2.5 border-b border-gray-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span id="log-file-name" class="text-xs font-mono text-gray-500">admin-access.log</span>
                <span id="log-line-count" class="text-xs text-gray-600">-- lines</span>
            </div>
            <div id="log-last-updated" class="text-xs text-gray-600">--</div>
        </div>

        <!-- Text log output (access/error) -->
        <div id="log-text-container" class="overflow-auto" style="max-height: 600px;">
            <pre id="log-text-output" class="p-4 text-xs font-mono text-gray-400 leading-relaxed whitespace-pre-wrap break-all">Loading...</pre>
        </div>

        <!-- Activity log output (table) -->
        <div id="log-activity-container" class="hidden overflow-auto" style="max-height: 600px;">
            <table class="w-full">
                <thead class="sticky top-0 bg-gray-900">
                    <tr class="border-b border-gray-800">
                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Time</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-36">Action</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detail</th>
                        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">IP</th>
                    </tr>
                </thead>
                <tbody id="log-activity-table" class="divide-y divide-gray-800/50">
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-600 text-sm">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
