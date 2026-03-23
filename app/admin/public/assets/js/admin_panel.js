// Admin Panel Settings — system information display

const AdminPanel = {
    data: null,
    envExpanded: false,
    ENV_COLLAPSED_LIMIT: 15,

    async load() {
        const data = await api.get('/api/admin/panel/info');
        if (!data || data.error) {
            Toast.error('Failed to load system information');
            return;
        }
        this.data = data;
        this.render();
    },

    render() {
        const d = this.data;
        if (!d) return;

        // Hide loading, show content
        document.getElementById('panel-loading').classList.add('hidden');
        document.getElementById('panel-content').classList.remove('hidden');

        // Container info
        this.setText('info-hostname', d.container?.hostname);
        this.setText('info-uptime', d.container?.uptime ? formatUptime(d.container.uptime) : 'Unknown');
        this.setText('info-os', d.container?.os);
        this.setText('info-arch', d.container?.arch);

        // Disk usage
        if (d.disk) {
            const usedPercent = d.disk.total > 0 ? ((d.disk.used / d.disk.total) * 100).toFixed(1) : 0;
            document.getElementById('disk-bar').style.width = usedPercent + '%';
            // Color the bar based on usage
            const bar = document.getElementById('disk-bar');
            if (usedPercent > 90) {
                bar.className = bar.className.replace(/bg-\w+-500/, 'bg-red-500');
            } else if (usedPercent > 75) {
                bar.className = bar.className.replace(/bg-\w+-500/, 'bg-yellow-500');
            }
            this.setText('disk-percent', usedPercent + '%');
            this.setText('disk-used', formatBytes(d.disk.used));
            this.setText('disk-free', formatBytes(d.disk.free));
            this.setText('disk-total', formatBytes(d.disk.total));
        }

        // PHP info
        this.setText('info-php-version', d.php?.version);
        this.setText('info-php-sapi', d.php?.sapi);
        this.setText('info-php-memory', d.php?.memory_limit);
        this.setText('info-php-upload', d.php?.upload_max_filesize);

        // PHP extensions
        if (d.php?.extensions && Array.isArray(d.php.extensions)) {
            const container = document.getElementById('info-php-extensions');
            const sorted = [...d.php.extensions].sort();
            container.innerHTML = sorted.map(ext =>
                `<span class="inline-flex px-2 py-0.5 text-[11px] font-mono bg-gray-800 text-gray-400 rounded">${this.esc(ext)}</span>`
            ).join('');
        }

        // Nginx info
        this.setText('info-nginx-version', d.nginx?.version);
        this.setText('info-nginx-workers', d.nginx?.workers);
        const nginxStatus = document.getElementById('info-nginx-status');
        if (d.nginx?.running) {
            nginxStatus.innerHTML = '<span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-green-500"></span><span class="text-green-400">Running</span></span>';
        } else {
            nginxStatus.innerHTML = '<span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-red-500"></span><span class="text-red-400">Stopped</span></span>';
        }

        // WireGuard info
        const wgStatus = document.getElementById('info-wg-status');
        if (d.wireguard?.status === 'active') {
            wgStatus.innerHTML = '<span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-green-500"></span><span class="text-green-400">Active</span></span>';
        } else {
            wgStatus.innerHTML = '<span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-gray-500"></span><span class="text-gray-400">Inactive</span></span>';
        }
        this.setText('info-wg-interface', d.wireguard?.interface);
        this.setText('info-wg-peers', d.wireguard?.peers ?? 0);

        // Environment variables
        this.renderEnv();
    },

    renderEnv() {
        const d = this.data;
        if (!d?.environment) return;

        const env = d.environment;
        const keys = Object.keys(env);
        const displayKeys = this.envExpanded ? keys : keys.slice(0, this.ENV_COLLAPSED_LIMIT);

        const tbody = document.getElementById('env-table');
        let html = '';
        displayKeys.forEach(key => {
            const val = env[key];
            const isMasked = val === '********';
            html += `<tr class="hover:bg-white/5 transition-colors">
                <td class="px-5 py-2 text-xs font-mono text-gray-300 align-top">${this.esc(key)}</td>
                <td class="px-5 py-2 text-xs font-mono ${isMasked ? 'text-gray-600 italic' : 'text-gray-400'} break-all">${this.esc(val)}</td>
            </tr>`;
        });

        if (keys.length > this.ENV_COLLAPSED_LIMIT && !this.envExpanded) {
            html += `<tr><td colspan="2" class="px-5 py-3 text-center text-xs text-gray-600">${keys.length - this.ENV_COLLAPSED_LIMIT} more variables hidden</td></tr>`;
        }

        tbody.innerHTML = html;

        // Update toggle button text
        const toggle = document.getElementById('toggle-env');
        if (keys.length <= this.ENV_COLLAPSED_LIMIT) {
            toggle.classList.add('hidden');
        } else {
            toggle.classList.remove('hidden');
            toggle.textContent = this.envExpanded ? 'Show Less' : `Show All (${keys.length})`;
        }
    },

    toggleEnv() {
        this.envExpanded = !this.envExpanded;
        this.renderEnv();
    },

    setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value ?? '--';
    },

    esc(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    },
};

// Initial load
AdminPanel.load();
