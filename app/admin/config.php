<?php
// Configuration — /data directory structure with multi-user support

define('DATA_DIR', '/data');
define('USER_DIR', DATA_DIR . '/user');
define('ADMIN_DIR', DATA_DIR . '/admin');
define('WEBROOT_DIR', USER_DIR . '/webroot');
define('USER_CONFIG_DIR', USER_DIR . '/config');
define('USER_LOGS_DIR', USER_DIR . '/logs');
define('USER_TMP_DIR', USER_DIR . '/tmp');
define('ADMIN_LOGS_DIR', ADMIN_DIR . '/logs');
define('USERS_FILE', ADMIN_DIR . '/users.json');
define('PANEL_SETTINGS_FILE', ADMIN_DIR . '/panel.json');
define('SESSIONS_DIR', ADMIN_DIR . '/sessions');

define('SESSION_LIFETIME', 86400); // 24 hours

/**
 * Read panel settings from /data/admin/panel.json (used by layout.php).
 * Returns defaults if file doesn't exist.
 */
function getPanelSettings(): array {
    $defaults = [
        'server_name' => 'WG-Nginx',
        'server_address' => '',
    ];
    if (!file_exists(PANEL_SETTINGS_FILE)) {
        return $defaults;
    }
    $data = @json_decode(@file_get_contents(PANEL_SETTINGS_FILE), true);
    return array_merge($defaults, is_array($data) ? $data : []);
}
