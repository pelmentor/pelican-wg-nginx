<!-- Users Management — Pelican Panel style -->
<?php $currentUser = Auth::getCurrentUser(); ?>

<div class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-primary-500/10 flex items-center justify-center">
                <svg class="w-4 h-4 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
            </div>
            <h2 class="text-base font-semibold text-white">User Management</h2>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="UserManager.showChangePasswordModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
                </svg>
                Change My Password
            </button>
            <button onclick="UserManager.showCreateModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Create User
            </button>
        </div>
    </div>

    <!-- Users table -->
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800 text-left">
                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Role</th>
                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-44">Last Login</th>
                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-44">Created</th>
                    <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider w-32 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="users-list" class="divide-y divide-gray-800">
                <tr><td colspan="5" class="px-4 py-12 text-center text-gray-600 text-sm">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div id="create-user-modal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background: rgba(0,0,0,0.75);">
    <div class="bg-gray-900 border border-gray-800 rounded-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-800">
            <h3 class="text-sm font-semibold text-white">Create User</h3>
            <button onclick="UserManager.closeModal('create-user-modal')" class="p-1 text-gray-500 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Username</label>
                <input type="text" id="create-username" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition" placeholder="Enter username">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Password</label>
                <input type="password" id="create-password" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition" placeholder="Enter password">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Role</label>
                <select id="create-role" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition">
                    <option value="viewer">Viewer</option>
                    <option value="operator">Operator</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-800">
            <button onclick="UserManager.closeModal('create-user-modal')" class="px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">Cancel</button>
            <button onclick="UserManager.createUser()" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition">Create</button>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background: rgba(0,0,0,0.75);">
    <div class="bg-gray-900 border border-gray-800 rounded-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-800">
            <h3 class="text-sm font-semibold text-white">Edit User</h3>
            <button onclick="UserManager.closeModal('edit-user-modal')" class="p-1 text-gray-500 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="edit-original-username">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Username</label>
                <input type="text" id="edit-username" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Role</label>
                <select id="edit-role" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition">
                    <option value="viewer">Viewer</option>
                    <option value="operator">Operator</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">New Password <span class="text-gray-600">(leave blank to keep current)</span></label>
                <input type="password" id="edit-password" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition" placeholder="Optional">
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-800">
            <button onclick="UserManager.closeModal('edit-user-modal')" class="px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">Cancel</button>
            <button onclick="UserManager.saveUser()" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition">Save</button>
        </div>
    </div>
</div>

<!-- Change My Password Modal -->
<div id="change-password-modal" class="fixed inset-0 z-50 hidden items-center justify-center" style="background: rgba(0,0,0,0.75);">
    <div class="bg-gray-900 border border-gray-800 rounded-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-800">
            <h3 class="text-sm font-semibold text-white">Change My Password</h3>
            <button onclick="UserManager.closeModal('change-password-modal')" class="p-1 text-gray-500 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Current Password</label>
                <input type="password" id="my-current-password" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition" placeholder="Enter current password">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">New Password</label>
                <input type="password" id="my-new-password" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition" placeholder="Enter new password">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1.5">Confirm New Password</label>
                <input type="password" id="my-confirm-password" class="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-white text-sm placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40 transition" placeholder="Confirm new password">
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-800">
            <button onclick="UserManager.closeModal('change-password-modal')" class="px-3 py-1.5 text-xs font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg transition">Cancel</button>
            <button onclick="UserManager.changeMyPassword()" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition">Change Password</button>
        </div>
    </div>
</div>
