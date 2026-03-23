<?php
// Configuration — reads from environment variables with sensible defaults

define('DATA_DIR', '/data');
define('WEBROOT_DIR', DATA_DIR . '/webroot');
define('LOGS_DIR', DATA_DIR . '/logs');
define('WG_DIR', DATA_DIR . '/wg');
define('TMP_DIR', DATA_DIR . '/tmp');

define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: trim(@file_get_contents(DATA_DIR . '/.admin_password') ?: ''));
define('SESSION_LIFETIME', 86400); // 24 hours
