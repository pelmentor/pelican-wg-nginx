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
define('SESSIONS_DIR', ADMIN_DIR . '/sessions');

define('SESSION_LIFETIME', 86400); // 24 hours
