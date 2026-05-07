<?php
// Database path
define('DB_PATH', '/var/lib/bakdrop/shares.db');

// System paths - EDIT IT
// confirm that www-data user has r/w privileges to your root directory
// base URL is used in share links
define('FILES_PATH', '/fsr');                    // Root directory for all files
define('BASE_URL', 'http://10.10.50.253:8080');       // Base URL for share links
define('DEFAULT_LANG', 'pl');                        // Default language for public pages (en, pl)

// Timezone is used for showing expiration time in share links
date_default_timezone_set('Europe/Warsaw');

?>
