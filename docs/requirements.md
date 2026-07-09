# Requirements

## Docker

- Docker Engine
- Docker Compose (the `docker compose` plugin, included with current Docker installs)

If you do not have Docker yet, install it. The quickest way on most Linux
distributions is the official convenience script:

```bash
curl -fsSL https://get.docker.com | sudo sh
```

Or install it from your distribution's packages:

**Debian / Ubuntu**

```bash
sudo apt update
sudo apt install docker.io docker-compose-v2
```

**Fedora / RHEL / Rocky / AlmaLinux**

```bash
sudo dnf install docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

For the most current, distribution-specific steps (and Docker Desktop on macOS or
Windows), follow the official guide at <https://docs.docker.com/engine/install/>.

After installing, start Docker and, optionally, allow your user to run it without
`sudo`:

```bash
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"   # log out and back in for this to take effect
```

Verify it works:

```bash
docker run --rm hello-world
```

## Manual (Apache)

- PHP 8.3 or newer, with the **sqlite3** and **mbstring** extensions
    - `sqlite3` - the app stores share links in a SQLite database.
    - `mbstring` - required by the ZIP streaming library used for folder shares.
- Apache with `mod_php`

Install the packages for your distribution:

**Debian / Ubuntu**

```bash
sudo apt update
sudo apt install php php-cli php-sqlite3 php-mbstring libapache2-mod-php
```

**Fedora / RHEL / Rocky / AlmaLinux**

```bash
sudo dnf install php php-cli php-mbstring php-pdo
```

On these distributions the `SQLite3` extension ships inside the `php-pdo`
package. Package names change between distributions and versions, so after
installing, verify that both extensions are actually loaded:

```bash
php -m | grep -iE 'sqlite3|mbstring'
```

You should see both `sqlite3` and `mbstring` in the output. If either is
missing, folder sharing (mbstring) or the whole app (sqlite3) will not work.
