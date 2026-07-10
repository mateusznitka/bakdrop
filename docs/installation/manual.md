# Manual installation (Apache)

**1. Download and extract** the latest release from <https://github.com/mateusznitka/bakdrop/releases> into your web root, for example
`/var/www/html/bakdrop`. Use the release package, not
a git clone - the release ships with dependencies already bundled.

**2. Configure.** Open `config.php`. Near the top you will see exactly these lines:

```php
define('DB_PATH',      getenv('DB_PATH')      ?: '/var/lib/bakdrop/shares.db');
define('FILES_PATH',   getenv('FILES_PATH')   ?: '/bakdrop');
define('BASE_URL',     getenv('BASE_URL')     ?: 'https://your-domain-or-ip');
define('DEFAULT_LANG',  getenv('DEFAULT_LANG')  ?: 'en');
define('DEFAULT_THEME', getenv('DEFAULT_THEME') ?: 'dark');
define('AUDIT_LOG',    getenv('AUDIT_LOG')    ?: '/var/log/bakdrop/audit.log');
```

On the end of each line, there are values you can edit.

**Example.** The `BASE_URL` line ships like this:

```php
define('BASE_URL',     getenv('BASE_URL')     ?: 'https://your-domain-or-ip');
```

To point Bakdrop at your real address, you change **only** the quoted string at the
end, so the line becomes:

```php
define('BASE_URL',     getenv('BASE_URL')     ?: 'https://files.example.com');
```

**Which ones do I need to change?**

- **`BASE_URL`** - **required**. It builds the download links; a wrong value means
  wrong links.
- **`FILES_PATH`** - change if you want to share files from different directory than default /bakdrop.
- `DB_PATH`, `AUDIT_LOG`, `DEFAULT_LANG`, `DEFAULT_THEME`, `TZ` - optional, the
  defaults are fine. See the [Configuration reference](../configuration.md).

**3. Set permissions.** The web server user (`www-data`) needs read and write
access on the app, the database directory, the log directory, and the files
directory:

```bash
sudo chown -R www-data:www-data /var/www/html/bakdrop
sudo mkdir -p /var/lib/bakdrop && sudo chown www-data:www-data /var/lib/bakdrop
sudo mkdir -p /var/log/bakdrop && sudo chown www-data:www-data /var/log/bakdrop
sudo mkdir -p /bakdrop && sudo chown www-data:www-data /bakdrop
```

The `/var/log/bakdrop` step matters: `/var/log` itself is owned by root, so
without this the app cannot write its audit log and the writes fail silently.

**4. Set up automatic cleanup.** Add an hourly cron job, running as the web
server user so the database keeps consistent ownership:

```bash
sudo crontab -u www-data -e
```

```
0 * * * * php /var/www/html/bakdrop/cleanup.php >> /var/log/bakdrop/cleanup.log 2>&1
```

Without this, scheduled file deletion never happens. Adjust the interval to your
needs.

**5. Create the first admin:** open `http://your-domain-or-ip/setup.php`. Set up
HTTPS as you would for any Apache app (see [TLS certificates](../tls.md)).

## Optional: block direct access to internal scripts

The Docker image already denies HTTP access to Bakdrop's internal PHP (the view
templates and the `manage.php` / `cleanup.php` / `config.php` scripts). On a manual
install you can do the same with an `.htaccess` in the app root (requires
`AllowOverride All` for that directory). It is optional - the sensitive data
(database, audit log) already lives outside the web root, and the CLI scripts
refuse to run over HTTP - but it is good practice:

```apache
<FilesMatch "\.view\.php$">
    Require all denied
</FilesMatch>
<FilesMatch "^(manage|cleanup|config)\.php$">
    Require all denied
</FilesMatch>
```
