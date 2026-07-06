<?php
require_once 'helpers.php';
require_once 'db.php';

// Composer autoload for ZipStream
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$lang = loadLanguage(DEFAULT_LANG);

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
    // Keep the row if a file deletion is still scheduled - cleanup.php needs it
    if (!$share['file_delete_at']) {
        $db->deleteShare($hash);
    }
    http_response_code(410);
    die(tr('link_expired'));
}

// Check password
if ($share['password'] && !isset($_SESSION['share_' . $hash])) {
    $basicAuthPassword = $_SERVER['PHP_AUTH_PW'] ?? '';
    if (!$basicAuthPassword || !password_verify($basicAuthPassword, $share['password'])) {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="Bakdrop"');
        die(tr('password_required'));
    }
}

// Release the session lock - otherwise every other request in the same
// browser session (admin panel, second download) waits until this transfer ends
session_write_close();

$fullPath = realpath(FILES_PATH . '/' . $share['file_path']);
$realFilesPath = realpath(FILES_PATH);

// Security check
if ($fullPath === false || $realFilesPath === false || ($fullPath !== $realFilesPath && strpos($fullPath, $realFilesPath . '/') !== 0)) {
    http_response_code(403);
    die(tr('access_denied'));
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    die(tr('file_not_found'));
}

// Transfers can take hours - the default time limit would kill them mid-stream
set_time_limit(0);

$isDir = is_dir($fullPath);
$completed = false; // full content delivered - only then delete_after_download may run

// Header-safe filename (quotes/CR/LF would break the Content-Disposition header)
$fileName = str_replace(['"', "\r", "\n"], '', basename($share['file_path']));

if ($isDir) {
    // === FOLDER - ZIPSTREAM ===
    // Generated on the fly, so size is unknown and resuming is not possible

    $db->incrementDownload($hash);

    $zipName = $fileName . '.zip';

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Cache-Control: no-cache, must-revalidate');

    // STORE instead of DEFLATE: backups are usually compressed already and
    // deflate would burn CPU for the whole (possibly huge) transfer
    $zip = new \ZipStream\ZipStream(
        outputName: $zipName,
        sendHttpHeaders: false,  // Headers already sent
        defaultCompressionMethod: \ZipStream\CompressionMethod::STORE,
        flushOutput: true,
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

    $completed = !connection_aborted();

} else {
    // === SINGLE FILE - PHP STREAMING with range support ===

    $fileSize = filesize($fullPath);

    $start = 0;
    $end = $fileSize - 1;

    if (isset($_SERVER['HTTP_RANGE'])) {
        // Only a single "bytes=start-end" range is supported; malformed or
        // multi-range headers are ignored and the full file is sent (RFC 9110 allows this)
        if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                // Suffix form "bytes=-N": last N bytes of the file
                $suffix = (int)$m[2];
                $start = max(0, $fileSize - $suffix);
                if ($suffix === 0) {
                    $start = $fileSize; // unsatisfiable, handled below
                }
            } else {
                $start = (int)$m[1];
                $end = ($m[2] === '') ? $fileSize - 1 : min((int)$m[2], $fileSize - 1);
            }

            if ($start > $end || $start >= $fileSize) {
                http_response_code(416);
                header('Content-Range: bytes */' . $fileSize);
                exit;
            }

            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        }
    }

    $length = $end - $start + 1;

    // Count once per download attempt - resumed range requests don't recount
    if ($start === 0) {
        $db->incrementDownload($hash);
    }

    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $length);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-cache, must-revalidate');

    // Stream file in chunks
    $fp = fopen($fullPath, 'rb');
    fseek($fp, $start);

    $chunkSize = 8 * 1024 * 1024; // 8MB
    $bytesLeft = $length;

    while ($bytesLeft > 0 && !feof($fp) && !connection_aborted()) {
        $read = min($chunkSize, $bytesLeft);
        echo fread($fp, $read);
        flush();
        $bytesLeft -= $read;
    }

    fclose($fp);

    // Complete only when the whole file went out in one response - a partial
    // (range) response must never trigger auto-delete
    $completed = ($start === 0 && $end === $fileSize - 1 && $bytesLeft <= 0 && !connection_aborted());
}

// Delete file/link if delete_after_download is enabled
if ($share['delete_after_download'] && $completed) {
    if ($isDir) {
        deleteDirectory($fullPath);
    } else {
        unlink($fullPath);
    }

    $db->deleteShare($hash);
}

exit;

?>
