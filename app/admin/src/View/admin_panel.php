<!-- Panel Settings — Admin Area -->

<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-2">
        <div class="w-8 h-8 rounded-lg bg-red-500/10 flex items-center justify-center">
            <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75"/>
            </svg>
        </div>
        <div>
            <h2 class="text-base font-semibold text-white">Panel Settings</h2>
            <p class="text-xs text-gray-500">System information and configuration overview</p>
        </div>
    </div>

    <!-- Panel Customization -->
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-800 flex items-center gap-2.5">
            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42"/>
            </svg>
            <h3 class="text-sm font-semibold text-white">Panel Customization</h3>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Server Name</label>
                    <input type="text" id="setting-server-name"
                        class="w-full bg-gray-950 border border-gray-800 rounded-lg px-3 py-2 text-sm text-gray-100 focus:border-amber-500 focus:ring-1 focus:ring-amber-500/30 focus:outline-none transition"
                        placeholder="WG-Nginx" maxlength="50">
                    <p class="text-xs text-gray-600 mt-1">Displayed in sidebar and header bar</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Server Address</label>
                    <input type="text" id="setting-server-address"
                        class="w-full bg-gray-950 border border-gray-800 rounded-lg px-3 py-2 text-sm text-gray-100 font-mono focus:border-amber-500 focus:ring-1 focus:ring-amber-500/30 focus:outline-none transition"
                        placeholder="Auto-detected from HTTP host" maxlength="100">
                    <p class="text-xs text-gray-600 mt-1">Leave empty to auto-detect from browser</p>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="AdminPanel.saveSettings()"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-medium text-white bg-amber-600 hover:bg-amber-500 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Save
                </button>
            </div>
        </div>
    </div>

    <!-- Loading state -->
    <div id="panel-loading" class="flex items-center justify-center py-12">
        <div class="flex items-center gap-3 text-gray-500">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm">Loading system information...</span>
        </div>
    </div>

    <!-- Content (hidden until loaded) -->
    <div id="panel-content" class="hidden space-y-6">

        <!-- Container Info -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-800 flex items-center gap-2.5">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7"/>
                </svg>
                <h3 class="text-sm font-semibold text-white">Container</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Hostname</div>
                        <div id="info-hostname" class="text-sm font-medium text-white font-mono">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Uptime</div>
                        <div id="info-uptime" class="text-sm font-medium text-white">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">OS</div>
                        <div id="info-os" class="text-sm font-medium text-white">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Architecture</div>
                        <div id="info-arch" class="text-sm font-medium text-white">--</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Disk Usage -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-800 flex items-center gap-2.5">
                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                </svg>
                <h3 class="text-sm font-semibold text-white">Disk Usage</h3>
            </div>
            <div class="p-5">
                <div class="flex items-center gap-4 mb-3">
                    <div class="flex-1">
                        <div class="h-2.5 bg-gray-800 rounded-full overflow-hidden">
                            <div id="disk-bar" class="h-full bg-green-500 rounded-full transition-all duration-500" style="width: 0%"></div>
                        </div>
                    </div>
                    <span id="disk-percent" class="text-sm font-medium text-white shrink-0">--%</span>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-xs text-gray-500 mb-0.5">Used</div>
                        <div id="disk-used" class="text-sm font-medium text-white">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-0.5">Free</div>
                        <div id="disk-free" class="text-sm font-medium text-white">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-0.5">Total</div>
                        <div id="disk-total" class="text-sm font-medium text-white">--</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PHP Info -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-800 flex items-center gap-2.5">
                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5"/>
                </svg>
                <h3 class="text-sm font-semibold text-white">PHP</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Version</div>
                        <div id="info-php-version" class="text-sm font-medium text-white font-mono">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">SAPI</div>
                        <div id="info-php-sapi" class="text-sm font-medium text-white font-mono">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Memory Limit</div>
                        <div id="info-php-memory" class="text-sm font-medium text-white">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Upload Max</div>
                        <div id="info-php-upload" class="text-sm font-medium text-white">--</div>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 mb-2">Loaded Extensions</div>
                    <div id="info-php-extensions" class="flex flex-wrap gap-1.5">
                        <span class="text-xs text-gray-600">--</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nginx Info -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-800 flex items-center gap-2.5">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
                </svg>
                <h3 class="text-sm font-semibold text-white">Nginx</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Version</div>
                        <div id="info-nginx-version" class="text-sm font-medium text-white font-mono">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Worker Processes</div>
                        <div id="info-nginx-workers" class="text-sm font-medium text-white">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Status</div>
                        <div id="info-nginx-status" class="text-sm font-medium">--</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- WireGuard Info -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-800 flex items-center gap-2.5">
                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                </svg>
                <h3 class="text-sm font-semibold text-white">WireGuard</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Status</div>
                        <div id="info-wg-status" class="text-sm font-medium">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Interface</div>
                        <div id="info-wg-interface" class="text-sm font-medium text-white font-mono">--</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 mb-1">Connected Peers</div>
                        <div id="info-wg-peers" class="text-sm font-medium text-white">--</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Environment Variables -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <svg class="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.745 3A23.933 23.933 0 003 12c0 3.183.62 6.22 1.745 9M19.5 3c.967 2.78 1.5 5.817 1.5 9s-.533 6.22-1.5 9M8.25 8.885l1.444-.89a.75.75 0 011.105.402l2.402 7.206a.75.75 0 001.105.401l1.444-.889"/>
                    </svg>
                    <h3 class="text-sm font-semibold text-white">Environment Variables</h3>
                </div>
                <button id="toggle-env" onclick="AdminPanel.toggleEnv()" class="text-xs text-gray-500 hover:text-gray-300 transition">
                    Show All
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-800">
                            <th class="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">Key</th>
                            <th class="px-5 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                        </tr>
                    </thead>
                    <tbody id="env-table" class="divide-y divide-gray-800/50">
                        <tr><td colspan="2" class="px-5 py-8 text-center text-gray-600 text-sm">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
