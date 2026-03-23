// Dashboard — polls /api/stats every 2 seconds
// Formats values to match Pelican Panel style

let prevNetwork = null;
let prevTime = null;

async function updateStats() {
    const data = await api.get('/api/stats');
    if (!data) return;
    const now = Date.now();

    // ── Services ──
    const svc = data.services;
    setServiceStatus('nginx', svc.nginx);
    setServiceStatus('phpfpm', svc['php-fpm']);
    setServiceStatus('wg', svc.wireguard);

    // ── CPU ──
    const cpuPct = parseFloat(data.cpu.percent) || 0;
    document.getElementById('stat-cpu').textContent = cpuPct.toFixed(2) + ' %';
    document.getElementById('stat-cpu-limit').innerHTML = '/ &infin;';
    document.getElementById('bar-cpu').style.width = Math.min(cpuPct, 100) + '%';

    // ── Memory ──
    const memUsed = parseFloat(data.memory.used_mb) || 0;
    const memTotal = parseFloat(data.memory.total_mb) || 0;
    document.getElementById('stat-memory').textContent = memUsed.toFixed(2) + ' MiB';
    if (memTotal > 0) {
        document.getElementById('stat-memory-limit').textContent = '/ ' + memTotal.toFixed(0) + ' MiB';
    } else {
        document.getElementById('stat-memory-limit').innerHTML = '/ &infin;';
    }
    document.getElementById('bar-memory').style.width = data.memory.percent + '%';

    // ── Disk ──
    const diskUsed = parseFloat(data.disk.used_gb) || 0;
    const diskTotal = parseFloat(data.disk.total_gb) || 0;
    document.getElementById('stat-disk').textContent = diskUsed.toFixed(2) + ' GiB';
    document.getElementById('stat-disk-limit').textContent = '/ ' + diskTotal.toFixed(2) + ' GiB';
    document.getElementById('bar-disk').style.width = data.disk.percent + '%';

    // ── Network ──
    const net = data.network.eth0 || {};
    const rxBytes = net.rx_bytes || 0;
    const txBytes = net.tx_bytes || 0;

    if (prevNetwork && prevTime) {
        const elapsed = (now - prevTime) / 1000; // seconds
        if (elapsed > 0) {
            const rxRate = (rxBytes - prevNetwork.rx_bytes) / elapsed;
            const txRate = (txBytes - prevNetwork.tx_bytes) / elapsed;
            document.getElementById('stat-net-rx').textContent = formatKiB(rxRate) + '/s';
            document.getElementById('stat-net-tx').textContent = formatKiB(txRate) + '/s';

            // Animated bar — scale relative to 10 MiB/s for visual feedback
            const maxRate = 10 * 1024 * 1024;
            const combinedRate = rxRate + txRate;
            const netPct = Math.min((combinedRate / maxRate) * 100, 100);
            document.getElementById('bar-network').style.width = netPct + '%';
        }
    }

    document.getElementById('stat-net-total').innerHTML =
        'Total: &darr; ' + formatBytesIEC(rxBytes) + ' &uarr; ' + formatBytesIEC(txBytes);

    prevNetwork = { rx_bytes: rxBytes, tx_bytes: txBytes };
    prevTime = now;

    // ── Uptime (header) ──
    const uptimeStr = formatUptimeShort(data.uptime);
    const headerUptime = document.getElementById('header-uptime');
    if (headerUptime) {
        headerUptime.textContent = '(' + uptimeStr + ')';
    }
}

/**
 * Set service status dot + text
 */
function setServiceStatus(id, running) {
    const dot = document.getElementById(`status-${id}`);
    const text = document.getElementById(`status-${id}-text`);

    if (running) {
        dot.innerHTML =
            '<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>' +
            '<span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>';
        dot.className = 'relative flex h-2.5 w-2.5 shrink-0';
        text.textContent = 'Running';
        text.className = 'text-xs text-green-400';
    } else {
        dot.innerHTML =
            '<span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>';
        dot.className = 'relative flex h-2.5 w-2.5 shrink-0';
        text.textContent = 'Stopped';
        text.className = 'text-xs text-red-400';
    }
}

/**
 * Format bytes as KiB with 2 decimal places (Pelican style)
 */
function formatKiB(bytes) {
    if (bytes < 0) bytes = 0;
    const kib = bytes / 1024;
    if (kib < 1024) {
        return kib.toFixed(2) + ' KiB';
    }
    const mib = kib / 1024;
    if (mib < 1024) {
        return mib.toFixed(2) + ' MiB';
    }
    const gib = mib / 1024;
    return gib.toFixed(2) + ' GiB';
}

/**
 * Format total bytes with IEC units
 */
function formatBytesIEC(bytes) {
    if (bytes === 0) return '0 B';
    const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    const k = 1024;
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const idx = Math.min(i, units.length - 1);
    return (bytes / Math.pow(k, idx)).toFixed(2) + ' ' + units[idx];
}

/**
 * Format uptime as compact "Xh Ym" like Pelican
 */
function formatUptimeShort(seconds) {
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (d > 0) return d + 'd ' + h + 'h ' + m + 'm';
    if (h > 0) return h + 'h ' + m + 'm';
    return m + 'm';
}

// ── Start polling ──
updateStats();
setInterval(updateStats, 2000);
