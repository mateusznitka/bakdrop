<?php
require_once 'helpers.php';
require_once 'db.php';

$db = new Database();

// Redirect to setup if no users exist
if (!$db->hasUsers()) {
    header('Location: setup.php');
    exit;
}

requireLogin();

$db->cleanup(); // Remove expired shares

// Get current user and load their language
$user = $db->getUser(getCurrentUser());
$lang = loadLanguage($user['language']);

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

// Security: prevent path traversal outside allowed_path
$fullPath = resolveUserPath($user['allowed_path'], $currentPath);
if ($fullPath === false) {
    $fullPath = resolveUserPath($user['allowed_path'], '');
    $currentPath = '';
}

$items = scanDirectory($fullPath, $userBasePath);
$pathFilter = trim($user['allowed_path'], '/');
$shares = $db->getAllShares($pathFilter !== '' ? $pathFilter : null);

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: auth.php');
    exit;
}

// Load view
require __DIR__ . '/views/index.view.php';
