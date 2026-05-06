<?php
require_once 'config.php';
require_once 'db.php';

$db = new Database();

// Check if setup already completed
$result = $db->db->query('SELECT COUNT(*) as count FROM users');
$row = $result->fetchArray(SQLITE3_ASSOC);

if ($row['count'] > 0) {
    // Load language for the message
    $lang = loadLanguage('en');
    $GLOBALS['lang'] = $lang;
    die(t('setup_complete') . ' <a href="auth.php">' . t('setup_login_link') . '</a>.');
}

// Default to English for setup
$lang = loadLanguage('en');
$GLOBALS['lang'] = $lang; // Make available for t() helper
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $language = $_POST['language'] ?? 'en';
    $theme = $_POST['theme'] ?? 'dark';
    $allowedPath = trim($_POST['allowed_path'] ?? '');
    
    // Validation
    if (empty($username)) {
        $error = $lang['username_required'];
    } elseif (strlen($password) < 8) {
        $error = $lang['password_too_short'];
    } else {
        try {
            // Create first user
            $db->createUser($username, $password, $language, $theme, $allowedPath);
            
            // Redirect to login
            header('Location: auth.php');
            exit;
        } catch (Exception $e) {
            $error = $lang['error_creating_user'] . ': ' . $e->getMessage();
        }
    }
}

// Load view
require __DIR__ . '/views/setup.view.php';
