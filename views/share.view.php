<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakdrop - <?= t('download_file') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body data-theme="<?= DEFAULT_THEME ?>">
    <div class="container">
        <div class="download-box">
            <img src="assets/logo.png" alt="Bakdrop" style="height: 80px; margin: 0 auto 20px; display: block;">
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php elseif ($passwordRequired && !$passwordValid): ?>
                <h2><?= t('password_protected') ?></h2>
                <form method="POST">
                    <div class="form-group">
                        <label><?= t('password') ?>:</label>
                        <input type="password" name="password" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= t('unlock') ?></button>
                </form>
            <?php elseif ($canDownload): ?>
                <?php
                $fileName = basename($share['file_path']);
                $fullPath = realpath(FILES_PATH . '/' . $share['file_path']);
                $isDir = is_dir($fullPath);
                $fileSize = $isDir ? 0 : filesize($fullPath);
                ?>
                
                <div class="file-info">
                    <div class="file-icon"><?= $isDir ? '📁' : '📄' ?></div>
                    <h2><?= htmlspecialchars($fileName) ?></h2>
                    <?php if (!$isDir): ?>
                        <p class="file-size"><?= formatBytes($fileSize) ?></p>
                    <?php else: ?>
                        <p class="file-size"><?= t('folder_will_be_zipped') ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if ($share['expires_at']): ?>
                    <p class="expiry-info">
                        <?= t('link_expires') ?>: <?= date('Y-m-d H:i', $share['expires_at']) ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($share['delete_after_download']): ?>
                    <p class="warning">
                        <?= t('warning_delete_after') ?>
                    </p>
                <?php endif; ?>
                
                <a href="download.php?h=<?= htmlspecialchars($hash) ?>" class="btn btn-primary btn-large">
                    <?= t('download_file') ?>
                </a>
                
                <p class="download-info">
                    <?= t($isDir ? 'click_to_download_folder' : 'click_to_download') ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
