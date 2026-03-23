<h2 class="text-2xl font-bold mb-6">Dashboard</h2>

<!-- Service Status Bar -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-panel-card border border-panel-border rounded-xl p-4 flex items-center gap-4">
        <div id="status-nginx" class="w-3 h-3 rounded-full bg-gray-500"></div>
        <div>
            <div class="text-sm text-gray-400">Nginx</div>
            <div id="status-nginx-text" class="text-white font-medium">Checking...</div>
        </div>
    </div>
    <div class="bg-panel-card border border-panel-border rounded-xl p-4 flex items-center gap-4">
        <div id="status-phpfpm" class="w-3 h-3 rounded-full bg-gray-500"></div>
        <div>
            <div class="text-sm text-gray-400">PHP-FPM</div>
            <div id="status-phpfpm-text" class="text-white font-medium">Checking...</div>
        </div>
    </div>
    <div class="bg-panel-card border border-panel-border rounded-xl p-4 flex items-center gap-4">
        <div id="status-wg" class="w-3 h-3 rounded-full bg-gray-500"></div>
        <div>
            <div class="text-sm text-gray-400">WireGuard</div>
            <div id="status-wg-text" class="text-white font-medium">Checking...</div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <!-- CPU -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-5">
        <div class="text-sm text-gray-400 mb-1">CPU</div>
        <div class="text-2xl font-bold text-white" id="stat-cpu">—</div>
        <div class="mt-2 h-2 bg-gray-700 rounded-full overflow-hidden">
            <div id="bar-cpu" class="h-full bg-blue-500 rounded-full transition-all duration-500" style="width:0%"></div>
        </div>
    </div>

    <!-- Memory -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-5">
        <div class="text-sm text-gray-400 mb-1">Memory</div>
        <div class="text-2xl font-bold text-white" id="stat-memory">—</div>
        <div class="mt-2 h-2 bg-gray-700 rounded-full overflow-hidden">
            <div id="bar-memory" class="h-full bg-green-500 rounded-full transition-all duration-500" style="width:0%"></div>
        </div>
    </div>

    <!-- Disk -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-5">
        <div class="text-sm text-gray-400 mb-1">Disk</div>
        <div class="text-2xl font-bold text-white" id="stat-disk">—</div>
        <div class="mt-2 h-2 bg-gray-700 rounded-full overflow-hidden">
            <div id="bar-disk" class="h-full bg-yellow-500 rounded-full transition-all duration-500" style="width:0%"></div>
        </div>
    </div>

    <!-- Network -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-5">
        <div class="text-sm text-gray-400 mb-1">Network</div>
        <div class="text-lg font-bold text-white" id="stat-network">—</div>
        <div class="text-xs text-gray-500 mt-1" id="stat-network-detail"></div>
    </div>
</div>

<!-- Uptime -->
<div class="bg-panel-card border border-panel-border rounded-xl p-5">
    <div class="text-sm text-gray-400 mb-1">Uptime</div>
    <div class="text-xl font-medium text-white" id="stat-uptime">—</div>
</div>
