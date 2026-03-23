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
    allowTransparency: true,
    rows: 25,
    theme: theme,
});

const fitAddon = new FitAddon.FitAddon();
terminal.loadAddon(fitAddon);
terminal.open(document.getElementById('terminal'));
fitAddon.fit();
window.addEventListener('resize', () => fitAddon.fit());

// Ctrl+C = copy selection
terminal.attachCustomKeyEventHandler((event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
        navigator.clipboard.writeText(terminal.getSelection());
        return false;
    }
    return true;
});

terminal.writeln(PRELUDE + INFO_STYLE + 'Console ready. Polling logs every 2s...' + RESET);
terminal.writeln(PRELUDE + 'Type commands below. Use "help" for available commands.' + RESET);
terminal.writeln('');

// Log polling — tracks byte positions per file, fetches only new data
let logPositions = {};

async function pollLogs() {
    try {
        const posParam = encodeURIComponent(JSON.stringify(logPositions));
        const data = await api.get('/api/console/poll?positions=' + posParam);
        if (!data) return;

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
        // Silent — will retry on next poll
    }
}

// Start polling
pollLogs();
setInterval(pollLogs, 2000);

// Command input
const cmdInput = document.getElementById('command-input');
const cmdHistory = [];
let historyIndex = -1;

cmdInput.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
        const cmd = cmdInput.value.trim();
        if (!cmd) return;

        cmdHistory.unshift(cmd);
        historyIndex = -1;
        cmdInput.value = '';

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
