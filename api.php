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

header('Content-Type: application/json');

// Create new share
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $path = $_POST['path'] ?? '';
    $isDir = isset($_POST['is_dir']) && $_POST['is_dir'] === '1';
    $password = $_POST['password'] ?? null;
    $expiry = isset($_POST['expiry']) ? (time() + intval($_POST['expiry'])) : null;
    $deleteAfter = isset($_POST['delete_after']) && $_POST['delete_after'] === '1';
    $fileDeleteAt = isset($_POST['file_delete_after']) ? (time() + intval($_POST['file_delete_after'])) : null;
    
    // Get user's allowed path
    $user = $db->getUser(getCurrentUser());
    $userBasePath = FILES_PATH . '/' . ltrim($user['allowed_path'], '/');
    $userBasePath = rtrim($userBasePath, '/');
    
    $fullPath = realpath($userBasePath . '/' . $path);
    
    // check if user is in allowed path
    if ($fullPath === false || strpos($fullPath, realpath($userBasePath)) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid path']);
        exit;
    }
    
    if (!file_exists($fullPath)) {
        echo json_encode(['success' => false, 'error' => 'File does not exist']);
        exit;
    }
    
    try {
        $hash = $db->createShare($path, $password, $expiry, $deleteAfter, $fileDeleteAt, getCurrentUserId());
        $link = BASE_URL . '/share.php?h=' . $hash;
        
        echo json_encode([
            'success' => true,
            'hash' => $hash,
            'link' => $link
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Delete share
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $hash = $_POST['hash'] ?? '';
    
    try {
        $db->deleteShare($hash);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    $username = getCurrentUser();
    
    if (!$db->verifyUser($username, $currentPassword)) {
        echo json_encode(['success' => false, 'error' => 'Invalid current password']);
        exit;
    }
    
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters']);
        exit;
    }
    
    try {
        $db->changePassword($username, $newPassword);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Save preferences (language & theme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_preferences') {
    $language = $_POST['language'] ?? 'en';
    $theme = $_POST['theme'] ?? 'dark';
    
    $username = getCurrentUser();
    
    // Validate language
    if (!in_array($language, ['en', 'pl'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid language']);
        exit;
    }
    
    // Validate theme
    if (!in_array($theme, ['dark', 'light'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid theme']);
        exit;
    }
    
    try {
        $db->updatePreferences($username, $language, $theme);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Delete file or folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    $path = $_POST['path'] ?? '';
    $isDir = isset($_POST['is_dir']) && $_POST['is_dir'] === '1';
    
    // Get user's allowed path
    $user = $db->getUser(getCurrentUser());
    $userBasePath = FILES_PATH . '/' . ltrim($user['allowed_path'], '/');
    $userBasePath = rtrim($userBasePath, '/');
    
    $fullPath = realpath($userBasePath . '/' . $path);
    
    // check if user is in allowed path
    if ($fullPath === false || strpos($fullPath, realpath($userBasePath)) !== 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid path']);
        exit;
    }
    
    if (!file_exists($fullPath)) {
        echo json_encode(['success' => false, 'error' => 'File does not exist']);
        exit;
    }
    
    try {
        // Delete associated shares first
        $shares = $db->getAllShares();
        foreach ($shares as $share) {
            // Check if this share points to the file/folder we're deleting
            if ($share['file_path'] === $path || strpos($share['file_path'], $path . '/') === 0) {
                $db->deleteShare($share['hash']);
            }
        }
        
        // Delete the file or folder
        if ($isDir) {
            if (!deleteDirectory($fullPath)) {
                echo json_encode(['success' => false, 'error' => 'Failed to delete folder']);
                exit;
            }
        } else {
            if (!unlink($fullPath)) {
                echo json_encode(['success' => false, 'error' => 'Failed to delete file']);
                exit;
            }
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Helper function to recursively delete directory
function deleteDirectory($dir) {
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

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
