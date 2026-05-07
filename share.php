<?php
require_once 'config.php';
require_once 'db.php';

$lang = loadLanguage(DEFAULT_LANG);
$GLOBALS['lang'] = $lang; // Make available for t() helper

$hash = $_GET['h'] ?? '';
$error = '';
$share = null;

if (!$hash) {
    $error = $lang['invalid_link'];
} else {
    $db = new Database();
    $share = $db->getShare($hash);
    
    if (!$share) {
        $error = $lang['link_not_found'];
    } else {
        // Check if expired
        if ($share['expires_at'] && $share['expires_at'] < time()) {
            $db->deleteShare($hash);
            $error = $lang['link_expired'];
            $share = null;
        } else {
            // Check if file still exists
            $fullPath = realpath(FILES_PATH . '/' . $share['file_path']);
            if ($fullPath === false || !file_exists($fullPath)) {
                $error = $lang['file_not_found'];
                $share = null;
            }
        }
    }
}

// Password verification
$passwordRequired = $share && $share['password'];
$passwordValid = false;

if ($passwordRequired && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputPassword = $_POST['password'] ?? '';
    if (password_verify($inputPassword, $share['password'])) {
        $passwordValid = true;
        $_SESSION['share_' . $hash] = true;
    } else {
        $error = $lang['invalid_password'];
    }
}

if ($passwordRequired && isset($_SESSION['share_' . $hash])) {
    $passwordValid = true;
}

$canDownload = $share && (!$passwordRequired || $passwordValid);

// Load view
require __DIR__ . '/views/share.view.php';
