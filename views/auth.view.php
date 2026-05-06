<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakdrop - <?= t('login') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h1>
                <img src="assets/logo.png" alt="Bakdrop" class="login-logo">
            </h1>
            <h2><?= t('login_prompt') ?></h2>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><?= t('username') ?>:</label>
                    <input type="text" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label><?= t('password') ?>:</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-actions" style="justify-content: center;">
                    <button type="submit" class="btn btn-primary"><?= t('login') ?></button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
