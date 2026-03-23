// Dashboard — polls /api/stats every 2 seconds
let prevNetwork = null;

async function updateStats() {
    const data = await api.get('/api/stats');
    if (!data) return;

    // Services
    const svc = data.services;
    setStatus('nginx', svc.nginx);
    setStatus('phpfpm', svc['php-fpm']);
    setStatus('wg', svc.wireguard);

    // CPU
    document.getElementById('stat-cpu').textContent = data.cpu.percent + '%';
    document.getElementById('bar-cpu').style.width = Math.min(data.cpu.percent, 100) + '%';

    // Memory
    document.getElementById('stat-memory').textContent =
        `${data.memory.used_mb.toFixed(0)} / ${data.memory.total_mb.toFixed(0)} MiB`;
    document.getElementById('bar-memory').style.width = data.memory.percent + '%';

    // Disk
    document.getElementById('stat-disk').textContent =
        `${data.disk.used_gb} / ${data.disk.total_gb} GiB`;
    document.getElementById('bar-disk').style.width = data.disk.percent + '%';

    // Network — calculate rates from deltas
    const net = data.network.eth0 || {};
    if (prevNetwork) {
        const rxRate = (net.rx_bytes - prevNetwork.rx_bytes) / 2; // per second (2s interval)
        const txRate = (net.tx_bytes - prevNetwork.tx_bytes) / 2;
        document.getElementById('stat-network').textContent =
            `↓ ${formatBytes(rxRate)}/s  ↑ ${formatBytes(txRate)}/s`;
    }
    document.getElementById('stat-network-detail').textContent =
        `Total: ↓ ${formatBytes(net.rx_bytes || 0)}  ↑ ${formatBytes(net.tx_bytes || 0)}`;
    prevNetwork = net;

    // Uptime
    document.getElementById('stat-uptime').textContent = formatUptime(data.uptime);
}

function setStatus(id, running) {
    const dot = document.getElementById(`status-${id}`);
    const text = document.getElementById(`status-${id}-text`);
    dot.className = `w-3 h-3 rounded-full ${running ? 'bg-green-500' : 'bg-red-500'}`;
    text.textContent = running ? 'Running' : 'Stopped';
}

// Start polling
updateStats();
setInterval(updateStats, 2000);
