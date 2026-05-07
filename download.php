<?php
require_once 'config.php';
require_once 'db.php';

// Composer autoload for ZipStream
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$lang = loadLanguage(DEFAULT_LANG);
$GLOBALS['lang'] = $lang;

$hash = $_GET['h'] ?? '';

if (!$hash) {
    http_response_code(400);
    die(tr('invalid_link'));
}

$db = new Database();
$share = $db->getShare($hash);

if (!$share) {
    http_response_code(404);
    die(tr('link_not_found'));
}

// Check expiration
if ($share['expires_at'] && $share['expires_at'] < time()) {
    $db->deleteShare($hash);
    http_response_code(410);
    die(tr('link_expired'));
}

// Check password
if ($share['password'] && !isset($_SESSION['share_' . $hash])) {
    http_response_code(403);
    die(tr('password_required'));
}

$fullPath = realpath(FILES_PATH . '/' . $share['file_path']);

// Security check
if ($fullPath === false || strpos($fullPath, realpath(FILES_PATH)) !== 0) {
    http_response_code(403);
    die(tr('access_denied'));
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    die(tr('file_not_found'));
}

// Increment download counter
$db->incrementDownload($hash);

$isDir = is_dir($fullPath);

if ($isDir) {
    // === FOLDER - ZIPSTREAM ===
    // if object is a directory - use zipstream to zip it and download
    
    $zipName = basename($share['file_path']) . '.zip';
    
    // Send headers manually
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Create ZipStream
    $zip = new \ZipStream\ZipStream(
        outputName: $zipName,
        sendHttpHeaders: false  // Headers already sent
    );
    
    // Recursively add all files
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $filePath = $file->getPathname();
        $relativePath = substr($filePath, strlen($fullPath) + 1);
        
        if ($file->isFile()) {
            $zip->addFileFromPath($relativePath, $filePath);
        }
    }
    
    $zip->finish();
    
} else {
    // === SINGLE FILE ===
    
    $fileName = basename($share['file_path']);
    $fileSize = filesize($fullPath);
    
    // Check if X-Sendfile is available
    $useXSendfile = false;
    
    if (function_exists('apache_get_modules')) {
        $modules = apache_get_modules();
        $useXSendfile = in_array('mod_xsendfile', $modules);
    } elseif (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false) {
        $useXSendfile = true;
    }
    
    if ($useXSendfile && $fileSize > 100 * 1024 * 1024) {
        // === X-SENDFILE (Apache) or X-ACCEL-REDIRECT (Nginx) ===
        if (function_exists('apache_get_modules')) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . $fileSize);
            header('X-Sendfile: ' . $fullPath);
        } else {
            $internalPath = str_replace(realpath(FILES_PATH), '/internal-files', $fullPath);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('X-Accel-Redirect: ' . $internalPath);
            header('X-Accel-Buffering: no');
        }
        
    } else {
        // === PHP STREAMING ===
        
        $mimeType = mime_content_type($fullPath);
        
        // Handle range requests
        $start = 0;
        $end = $fileSize - 1;
        $length = $fileSize;
        
        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
            $range = str_replace('bytes=', '', $range);
            $range = explode('-', $range);
            $start = intval($range[0]);
            $end = isset($range[1]) && $range[1] !== '' ? intval($range[1]) : $end;
            $length = $end - $start + 1;
            
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        }
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $length);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        // Stream file in chunks
        $fp = fopen($fullPath, 'rb');
        fseek($fp, $start);
        
        $chunkSize = 8 * 1024 * 1024; // 8MB
        $bytesLeft = $length;
        
        while ($bytesLeft > 0 && !feof($fp)) {
            $read = min($chunkSize, $bytesLeft);
            echo fread($fp, $read);
            flush();
            $bytesLeft -= $read;
        }
        
        fclose($fp);
    }
}

// Delete file/link if delete_after_download is enabled
if ($share['delete_after_download']) {
    if ($isDir) {
        deleteDirectory($fullPath);
    } else {
        unlink($fullPath);
    }
    
    $db->deleteShare($hash);
}

exit;

// Helper function to delete directory
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
?>
