<?php
require_once 'helpers.php';
require_once 'db.php';

$lang = loadLanguage(DEFAULT_LANG);

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
            // Keep the row if a file deletion is still scheduled - cleanup.php needs it
            if (!$share['file_delete_at']) {
                $db->deleteShare($hash);
            }
            $error = $lang['link_expired'];
            $share = null;
        } else {
            // Check the file still exists and stays within FILES_PATH
            // (defense in depth - same guard as download.php)
            if (resolveWithinFiles($share['file_path']) === false) {
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
        audit('share_pw', ['status' => 'fail', 'hash' => $hash]);
        $error = $lang['invalid_password'];
    }
}

if ($passwordRequired && isset($_SESSION['share_' . $hash])) {
    $passwordValid = true;
}

$canDownload = $share && (!$passwordRequired || $passwordValid);

// Log the visit (who opened the download page): GET only, valid share. The
// access log has this too, but keeping it in the audit trail is more convenient.
if ($share && $_SERVER['REQUEST_METHOD'] === 'GET') {
    audit('share_view', [
        'hash' => $hash,
        'file' => $share['file_path'],
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '-',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '-',
    ]);
}

// Load view
require __DIR__ . '/views/share.view.php';
