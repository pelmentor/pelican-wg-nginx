// Console — xterm.js terminal with SSE log streaming + command input
// Ref: Pelican Panel server-console.blade.php (adapted from WebSocket to SSE)

const PRELUDE = '\x1b[1m\x1b[33mwg-nginx ~ \x1b[0m';
const ERROR_STYLE = '\x1b[1m\x1b[31m';
const INFO_STYLE = '\x1b[1m\x1b[32m';
const WARN_STYLE = '\x1b[1m\x1b[33m';
const RESET = '\x1b[0m';

// Theme from Pelican Panel
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

// Copy support (Ctrl+C)
terminal.attachCustomKeyEventHandler((event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
        navigator.clipboard.writeText(terminal.getSelection());
        return false;
    }
    return true;
});

// Write welcome message
terminal.writeln(PRELUDE + INFO_STYLE + 'Console connected. Streaming live logs...' + RESET);
terminal.writeln(PRELUDE + 'Type commands below. Use "help" for available commands.' + RESET);
terminal.writeln('');

// SSE connection for real-time log streaming
function connectSSE() {
    const source = new EventSource('/api/console/stream');

    source.onopen = () => {
        terminal.writeln(PRELUDE + INFO_STYLE + '[SSE connected]' + RESET);
    };

    source.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);
            const sourceLabel = data.source || 'log';
            const time = data.time || '';

            // Color-code by source
            let style = '';
            if (sourceLabel.includes('error')) style = ERROR_STYLE;
            else if (sourceLabel.includes('access')) style = '\x1b[90m'; // dim
            else if (sourceLabel.includes('wireguard')) style = '\x1b[36m'; // cyan

            terminal.writeln(`${style}[${time}][${sourceLabel}]${RESET} ${data.line}`);
        } catch (e) {
            // Non-JSON event (e.g. connected event)
        }
    };

    source.onerror = () => {
        terminal.writeln(PRELUDE + ERROR_STYLE + '[SSE disconnected — reconnecting in 3s...]' + RESET);
        source.close();
        setTimeout(connectSSE, 3000);
    };
}

connectSSE();

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

    // Command history (up/down arrows)
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
