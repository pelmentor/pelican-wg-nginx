<!-- Settings — Pelican Panel style -->

<!-- Service Controls -->
<div class="mb-6">
    <h3 class="text-sm font-medium text-gray-400 uppercase tracking-wider mb-3">Service Controls</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <!-- Nginx -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                    </svg>
                </div>
                <span class="text-sm font-medium text-white">Nginx</span>
            </div>
            <div class="flex gap-2">
                <button onclick="Settings.service('nginx','reload')" class="flex-1 px-2.5 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition">Reload</button>
                <button onclick="Settings.service('nginx','test')" class="flex-1 px-2.5 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">Test</button>
                <button onclick="Settings.service('nginx','restart')" class="flex-1 px-2.5 py-1.5 text-xs font-medium text-white bg-yellow-600 hover:bg-yellow-500 rounded-lg transition">Restart</button>
            </div>
        </div>

        <!-- PHP-FPM -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <span class="text-sm font-medium text-white">PHP-FPM</span>
            </div>
            <div class="flex gap-2">
                <button onclick="Settings.service('php-fpm','restart')" class="flex-1 px-2.5 py-1.5 text-xs font-medium text-white bg-yellow-600 hover:bg-yellow-500 rounded-lg transition">Restart</button>
            </div>
        </div>

        <!-- WireGuard -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <span class="text-sm font-medium text-white">WireGuard</span>
            </div>
            <div class="flex gap-2">
                <button onclick="Settings.service('wireguard','up')" class="flex-1 px-2.5 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-500 rounded-lg transition">Up</button>
                <button onclick="Settings.service('wireguard','down')" class="flex-1 px-2.5 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-500 rounded-lg transition">Down</button>
                <button onclick="Settings.service('wireguard','restart')" class="flex-1 px-2.5 py-1.5 text-xs font-medium text-white bg-yellow-600 hover:bg-yellow-500 rounded-lg transition">Restart</button>
            </div>
        </div>
    </div>

    <!-- Service output -->
    <div id="service-output" class="hidden mt-3 bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-2 border-b border-gray-800">
            <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Output</span>
        </div>
        <pre id="service-output-text" class="text-sm font-mono text-gray-300 p-4 whitespace-pre-wrap leading-relaxed"></pre>
    </div>
</div>

<!-- Config Editor -->
<div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <!-- Tabs -->
    <div class="flex border-b border-gray-800">
        <button onclick="Settings.loadConfig('nginx')" class="config-tab relative px-5 py-3 text-sm font-medium text-gray-500 hover:text-gray-300 transition" data-tab="nginx">
            Nginx
            <span class="config-tab-indicator absolute bottom-0 left-0 right-0 h-0.5 bg-blue-500 opacity-0 transition-opacity"></span>
        </button>
        <button onclick="Settings.loadConfig('php')" class="config-tab relative px-5 py-3 text-sm font-medium text-gray-500 hover:text-gray-300 transition" data-tab="php">
            PHP-FPM
            <span class="config-tab-indicator absolute bottom-0 left-0 right-0 h-0.5 bg-blue-500 opacity-0 transition-opacity"></span>
        </button>
        <button onclick="Settings.loadConfig('wireguard')" class="config-tab relative px-5 py-3 text-sm font-medium text-gray-500 hover:text-gray-300 transition" data-tab="wireguard">
            WireGuard
            <span class="config-tab-indicator absolute bottom-0 left-0 right-0 h-0.5 bg-blue-500 opacity-0 transition-opacity"></span>
        </button>
    </div>

    <!-- Editor area -->
    <div class="relative">
        <textarea
            id="config-editor"
            class="w-full bg-gray-950 text-gray-100 text-sm font-mono p-4 resize-none focus:outline-none leading-relaxed"
            style="height: 28rem;"
            spellcheck="false"
            placeholder="Select a configuration tab above..."
        ></textarea>
    </div>

    <!-- Save bar -->
    <div class="flex items-center justify-between px-4 py-3 border-t border-gray-800 bg-gray-900">
        <span id="config-status" class="text-xs text-gray-600"></span>
        <button onclick="Settings.saveConfig()" class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Save Config
        </button>
    </div>
</div>
