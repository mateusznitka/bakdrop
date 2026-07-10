# Manual (Apache) requirements

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

Next: [install Bakdrop manually](../installation/manual.md).
