// File Manager — Pelican Panel style (Filament tables)

const FileManager = {
    currentPath: '/',
    editingPath: null,

    async navigate(path) {
        this.currentPath = path;
        this.updateBreadcrumb();
        const data = await api.get('/api/files/list?path=' + encodeURIComponent(path));
        if (!data) return;
        this.render(data.entries);
    },

    render(entries) {
        const tbody = document.getElementById('file-list');
        if (!entries || entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-12 text-center text-gray-600 text-sm">Empty directory</td></tr>';
            return;
        }

        let html = '';

        // Parent directory link
        if (this.currentPath !== '/') {
            html += `<tr class="hover:bg-white/5 cursor-pointer transition-colors" onclick="FileManager.navigate('${this.parentPath()}')">
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                        <span class="text-sm text-gray-400">..</span>
                    </div>
                </td>
                <td></td><td></td><td></td>
            </tr>`;
        }

        entries.forEach(entry => {
            const fullPath = (this.currentPath === '/' ? '/' : this.currentPath + '/') + entry.name;
            const isDir = entry.type === 'directory';

            // Folder icon (blue) or file icon (gray)
            const icon = isDir
                ? `<svg class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>`
                : `<svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`;

            const nameHtml = isDir
                ? `<a href="#" onclick="FileManager.navigate('${fullPath}'); return false;" class="flex items-center gap-2.5 text-sm text-gray-200 hover:text-white transition">${icon}<span>${entry.name}</span></a>`
                : `<div class="flex items-center gap-2.5 text-sm text-gray-300">${icon}<span>${entry.name}</span></div>`;

            // Action buttons
            let actions = '';
            if (!isDir) {
                actions += `
                    <button onclick="FileManager.editFile('${fullPath}')" class="p-1.5 text-gray-500 hover:text-white hover:bg-white/10 rounded-lg transition" title="Edit">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <a href="/api/files/download?path=${encodeURIComponent(fullPath)}" class="p-1.5 text-gray-500 hover:text-white hover:bg-white/10 rounded-lg transition" title="Download">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </a>`;
            }
            actions += `
                <button onclick="FileManager.deleteEntry('${fullPath}')" class="p-1.5 text-gray-500 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition" title="Delete">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>`;

            html += `<tr class="hover:bg-white/5 transition-colors">
                <td class="px-4 py-2.5">${nameHtml}</td>
                <td class="px-4 py-2.5 text-xs text-gray-500">${isDir ? '' : formatBytes(entry.size)}</td>
                <td class="px-4 py-2.5 text-xs text-gray-500">${formatDate(entry.modified)}</td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center justify-end gap-0.5">${actions}</div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    },

    updateBreadcrumb() {
        const el = document.getElementById('breadcrumb');
        const parts = this.currentPath.split('/').filter(Boolean);
        let html = `<svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>`;
        html += `<a href="#" onclick="FileManager.navigate('/'); return false;" class="text-gray-400 hover:text-white transition">/data</a>`;
        let path = '';
        parts.forEach(part => {
            path += '/' + part;
            html += `<svg class="w-3 h-3 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>`;
            html += `<a href="#" onclick="FileManager.navigate('${path}'); return false;" class="text-gray-400 hover:text-white transition">${part}</a>`;
        });
        el.innerHTML = html;
    },

    parentPath() {
        const parts = this.currentPath.split('/').filter(Boolean);
        parts.pop();
        return '/' + parts.join('/');
    },

    async editFile(path) {
        const data = await api.get('/api/files/read?path=' + encodeURIComponent(path));
        if (!data) return;
        this.editingPath = path;
        document.getElementById('editor-title').textContent = path;
        document.getElementById('editor-content').value = data.content;
        document.getElementById('editor-modal').classList.remove('hidden');
        document.getElementById('editor-modal').classList.add('flex');
    },

    async saveFile() {
        const content = document.getElementById('editor-content').value;
        await api.post('/api/files/write', { path: this.editingPath, content });
        this.closeEditor();
        this.navigate(this.currentPath);
    },

    closeEditor() {
        document.getElementById('editor-modal').classList.add('hidden');
        document.getElementById('editor-modal').classList.remove('flex');
        this.editingPath = null;
    },

    async deleteEntry(path) {
        if (!confirm(`Delete ${path}?`)) return;
        await api.post('/api/files/delete', { path });
        this.navigate(this.currentPath);
    },

    async newFolder() {
        const name = prompt('Folder name:');
        if (!name) return;
        const path = (this.currentPath === '/' ? '/' : this.currentPath + '/') + name;
        await api.post('/api/files/mkdir', { path });
        this.navigate(this.currentPath);
    },

    async uploadFiles(fileList) {
        const progressContainer = document.getElementById('upload-progress');
        const progressBar = document.getElementById('upload-bar');
        const progressPercent = document.getElementById('upload-percent');
        const progressStatus = document.getElementById('upload-status');

        progressContainer.classList.remove('hidden');
        progressBar.style.width = '0%';
        progressPercent.textContent = '0%';
        progressStatus.textContent = `Uploading ${fileList.length} file${fileList.length > 1 ? 's' : ''}...`;

        const formData = new FormData();
        formData.append('path', this.currentPath);
        for (const file of fileList) {
            formData.append('files[]', file);
        }

        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/api/files/upload');

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = pct + '%';
                    progressPercent.textContent = pct + '%';
                }
            });

            await new Promise((resolve, reject) => {
                xhr.onload = resolve;
                xhr.onerror = reject;
                xhr.send(formData);
            });

            progressStatus.textContent = 'Upload complete';
            progressBar.style.width = '100%';
            progressPercent.textContent = '100%';
            setTimeout(() => progressContainer.classList.add('hidden'), 2000);
        } catch (e) {
            progressStatus.textContent = 'Upload failed';
            setTimeout(() => progressContainer.classList.add('hidden'), 3000);
        }

        this.navigate(this.currentPath);
    },
};

// Drag and drop
let dragCounter = 0;
document.addEventListener('dragenter', (e) => {
    e.preventDefault();
    dragCounter++;
    document.getElementById('drop-overlay').classList.remove('hidden');
    document.getElementById('drop-overlay').classList.add('flex');
});
document.addEventListener('dragleave', (e) => {
    e.preventDefault();
    dragCounter--;
    if (dragCounter === 0) {
        document.getElementById('drop-overlay').classList.add('hidden');
        document.getElementById('drop-overlay').classList.remove('flex');
    }
});
document.addEventListener('dragover', (e) => e.preventDefault());
document.addEventListener('drop', async (e) => {
    e.preventDefault();
    dragCounter = 0;
    document.getElementById('drop-overlay').classList.add('hidden');
    document.getElementById('drop-overlay').classList.remove('flex');
    if (e.dataTransfer.files.length > 0) {
        await FileManager.uploadFiles(e.dataTransfer.files);
    }
});

// Initial load
FileManager.navigate('/');
