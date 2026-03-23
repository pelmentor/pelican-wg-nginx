<!-- Stats Cards Row -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">

    <!-- CPU Card -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-5 transition-shadow duration-200 hover:shadow-lg hover:shadow-black/20">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-400">CPU</span>
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z"/>
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-white tracking-tight" id="stat-cpu">0.00 %</div>
        <div class="text-xs text-gray-500 mt-0.5 mb-3" id="stat-cpu-limit">/ &infin;</div>
        <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div id="bar-cpu" class="h-full bg-blue-500 rounded-full transition-all duration-700 ease-out" style="width: 0%"></div>
        </div>
    </div>

    <!-- Memory Card -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-5 transition-shadow duration-200 hover:shadow-lg hover:shadow-black/20">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-400">Memory</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v2.25M18 3v2.25M6 18.75V21M18 18.75V21M3 9.75h18M3 14.25h18M9.75 3h4.5v18h-4.5V3z"/>
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-white tracking-tight" id="stat-memory">0.00 MiB</div>
        <div class="text-xs text-gray-500 mt-0.5 mb-3" id="stat-memory-limit">/ &infin;</div>
        <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div id="bar-memory" class="h-full bg-emerald-500 rounded-full transition-all duration-700 ease-out" style="width: 0%"></div>
        </div>
    </div>

    <!-- Disk Card -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-5 transition-shadow duration-200 hover:shadow-lg hover:shadow-black/20">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-400">Disk</span>
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                </svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-white tracking-tight" id="stat-disk">0.00 GiB</div>
        <div class="text-xs text-gray-500 mt-0.5 mb-3" id="stat-disk-limit">/ 0.00 GiB</div>
        <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div id="bar-disk" class="h-full bg-amber-500 rounded-full transition-all duration-700 ease-out" style="width: 0%"></div>
        </div>
    </div>

    <!-- Network Card -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-5 transition-shadow duration-200 hover:shadow-lg hover:shadow-black/20">
        <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-400">Network</span>
            <div class="w-8 h-8 rounded-lg bg-violet-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                </svg>
            </div>
        </div>
        <div class="flex items-baseline gap-3">
            <div>
                <span class="text-xs text-gray-500 mr-1">&darr;</span>
                <span class="text-lg font-bold text-white" id="stat-net-rx">0.00 KiB</span>
            </div>
            <div>
                <span class="text-xs text-gray-500 mr-1">&uarr;</span>
                <span class="text-lg font-bold text-white" id="stat-net-tx">0.00 KiB</span>
            </div>
        </div>
        <div class="text-xs text-gray-600 mt-1.5 mb-3" id="stat-net-total">Total: &darr; 0 B &uarr; 0 B</div>
        <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div id="bar-network" class="h-full bg-violet-500 rounded-full transition-all duration-700 ease-out" style="width: 0%"></div>
        </div>
    </div>

</div>

<!-- Service Status Row -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <!-- Nginx -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-4 flex items-center gap-4 transition-shadow duration-200 hover:shadow-lg hover:shadow-black/20">
        <div class="w-10 h-10 rounded-lg bg-gray-800 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-green-400" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2L2 19.5h20L12 2zm0 4l7 13H5l7-13z" opacity="0.9"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-white">Nginx</div>
            <div class="text-xs text-gray-500" id="status-nginx-text">Checking...</div>
        </div>
        <span id="status-nginx" class="relative flex h-2.5 w-2.5 shrink-0">
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gray-600"></span>
        </span>
    </div>

    <!-- PHP-FPM -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-4 flex items-center gap-4 transition-shadow duration-200 hover:shadow-lg hover:shadow-black/20">
        <div class="w-10 h-10 rounded-lg bg-gray-800 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-indigo-400" viewBox="0 0 24 24" fill="currentColor">
                <path d="M7.01 10.207h-.944l-.515 2.648h.838c.556 0 .97-.105 1.242-.314.272-.21.455-.559.55-1.049.092-.47.05-.802-.124-.995-.175-.193-.523-.29-1.047-.29zM12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm3.003 8.076c.146.218.2.497.16.836l-.07.385c-.09.465-.263.849-.518 1.152a2.243 2.243 0 01-.982.691v.026c.399.13.655.378.77.744.114.367.087.838-.082 1.414l-.172.596c-.06.21-.09.399-.09.566l-.015.166h-1.43l.012-.128.035-.315c.02-.146.06-.345.125-.594l.184-.67c.115-.437.1-.735-.05-.893-.148-.158-.456-.237-.924-.237H10.54l-.616 3.164H8.51l1.748-8.978h3.076c.736 0 1.267.113 1.594.34l.075.06z"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-white">PHP-FPM</div>
            <div class="text-xs text-gray-500" id="status-phpfpm-text">Checking...</div>
        </div>
        <span id="status-phpfpm" class="relative flex h-2.5 w-2.5 shrink-0">
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gray-600"></span>
        </span>
    </div>

    <!-- WireGuard -->
    <div class="bg-panel-card border border-panel-border rounded-xl p-4 flex items-center gap-4 transition-shadow duration-200 hover:shadow-lg hover:shadow-black/20">
        <div class="w-10 h-10 rounded-lg bg-gray-800 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-rose-400" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v4.7c0 4.83-3.13 9.37-7 10.5-3.87-1.13-7-5.67-7-10.5V6.3l7-3.12z"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-white">WireGuard</div>
            <div class="text-xs text-gray-500" id="status-wg-text">Checking...</div>
        </div>
        <span id="status-wg" class="relative flex h-2.5 w-2.5 shrink-0">
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-gray-600"></span>
        </span>
    </div>
</div>

<!-- Recent Activity Widget -->
<div class="mt-6">
    <div class="bg-panel-card border border-panel-border rounded-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-panel-border">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-primary-500/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 class="text-sm font-semibold text-white">Recent Activity</h2>
            </div>
            <a href="/activity" class="text-xs text-primary-400 hover:text-primary-300 transition-colors">View all</a>
        </div>
        <div id="dashboard-activity" class="divide-y divide-panel-border">
            <div class="px-5 py-6 text-center text-sm text-gray-500">Loading...</div>
        </div>
    </div>
</div>
