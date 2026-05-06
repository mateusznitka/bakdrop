#!/usr/bin/env php
<?php
/**
 * Bakdrop - Cleanup Script
 * 
 * Deletes expired shares and files that reached file_delete_at timestamp
 * 
 * Run via cron:
 *   0 * * * * php /path/to/bakdrop/cleanup.php >> /var/log/bakdrop-cleanup.log 2>&1
 * 
 * Or manually:
 *   php cleanup.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Check if running from CLI
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cgi-fcgi') {
    die('This script must be run from command line');
}

$db = new Database();

echo "[" . date('Y-m-d H:i:s') . "] Starting cleanup...\n";

// 1. Clean expired shares (links only - doesn't delete files)
$db->cleanup();
echo "Expired shares cleaned from database\n";

// 2. Delete files that reached file_delete_at
$filesToDelete = $db->getFilesToDelete();

if (empty($filesToDelete)) {
    echo "No files to delete\n";
} else {
    $deletedCount = 0;
    $errorCount = 0;
    
    foreach ($filesToDelete as $share) {
        $fullPath = realpath(FILES_PATH . '/' . $share['file_path']);
        
        // Security check
        if ($fullPath === false || strpos($fullPath, realpath(FILES_PATH)) !== 0) {
            echo "SECURITY WARNING: Skipping invalid path: {$share['file_path']}\n";
            $errorCount++;
            continue;
        }
        
        if (file_exists($fullPath)) {
            if (is_dir($fullPath)) {
                if (deleteDirectory($fullPath)) {
                    echo "✓ Deleted folder: {$share['file_path']}\n";
                    $deletedCount++;
                } else {
                    echo "✗ ERROR: Failed to delete folder: {$share['file_path']}\n";
                    $errorCount++;
                    continue;
                }
            } else {
                if (unlink($fullPath)) {
                    echo "✓ Deleted file: {$share['file_path']}\n";
                    $deletedCount++;
                } else {
                    echo "✗ ERROR: Failed to delete file: {$share['file_path']}\n";
                    $errorCount++;
                    continue;
                }
            }
        } else {
            echo "- File already deleted: {$share['file_path']}\n";
        }
        
        // Delete share from database
        $db->deleteShare($share['hash']);
        echo "  Removed share link: {$share['hash']}\n";
    }
    
    echo "\nSummary: $deletedCount deleted, $errorCount errors\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed\n";

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
