#!/usr/bin/env php
<?php
/**
 * Bakdrop - User Management CLI
 * 
 * Usage:
 *   php manage.php create <username> <password> <path> [lang] [theme]
 *   php manage.php list
 *   php manage.php delete <username>
 *   php manage.php set-path <username> <new-path>
 * 
 * Make executable: chmod +x manage.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die('This script must be run from command line');
}

$command = $argv[1] ?? '';
$db = new Database();

switch ($command) {
    case 'create':
        $username = $argv[2] ?? '';
        $password = $argv[3] ?? '';
        $allowedPath = $argv[4] ?? null;
        $language = $argv[5] ?? 'en';
        $theme = $argv[6] ?? 'dark';
        
        if (empty($username) || empty($password)) {
            die("Usage: php manage.php create <username> <password> <path> [lang] [theme]\n");
        }
        
        if ($allowedPath === null) {
            die("Error: Path is required. Use '' for root or 'finance' for subfolder\n");
        }
        
        if (strlen($password) < 8) {
            die("Error: Password must be at least 8 characters\n");
        }
        
        try {
            $db->createUser($username, $password, $language, $theme, $allowedPath);
            
            echo "✓ User '$username' created successfully\n";
            echo "  Path: " . ($allowedPath ?: '/ (root)') . "\n";
            echo "  Language: $language\n";
            echo "  Theme: $theme\n";
        } catch (Exception $e) {
            die("Error: " . $e->getMessage() . "\n");
        }
        break;
        
    case 'list':
        $result = $db->db->query('SELECT id, username, allowed_path, language, theme, created_at FROM users ORDER BY created_at');
        
        echo str_pad('ID', 5) . str_pad('Username', 20) . str_pad('Path', 30) . str_pad('Lang', 8) . str_pad('Theme', 10) . "Created\n";
        echo str_repeat('-', 100) . "\n";
        
        while ($user = $result->fetchArray(SQLITE3_ASSOC)) {
            echo str_pad($user['id'], 5);
            echo str_pad($user['username'], 20);
            echo str_pad($user['allowed_path'] ?: '/ (root)', 30);
            echo str_pad($user['language'], 8);
            echo str_pad($user['theme'], 10);
            echo date('Y-m-d H:i', $user['created_at']) . "\n";
        }
        break;
        
    case 'delete':
        $username = $argv[2] ?? '';
        
        if (empty($username)) {
            die("Usage: php manage.php delete <username>\n");
        }
        
        // Check if user exists
        $user = $db->getUser($username);
        if (!$user) {
            die("Error: User '$username' not found\n");
        }
        
        // Check if last user
        $count = $db->db->querySingle('SELECT COUNT(*) FROM users');
        if ($count <= 1) {
            die("Error: Cannot delete the last user\n");
        }
        
        echo "Delete user '$username'? (y/n): ";
        $confirm = trim(fgets(STDIN));
        
        if ($confirm !== 'y') {
            die("Cancelled\n");
        }
        
        // Delete user (db->deleteUser handles orphaned shares)
        if ($db->deleteUser($username)) {
            echo "✓ User '$username' deleted (shares preserved with 'Deleted user')\n";
        } else {
            echo "Error: Could not delete user\n";
        }
        break;
        
    case 'set-path':
        $username = $argv[2] ?? '';
        $newPath = $argv[3] ?? null;
        
        if (empty($username) || $newPath === null) {
            die("Usage: php manage.php set-path <username> <new-path>\n");
        }
        
        $stmt = $db->db->prepare('UPDATE users SET allowed_path = :path WHERE username = :username');
        $stmt->bindValue(':path', $newPath, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->execute();
        
        if ($db->db->changes() > 0) {
            echo "✓ Path updated for '$username': " . ($newPath ?: '/ (root)') . "\n";
        } else {
            echo "Error: User '$username' not found\n";
        }
        break;
        
    default:
        echo "Bakdrop - User Management\n\n";
        echo "Usage:\n";
        echo "  php manage.php create <username> <password> <path> [lang] [theme]\n";
        echo "  php manage.php list\n";
        echo "  php manage.php delete <username>\n";
        echo "  php manage.php set-path <username> <new-path>\n\n";
        echo "Examples:\n";
        echo "  php manage.php create admin1 MyPass123 '' en dark\n";
        echo "  php manage.php create finance SecureP@ss finance pl light\n";
        echo "  php manage.php list\n";
        echo "  php manage.php delete admin1\n";
        echo "  php manage.php set-path admin1 /new-folder\n\n";
        echo "Notes:\n";
        echo "  - Path is relative to FILES_PATH (defined in config.php)\n";
        echo "  - Use '' for root access to all FILES_PATH\n";
        echo "  - Language: en or pl\n";
        echo "  - Theme: dark or light\n";
        break;
}
?>
