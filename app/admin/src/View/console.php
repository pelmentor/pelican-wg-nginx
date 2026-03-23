<!-- Console — Pelican Panel style terminal -->

<div class="flex flex-col" style="height: calc(100vh - 3rem);">
    <!-- Terminal container -->
    <div class="flex-1 flex flex-col bg-gray-900 rounded-xl border border-gray-800 overflow-hidden min-h-0">
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
        </div>
    </div>

    <!-- Hint text -->
    <p class="text-xs text-gray-600 mt-3 px-1">
        Type <code class="text-gray-500 bg-gray-900 px-1.5 py-0.5 rounded text-xs font-mono">help</code> for available commands.
        Error logs from Nginx and PHP-FPM stream in real time.
    </p>
</div>
