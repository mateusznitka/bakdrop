<?php
require_once 'config.php';
require_once 'db.php';

$db = new Database();

// Redirect to setup if no users exist
$userCount = $db->db->querySingle('SELECT COUNT(*) FROM users');
if ($userCount == 0) {
    header('Location: setup.php');
    exit;
}

requireLogin();

$db->cleanup(); // Remove expired shares

// Get current user and load their language
$user = $db->getUser(getCurrentUser());
$lang = loadLanguage($user['language']);
$GLOBALS['lang'] = $lang; // Make available for t() helper

// Scan directory function
function scanDirectory($dir, $baseDir = null) {
    if ($baseDir === null) {
        $baseDir = $dir;
    }
    
    $items = [];
    
    if (!is_dir($dir)) {
        return $items;
    }
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $fullPath = $dir . '/' . $file;
        $relativePath = str_replace($baseDir . '/', '', $fullPath);
        
        $item = [
            'name' => $file,
            'path' => $relativePath,
            'full_path' => $fullPath,
            'is_dir' => is_dir($fullPath),
            'size' => is_file($fullPath) ? filesize($fullPath) : 0,
            'modified' => filemtime($fullPath)
        ];
        
        $items[] = $item;
    }
    
    // Sort: folders first, then alphabetically
    usort($items, function($a, $b) {
        if ($a['is_dir'] != $b['is_dir']) {
            return $b['is_dir'] - $a['is_dir'];
        }
        return strcasecmp($a['name'], $b['name']);
    });
    
    return $items;
}

// Get user's base path (FILES_PATH + allowed_path)
$userBasePath = FILES_PATH . '/' . ltrim($user['allowed_path'], '/');
$userBasePath = rtrim($userBasePath, '/');

$currentPath = $_GET['path'] ?? '';
$fullPath = $userBasePath . '/' . ltrim($currentPath, '/');
$fullPath = realpath($fullPath);

// Security: prevent path traversal outside allowed_path
if ($fullPath === false || strpos($fullPath, realpath($userBasePath)) !== 0) {
    $fullPath = realpath($userBasePath);
    $currentPath = '';
}

$items = scanDirectory($fullPath, $userBasePath);
$shares = $db->getAllShares();

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: auth.php');
    exit;
}

// Load view
require __DIR__ . '/views/index.view.php';
