![Bakdrop](assets/logo.png)

Bakdrop is a simple web app for sharing files from a server with end users through unique, self-expiring download links.

While it can be used in any case when you need to share files through web, it was built mainly for one specific job: sharing data restored from backups.

[Sometimes you can't restore straight to the target host](https://mtnt.pl/blog/en/posts/bakdrop-sharing-backups/) - you don't have agent, credentials or network access - so you need to restore the files *somewhere* and share them *somehow*. 

Bakdrop is that *somewhere and somehow*: you restore data here and share it with a link that can expire or require a password. The file can also be deleted automatically once the end user downloads it.

![How it works](assets/diagram.png)

## Features

- **Temporary links** - random, unguessable URLs with optional expiration.
- **Password protection** - optional per-link password.
- **Automatic cleanup** - delete the file after download, or after some time.
- **Folder sharing** - folders are streamed on the fly as a ZIP.
- **Large files** - TB-scale downloads with chunked streaming and resumable transfers.
- **Audit trail** - every action is recorded in audit logs.

## How it works

1. An admin logs in, shares a file or folder, and sends the link to the end user.
2. The end user opens the link and downloads the data. No accounts or permissions needed.
3. File can be optionally deleted after download.

There is no web upload feature by design: Bakdrop shares files that are already on the
host, put there however you like (rsync, scp, a backup restore, ...).

## Documentation

You can find full documentation here:

https://mateusznitka.github.io/bakdrop/

## Installation

You can run Bakdrop with Docker or as a plain Apache app. You can download package from here:

https://github.com/mateusznitka/bakdrop/releases

> [!NOTE]
> Use release files instead of cloning repo.

Per-distribution packages, TLS, log management, and troubleshooting are covered in
the [full documentation](https://mateusznitka.github.io/bakdrop/).

### Option A - Docker

**Requirements:** Docker and Docker Compose.

**1. Download and extract** the latest release.

**2. Prepare the files directory on the host.**

Bakdrop serves files from a directory on the host (default `/bakdrop`). You'll want to
copy files into it without sudo, so it's easiest to make the container run as
your own user. Bind mounts match by numeric UID/GID, so check your user and group ID:

```bash
id -u   # e.g. 1000
id -g   # e.g. 1000
```
Then set directory permissions:
```bash
sudo mkdir /bakdrop
sudo chown 1000:1000 /bakdrop
chmod 750 /bakdrop
```

**3. Edit `compose.prod.yml`** and set your values:

```yaml
build:
  args:
    PUID: 1000                           # your user ID from id -u
    PGID: 1000                           # your group ID from id -g
environment:
  - BASE_URL=https://your-ip-or-domain   # used in generated links
  - DEFAULT_LANG=en                      # en or pl
  - TZ=Europe/Warsaw                     # timezone
volumes:
  - bakdrop_data:/var/lib/bakdrop        # database (named volume, auto-created)
  - bakdrop_logs:/var/log/bakdrop        # audit log (named volume, auto-created)
  - /bakdrop:/bakdrop                    # your files directory on the host
```

If you leave `PUID`/`PGID` at the default (33 = www-data), you'll need `sudo` to write into `/bakdrop`.

**4. Start it:**

```bash
docker compose -f compose.prod.yml up -d --build
```

This starts two containers: `bakdrop` (the app) and `bakdrop-cleanup` (an hourly
job that removes expired links and deletes files scheduled for deletion). The app
uses a self-signed certificate and listens on ports 80 (redirect) and 443
(HTTPS) - your browser will warn about the certificate, which is expected for
internal use (to use your own certificate, see [TLS certificates](https://mateusznitka.github.io/bakdrop/tls/)).
You can check the cleanup job with `docker logs bakdrop-cleanup`.

**5. Create the first admin:** open `https://your-ip-or-domain/setup.php` and
follow the prompts.

### Option B - Manual (Apache)

**Requirements:** PHP 8.3+ with the SQLite3 and mbstring extension, Apache with mod_php.

**1. Download and extract** the latest release into your web root
(e.g. `/var/www/html/bakdrop`).

**2. Configure.** Edit `config.php`:

| Setting | Default | Description |
|---|---|---|
| `DB_PATH` | `/var/lib/bakdrop/shares.db` | SQLite database file |
| `FILES_PATH` | `/bakdrop` | Root directory for shared files |
| `BASE_URL` | `https://your-domain-or-ip` | Base URL used in generated links |
| `DEFAULT_LANG` | `en` | Default language (`en` / `pl`) |
| `DEFAULT_THEME` | `dark` | Default theme (`dark` / `light`) |
| `TZ` | `Europe/Warsaw` | Timezone |

**3. Set permissions.** The web server user (`www-data`) needs read/write on the
app, the database directory, and the files directory:

```bash
sudo chown -R www-data:www-data /var/www/html/bakdrop
sudo mkdir -p /var/lib/bakdrop && sudo chown www-data:www-data /var/lib/bakdrop
sudo mkdir -p /var/log/bakdrop && sudo chown www-data:www-data /var/log/bakdrop
sudo mkdir -p /bakdrop && sudo chown www-data:www-data /bakdrop
```

**4. Set up automatic cleanup.** Add an hourly cron job, running as the web server
user so the database keeps consistent ownership:

```bash
sudo crontab -u www-data -e
```
```
0 * * * * php /var/www/html/bakdrop/cleanup.php >> /var/log/bakdrop/cleanup.log 2>&1
```

Without this, scheduled file deletion never happens. You can adjust cron check to your needs.

**5. Create the first admin:** open `http://your-domain-or-ip/setup.php`. Set up
HTTPS as you would for any Apache app (see [TLS certificates](https://mateusznitka.github.io/bakdrop/tls/)).

## Managing admins

The first admin is created through the web setup page. Additional admins,
password resets, and per-admin paths are managed from the CLI with `manage.php`:

```bash
docker exec -it -u www-data bakdrop php manage.php   # Docker
sudo -u www-data php manage.php                      # manual install
```

It's an interactive menu, meant for a "super admin" with SSH access. See the
[admin model](https://mateusznitka.github.io/bakdrop/users/) in the docs for how
per-admin paths work.

## FAQ

**Is this a Nextcloud / WeTransfer / Filebrowser alternative?**

- No. It's intentionally minimal - sharing files by link, nothing more.

**Why is there no upload button?**

- Because Bakdrop shares files that are *already* on the host (copied there by scp,
rsync, a backup restore, ...). Getting files onto the server is a separate job.

## License

GPL-3.0 - see [LICENSE](LICENSE).

## Author

Mateusz Nitka - [mtnt.pl](https://mtnt.pl/blog)
