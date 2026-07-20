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
access on the app, the database directory, and the log directory:

```bash
sudo chown -R www-data:www-data /var/www/html/bakdrop
sudo mkdir -p /var/lib/bakdrop && sudo chown www-data:www-data /var/lib/bakdrop
sudo mkdir -p /var/log/bakdrop && sudo chown www-data:www-data /var/log/bakdrop
```

The `/var/log/bakdrop` step matters: `/var/log` itself is owned by root, so
without this the app cannot write its audit log and the writes fail silently.

The **files directory** (`/bakdrop` by default) is different. Two sides need it:
whoever puts files there (you, another admin, or a backup agent), and `www-data`,
which reads them out and deletes them for "delete after download" and scheduled
cleanup. Give both a shared group:

```bash
sudo groupadd bakdrop
sudo usermod -aG bakdrop www-data      # the app
sudo usermod -aG bakdrop "$USER"       # you, and every other admin who uploads

sudo mkdir -p /bakdrop
sudo chgrp -R bakdrop /bakdrop
sudo chmod -R g=rwX,o= /bakdrop
sudo chmod g+s /bakdrop
```

Everyone in the group uploads as themselves, nobody outside it gets in, and
Bakdrop serves and deletes all of it regardless of who added it.

Group membership is only read at login, so each admin has to log out and back in
(a fresh SSH session, not a new terminal tab) before they can write. `id` should
list `bakdrop`; if it does not, the session is still the old one.

!!! warning "This is not enough on its own"
    Tools that write files with the permissions of the source system undo the
    above. A backup restore is the common case: an agent running as root drops a
    root-owned, `0600` tree that Bakdrop cannot read, so shares show up as empty
    folders. **Step 6 adds a cron job that keeps this in order automatically** -
    do not skip it.

??? note "Alternative: who is allowed to upload"
    The setup above is the middle ground. The other two are one change each:

    **Only root** - do not add anyone to the `bakdrop` group. `www-data` stays in
    it, so the app still works, but writing to `/bakdrop` then needs `sudo`.

    **Anyone on the host** - make the tree world-writable by setting `BAKDROP_MODE`
    in the cron job from step 6:

    ```
    * * * * * BAKDROP_MODE="g=rwX,o=rwX" flock -n /run/lock/bakdrop-fixperms /usr/local/sbin/bakdrop-fixperms
    ```

    This lets any local user read and modify the shared files, so only do it on a
    host where that is acceptable.

**4. Set up the Apache site.** Serve the app directory through a virtual host.
`mod_php` is enabled automatically by the PHP package, and `mod_ssl` is enabled by
Certbot in the next step, so there is nothing to enable by hand here.

Create a virtual host, for example `/etc/apache2/sites-available/bakdrop.conf`:

```apache
<VirtualHost *:80>
    ServerName bakdrop.example.com
    DocumentRoot /var/www/html/bakdrop

    <Directory /var/www/html/bakdrop>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Point `DocumentRoot` at the folder you extracted in step 1 and set `ServerName` to
your domain. `AllowOverride All` lets the optional `.htaccess` (below) take effect.

Enable the site and reload Apache:

```bash
sudo a2ensite bakdrop
sudo a2dissite 000-default   # optional: disable the default welcome site
sudo systemctl reload apache2
```

> **RHEL / Fedora / Rocky / AlmaLinux:** there is no `a2ensite`. Put the same
> `<VirtualHost>` block in a file under `/etc/httpd/conf.d/` (e.g. `bakdrop.conf`)
> and reload with `sudo systemctl reload httpd`. See the
> [Apache virtual host docs](https://httpd.apache.org/docs/current/vhosts/) for
> details.

**5. Enable HTTPS.** The simplest way is Certbot, which obtains a trusted
certificate **and** adds the HTTP-to-HTTPS redirect for you in one command:

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d bakdrop.example.com
```

See [TLS certificates](../tls.md) for the full picture and other options (bringing
your own certificate, or terminating TLS in a reverse proxy).

**6. Set up the background jobs.** Bakdrop needs two, and they run as different
users.

**Cleanup**, hourly, as the web server user so the database keeps consistent
ownership:

```bash
sudo crontab -u www-data -e
```

```
0 * * * * php /var/www/html/bakdrop/cleanup.php >> /var/log/bakdrop/cleanup.log 2>&1
```

Without this, expired links stay live and scheduled file deletion never happens.

**Permissions**, every minute, as root. This is what makes restored files show up
on their own. Install the script that ships with the release, then add the job:

```bash
sudo install -m 0755 -o root -g root \
    /var/www/html/bakdrop/bakdrop-fixperms /usr/local/sbin/bakdrop-fixperms
sudo crontab -e
```

```
* * * * * flock -n /run/lock/bakdrop-fixperms /usr/local/sbin/bakdrop-fixperms
```

It resets group ownership and mode across the files directory, so anything that
lands there becomes readable to Bakdrop within a minute, whoever wrote it and with
whatever permissions. It is idempotent and cheap: metadata only, roughly 0.7 s per
100k files. `flock` keeps two runs from overlapping on a very large tree.

If you changed `FILES_PATH` in step 2, pass it as an argument
(`... /usr/local/sbin/bakdrop-fixperms /srv/files`). The script must run as root:
only root can reset ownership of files written by someone else. Nothing else gains
privileges from it - the web server never invokes it.

**7. Create the first admin:** open `https://your-domain-or-ip/setup.php` and
follow the prompts.

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
