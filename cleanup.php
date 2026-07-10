#!/usr/bin/env php
<?php
/**
 * Bakdrop - Cleanup Script
 * 
 * Deletes expired shares and files that reached file_delete_at timestamp
 * 
 * Run via cron:
 *   0 * * * * php /path/to/bakdrop/cleanup.php >> /var/log/bakdrop/cleanup.log 2>&1
 * 
 * Or manually:
 *   php cleanup.php
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
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
        // File already gone (deleted out of band or after download): drop the
        // stale share row and move on - nothing to delete, not a security issue
        $fullPath = resolveWithinFiles($share['file_path']);
        if ($fullPath === false && realpath(FILES_PATH . '/' . $share['file_path']) === false) {
            $db->deleteShare($share['hash']);
            echo "- File already deleted: {$share['file_path']}\n";
            continue;
        }

        // resolveWithinFiles returned false but the path exists -> it escapes
        // FILES_PATH. Skip it and flag loudly (defense in depth against traversal)
        if ($fullPath === false) {
            echo "SECURITY WARNING: Skipping invalid path: {$share['file_path']}\n";
            $errorCount++;
            continue;
        }

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

        // Delete share from database
        $db->deleteShare($share['hash']);
        echo "  Removed share link: {$share['hash']}\n";

        audit('file_delete', [
            'actor' => 'system',
            'hash' => $share['hash'],
            'file' => $share['file_path'],
            'reason' => 'scheduled',
        ]);
    }
    
    echo "\nSummary: $deletedCount deleted, $errorCount errors\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Cleanup completed\n";

?>