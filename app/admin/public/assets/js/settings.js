// Settings — config editor + service controls (Pelican Panel style)

const Settings = {
    activeTab: null,

    async loadConfig(name) {
        // Update tab styles
        document.querySelectorAll('.config-tab').forEach(tab => {
            tab.classList.remove('text-white');
            tab.classList.add('text-gray-500');
            tab.querySelector('.config-tab-indicator').style.opacity = '0';
        });
        const activeTab = document.querySelector(`[data-tab="${name}"]`);
        activeTab.classList.add('text-white');
        activeTab.classList.remove('text-gray-500');
        activeTab.querySelector('.config-tab-indicator').style.opacity = '1';

        this.activeTab = name;
        this.setStatus('Loading...');
        const data = await api.get('/api/settings/config?file=' + name);
        if (data && data.content !== undefined) {
            document.getElementById('config-editor').value = data.content;
            this.setStatus('');
        } else {
            document.getElementById('config-editor').value = data?.error || 'Failed to load config';
            this.setStatus('Failed to load');
        }
    },

    async saveConfig() {
        if (!this.activeTab) {
            alert('Select a config tab first');
            return;
        }
        const content = document.getElementById('config-editor').value;

        // Validate before saving
        this.setStatus('Validating...');
        this.showOutput('Validating config...');

        const validation = await api.post('/api/settings/validate', {
            file: this.activeTab,
            content: content,
        });

        if (validation && !validation.valid) {
            this.setStatus('Validation failed');
            this.showOutput('Validation failed: ' + (validation.output || 'Unknown error'));

            // Ask user to confirm saving invalid config
            const proceed = confirm(
                'Config validation failed:\n\n' +
                (validation.output || 'Unknown error') +
                '\n\nSave anyway?'
            );
            if (!proceed) {
                this.setStatus('Cancelled');
                setTimeout(() => this.setStatus(''), 3000);
                return;
            }
        } else if (validation && validation.valid) {
            this.showOutput('Validation passed: ' + (validation.output || 'OK'));
        }

        // Proceed with save
        this.setStatus('Saving...');
        const result = await api.post('/api/settings/config', {
            file: this.activeTab,
            content: content,
        });
        if (result?.success) {
            this.setStatus('Saved');
            this.showOutput('Config saved successfully.');
            setTimeout(() => this.setStatus(''), 3000);
        } else {
            this.setStatus('Error');
            this.showOutput('Error: ' + (result?.error || 'Failed to save'));
        }
    },

    async service(name, action) {
        this.showOutput(`Running ${action} on ${name}...`);
        const result = await api.post('/api/settings/service', { service: name, action: action });
        if (result) {
            this.showOutput(`[${name}] ${action}: ${result.output || 'Done'}`);
        }
    },

    showOutput(text) {
        const container = document.getElementById('service-output');
        const textEl = document.getElementById('service-output-text');
        textEl.textContent = text;
        container.classList.remove('hidden');
        clearTimeout(this._outputTimer);
        this._outputTimer = setTimeout(() => container.classList.add('hidden'), 10000);
    },

    setStatus(text) {
        const el = document.getElementById('config-status');
        if (el) el.textContent = text;
    },

    _outputTimer: null,
};

// Load nginx config by default
Settings.loadConfig('nginx');
