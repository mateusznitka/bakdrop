<?php
require_once 'helpers.php';
require_once 'db.php';

$db = new Database();

// Redirect to setup if no users exist
if (!$db->hasUsers()) {
    header('Location: setup.php');
    exit;
}


// Redirect to admin site if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$lang = loadLanguage(DEFAULT_LANG);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($db->verifyUser($username, $password)) {
        $user = $db->getUser($username);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $user['id'];
        
        // Load user's language for future requests
        header('Location: index.php');
        exit;
    } else {
        $error = $lang['invalid_credentials'];
    }
}

// Load view
require __DIR__ . '/views/auth.view.php';
