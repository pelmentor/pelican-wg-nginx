// Settings — config editor + service controls

const Settings = {
    activeTab: null,

    async loadConfig(name) {
        // Update tab styles
        document.querySelectorAll('.config-tab').forEach(tab => {
            tab.classList.remove('border-blue-500', 'text-white');
            tab.classList.add('border-transparent', 'text-gray-400');
        });
        const activeTab = document.querySelector(`[data-tab="${name}"]`);
        activeTab.classList.add('border-blue-500', 'text-white');
        activeTab.classList.remove('border-transparent', 'text-gray-400');

        this.activeTab = name;
        const data = await api.get('/api/settings/config?file=' + name);
        if (data && data.content !== undefined) {
            document.getElementById('config-editor').value = data.content;
        } else {
            document.getElementById('config-editor').value = data?.error || 'Failed to load config';
        }
    },

    async saveConfig() {
        if (!this.activeTab) {
            alert('Select a config tab first');
            return;
        }
        const content = document.getElementById('config-editor').value;
        const result = await api.post('/api/settings/config', {
            file: this.activeTab,
            content: content,
        });
        if (result?.success) {
            this.showOutput('Config saved successfully.');
        } else {
            this.showOutput('Error: ' + (result?.error || 'Failed to save'));
        }
    },

    async service(name, action) {
        const result = await api.post('/api/settings/service', { service: name, action: action });
        if (result) {
            this.showOutput(`[${name}] ${action}: ${result.output || 'Done'}`);
        }
    },

    showOutput(text) {
        const el = document.getElementById('service-output');
        el.textContent = text;
        el.classList.remove('hidden');
        setTimeout(() => el.classList.add('hidden'), 8000);
    },
};

// Load nginx config by default
Settings.loadConfig('nginx');
