# Manual installation (Apache)

**1. Download and extract** the latest release into your web root, for example
`/var/www/html/bakdrop`. Use the release package, not a git clone.

**2. Configure.** Edit `config.php` (see
[Configuration reference](configuration.md) for every setting).

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
HTTPS as you would for any Apache app (see [TLS certificates](tls.md)).
