# Bakdrop

![Bakdrop]("assets/logo.png")

*Work in progress*

Simple file sharing web app for temporary file distribution.

Bakdrop (backup drop) is designed for administrators who need to share files temporarily with end users.

Main scenario is to share data restored from backups to end users by self-expiring link with option do auto-delete data after downloading.

## Features

- **Temporary share links** - Generate random links with optional expiration
- **Password protection** - Optionally protect links with passwords
- **Auto-deletion** - Files can be automatically deleted after download
- **Folder sharing** - Share entire folders (streamed as ZIP)
- **Multi-language** - English and Polish UI
- **Themes** - Dark and Light modes
- **Efficient streaming** - Large file support with chunked streaming and Range requests
- **Multi-user support** - Each user has their own isolated folder (but proper user management isn't completed yet)

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
define('FILES_PATH', '/your_base_data_directory');                    // Root directory for files
define('BASE_URL', 'http://IP_of_your_server');       // Your server URL
```

### 4. Set permissions

```bash
# Make manage.php executable
chmod +x manage.php

# Ensure web server can write to database
chown www-data:www-data .
chmod 755 .
```

### 5. Initial setup

Navigate to `http://yourserver.com/setup.php` in your browser and create your first admin account.

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
3. Download file or folder

## Security 

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
