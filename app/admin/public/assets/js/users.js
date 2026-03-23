// User Management — Pelican Panel style

const UserManager = {
    users: [],

    async load() {
        const data = await api.get('/api/users');
        if (!data || !data.users) return;
        this.users = data.users;
        this.render();
    },

    render() {
        const tbody = document.getElementById('users-list');
        if (!this.users || this.users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-12 text-center text-gray-600 text-sm">No users found</td></tr>';
            return;
        }

        let html = '';
        this.users.forEach(user => {
            const escapedUsername = user.username.replace(/'/g, "\\'").replace(/"/g, '&quot;');

            // Role badge
            let roleBadge = '';
            switch (user.role) {
                case 'admin':
                    roleBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Admin</span>';
                    break;
                case 'operator':
                    roleBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-yellow-500/10 text-yellow-400 border border-yellow-500/20">Operator</span>';
                    break;
                default:
                    roleBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">Viewer</span>';
            }

            const lastLogin = user.last_login ? formatDate(user.last_login) : '<span class="text-gray-600">Never</span>';
            const created = user.created_at ? formatDate(user.created_at) : '--';

            // Action buttons
            let actions = '';
            actions += `
                <button onclick="UserManager.showEditModal('${escapedUsername}')" class="p-1.5 text-gray-500 hover:text-white hover:bg-white/10 rounded-lg transition" title="Edit">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>`;
            actions += `
                <button onclick="UserManager.deleteUser('${escapedUsername}')" class="p-1.5 text-gray-500 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition" title="Delete">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>`;

            html += `<tr class="hover:bg-white/5 transition-colors">
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-full bg-gray-800 flex items-center justify-center text-xs font-medium text-gray-400 uppercase">${user.username.charAt(0)}</div>
                        <span class="text-sm font-medium text-white">${user.username}</span>
                    </div>
                </td>
                <td class="px-4 py-2.5">${roleBadge}</td>
                <td class="px-4 py-2.5 text-xs text-gray-500">${lastLogin}</td>
                <td class="px-4 py-2.5 text-xs text-gray-500">${created}</td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center justify-end gap-0.5">${actions}</div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    },

    // --- Create User ---

    showCreateModal() {
        document.getElementById('create-username').value = '';
        document.getElementById('create-password').value = '';
        document.getElementById('create-role').value = 'viewer';
        this.openModal('create-user-modal');
        document.getElementById('create-username').focus();
    },

    async createUser() {
        const username = document.getElementById('create-username').value.trim();
        const password = document.getElementById('create-password').value;
        const role = document.getElementById('create-role').value;

        if (!username) { Toast.error('Username is required'); return; }
        if (!password) { Toast.error('Password is required'); return; }

        const result = await api.post('/api/users/create', { username, password, role });
        if (result && !result.error) {
            Toast.success('User created successfully');
            this.closeModal('create-user-modal');
            this.load();
        }
    },

    // --- Edit User ---

    showEditModal(username) {
        const user = this.users.find(u => u.username === username);
        if (!user) return;

        document.getElementById('edit-original-username').value = user.username;
        document.getElementById('edit-username').value = user.username;
        document.getElementById('edit-role').value = user.role;
        document.getElementById('edit-password').value = '';
        this.openModal('edit-user-modal');
    },

    async saveUser() {
        const originalUsername = document.getElementById('edit-original-username').value;
        const username = document.getElementById('edit-username').value.trim();
        const role = document.getElementById('edit-role').value;
        const password = document.getElementById('edit-password').value;

        if (!username) { Toast.error('Username is required'); return; }

        const payload = { original_username: originalUsername, username, role };
        if (password) payload.password = password;

        const result = await api.post('/api/users/update', payload);
        if (result && !result.error) {
            Toast.success('User updated successfully');
            this.closeModal('edit-user-modal');
            this.load();
        }
    },

    // --- Delete User ---

    async deleteUser(username) {
        if (!confirm(`Delete user "${username}"? This action cannot be undone.`)) return;

        const result = await api.post('/api/users/delete', { username });
        if (result && !result.error) {
            Toast.success('User deleted successfully');
            this.load();
        }
    },

    // --- Change My Password ---

    showChangePasswordModal() {
        document.getElementById('my-current-password').value = '';
        document.getElementById('my-new-password').value = '';
        document.getElementById('my-confirm-password').value = '';
        this.openModal('change-password-modal');
        document.getElementById('my-current-password').focus();
    },

    async changeMyPassword() {
        const currentPassword = document.getElementById('my-current-password').value;
        const newPassword = document.getElementById('my-new-password').value;
        const confirmPassword = document.getElementById('my-confirm-password').value;

        if (!currentPassword) { Toast.error('Current password is required'); return; }
        if (!newPassword) { Toast.error('New password is required'); return; }
        if (newPassword !== confirmPassword) { Toast.error('Passwords do not match'); return; }
        if (newPassword.length < 6) { Toast.error('Password must be at least 6 characters'); return; }

        const result = await api.post('/api/users/change-password', {
            current_password: currentPassword,
            new_password: newPassword,
        });
        if (result && !result.error) {
            Toast.success('Password changed successfully');
            this.closeModal('change-password-modal');
        }
    },

    // --- Modal helpers ---

    openModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    },

    closeModal(id) {
        const modal = document.getElementById(id);
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    },
};

// Close modals on backdrop click
['create-user-modal', 'edit-user-modal', 'change-password-modal'].forEach(id => {
    const modal = document.getElementById(id);
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) UserManager.closeModal(id);
        });
    }
});

// Close modals on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        ['create-user-modal', 'edit-user-modal', 'change-password-modal'].forEach(id => {
            const modal = document.getElementById(id);
            if (modal && !modal.classList.contains('hidden')) {
                UserManager.closeModal(id);
            }
        });
    }
});

// Initial load
UserManager.load();
