<h2 class="text-2xl font-bold mb-4">Console</h2>

<!-- Terminal -->
<div class="bg-panel-card border border-panel-border rounded-xl overflow-hidden">
    <div id="terminal" class="w-full" style="height: 500px;"></div>

    <!-- Command input -->
    <div class="flex items-center border-t border-panel-border bg-gray-900">
        <span class="text-yellow-400 font-bold pl-3 text-sm select-none">&raquo;</span>
        <input
            id="command-input"
            type="text"
            class="w-full bg-gray-900 text-white px-3 py-2.5 text-sm focus:outline-none border-none"
            placeholder="Type a command..."
            autocomplete="off"
        >
    </div>
</div>

<p class="text-xs text-gray-500 mt-2">
    Type <code class="text-gray-400">help</code> for available commands.
    Error logs from Nginx and PHP-FPM stream in real time.
</p>
