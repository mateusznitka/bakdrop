<?php

// System paths - EDIT IT
// confirm that www-data user has r/w privileges to your root and DB directory
define('DB_PATH', '/var/lib/bakdrop/shares.db');    // Database path, it will be created there
define('FILES_PATH', '/path-to-your-data-dir');      // Root directory for all files
define('BASE_URL', 'https://your-domain-or-ip');    // Base URL for share links
define('DEFAULT_LANG', 'en');                        // Default language for public pages (en, pl)
date_default_timezone_set('Europe/Warsaw');          // Timezone is used for showing expiration time in share links

?>