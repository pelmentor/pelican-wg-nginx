// Console — xterm.js terminal with log polling + command input
//
// Why polling instead of SSE:
// SSE holds a PHP-FPM worker for the entire connection, starving other
// requests (files, dashboard, settings). Polling releases the worker
// immediately after each 50ms request. 2s interval is fast enough for logs.
//
// Ref: Pelican Panel uses WebSocket via Wings daemon (Go process).
// We don't have a Go daemon, so polling is the proper PHP approach.

const PRELUDE = '\x1b[1m\x1b[33mwg-nginx ~ \x1b[0m';
const ERROR_STYLE = '\x1b[1m\x1b[31m';
const WARN_STYLE = '\x1b[1m\x1b[33m';
const INFO_STYLE = '\x1b[1m\x1b[32m';
const RESET = '\x1b[0m';

// Theme from Pelican Panel (server-console.blade.php)
const theme = {
    background: 'rgba(19,26,32,0.7)',
    cursor: 'transparent',
    black: '#000000',
    red: '#E54B4B',
    green: '#9ECE58',
    yellow: '#FAED70',
    blue: '#396FE2',
    magenta: '#BB80B3',
    cyan: '#2DDAFD',
    white: '#d0d0d0',
    brightBlack: 'rgba(255, 255, 255, 0.2)',
    brightRed: '#FF5370',
    brightGreen: '#C3E88D',
    brightYellow: '#FFCB6B',
    brightBlue: '#82AAFF',
    brightMagenta: '#C792EA',
    brightCyan: '#89DDFF',
    brightWhite: '#ffffff',
    selection: '#FAF089'
};

const terminal = new Terminal({
    fontSize: 13,
    fontFamily: 'Menlo, Monaco, "Courier New", monospace',
    lineHeight: 1.2,
    disableStdin: true,
    cursorStyle: 'underline',
    cursorInactiveStyle: 'underline',
    allowTransparency: true,
    rows: 25,
    theme: theme,
});

const fitAddon = new FitAddon.FitAddon();
terminal.loadAddon(fitAddon);

// Try to load WebLinksAddon if available (graceful fallback)
try {
    if (typeof WebLinksAddon !== 'undefined' && WebLinksAddon.WebLinksAddon) {
        const webLinksAddon = new WebLinksAddon.WebLinksAddon();
        terminal.loadAddon(webLinksAddon);
    }
} catch (_) {
    // WebLinksAddon not available — links won't be clickable, no big deal
}

terminal.open(document.getElementById('terminal'));
fitAddon.fit();
window.addEventListener('resize', () => fitAddon.fit());

// Ctrl+C = copy selection, Ctrl+F = open search
terminal.attachCustomKeyEventHandler((event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
        navigator.clipboard.writeText(terminal.getSelection());
        return false;
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
        event.preventDefault();
        toggleSearch(true);
        return false;
    }
    return true;
});

terminal.writeln(PRELUDE + INFO_STYLE + 'Console ready. Polling logs every 2s...' + RESET);
terminal.writeln(PRELUDE + 'Type commands below. Use "help" for available commands.' + RESET);
terminal.writeln('');

// --- In-terminal search (Ctrl+F) ---
const searchBar = document.getElementById('terminal-search-bar');
const searchInput = document.getElementById('terminal-search-input');
const searchInfo = document.getElementById('terminal-search-info');

function toggleSearch(open) {
    if (!searchBar) return;
    if (open) {
        searchBar.classList.remove('hidden');
        searchInput.focus();
        searchInput.select();
    } else {
        searchBar.classList.add('hidden');
        searchInput.value = '';
        searchInfo.textContent = '';
    }
}

function getTerminalText() {
    const buffer = terminal.buffer.active;
    const lines = [];
    for (let i = 0; i < buffer.length; i++) {
        const line = buffer.getLine(i);
        if (line) lines.push(line.translateToString(true));
    }
    return lines;
}

function doSearch() {
    const query = searchInput.value.trim().toLowerCase();
    if (!query) {
        searchInfo.textContent = '';
        return;
    }
    const lines = getTerminalText();
    let matches = 0;
    let lastMatchRow = -1;
    for (let i = 0; i < lines.length; i++) {
        if (lines[i].toLowerCase().includes(query)) {
            matches++;
            lastMatchRow = i;
        }
    }
    searchInfo.textContent = matches > 0 ? `${matches} match${matches > 1 ? 'es' : ''}` : 'No matches';
    // Scroll to the last match so user sees the most recent occurrence
    if (lastMatchRow >= 0) {
        terminal.scrollToLine(Math.max(0, lastMatchRow - 5));
    }
}

if (searchInput) {
    searchInput.addEventListener('input', doSearch);
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            toggleSearch(false);
        }
        if (e.key === 'Enter') {
            doSearch();
        }
    });
}

// Close search button
const searchClose = document.getElementById('terminal-search-close');
if (searchClose) {
    searchClose.addEventListener('click', () => toggleSearch(false));
}

// Global Ctrl+F capture (when focus is outside terminal)
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        // Only intercept on the console page
        if (document.getElementById('terminal')) {
            e.preventDefault();
            toggleSearch(true);
        }
    }
});

// --- Log polling — tracks byte positions per file, fetches only new data ---
let logPositions = {};
let pollFailCount = 0;

async function pollLogs() {
    try {
        const posParam = encodeURIComponent(JSON.stringify(logPositions));
        const data = await api.get('/api/console/poll?positions=' + posParam);
        if (!data) {
            pollFailCount++;
            showPollWarning();
            return;
        }

        // Reset fail counter on success
        pollFailCount = 0;

        // Update positions for next poll
        logPositions = data.positions;

        // Render new lines
        if (data.lines && data.lines.length > 0) {
            data.lines.forEach(entry => {
                let style = '';
                if (entry.source.includes('error')) style = ERROR_STYLE;
                else if (entry.source.includes('access')) style = '\x1b[90m';
                else if (entry.source.includes('wireguard')) style = '\x1b[36m';

                terminal.writeln(`${style}[${entry.time}][${entry.source}]${RESET} ${entry.line}`);
            });
        }
    } catch (e) {
        pollFailCount++;
        showPollWarning();
    }
}

function showPollWarning() {
    if (pollFailCount === 3) {
        terminal.writeln('');
        terminal.writeln(PRELUDE + WARN_STYLE + '[WARN] Log polling failed \u2014 retrying...' + RESET);
        terminal.writeln('');
    }
}

// Start polling
pollLogs();
setInterval(pollLogs, 2000);

// --- Command input with persistent history ---
const cmdInput = document.getElementById('command-input');

// Restore command history from localStorage
const cmdHistory = JSON.parse(localStorage.getItem('wg-console-history')) || [];
let historyIndex = -1;

function saveHistory() {
    localStorage.setItem('wg-console-history', JSON.stringify(cmdHistory.slice(0, 50)));
}

cmdInput.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
        const cmd = cmdInput.value.trim();
        if (!cmd) return;

        cmdHistory.unshift(cmd);
        historyIndex = -1;
        cmdInput.value = '';
        saveHistory();

        // Handle client-side 'clear' command
        if (cmd === 'clear') {
            terminal.clear();
            terminal.writeln(PRELUDE + INFO_STYLE + 'Terminal cleared.' + RESET);
            terminal.writeln('');
            return;
        }

        terminal.writeln('');
        terminal.writeln(PRELUDE + '$ ' + cmd);

        const result = await api.post('/api/console/command', { command: cmd });
        if (result && result.output) {
            result.output.split('\n').forEach(line => {
                terminal.writeln('  ' + line);
            });
        } else if (result && result.error) {
            terminal.writeln(ERROR_STYLE + '  ' + result.error + RESET);
        }
        terminal.writeln('');
    }

    if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (historyIndex < cmdHistory.length - 1) {
            historyIndex++;
            cmdInput.value = cmdHistory[historyIndex];
        }
    }
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (historyIndex > 0) {
            historyIndex--;
            cmdInput.value = cmdHistory[historyIndex];
        } else {
            historyIndex = -1;
            cmdInput.value = '';
        }
    }
});

// --- Clear button ---
const clearBtn = document.getElementById('clear-terminal-btn');
if (clearBtn) {
    clearBtn.addEventListener('click', () => {
        terminal.clear();
        terminal.writeln(PRELUDE + INFO_STYLE + 'Terminal cleared.' + RESET);
        terminal.writeln('');
    });
}

// --- Live stats on console page (like Pelican) ---
// Polls /api/stats and updates the mini stat widgets above the terminal.
let prevConsoleNet = null;
let prevConsoleTime = null;

async function updateConsoleStats() {
    try {
        const data = await api.get('/api/stats');
        if (!data) return;
        const now = Date.now();

        const cpuEl = document.getElementById('console-cpu');
        const memEl = document.getElementById('console-memory');
        const netEl = document.getElementById('console-network');

        if (cpuEl) cpuEl.textContent = parseFloat(data.cpu.percent).toFixed(1) + '%';
        if (memEl) memEl.textContent = parseFloat(data.memory.used_mb).toFixed(0) + ' MiB';

        if (netEl) {
            const net = data.network.eth0 || {};
            if (prevConsoleNet && prevConsoleTime) {
                const elapsed = (now - prevConsoleTime) / 1000;
                if (elapsed > 0) {
                    const rx = ((net.rx_bytes || 0) - prevConsoleNet.rx) / elapsed / 1024;
                    const tx = ((net.tx_bytes || 0) - prevConsoleNet.tx) / elapsed / 1024;
                    netEl.textContent = '↓' + rx.toFixed(1) + ' ↑' + tx.toFixed(1) + ' KiB/s';
                }
            }
            prevConsoleNet = { rx: net.rx_bytes || 0, tx: net.tx_bytes || 0 };
            prevConsoleTime = now;
        }

        // Update header uptime if available
        const headerUptime = document.getElementById('header-uptime');
        if (headerUptime && data.uptime) {
            const d = Math.floor(data.uptime / 86400);
            const h = Math.floor((data.uptime % 86400) / 3600);
            const m = Math.floor((data.uptime % 3600) / 60);
            headerUptime.textContent = '(' + (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm)';
        }
    } catch (_) {}
}

updateConsoleStats();
setInterval(updateConsoleStats, 3000);

// --- Power controls (Restart/Stop services) ---
const ConsoleActions = {
    async restartAll() {
        if (!confirm('Restart all services (Nginx + PHP-FPM + WireGuard)?')) return;
        terminal.writeln('');
        terminal.writeln(PRELUDE + WARN_STYLE + 'Restarting services...' + RESET);

        const results = await Promise.all([
            api.post('/api/settings/service', { service: 'nginx', action: 'restart' }),
            api.post('/api/settings/service', { service: 'php-fpm', action: 'restart' }),
            api.post('/api/settings/service', { service: 'wireguard', action: 'restart' }),
        ]);

        results.forEach(r => {
            if (r && r.output) {
                r.output.split('\n').forEach(line => {
                    terminal.writeln('  ' + line);
                });
            }
        });
        terminal.writeln(PRELUDE + INFO_STYLE + 'Services restarted.' + RESET);
        terminal.writeln('');
    },

    async stopWireguard() {
        if (!confirm('Stop WireGuard? (Nginx will keep running so the admin panel stays reachable.)')) return;
        terminal.writeln('');
        terminal.writeln(PRELUDE + WARN_STYLE + 'Stopping WireGuard...' + RESET);

        const result = await api.post('/api/settings/service', { service: 'wireguard', action: 'down' });
        if (result && result.output) {
            result.output.split('\n').forEach(line => {
                terminal.writeln('  ' + line);
            });
        }

        terminal.writeln(PRELUDE + INFO_STYLE + 'WireGuard stopped.' + RESET);
        terminal.writeln('');
    },
};
