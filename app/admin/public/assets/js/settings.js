// Settings — config editor + WireGuard form + service controls

const Settings = {
    activeTab: null,
    advancedMode: false,

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

        if (name === 'wireguard' && !this.advancedMode) {
            // Show WG form, hide raw editor
            document.getElementById('config-editor-wrap').classList.add('hidden');
            document.getElementById('wg-form').classList.remove('hidden');
            await this.loadWgForm();
        } else {
            // Show raw editor, hide WG form
            document.getElementById('config-editor-wrap').classList.remove('hidden');
            document.getElementById('wg-form').classList.add('hidden');

            this.setStatus('Loading...');
            const data = await api.get('/api/settings/config?file=' + name);
            if (data && data.content !== undefined) {
                document.getElementById('config-editor').value = data.content;
                this.setStatus('');
            } else {
                document.getElementById('config-editor').value = data?.error || 'Failed to load config';
                this.setStatus('Failed to load');
            }
        }
    },

    async loadWgForm() {
        this.setStatus('Loading...');
        const data = await api.get('/api/settings/wireguard');
        if (!data) {
            this.setStatus('Failed to load');
            return;
        }

        document.getElementById('wg-private-key').value = data.interface?.private_key || '';
        document.getElementById('wg-address').value = data.interface?.address || '';
        document.getElementById('wg-dns').value = data.interface?.dns || '';
        document.getElementById('wg-listen-port').value = data.interface?.listen_port || '';
        document.getElementById('wg-peer-public-key').value = data.peer?.public_key || '';
        document.getElementById('wg-preshared-key').value = data.peer?.preshared_key || '';
        document.getElementById('wg-endpoint').value = data.peer?.endpoint || '';
        document.getElementById('wg-allowed-ips').value = data.peer?.allowed_ips || '';
        document.getElementById('wg-keepalive').value = data.peer?.persistent_keepalive || '';

        if (!data.exists) {
            this.setStatus('New config');
            this.showOutput('Fill in your WireGuard details and click Save. Then click Up to connect.');
        } else {
            this.setStatus('');
        }
    },

    toggleAdvanced() {
        this.advancedMode = !this.advancedMode;
        const btn = document.getElementById('wg-advanced-btn');
        if (this.advancedMode) {
            btn.textContent = 'Form editor';
        } else {
            btn.textContent = 'Raw editor';
        }
        // Reload the WG tab in the new mode
        this.loadConfig('wireguard');
    },

    async saveConfig() {
        if (!this.activeTab) {
            alert('Select a config tab first');
            return;
        }

        // WireGuard form mode — save structured fields
        if (this.activeTab === 'wireguard' && !this.advancedMode) {
            return this.saveWgForm();
        }

        // Raw editor mode — validate then save text
        const content = document.getElementById('config-editor').value;

        this.setStatus('Validating...');
        this.showOutput('Validating config...');

        const validation = await api.post('/api/settings/validate', {
            file: this.activeTab,
            content: content,
        });

        if (validation && !validation.valid) {
            this.setStatus('Validation failed');
            this.showOutput('Validation failed: ' + (validation.output || 'Unknown error'));

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

    async saveWgForm() {
        const fields = {
            interface: {
                private_key: document.getElementById('wg-private-key').value.trim(),
                address: document.getElementById('wg-address').value.trim(),
                dns: document.getElementById('wg-dns').value.trim(),
                listen_port: document.getElementById('wg-listen-port').value.trim(),
            },
            peer: {
                public_key: document.getElementById('wg-peer-public-key').value.trim(),
                preshared_key: document.getElementById('wg-preshared-key').value.trim(),
                endpoint: document.getElementById('wg-endpoint').value.trim(),
                allowed_ips: document.getElementById('wg-allowed-ips').value.trim(),
                persistent_keepalive: document.getElementById('wg-keepalive').value.trim(),
            },
        };

        // Client-side validation
        if (!fields.interface.private_key) {
            Toast.error('Private Key is required');
            return;
        }
        if (!fields.interface.address) {
            Toast.error('Address is required');
            return;
        }

        this.setStatus('Saving...');
        const result = await api.post('/api/settings/wireguard', fields);
        if (result?.success) {
            this.setStatus('Saved');
            this.showOutput('WireGuard config saved. Click "Up" to connect.');
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
        this._outputTimer = setTimeout(() => container.classList.add('hidden'), 15000);
    },

    setStatus(text) {
        const el = document.getElementById('config-status');
        if (el) el.textContent = text;
    },

    _outputTimer: null,
};

// Load nginx config by default
Settings.loadConfig('nginx');
