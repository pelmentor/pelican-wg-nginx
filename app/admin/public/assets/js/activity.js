// Activity Log page — fetches and renders the full activity log

const actionMeta = {
    'auth.login':        { label: 'Login',          icon: 'login',    color: 'green' },
    'auth.logout':       { label: 'Logout',         icon: 'logout',   color: 'gray' },
    'auth.login_failed': { label: 'Failed Login',   icon: 'warning',  color: 'red' },
    'file.write':        { label: 'File Saved',     icon: 'file',     color: 'blue' },
    'file.delete':       { label: 'File Deleted',   icon: 'trash',    color: 'red' },
    'file.rename':       { label: 'File Renamed',   icon: 'file',     color: 'amber' },
    'file.upload':       { label: 'File Uploaded',  icon: 'upload',   color: 'blue' },
    'file.compress':     { label: 'Compressed',     icon: 'archive',  color: 'violet' },
    'file.chmod':        { label: 'Permissions',    icon: 'lock',     color: 'amber' },
    'config.save':       { label: 'Config Saved',   icon: 'settings', color: 'emerald' },
    'console.command':   { label: 'Command',        icon: 'terminal', color: 'gray' },
};

const colorMap = {
    green:   'bg-green-500/10 text-green-400',
    red:     'bg-red-500/10 text-red-400',
    blue:    'bg-blue-500/10 text-blue-400',
    amber:   'bg-amber-500/10 text-amber-400',
    violet:  'bg-violet-500/10 text-violet-400',
    emerald: 'bg-emerald-500/10 text-emerald-400',
    gray:    'bg-gray-500/10 text-gray-400',
};

const badgeColor = {
    green:   'bg-green-500/15 text-green-400',
    red:     'bg-red-500/15 text-red-400',
    blue:    'bg-blue-500/15 text-blue-400',
    amber:   'bg-amber-500/15 text-amber-400',
    violet:  'bg-violet-500/15 text-violet-400',
    emerald: 'bg-emerald-500/15 text-emerald-400',
    gray:    'bg-gray-500/15 text-gray-400',
};

function iconSvg(type) {
    const icons = {
        login:    '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>',
        logout:   '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>',
        warning:  '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>',
        file:     '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>',
        trash:    '<path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>',
        upload:   '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>',
        archive:  '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>',
        lock:     '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>',
        settings: '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>',
        terminal: '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z"/>',
    };
    return `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">${icons[type] || icons.file}</svg>`;
}

function timeAgo(ts) {
    const diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

async function loadActivity() {
    const data = await api.get('/api/activity?limit=100');
    if (!data) return;

    const list = document.getElementById('activity-list');
    const count = document.getElementById('activity-count');
    const entries = data.entries || [];

    count.textContent = entries.length + ' events';

    if (entries.length === 0) {
        list.innerHTML = '<div class="px-5 py-8 text-center text-sm text-gray-500">No activity recorded yet.</div>';
        return;
    }

    list.innerHTML = entries.map(e => {
        const meta = actionMeta[e.action] || { label: e.action, icon: 'file', color: 'gray' };
        const colors = colorMap[meta.color] || colorMap.gray;
        const badge = badgeColor[meta.color] || badgeColor.gray;
        const detail = e.detail ? `<span class="text-gray-400 truncate">${escapeHtml(e.detail)}</span>` : '';
        const fullTime = new Date(e.time * 1000).toLocaleString();

        return `
            <div class="flex items-center gap-4 px-5 py-3 hover:bg-white/[0.02] transition-colors duration-100">
                <div class="w-8 h-8 rounded-lg ${colors} flex items-center justify-center shrink-0">
                    ${iconSvg(meta.icon)}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full ${badge}">${escapeHtml(meta.label)}</span>
                        ${detail ? `<span class="text-xs text-gray-500 truncate">${detail}</span>` : ''}
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-xs text-gray-500" title="${fullTime}">${timeAgo(e.time)}</div>
                    <div class="text-[10px] text-gray-600 font-mono">${escapeHtml(e.ip)}</div>
                </div>
            </div>`;
    }).join('');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

loadActivity();
