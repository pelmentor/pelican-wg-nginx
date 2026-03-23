<h2 class="text-2xl font-bold mb-6">Settings</h2>

<!-- Service Controls -->
<div class="bg-panel-card border border-panel-border rounded-xl p-5 mb-6">
    <h3 class="text-lg font-medium text-white mb-4">Service Controls</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="flex items-center justify-between bg-gray-800 rounded-lg px-4 py-3">
            <span class="text-white">Nginx</span>
            <div class="flex gap-2">
                <button onclick="Settings.service('nginx','reload')" class="px-2 py-1 text-xs bg-blue-600 hover:bg-blue-700 rounded transition text-white">Reload</button>
                <button onclick="Settings.service('nginx','test')" class="px-2 py-1 text-xs bg-gray-600 hover:bg-gray-500 rounded transition text-white">Test</button>
                <button onclick="Settings.service('nginx','restart')" class="px-2 py-1 text-xs bg-yellow-600 hover:bg-yellow-700 rounded transition text-white">Restart</button>
            </div>
        </div>
        <div class="flex items-center justify-between bg-gray-800 rounded-lg px-4 py-3">
            <span class="text-white">PHP-FPM</span>
            <div class="flex gap-2">
                <button onclick="Settings.service('php-fpm','restart')" class="px-2 py-1 text-xs bg-yellow-600 hover:bg-yellow-700 rounded transition text-white">Restart</button>
            </div>
        </div>
        <div class="flex items-center justify-between bg-gray-800 rounded-lg px-4 py-3">
            <span class="text-white">WireGuard</span>
            <div class="flex gap-2">
                <button onclick="Settings.service('wireguard','up')" class="px-2 py-1 text-xs bg-green-600 hover:bg-green-700 rounded transition text-white">Up</button>
                <button onclick="Settings.service('wireguard','down')" class="px-2 py-1 text-xs bg-red-600 hover:bg-red-700 rounded transition text-white">Down</button>
                <button onclick="Settings.service('wireguard','restart')" class="px-2 py-1 text-xs bg-yellow-600 hover:bg-yellow-700 rounded transition text-white">Restart</button>
            </div>
        </div>
    </div>
    <div id="service-output" class="mt-3 text-sm font-mono text-gray-400 whitespace-pre-wrap hidden bg-gray-900 rounded-lg p-3"></div>
</div>

<!-- Config Tabs -->
<div class="bg-panel-card border border-panel-border rounded-xl overflow-hidden">
    <div class="flex border-b border-panel-border">
        <button onclick="Settings.loadConfig('nginx')" class="config-tab px-4 py-3 text-sm font-medium text-gray-400 hover:text-white border-b-2 border-transparent transition" data-tab="nginx">Nginx</button>
        <button onclick="Settings.loadConfig('php')" class="config-tab px-4 py-3 text-sm font-medium text-gray-400 hover:text-white border-b-2 border-transparent transition" data-tab="php">PHP-FPM</button>
        <button onclick="Settings.loadConfig('wireguard')" class="config-tab px-4 py-3 text-sm font-medium text-gray-400 hover:text-white border-b-2 border-transparent transition" data-tab="wireguard">WireGuard</button>
    </div>
    <div class="relative">
        <textarea id="config-editor" class="w-full h-96 bg-gray-900 text-gray-100 text-sm font-mono p-4 resize-none focus:outline-none" spellcheck="false" placeholder="Select a config tab above..."></textarea>
        <div class="flex justify-end gap-2 p-3 border-t border-panel-border">
            <button onclick="Settings.saveConfig()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-sm text-white rounded-lg transition">Save Config</button>
        </div>
    </div>
</div>
