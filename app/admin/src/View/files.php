<div class="flex items-center justify-between mb-4">
    <h2 class="text-2xl font-bold">Files</h2>
    <div class="flex gap-2">
        <button onclick="FileManager.newFolder()" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-sm text-white rounded-lg transition">
            New Folder
        </button>
        <label class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-sm text-white rounded-lg transition cursor-pointer">
            Upload
            <input type="file" multiple class="hidden" onchange="FileManager.uploadFiles(this.files)">
        </label>
    </div>
</div>

<!-- Breadcrumb -->
<div class="flex items-center gap-1 text-sm text-gray-400 mb-3" id="breadcrumb">
    <a href="#" onclick="FileManager.navigate('/')" class="hover:text-white">/data</a>
</div>

<!-- File table -->
<div class="bg-panel-card border border-panel-border rounded-xl overflow-hidden">
    <table class="w-full">
        <thead>
            <tr class="border-b border-panel-border text-left text-xs text-gray-400 uppercase">
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3 w-28">Size</th>
                <th class="px-4 py-3 w-44">Modified</th>
                <th class="px-4 py-3 w-24">Actions</th>
            </tr>
        </thead>
        <tbody id="file-list" class="divide-y divide-panel-border">
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Loading...</td></tr>
        </tbody>
    </table>
</div>

<!-- Editor modal -->
<div id="editor-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60">
    <div class="bg-gray-800 border border-gray-700 rounded-xl w-full max-w-4xl max-h-[80vh] flex flex-col mx-4">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
            <h3 id="editor-title" class="text-sm font-medium text-white">Edit file</h3>
            <div class="flex gap-2">
                <button onclick="FileManager.saveFile()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-sm text-white rounded-lg transition">Save</button>
                <button onclick="FileManager.closeEditor()" class="px-3 py-1 bg-gray-600 hover:bg-gray-500 text-sm text-white rounded-lg transition">Close</button>
            </div>
        </div>
        <textarea id="editor-content" class="flex-1 bg-gray-900 text-gray-100 text-sm font-mono p-4 resize-none focus:outline-none" spellcheck="false"></textarea>
    </div>
</div>

<!-- Drag and drop overlay -->
<div id="drop-overlay" class="fixed inset-0 z-40 hidden items-center justify-center bg-gray-900/50">
    <div class="bg-gray-800 rounded-xl p-8 border-2 border-dashed border-blue-500">
        <p class="text-lg font-medium text-white">Drop files to upload</p>
    </div>
</div>
