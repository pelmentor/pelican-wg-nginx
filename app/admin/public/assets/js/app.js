// ── Toast Notification System ─────────────────────────────────────────
// SECURITY: textContent (not innerHTML) is used to render toast messages. This prevents XSS —
// if an API error message contains "<script>", it is displayed as text, not executed as HTML.
const Toast = {
    show(message, type = 'success', duration = 3000) {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const span = document.createElement('span');
        span.textContent = message;
        toast.appendChild(span);
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('toast-exit');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    success(msg) { this.show(msg, 'success'); },
    error(msg) { this.show(msg, 'error', 5000); },
    warning(msg) { this.show(msg, 'warning', 4000); },
};

// CSRF token — read from the <meta> tag injected by the layout
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Global helpers
const api = {
    async get(url) {
        try {
            const res = await fetch(url);
            if (res.status === 401) { window.location = '/login'; return null; }
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                console.warn(`[api.get] ${url} returned ${res.status}:`, err);
                return err;
            }
            return await res.json();
        } catch (e) {
            console.warn(`[api.get] ${url} failed:`, e);
            return null;
        }
    },
    async post(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfToken(),
            },
            body: JSON.stringify(data),
        });
        if (res.status === 401) { window.location = '/login'; return null; }
        if (res.status === 403) {
            const err = await res.json().catch(() => ({}));
            if (err.error && err.error.includes('CSRF')) {
                // Token may have rotated — reload the page to get a fresh one
                window.location.reload();
                return null;
            }
            Toast.error(err.error || 'Forbidden');
            return null;
        }
        // Auto-show toast on 4xx / 5xx errors
        if (res.status >= 400) {
            const err = await res.json().catch(() => ({}));
            Toast.error(err.error || `Request failed (${res.status})`);
            return err;
        }
        return res.json();
    }
};

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
}

function formatUptime(seconds) {
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (d > 0) return `${d}d ${h}h ${m}m`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

function formatDate(timestamp) {
    if (!timestamp) return '--';
    return new Date(timestamp * 1000).toLocaleString();
}
