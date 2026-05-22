<!DOCTYPE html>
<html lang="<?= $user['language'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakdrop - <?= t('admin_panel') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body data-theme="<?= htmlspecialchars($user['theme']) ?>">
    <div class="container">
        <div class="header">
            <h1>
                <img src="assets/logo.png" alt="Bakdrop" class="header-logo">
            </h1>
            <div class="header-actions">
                <!-- Settings dropdown -->
                <div class="dropdown">
                    <button class="btn btn-secondary" onclick="toggleDropdown(event)">⚙️ <?= t('settings') ?></button>
                    <div class="dropdown-content" id="settingsDropdown">
                        <div class="user-info">
                            <strong><?= htmlspecialchars(getCurrentUser()) ?></strong>
                        </div>
                        <a href="#" onclick="openPreferencesModal(); return false;"><?= t('preferences') ?></a>
                        <a href="#" onclick="openPasswordModal(); return false;"><?= t('change_password') ?></a>
                    </div>
                </div>
                
                <a href="?logout=1" class="btn btn-secondary"><?= t('logout') ?></a>
            </div>
        </div>
        
        <div class="content">
            <!-- Files list -->
            <div class="section">
                <!-- Breadcrumb navigation -->
                <div class="breadcrumb">
                    <a href="index.php"><?= t('home') ?></a>
                    <?php
                    if ($currentPath) {
                        $parts = explode('/', $currentPath);
                        $pathSoFar = '';
                        foreach ($parts as $part) {
                            $pathSoFar .= ($pathSoFar ? '/' : '') . $part;
                            echo ' / <a href="index.php?path=' . urlencode($pathSoFar) . '">' . htmlspecialchars($part) . '</a>';
                        }
                    }
                    ?>
                </div>
                
                <h2><?= t('files_in_directory') ?></h2>
                <table class="file-table">
                    <thead>
                        <tr>
                            <th><?= t('name') ?></th>
                            <th><?= t('size') ?></th>
                            <th><?= t('modified') ?></th>
                            <th><?= t('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($currentPath): ?>
                        <tr>
                            <td colspan="4">
                                <a href="index.php?path=<?= urlencode(dirname($currentPath)) ?>">📁 ..</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['is_dir']): ?>
                                    <a href="index.php?path=<?= urlencode($item['path']) ?>">
                                        📁 <?= htmlspecialchars($item['name']) ?>
                                    </a>
                                <?php else: ?>
                                    📄 <?= htmlspecialchars($item['name']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['is_dir'] ? '-' : formatBytes($item['size']) ?></td>
                            <td><?= date('Y-m-d H:i', $item['modified']) ?></td>
                            <td>
                                <button class="btn btn-small btn-primary" onclick="createShare('<?= htmlspecialchars($item['path'], ENT_QUOTES) ?>', <?= $item['is_dir'] ? 'true' : 'false' ?>)">
                                    <?= t('share') ?>
                                </button>
                                <button class="btn btn-small btn-danger" onclick="deleteFile('<?= htmlspecialchars($item['path'], ENT_QUOTES) ?>', <?= $item['is_dir'] ? 'true' : 'false' ?>, '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>')">
                                    <?= t('delete') ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #666;"><?= t('no_files') ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Active shares -->
            <div class="section">
                <h2><?= t('active_shares') ?></h2>
                <table class="file-table">
                    <thead>
                        <tr>
                            <th><?= t('file') ?></th>
                            <th><?= t('password') ?></th>
                            <th><?= t('created_by') ?></th>
                            <th><?= t('expires') ?></th>
                            <th><?= t('downloads') ?></th>
                            <th><?= t('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shares as $share): ?>
                        <tr>
                            <td><?= htmlspecialchars(basename($share['file_path'])) ?></td>
                            <td><?= $share['password'] ? t('yes') : t('no') ?></td>
                            <td><?= htmlspecialchars($share['created_by_name'] ?? tr('deleted_user')) ?></td>
                            <td>
                                <?= $share['expires_at'] ? date('Y-m-d H:i', $share['expires_at']) : t('never') ?>
                            </td>
                            <td><?= $share['download_count'] ?></td>
                            <td>
                                <button class="btn btn-small btn-primary" onclick="copyText('<?= BASE_URL ?>/share.php?h=<?= $share['hash'] ?>', this)">
                                    <?= t('copy_link') ?>
                                </button>
                                <button class="btn btn-small btn-primary" onclick="copyText('<?= BASE_URL ?>/download.php?h=<?= $share['hash'] ?>', this)">
                                    <?= t('copy_direct_link') ?>
                                </button>
                                <button class="btn btn-small btn-danger" onclick="deleteShare('<?= $share['hash'] ?>')">
                                    <?= t('delete_link') ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($shares)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #666;"><?= t('no_active_shares') ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal: Create share -->
    <div id="shareModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2><?= t('create_share') ?></h2>
            
            <form id="shareForm">
                <input type="hidden" id="sharePath" name="path">
                <input type="hidden" id="shareIsDir" name="is_dir">
                
                <p><?= t('sharing_file') ?>: <strong id="shareFileName"></strong></p>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="setExpiry" onchange="toggleExpiry()">
                        <?= t('set_expiration') ?>
                    </label>
                    <div id="expiryOptions" style="display: none; margin-left: 20px;">
                        <label><?= t('expiration_time') ?>:</label>
                        <select id="expiryTime" name="expiry">
                            <option value="3600"><?= t('1_hour') ?></option>
                            <option value="86400"><?= t('24_hours') ?></option>
                            <option value="604800"><?= t('7_days') ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="setFileDelete" onchange="toggleFileDelete()">
                        <?= t('auto_delete_file') ?>
                    </label>
                    <div id="fileDeleteOptions" style="display: none; margin-left: 20px;">
                        <label><?= t('delete_file_after') ?>:</label>
                        <select id="fileDeleteTime" name="file_delete_after">
                            <option value="3600"><?= t('1_hour') ?></option>
                            <option value="86400"><?= t('24_hours') ?></option>
                            <option value="604800"><?= t('7_days') ?></option>
                        </select>
                        <small style="color: var(--text-secondary);"><?= t('file_delete_warning') ?></small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="setPassword" onchange="togglePassword()">
                        <?= t('protect_with_password') ?>
                    </label>
                    <div id="passwordOptions" style="display: none; margin-left: 20px;">
                        <label><?= t('password') ?>:</label>
                        <input type="text" id="sharePassword" name="password" placeholder="<?= tr('enter_password') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="deleteAfter" name="delete_after" value="1">
                        <?= t('delete_after_download') ?>
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= t('create_link') ?></button>
                </div>
            </form>
            
            <div id="shareResult" style="display: none; margin-top: 20px;">
                <h3><?= t('link_created') ?></h3>
                <p style="margin: 0 0 4px; font-size: 12px; color: var(--text-secondary);"><?= t('browser_link') ?></p>
                <div class="link-with-copy" style="margin-bottom: 12px;">
                    <input type="text" id="generatedLink" readonly onclick="this.select()"
                           style="padding: 10px; font-family: monospace; font-size: 12px;">
                    <button class="btn btn-primary btn-copy" onclick="copyToClipboard('generatedLink', this)">
                        <?= t('copy_link') ?>
                    </button>
                </div>
                <p style="margin: 0 0 4px; font-size: 12px; color: var(--text-secondary);"><?= t('direct_link') ?></p>
                <div class="link-with-copy" style="margin-bottom: 12px;">
                    <input type="text" id="generatedDirectLink" readonly onclick="this.select()"
                           style="padding: 10px; font-family: monospace; font-size: 12px;">
                    <button class="btn btn-primary btn-copy" onclick="copyToClipboard('generatedDirectLink', this)">
                        <?= t('copy_link') ?>
                    </button>
                </div>
                <div id="cliCommands" style="display: none;">
                    <p style="margin: 0 0 4px; font-size: 12px; color: var(--text-secondary);"><?= t('cli_commands') ?></p>
                    <div class="link-with-copy" style="margin-bottom: 6px;">
                        <input type="text" id="curlCommand" readonly onclick="this.select()"
                               style="padding: 10px; font-family: monospace; font-size: 12px;">
                        <button class="btn btn-primary btn-copy" onclick="copyToClipboard('curlCommand', this)">
                            <?= t('copy_link') ?>
                        </button>
                    </div>
                    <div class="link-with-copy">
                        <input type="text" id="wgetCommand" readonly onclick="this.select()"
                               style="padding: 10px; font-family: monospace; font-size: 12px;">
                        <button class="btn btn-primary btn-copy" onclick="copyToClipboard('wgetCommand', this)">
                            <?= t('copy_link') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal: Preferences -->
    <div id="preferencesModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePreferencesModal()">&times;</span>
            <h2><?= t('preferences') ?></h2>
            
            <form id="preferencesForm">
                <div class="form-group">
                    <label><?= t('language') ?>:</label>
                    <select id="prefLanguage" name="language">
                        <option value="en" <?= $user['language'] === 'en' ? 'selected' : '' ?>><?= t('english') ?></option>
                        <option value="pl" <?= $user['language'] === 'pl' ? 'selected' : '' ?>><?= t('polish') ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?= t('theme') ?>:</label>
                    <select id="prefTheme" name="theme">
                        <option value="dark" <?= $user['theme'] === 'dark' ? 'selected' : '' ?>><?= t('dark') ?></option>
                        <option value="light" <?= $user['theme'] === 'light' ? 'selected' : '' ?>><?= t('light') ?></option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closePreferencesModal()"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= t('save_changes') ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal: Change password -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePasswordModal()">&times;</span>
            <h2><?= t('change_password') ?></h2>
            
            <form id="passwordForm">
                <div class="form-group">
                    <label><?= t('current_password') ?>:</label>
                    <input type="password" id="currentPassword" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label><?= t('new_password_min') ?>:</label>
                    <input type="password" id="newPassword" name="new_password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label><?= t('confirm_password') ?>:</label>
                    <input type="password" id="confirmPassword" required minlength="8">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closePasswordModal()"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= t('change_password') ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const lang = <?= json_encode($lang) ?>;
        
        // Toggle settings dropdown
        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('settingsDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('settingsDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        });
        
        function createShare(path, isDir) {
            document.getElementById('sharePath').value = path;
            document.getElementById('shareIsDir').value = isDir ? '1' : '0';
            document.getElementById('shareFileName').textContent = path.split('/').pop();
            document.getElementById('shareModal').style.display = 'block';
            document.getElementById('shareResult').style.display = 'none';
            document.getElementById('shareForm').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('shareModal').style.display = 'none';
            document.getElementById('shareForm').reset();
            document.getElementById('shareForm').style.display = 'block';
            document.getElementById('shareResult').style.display = 'none';
            // Reload page to show newly created share in the list
            location.reload();
        }
        
        function toggleExpiry() {
            document.getElementById('expiryOptions').style.display = 
                document.getElementById('setExpiry').checked ? 'block' : 'none';
        }
        
        function toggleFileDelete() {
            document.getElementById('fileDeleteOptions').style.display = 
                document.getElementById('setFileDelete').checked ? 'block' : 'none';
        }
        
        function togglePassword() {
            document.getElementById('passwordOptions').style.display = 
                document.getElementById('setPassword').checked ? 'block' : 'none';
        }
        
        document.getElementById('shareForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('path', document.getElementById('sharePath').value);
            formData.append('is_dir', document.getElementById('shareIsDir').value);
            
            if (document.getElementById('setExpiry').checked) {
                formData.append('expiry', document.getElementById('expiryTime').value);
            }
            
            if (document.getElementById('setFileDelete').checked) {
                formData.append('file_delete_after', document.getElementById('fileDeleteTime').value);
            }
            
            if (document.getElementById('setPassword').checked) {
                formData.append('password', document.getElementById('sharePassword').value);
            }
            
            if (document.getElementById('deleteAfter').checked) {
                formData.append('delete_after', '1');
            }
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('shareForm').style.display = 'none';
                document.getElementById('shareResult').style.display = 'block';
                document.getElementById('generatedLink').value = result.link;
                document.getElementById('generatedDirectLink').value = result.direct_link;

                if (result.has_password) {
                    const pwd = document.getElementById('sharePassword').value;
                    document.getElementById('cliCommands').style.display = 'block';
                    document.getElementById('curlCommand').value = `curl -u ":${pwd}" -JO "${result.direct_link}"`;
                    document.getElementById('wgetCommand').value = `wget --content-disposition --user="" --password="${pwd}" "${result.direct_link}"`;
                } else {
                    document.getElementById('cliCommands').style.display = 'block';
                    document.getElementById('curlCommand').value = `curl -JO "${result.direct_link}"`;
                    document.getElementById('wgetCommand').value = `wget --content-disposition "${result.direct_link}"`;
                }

                // Don't auto-close modal - user will close it manually or click outside
            } else {
                alert(lang.error + ': ' + result.error);
            }
        });
        
        function flashCopied(btn) {
            btn.style.width = btn.offsetWidth + 'px';
            const original = btn.textContent;
            btn.textContent = '✓';
            btn.disabled = true;
            setTimeout(() => {
                btn.textContent = original;
                btn.disabled = false;
                btn.style.width = '';
            }, 1500);
        }

        function copyToClipboard(elementId, btn) {
            const input = document.getElementById(elementId);
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            if (btn) flashCopied(btn);
        }

        function copyText(text, btn) {
            navigator.clipboard.writeText(text).catch(() => {
                const tmp = document.createElement('textarea');
                tmp.value = text;
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                document.body.removeChild(tmp);
            });
            if (btn) flashCopied(btn);
        }
        
        async function deleteShare(hash) {
            if (!confirm(lang.confirm_delete_share)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('hash', hash);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload(); // Reload to show updated list
            } else {
                alert(lang.error + ': ' + result.error);
            }
        }
        
        async function deleteFile(path, isDir, name) {
            const fileType = isDir ? lang.folder : lang.file;
            const confirmMsg = lang.confirm_delete_file.replace('{type}', fileType).replace('{name}', name);
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_file');
            formData.append('path', path);
            formData.append('is_dir', isDir ? '1' : '0');
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload(); // Reload to show updated file list
            } else {
                alert(lang.error + ': ' + result.error);
            }
        }
        
        function openPreferencesModal() {
            document.getElementById('preferencesModal').style.display = 'block';
        }
        
        function closePreferencesModal() {
            document.getElementById('preferencesModal').style.display = 'none';
        }
        
        document.getElementById('preferencesForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'save_preferences');
            formData.append('language', document.getElementById('prefLanguage').value);
            formData.append('theme', document.getElementById('prefTheme').value);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert(lang.error + ': ' + result.error);
            }
        });
        
        function openPasswordModal() {
            document.getElementById('passwordModal').style.display = 'block';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.getElementById('passwordForm').reset();
        }
        
        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                alert(lang.passwords_dont_match);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', document.getElementById('currentPassword').value);
            formData.append('new_password', newPassword);
            
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert(lang.password_changed);
                closePasswordModal();
            } else {
                alert(lang.error + ': ' + result.error);
            }
        });
        
        // Close modals on outside click
        window.onclick = function(event) {
            const shareModal = document.getElementById('shareModal');
            const passwordModal = document.getElementById('passwordModal');
            const preferencesModal = document.getElementById('preferencesModal');
            
            if (event.target == shareModal) {
                closeModal();
            }
            if (event.target == passwordModal) {
                closePasswordModal();
            }
            if (event.target == preferencesModal) {
                closePreferencesModal();
            }
        }
    </script>
</body>
</html>
