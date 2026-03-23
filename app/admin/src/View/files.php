<!-- File Manager — Pelican Panel style -->

<!-- Header bar -->
<div class="flex items-center justify-between mb-4">
    <!-- Breadcrumb path -->
    <div class="flex items-center gap-1.5 text-sm" id="breadcrumb">
        <svg class="w-4 h-4 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
        </svg>
        <a href="#" onclick="FileManager.navigate('/')" class="text-gray-400 hover:text-white transition">/data</a>
    </div>

    <!-- Action buttons -->
    <div class="flex items-center gap-2">
        <button onclick="FileManager.newFolder()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            New Folder
        </button>
        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Upload
            <input type="file" multiple class="hidden" onchange="FileManager.uploadFiles(this.files)">
        </label>
    </div>
</div>

<!-- Upload progress -->
<div id="upload-progress" class="hidden mb-3">
    <div class="bg-gray-900 border border-gray-800 rounded-lg p-3">
        <div class="flex items-center justify-between text-xs text-gray-400 mb-2">
            <span id="upload-status">Uploading...</span>
            <span id="upload-percent">0%</span>
        </div>
        <div class="h-1 bg-gray-800 rounded-full overflow-hidden">
            <div id="upload-bar" class="h-full bg-blue-500 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
    </div>
</div>

<!-- File table -->
<div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="border-b border-gray-800 text-left">
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Size</th>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-44">Modified</th>
                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-28 text-right">Actions</th>
            </tr>
        </thead>
        <tbody id="file-list" class="divide-y divide-gray-800">
            <tr><td colspan="4" class="px-4 py-12 text-center text-gray-600 text-sm">Loading...</td></tr>
        </tbody>
    </table>
</div>

<!-- Editor modal -->
<div id="editor-modal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background: rgba(0,0,0,0.75);">
    <div class="bg-gray-900 border border-gray-800 rounded-xl w-full max-w-5xl flex flex-col mx-4" style="height: 85vh;">
        <!-- Editor header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 id="editor-title" class="text-sm font-medium text-gray-300 font-mono">Edit file</h3>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="FileManager.saveFile()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save
                </button>
                <button onclick="FileManager.closeEditor()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">
                    Close
                </button>
            </div>
        </div>
        <!-- Editor textarea -->
        <textarea
            id="editor-content"
            class="flex-1 bg-gray-950 text-gray-100 text-sm font-mono p-4 resize-none focus:outline-none leading-relaxed"
            spellcheck="false"
        ></textarea>
    </div>
</div>

<!-- Drag-and-drop overlay -->
<div id="drop-overlay" class="fixed inset-0 z-40 hidden items-center justify-center" style="background: rgba(3,7,18,0.85);">
    <div class="flex flex-col items-center gap-4">
        <div class="w-20 h-20 rounded-2xl bg-blue-600/20 border-2 border-dashed border-blue-500 flex items-center justify-center">
            <svg class="w-10 h-10 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
        </div>
        <div class="text-center">
            <p class="text-lg font-medium text-white">Drop files to upload</p>
            <p class="text-sm text-gray-500 mt-1">Files will be uploaded to the current directory</p>
        </div>
    </div>
</div>
