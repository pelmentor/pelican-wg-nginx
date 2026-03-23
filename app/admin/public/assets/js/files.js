// File Manager — Pelican Panel style (Filament tables)

const FileManager = {
    currentPath: '/',
    editingPath: null,
    searchActive: false,

    async navigate(path) {
        this.currentPath = path;
        this.searchActive = false;
        this.updateBreadcrumb();
        const data = await api.get('/api/files/list?path=' + encodeURIComponent(path));
        if (!data) return;
        this.render(data.entries);
    },

    render(entries) {
        const tbody = document.getElementById('file-list');
        if (!entries || entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-gray-600 text-sm">Empty directory</td></tr>';
            return;
        }

        let html = '';

        // Parent directory link
        if (this.currentPath !== '/' && !this.searchActive) {
            html += `<tr class="hover:bg-white/5 cursor-pointer transition-colors" onclick="FileManager.navigate('${this.parentPath()}')">
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                        <span class="text-sm text-gray-400">..</span>
                    </div>
                </td>
                <td></td><td></td><td></td><td></td>
            </tr>`;
        }

        entries.forEach(entry => {
            const fullPath = entry.path ? entry.path : (this.currentPath === '/' ? '/' : this.currentPath + '/') + entry.name;
            const escapedPath = fullPath.replace(/'/g, "\\'");
            const isDir = entry.type === 'directory';
            const isZip = !isDir && entry.name.toLowerCase().endsWith('.zip');

            // Folder icon (blue) or file icon (gray)
            const icon = isDir
                ? `<svg class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>`
                : `<svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`;

            const nameHtml = isDir
                ? `<a href="#" onclick="FileManager.navigate('${escapedPath}'); return false;" class="flex items-center gap-2.5 text-sm text-gray-200 hover:text-white transition">${icon}<span>${entry.name}</span></a>`
                : `<div class="flex items-center gap-2.5 text-sm text-gray-300">${icon}<span>${entry.name}</span></div>`;

            // Search result path hint
            const pathHint = this.searchActive && entry.path
                ? `<span class="ml-2 text-xs text-gray-600 font-mono">${entry.path}</span>`
                : '';

            // Action buttons
            let actions = '';

            // Rename button (for all entries)
            actions += `
                <button onclick="FileManager.renameEntry('${escapedPath}')" class="p-1.5 text-gray-500 hover:text-yellow-400 hover:bg-yellow-400/10 rounded-lg transition" title="Rename">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>`;

            // Copy button (for all entries)
            actions += `
                <button onclick="FileManager.copyEntry('${escapedPath}')" class="p-1.5 text-gray-500 hover:text-green-400 hover:bg-green-400/10 rounded-lg transition" title="Copy">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>`;

            if (!isDir) {
                // Edit button
                actions += `
                    <button onclick="FileManager.editFile('${escapedPath}')" class="p-1.5 text-gray-500 hover:text-white hover:bg-white/10 rounded-lg transition" title="Edit">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>`;

                // Download button
                actions += `
                    <a href="/api/files/download?path=${encodeURIComponent(fullPath)}" class="p-1.5 text-gray-500 hover:text-white hover:bg-white/10 rounded-lg transition" title="Download">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </a>`;
            }

            // Compress button (for all entries)
            actions += `
                <button onclick="FileManager.compressEntries('${escapedPath}', '${entry.name.replace(/'/g, "\\'")}')" class="p-1.5 text-gray-500 hover:text-purple-400 hover:bg-purple-400/10 rounded-lg transition" title="Compress">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v8"/></svg>
                </button>`;

            // Decompress button (only for .zip files)
            if (isZip) {
                actions += `
                    <button onclick="FileManager.decompressEntry('${escapedPath}')" class="p-1.5 text-gray-500 hover:text-orange-400 hover:bg-orange-400/10 rounded-lg transition" title="Extract">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    </button>`;
            }

            // Chmod button
            actions += `
                <button onclick="FileManager.chmodEntry('${escapedPath}')" class="p-1.5 text-gray-500 hover:text-cyan-400 hover:bg-cyan-400/10 rounded-lg transition" title="Permissions">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </button>`;

            // Delete button
            actions += `
                <button onclick="FileManager.deleteEntry('${escapedPath}')" class="p-1.5 text-gray-500 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition" title="Delete">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>`;

            html += `<tr class="hover:bg-white/5 transition-colors">
                <td class="px-4 py-2.5">${nameHtml}${pathHint}</td>
                <td class="px-4 py-2.5 text-xs text-gray-500">${isDir ? '' : formatBytes(entry.size)}</td>
                <td class="px-4 py-2.5 text-xs text-gray-500">${formatDate(entry.modified)}</td>
                <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">${entry.permissions || ''}</td>
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

    async createFile() {
        const name = prompt('File name:');
        if (!name) return;
        const path = (this.currentPath === '/' ? '/' : this.currentPath + '/') + name;
        await api.post('/api/files/create', { path });
        this.navigate(this.currentPath);
    },

    async renameEntry(path) {
        const oldName = path.split('/').pop();
        const newName = prompt('New name:', oldName);
        if (!newName || newName === oldName) return;
        const parts = path.split('/');
        parts.pop();
        const newPath = parts.join('/') + '/' + newName;
        await api.post('/api/files/rename', { from: path, to: newPath });
        this.navigate(this.currentPath);
    },

    async copyEntry(path) {
        await api.post('/api/files/copy', { path });
        this.navigate(this.currentPath);
    },

    async compressEntries(fullPath, name) {
        const archiveName = prompt('Archive name:', name + '.zip');
        if (!archiveName) return;
        const fileName = fullPath.split('/').pop();
        await api.post('/api/files/compress', {
            path: this.currentPath,
            files: [fileName],
            name: archiveName
        });
        this.navigate(this.currentPath);
    },

    async decompressEntry(path) {
        if (!confirm(`Extract ${path}?`)) return;
        await api.post('/api/files/decompress', { path });
        this.navigate(this.currentPath);
    },

    async chmodEntry(path) {
        const mode = prompt('Permissions (e.g. 0755, 0644):');
        if (!mode) return;
        await api.post('/api/files/chmod', { path, mode });
        this.navigate(this.currentPath);
    },

    async searchFiles(query) {
        if (!query || query.trim().length === 0) {
            this.searchActive = false;
            this.navigate(this.currentPath);
            return;
        }
        this.searchActive = true;
        const data = await api.get('/api/files/search?path=' + encodeURIComponent(this.currentPath) + '&query=' + encodeURIComponent(query));
        if (!data) return;
        this.render(data.results);
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
            xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());

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

// Search debounce
let searchTimeout = null;
function handleSearch(input) {
    clearTimeout(searchTimeout);
    const query = input.value.trim();
    if (query.length === 0) {
        FileManager.searchActive = false;
        FileManager.navigate(FileManager.currentPath);
        return;
    }
    searchTimeout = setTimeout(() => {
        FileManager.searchFiles(query);
    }, 300);
}

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
