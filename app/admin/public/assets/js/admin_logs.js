// Admin System Logs — log viewer with auto-refresh

const AdminLogs = {
    currentTab: 'access',
    autoRefresh: true,
    refreshInterval: null,
    searchDebounce: null,
    REFRESH_MS: 5000,

    init() {
        this.startAutoRefresh();
        this.refresh();
    },

    async refresh() {
        const search = document.getElementById('log-search')?.value ?? '';
        const url = `/api/admin/logs?type=${encodeURIComponent(this.currentTab)}&lines=200&search=${encodeURIComponent(search)}`;
        const data = await api.get(url);
        if (!data || data.error) return;

        this.renderLog(data);
    },

    renderLog(data) {
        // Update info bar
        document.getElementById('log-file-name').textContent = data.file || '--';
        document.getElementById('log-line-count').textContent = `${data.total ?? 0} entries`;
        document.getElementById('log-last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();

        if (this.currentTab === 'activity') {
            // Show activity table, hide text
            document.getElementById('log-text-container').classList.add('hidden');
            document.getElementById('log-activity-container').classList.remove('hidden');
            this.renderActivityTable(data.entries || []);
        } else {
            // Show text, hide activity table
            document.getElementById('log-text-container').classList.remove('hidden');
            document.getElementById('log-activity-container').classList.add('hidden');
            this.renderTextLog(data.lines || []);
        }
    },

    renderTextLog(lines) {
        const pre = document.getElementById('log-text-output');
        if (lines.length === 0) {
            pre.textContent = 'No log entries found.';
            pre.classList.add('text-gray-600');
            return;
        }
        pre.classList.remove('text-gray-600');

        // Syntax highlight log lines
        const highlighted = lines.map(line => this.highlightLogLine(line)).join('\n');
        pre.innerHTML = highlighted;

        // Auto-scroll to bottom
        const container = document.getElementById('log-text-container');
        container.scrollTop = container.scrollHeight;
    },

    highlightLogLine(line) {
        const escaped = this.esc(line);

        // Highlight timestamps like [2024-01-15 12:34:56] or 2024/01/15 12:34:56
        let result = escaped.replace(
            /(\[?\d{4}[-\/]\d{2}[-\/]\d{2}[\sT]\d{2}:\d{2}:\d{2}\]?)/g,
            '<span class="text-gray-500">$1</span>'
        );

        // Highlight error/warning keywords
        result = result.replace(
            /\b(error|fatal|critical|fail(?:ed|ure)?|exception)\b/gi,
            '<span class="text-red-400 font-medium">$1</span>'
        );
        result = result.replace(
            /\b(warn(?:ing)?|notice)\b/gi,
            '<span class="text-yellow-400">$1</span>'
        );
        result = result.replace(
            /\b(info)\b/gi,
            '<span class="text-blue-400">$1</span>'
        );

        // Highlight HTTP status codes
        result = result.replace(
            /\b([45]\d{2})\b/g,
            '<span class="text-red-400">$1</span>'
        );
        result = result.replace(
            /\b([23]\d{2})\b/g,
            '<span class="text-green-400">$1</span>'
        );

        // Highlight IP addresses
        result = result.replace(
            /\b(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\b/g,
            '<span class="text-cyan-400">$1</span>'
        );

        return result;
    },

    renderActivityTable(entries) {
        const tbody = document.getElementById('log-activity-table');
        if (entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-600 text-sm">No activity entries found</td></tr>';
            return;
        }

        let html = '';
        entries.forEach(entry => {
            // Action badge color
            let actionClass = 'bg-gray-500/10 text-gray-400 border-gray-500/20';
            const action = entry.action || '';
            if (action.includes('login') || action.includes('auth')) {
                actionClass = 'bg-blue-500/10 text-blue-400 border-blue-500/20';
            } else if (action.includes('create') || action.includes('add')) {
                actionClass = 'bg-green-500/10 text-green-400 border-green-500/20';
            } else if (action.includes('delete') || action.includes('remove')) {
                actionClass = 'bg-red-500/10 text-red-400 border-red-500/20';
            } else if (action.includes('update') || action.includes('change') || action.includes('edit')) {
                actionClass = 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
            } else if (action.includes('service') || action.includes('restart')) {
                actionClass = 'bg-purple-500/10 text-purple-400 border-purple-500/20';
            }

            const time = entry.time ? new Date(entry.time * 1000).toLocaleString() : '--';

            html += `<tr class="hover:bg-white/5 transition-colors">
                <td class="px-4 py-2.5 text-xs text-gray-500 whitespace-nowrap">${this.esc(time)}</td>
                <td class="px-4 py-2.5">
                    <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-medium border ${actionClass}">${this.esc(action)}</span>
                </td>
                <td class="px-4 py-2.5 text-xs text-gray-400">${this.esc(entry.detail || '')}</td>
                <td class="px-4 py-2.5 text-xs font-mono text-gray-500">${this.esc(entry.ip || '')}</td>
            </tr>`;
        });

        tbody.innerHTML = html;
    },

    switchTab(tab) {
        this.currentTab = tab;

        // Update tab buttons
        ['access', 'error', 'activity'].forEach(t => {
            const btn = document.getElementById('tab-' + t);
            if (t === tab) {
                btn.className = btn.className
                    .replace('text-gray-400 hover:text-white hover:bg-white/5', '')
                    .replace(/bg-red-600 text-white/g, '') + ' bg-red-600 text-white';
            } else {
                btn.className = btn.className
                    .replace('bg-red-600 text-white', '')
                    + ' text-gray-400 hover:text-white hover:bg-white/5';
            }
        });

        this.refresh();
    },

    toggleAutoRefresh() {
        const checkbox = document.getElementById('auto-refresh-toggle');
        const dot = document.getElementById('auto-refresh-dot');
        this.autoRefresh = checkbox.checked;

        if (this.autoRefresh) {
            dot.classList.add('translate-x-4');
            dot.classList.remove('translate-x-0');
            dot.parentElement.querySelector('div:not(#auto-refresh-dot)').classList.add('bg-green-600');
            dot.parentElement.querySelector('div:not(#auto-refresh-dot)').classList.remove('bg-gray-700');
            this.startAutoRefresh();
        } else {
            dot.classList.remove('translate-x-4');
            dot.classList.add('translate-x-0');
            dot.parentElement.querySelector('div:not(#auto-refresh-dot)').classList.remove('bg-green-600');
            dot.parentElement.querySelector('div:not(#auto-refresh-dot)').classList.add('bg-gray-700');
            this.stopAutoRefresh();
        }
    },

    startAutoRefresh() {
        this.stopAutoRefresh();
        if (this.autoRefresh) {
            this.refreshInterval = setInterval(() => this.refresh(), this.REFRESH_MS);
        }
    },

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    },

    onSearchChange() {
        clearTimeout(this.searchDebounce);
        this.searchDebounce = setTimeout(() => this.refresh(), 300);
    },

    esc(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    },
};

// Initialize
AdminLogs.init();
