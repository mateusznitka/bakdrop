# Bakdrop

Simple and secure file sharing application for backup restoration and temporary file distribution.

Bakdrop (backup drop) is designed for system administrators who need to share backup files temporarily with end users.

## Features

- **Multi-user support** - Each user has their own isolated folder
- **Temporary share links** - Generate random links with optional expiration
- **Password protection** - Optionally protect links with passwords
- **Auto-deletion** - Files can be automatically deleted after download
- **Folder sharing** - Share entire folders (streamed as ZIP)
- **Multi-language** - English and Polish UI
- **Themes** - Dark and Light modes
- **Efficient streaming** - Large file support with chunked streaming and Range requests

## Requirements

- PHP 8.4+
- SQLite3
- Apache/Nginx
- Composer (for ZipStream dependency)

## Installation

### 1. Clone or download

```bash
git clone https://github.com/yourusername/bakdrop.git
cd bakdrop
```

Or extract the ZIP file to your web server directory.

### 2. Install dependencies

The application requires ZipStream library for folder downloads. Install it using Composer:

```bash
composer install
```

**Note:** `composer.json` and `composer.lock` are included in the package. This will install:
- `maennchen/zipstream-php` v3.2

If you don't have Composer installed, get it from [getcomposer.org](https://getcomposer.org/)

### 3. Configure

Edit `config.php`:

```php
define('FILES_PATH', '/fsr');                    // Root directory for files
define('BASE_URL', 'http://10.10.99.251');       // Your server URL
```

### 4. Set permissions

```bash
# Make manage.php executable
chmod +x manage.php

# Ensure web server can write to database
chown www-data:www-data .
chmod 755 .
```

### 5. Setup cron (for auto-delete files)

Bakdrop can automatically delete files after a specified time. To enable this feature, setup a cron job:

```bash
# Edit crontab
crontab -e

# Add this line (runs every hour):
0 * * * * php /var/www/html/cleanup.php >> /var/log/bakdrop-cleanup.log 2>&1
```

**Note:** Auto-delete is optional. Without cron:
- Link expiration works normally (checked when user downloads)
- "Delete after download" works normally
- "Auto-delete file after X hours" will NOT work (requires cron)

### 6. Initial setup

Navigate to `http://yourserver.com/setup.php` in your browser and create your first admin account.

## User Management

All user management is done via CLI for security. The web UI has no user management interface.

### Create a user

```bash
php manage.php create username password path [language] [theme]
```

**Examples:**

```bash
# User with root access (sees all FILES_PATH)
php manage.php create admin SecurePass123 '' en dark

# User with restricted access to /finance folder
php manage.php create finance MyPass456 finance pl light

# User with access to nested folder
php manage.php create backup-ops P@ssw0rd backups/team1 en dark
```

**Parameters:**
- `username` - Unique username
- `password` - Minimum 8 characters
- `path` - Relative to FILES_PATH (use `''` for root access)
- `language` - `en` or `pl` (default: `en`)
- `theme` - `dark` or `light` (default: `dark`)

### List all users

```bash
php manage.php list
```

Output:
```
ID    Username            Path                          Lang    Theme     Created
-------------------------------------------------------------------------------------------------
1     admin               / (root)                      en      dark      2024-04-30 10:00
2     finance             finance                       pl      light     2024-04-30 10:15
```

### Delete a user

```bash
php manage.php delete username
```

**Note:** 
- Cannot delete the last user
- User's shares are preserved (creator shown as "Deleted user")
- Requires confirmation

### Change user's folder path

```bash
php manage.php set-path username new-path
```

**Example:**

```bash
# Give user access to root
php manage.php set-path finance ''

# Restrict user to specific folder
php manage.php set-path admin backups/critical
```

## Usage

### For Administrators

1. **Login** - Navigate to `http://yourserver.com/auth.php`
2. **Browse files** - Navigate through your assigned folder
3. **Create share link**:
   - Click "Share" next to any file or folder
   - Optionally set expiration (1h, 24h, 7 days)
   - Optionally add password protection
   - Optionally enable auto-delete after download
4. **Copy link** - Share the generated link with end users
5. **Manage shares** - View active shares, download counts, and delete links

### For End Users

End users receive a share link (e.g., `http://yourserver.com/share.php?h=abc123def456`):

1. Click the link
2. Enter password if required
3. Download file or folder (auto-zipped)

## User Settings

Users can customize their experience via **Settings** dropdown:

- **Preferences** - Change language and theme
- **Change Password** - Update account password

Settings are stored per-user and persist across sessions.

## Security Features

- Per-user folder isolation (path traversal protection)
- Password hashing (bcrypt)
- Prepared SQL statements (SQL injection protection)
- Session-based authentication
- XSS protection (htmlspecialchars on all outputs)
- Random 16-character share hashes
- Automatic cleanup of expired links
- No web UI for user management (SSH access required)

## File Streaming

### Small files (<100MB)
- Streamed in 8MB chunks
- Support for Range requests (resumable downloads)

### Large files (>100MB)
- X-Sendfile (Apache) or X-Accel-Redirect (Nginx) if available
- Falls back to PHP streaming

### Folders
- On-the-fly ZIP creation using ZipStream
- No temporary files created
- Memory-efficient streaming

## Database Structure

### users table
```sql
id INTEGER PRIMARY KEY
username TEXT UNIQUE
password TEXT (bcrypt hash)
language TEXT (en/pl)
theme TEXT (dark/light)
allowed_path TEXT (relative to FILES_PATH)
created_at INTEGER (unix timestamp)
```

### shares table
```sql
id INTEGER PRIMARY KEY
hash TEXT UNIQUE (16 chars)
file_path TEXT
password TEXT (bcrypt hash, nullable)
expires_at INTEGER (unix timestamp, nullable)
delete_after_download INTEGER (0/1)
download_count INTEGER
created_at INTEGER (unix timestamp)
created_by INTEGER (foreign key to users)
```

## Translations

Translations are stored in `lang/` folder:
- `lang/en.json` - English
- `lang/pl.json` - Polish

To add a new language:
1. Copy `lang/en.json` to `lang/xx.json`
2. Translate all strings
3. Add language option to user preferences in `admin.php`

## License

MIT License - See LICENSE file

## Contributing

1. Fork the repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

## Support

For issues and questions, please use GitHub Issues.
