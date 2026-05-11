<?php

// System paths - EDIT IT
// confirm that www-data user has r/w privileges to your root and DB directory
define('DB_PATH',      getenv('DB_PATH')      ?: '/var/lib/bakdrop/shares.db');
define('FILES_PATH',   getenv('FILES_PATH')   ?: '/path-to-your-data-dir');
define('BASE_URL',     getenv('BASE_URL')     ?: 'https://your-domain-or-ip');
define('DEFAULT_LANG', getenv('DEFAULT_LANG') ?: 'en');
date_default_timezone_set(getenv('TZ') ?: 'Europe/Warsaw');

?>