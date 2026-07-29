![Bakdrop](assets/logo.png)

Bakdrop is a simple web app for sharing files from a server with end users through unique, self-expiring download links.

While it can be used in any case when you need to share files through web, it was built mainly for one specific job: sharing data restored from backups.

[Sometimes you can't restore straight to the target host](https://mtnt.pl/blog/en/posts/bakdrop-sharing-backups/) - you don't have agent, credentials or network access - so you need to restore the files *somewhere* and share them *somehow*. 

Bakdrop is that *somewhere and somehow*: you restore data here and share it with a link that can expire or require a password. The file can also be deleted automatically once the end user downloads it.

![How it works](assets/diagram.png)

> [!IMPORTANT]
> Bakdrop is still in beta. It should be stable and solid, but still there is some work to do. Feel free to test and open issues.

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
host, put them there however you like (rsync, scp, a restore by backup agent, ...).

## Documentation

You can find full documentation here:

https://mateusznitka.github.io/bakdrop/

## Requirements

Bakdrop can be deployed as a Docker container or a classic Apache / PHP webapp.

- Container: Docker & Docker Compose
- Manual installation: Apache, PHP 8.3+ with sqlite3 and mbstring extensions.

## Installation

You can find detailed installation instructions in [full documentation](https://mateusznitka.github.io/bakdrop/installation/).

Download latest version from releases:

https://github.com/mateusznitka/bakdrop/releases



> [!NOTE]
> Use release files instead of cloning repo.

### Option A - Docker

1. Download and extract the latest release.

2. Prepare the files directory on the host. Set directory permissions:
```bash
sudo mkdir /bakdrop
sudo chown 1000:1000 /bakdrop   # use your user PUID/PGID
chmod 750 /bakdrop
```

3. Edit `compose.yml` and set your values:

```yaml
build:
  args:
    PUID: 1000                             # your user ID from id -u
    PGID: 1000                             # your group ID from id -g
environment:
  - BASE_URL=https://your-ip-or-domain     # used in generated links
volumes:
  - bakdrop_data:/var/lib/bakdrop          # database (named volume, auto-created)
  - bakdrop_logs:/var/log/bakdrop          # audit log (named volume, auto-created)
  - /bakdrop:/bakdrop  # your files directory on the host
```

4. Start it:

```bash
docker compose up -d --build
```

5. Create the first admin: open `https://your-ip-or-domain/setup.php` and
follow the prompts.

### Option B - Manual (Apache)

1. Download and extract the latest release into your web root
(e.g. `/var/www/html/bakdrop`).

2. Configure: Open and edit `config.php`:

```php
define('FILES_PATH',   getenv('FILES_PATH')   ?: '/your_data_directory_on_host');
define('BASE_URL',     getenv('BASE_URL')     ?: 'https://your-domain-or-ip');
```

3. Set permissions. The web server user (`www-data`) needs read/write on the
app, the database directory, and the files directory:

```bash
sudo chown -R www-data:www-data /var/www/html/bakdrop
sudo mkdir -p /var/lib/bakdrop && sudo chown www-data:www-data /var/lib/bakdrop
sudo mkdir -p /var/log/bakdrop && sudo chown www-data:www-data /var/log/bakdrop
sudo mkdir -p /bakdrop && sudo chown www-data:www-data /bakdrop
```

4. Set up [automatic cleanup](https://mateusznitka.github.io/bakdrop/cleanup/).


5. Create the first admin: open `http://your-domain-or-ip/setup.php`.

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

**What about files/folders permissions? Can I share files owned by root?**

- For now there is simple cron script which normalize files permissions every minute (they are added to group that bakdrop can safely control). I know this is not ideal and I will take a look on this in the future.

**Is this a Nextcloud / PicoShare / Filebrowser alternative?**

- These are great pieces of software but no. It's built for different use-cases and it's intentionally minimal.

**Why is there no upload button?**

- Because Bakdrop shares files that are *already* on the host (copied there by scp,
rsync, a backup restore, ...). Getting files onto the server is a separate job.

## License

GPL-3.0 - see [LICENSE](LICENSE).

## Author

Mateusz Nitka - [mtnt.pl](https://mtnt.pl/blog)
