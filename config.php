<?php
session_start();

// Database path
define('DB_PATH', __DIR__ . '/shares.db');

// System paths - EDIT IT
// confirm that www-data user has r/w privileges to your root directory
// base URL is used in link shares
define('FILES_PATH', '/fsr');                    // Root directory for all files
define('BASE_URL', 'http://10.10.99.251');       // Base URL for share links

// You can leave it or change to your timezone
date_default_timezone_set('Europe/Warsaw');

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

// Generate random hash for shares
function generateHash($length = 16) {
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

// Get current logged in user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
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
?>
