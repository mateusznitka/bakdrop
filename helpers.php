<?php
// Keep PHP errors out of the HTTP response: a leaked warning corrupts file
// downloads (extra bytes past Content-Length, so the browser rejects the whole
// response) and exposes filesystem paths. They are still written to the log.
// Under CLI (the cleanup cron) errors stay visible on stderr so the job can
// report them. Set BAKDROP_DEBUG=1 to show errors while developing.
if (PHP_SAPI !== 'cli' && getenv('BAKDROP_DEBUG') !== '1') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_samesite' => 'Strict',
]);
require_once 'config.php';

// App metadata shown in the About dialog (bump APP_VERSION on releases)
define('APP_VERSION', '1.1.0');
define('APP_LICENSE', 'GPL-3.0');

// Baseline security headers for all web responses (no-op under CLI)
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

// Load language file
function loadLanguage($lang) {
    $file = __DIR__ . "/lang/$lang.json";
    if (!file_exists($file)) {
        $file = __DIR__ . "/lang/en.json"; // default is english
    }
    $content = file_get_contents($file);
    return json_decode($content, true);
}

// Translation helper with auto-escaping
function t($key) {
    global $lang;
    return htmlspecialchars($lang[$key] ?? $key, ENT_QUOTES, 'UTF-8');
}

// Translation helper without escaping (for attributes, JS, etc.)
function tr($key) {
    global $lang;
    return $lang[$key] ?? $key;
}

// Generate random hash for shares (32 hex chars = 128 bits of entropy)
function generateHash($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Require login (redirect to auth if not logged in)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: auth.php');
        exit;
    }
}

// Get current logged in username
function getCurrentUser() {
    return $_SESSION['username'] ?? null;
}

// Prevent log injection
function auditValue($v) {
    $v = preg_replace('/[\x00-\x1F\x7F]/', ' ', (string)$v);
    $v = trim($v);
    if ($v === '') {
        return '-';
    }
    if (preg_match('/[\s"=]/', $v)) {
        return '"' . str_replace('"', '\\"', $v) . '"';
    }
    return $v;
}

// Append one audit event to the log
function audit($event, array $fields = []) {
    $actor = array_key_exists('actor', $fields) ? $fields['actor'] : (getCurrentUser() ?? '-');
    $ip    = array_key_exists('ip', $fields)    ? $fields['ip']    : ($_SERVER['REMOTE_ADDR'] ?? '-');
    unset($fields['actor'], $fields['ip']);

    $line = date('c')
          . ' event=' . auditValue($event)
          . ' actor=' . auditValue($actor)
          . ' ip=' . auditValue($ip);
    foreach ($fields as $key => $value) {
        if ($value === null) {
            continue;
        }
        $line .= ' ' . $key . '=' . auditValue($value);
    }

    @file_put_contents(AUDIT_LOG, $line . "\n", FILE_APPEND | LOCK_EX);
}

// Get current logged in user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get (create on first use) the per-session CSRF token
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token from POST data
function isValidCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';
    return $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Format file size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Delete directory
function deleteDirectory($dir) {
    // Remove a symlink as a link, never follow it. This must come first:
    // is_dir() follows the link (so a link to an external directory would get its
    // contents deleted), and file_exists() is false for a dangling link (so a
    // Windows junction restored as a broken symlink - common in ProgramData -
    // would be skipped, leaving the parent non-empty and rmdir failing).
    if (is_link($dir)) {
        return unlink($dir);
    }

    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

// Resolve wheter user path is in allowed path
function resolveUserPath($allowedPath, $relativePath) {
    $realFilesRoot = realpath(FILES_PATH);
    $basePath = rtrim(FILES_PATH . '/' . ltrim($allowedPath, '/'), '/');
    $realBase = realpath($basePath);
    $fullPath = realpath($basePath . '/' . ltrim($relativePath, '/'));

    if ($fullPath === false || $realBase === false || $realFilesRoot === false) {
        return false;
    }

    if ($fullPath !== $realBase && strpos($fullPath, $realBase . '/') !== 0) {
        return false;
    }

    // Also clamp to FILES_PATH itself, so a malformed allowed_path (e.g. one
    // containing '..') can never grant access outside the files root.
    if ($fullPath !== $realFilesRoot && strpos($fullPath, $realFilesRoot . '/') !== 0) {
        return false;
    }

    return $fullPath;
}

// Resolve a path relative to FILES_PATH to a safe, existing absolute path within
// FILES_PATH, or false if it does not exist or escapes the root. Shared by the
// public download/share endpoints and cleanup (defense in depth against traversal).
function resolveWithinFiles($relativePath) {
    $realBase = realpath(FILES_PATH);
    $fullPath = realpath(FILES_PATH . '/' . ltrim($relativePath, '/'));

    if ($fullPath === false || $realBase === false) {
        return false;
    }

    if ($fullPath !== $realBase && strpos($fullPath, $realBase . '/') !== 0) {
        return false;
    }

    return $fullPath;
}