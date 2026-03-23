<!-- Console — Pelican Panel style with power controls + live stats -->

<!-- Top bar: service controls + live stats (like Pelican's console page) -->
<div class="flex items-center justify-between mb-3">
    <!-- Live mini stats -->
    <div class="flex items-center gap-3">
        <div class="flex items-center gap-2 bg-gray-900 border border-gray-800 rounded-lg px-3 py-1.5">
            <span class="text-xs text-gray-500">CPU</span>
            <span id="console-cpu" class="text-xs font-mono text-white">—</span>
        </div>
        <div class="flex items-center gap-2 bg-gray-900 border border-gray-800 rounded-lg px-3 py-1.5">
            <span class="text-xs text-gray-500">Memory</span>
            <span id="console-memory" class="text-xs font-mono text-white">—</span>
        </div>
        <div class="flex items-center gap-2 bg-gray-900 border border-gray-800 rounded-lg px-3 py-1.5">
            <span class="text-xs text-gray-500">Network</span>
            <span id="console-network" class="text-xs font-mono text-white">—</span>
        </div>
    </div>

    <!-- Power controls (like Pelican's Start/Restart/Stop) -->
    <div class="flex items-center gap-2">
        <button onclick="ConsoleActions.restartAll()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Restart
        </button>
        <button onclick="ConsoleActions.stopAll()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-500 rounded-lg transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/></svg>
            Stop
        </button>
    </div>
</div>

<!-- Terminal container -->
<div class="flex flex-col" style="height: calc(100vh - 11rem);">
    <div class="flex-1 flex flex-col bg-gray-900 rounded-xl border border-gray-800 overflow-hidden min-h-0">
        <!-- Search bar (Ctrl+F) — hidden by default -->
        <div id="terminal-search-bar" class="hidden flex items-center gap-2 px-3 py-1.5 bg-gray-800 border-b border-gray-700">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                id="terminal-search-input"
                type="text"
                class="flex-1 bg-gray-900 text-gray-100 text-sm px-2 py-1 rounded border border-gray-700 focus:outline-none focus:border-blue-500 font-mono"
                placeholder="Search terminal..."
                autocomplete="off"
                spellcheck="false"
            >
            <span id="terminal-search-info" class="text-xs text-gray-500 min-w-[70px]"></span>
            <button id="terminal-search-close" class="text-gray-500 hover:text-gray-300 p-0.5" title="Close (Esc)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Terminal output -->
        <div id="terminal" class="flex-1 w-full min-h-0"></div>

        <!-- Command input bar -->
        <div class="flex items-center bg-gray-900 border-t border-gray-800/50">
            <span class="pl-4 pr-1 text-gray-500 select-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 5l5 5-5 5m5-10l5 5-5 5"/>
                </svg>
            </span>
            <input
                id="command-input"
                type="text"
                class="flex-1 bg-transparent text-gray-100 text-sm px-2 py-3 focus:outline-none placeholder-gray-600 font-mono"
                placeholder="Type a command..."
                autocomplete="off"
                spellcheck="false"
            >
            <button
                id="clear-terminal-btn"
                class="text-gray-600 hover:text-gray-300 px-3 py-2 text-xs font-mono transition-colors"
                title="Clear terminal"
            >Clear</button>
        </div>
    </div>

    <!-- Hint text -->
    <p class="text-xs text-gray-600 mt-2 px-1">
        <code class="text-gray-500 bg-gray-900 px-1.5 py-0.5 rounded text-xs font-mono">help</code> — commands
        <code class="text-gray-500 bg-gray-900 px-1.5 py-0.5 rounded text-xs font-mono ml-2">clear</code> — reset
        <code class="text-gray-500 bg-gray-900 px-1.5 py-0.5 rounded text-xs font-mono ml-2">Ctrl+F</code> — search
        <span class="ml-2">Logs poll every 2s.</span>
    </p>
</div>
