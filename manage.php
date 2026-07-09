#!/usr/bin/env php
<?php
// Bakdrop - Interactive User Management CLI

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

$db = new Database();

// === I/O helpers ===

function prompt(string $label): string {
    echo $label;
    $line = fgets(STDIN);
    if ($line === false) { // EOF (e.g. Ctrl-D)
        echo "\n";
        exit(0);
    }
    return trim($line);
}

function promptPassword(string $label): string {
    echo $label;
    // Disable terminal echo so the password isn't shown while typing.
    $hasStty = @system('stty -echo 2>/dev/null') !== false;
    if ($hasStty) {
        register_shutdown_function(fn() => @system('stty echo 2>/dev/null'));
    }
    $line = fgets(STDIN);
    if ($hasStty) {
        @system('stty echo 2>/dev/null');
    }
    echo "\n";
    if ($line === false) {
        exit(0);
    }
    return rtrim($line, "\r\n");
}

function confirm(string $label): bool {
    return strtolower(prompt($label . ' (y/n): ')) === 'y';
}

// Ask for a new password with confirmation; loops until valid.
// Returns null if the user submits an empty password (to cancel).
function promptNewPassword(): ?string {
    while (true) {
        $pw = promptPassword('Password (min 8 chars, empty to cancel): ');
        if ($pw === '') {
            return null;
        }
        if (strlen($pw) < 8) {
            echo "Password must be at least 8 characters, try again.\n";
            continue;
        }
        if ($pw !== promptPassword('Confirm password: ')) {
            echo "Passwords do not match, try again.\n";
            continue;
        }
        return $pw;
    }
}

// Ask for a value that must be one of $valid; empty input returns $default,
// anything else invalid re-prompts.
function promptChoice(string $label, array $valid, string $default): string {
    while (true) {
        $val = prompt($label);
        if ($val === '') {
            return $default;
        }
        if (in_array($val, $valid, true)) {
            return $val;
        }
        echo "Invalid value. Choose " . implode(' / ', $valid) . " (or Enter for '$default').\n";
    }
}

// Ask for a files path (relative to FILES_PATH). Empty means root. Warns if the
// folder doesn't exist yet but allows it.
function promptPath(): string {
    echo "Files path is relative to FILES_PATH (" . FILES_PATH . ").\n";
    echo "Leave empty for full root access, or e.g. 'finance' for a subfolder.\n";
    while (true) {
        $path = trim(prompt('Path: '));
        if ($path === '') {
            return '';
        }
        $full = rtrim(FILES_PATH, '/') . '/' . ltrim($path, '/');
        if (is_dir($full) || confirm("Folder '$full' does not exist yet. Use it anyway?")) {
            return $path;
        }
    }
}

// === Actions ===

function listUsers(Database $db): void {
    $users = $db->getAllUsers();
    echo "\n";
    if (empty($users)) {
        echo "No users.\n";
        return;
    }
    echo str_pad('ID', 5) . str_pad('Username', 20) . str_pad('Path', 30)
        . str_pad('Lang', 6) . str_pad('Theme', 8) . "Created\n";
    echo str_repeat('-', 90) . "\n";
    foreach ($users as $u) {
        echo str_pad((string)$u['id'], 5)
            . str_pad($u['username'], 20)
            . str_pad($u['allowed_path'] !== '' ? $u['allowed_path'] : '/ (root)', 30)
            . str_pad($u['language'], 6)
            . str_pad($u['theme'], 8)
            . date('Y-m-d H:i', $u['created_at']) . "\n";
    }
}

function createUser(Database $db): void {
    echo "\n";
    $username = prompt('Username: ');
    if ($username === '') {
        echo "Username is required.\n";
        return;
    }
    if ($db->getUser($username)) {
        echo "User '$username' already exists.\n";
        return;
    }

    $path = promptPath();
    $language = promptChoice('Language [en/pl] (default en): ', ['en', 'pl'], 'en');
    $theme = promptChoice('Theme [dark/light] (default dark): ', ['dark', 'light'], 'dark');

    $pw = promptNewPassword();
    if ($pw === null) {
        echo "Cancelled.\n";
        return;
    }

    try {
        $db->createUser($username, $pw, $language, $theme, $path);
        echo "\n✓ User '$username' created (path: " . ($path !== '' ? $path : '/ (root)') . ").\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

function resetPassword(Database $db): void {
    echo "\n";
    $username = prompt('Username: ');
    if (!$db->getUser($username)) {
        echo "User '$username' not found.\n";
        return;
    }

    $pw = promptNewPassword();
    if ($pw === null) {
        echo "Cancelled.\n";
        return;
    }

    $db->changePassword($username, $pw);
    echo "\n✓ Password updated for '$username'.\n";
}

function changePath(Database $db): void {
    echo "\n";
    $username = prompt('Username: ');
    if (!$db->getUser($username)) {
        echo "User '$username' not found.\n";
        return;
    }
    $path = promptPath();
    if ($db->updatePath($username, $path)) {
        echo "\n✓ Path updated for '$username': " . ($path !== '' ? $path : '/ (root)') . ".\n";
    } else {
        echo "Path unchanged.\n";
    }
}

function deleteUser(Database $db): void {
    echo "\n";
    $username = prompt('Username to delete: ');
    if (!$db->getUser($username)) {
        echo "User '$username' not found.\n";
        return;
    }
    if (!confirm("Delete user '$username'?")) {
        echo "Cancelled.\n";
        return;
    }
    // deleteUser() refuses to remove the last user and orphans their shares
    if ($db->deleteUser($username)) {
        echo "\n✓ User '$username' deleted (their shares are kept, marked as 'Deleted user').\n";
    } else {
        echo "Could not delete user (cannot delete the last remaining user).\n";
    }
}

// === Menu loop ===

while (true) {
    echo "\n=== Bakdrop user management ===\n";
    echo "1. List users\n";
    echo "2. Create user\n";
    echo "3. Reset user password\n";
    echo "4. Change user path\n";
    echo "5. Delete user\n";
    echo "6. Exit\n";

    switch (prompt('Choose option (1-6): ')) {
        case '1': listUsers($db); break;
        case '2': createUser($db); break;
        case '3': resetPassword($db); break;
        case '4': changePath($db); break;
        case '5': deleteUser($db); break;
        case '6':
        case 'q':
            echo "Bye.\n";
            exit(0);
        default:
            echo "Invalid option.\n";
    }
}
