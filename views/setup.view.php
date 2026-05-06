<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakdrop - <?= t('setup_title') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h1>Bakdrop - <?= t('setup_heading') ?></h1>
            <p><?= t('setup_description') ?></p>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><?= t('username') ?>:</label>
                    <input type="text" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label><?= t('password_min_length') ?>:</label>
                    <input type="password" name="password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label><?= t('language') ?>:</label>
                    <select name="language">
                        <option value="en"><?= t('english') ?></option>
                        <option value="pl"><?= t('polish') ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= t('theme') ?>:</label>
                    <select name="theme">
                        <option value="dark"><?= t('dark') ?></option>
                        <option value="light"><?= t('light') ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= t('files_path_label') ?>:</label>
                    <input type="text" name="allowed_path" placeholder="<?= tr('files_path_placeholder') ?>">
                    <small><?= t('files_path_current') ?>: <?= htmlspecialchars(FILES_PATH) ?></small>
                </div>
                
                <button type="submit" class="btn btn-primary"><?= t('create_account') ?></button>
            </form>
        </div>
    </div>
</body>
</html>
