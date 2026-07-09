# Configuration reference

In Docker, set these through the `environment:` block in `compose.prod.yml`. In a
manual installation, edit the `define(...)` values in `config.php` (or export the
matching environment variables). Every setting reads an environment variable and
falls back to the default shown below.

| Setting | Env variable | Default | Description |
|---|---|---|---|
| Database file | `DB_PATH` | `/var/lib/bakdrop/shares.db` | SQLite database with share links and users |
| Files directory | `FILES_PATH` | `/bakdrop` | Root directory that Bakdrop shares files from |
| Base URL | `BASE_URL` | `https://your-domain-or-ip` | Used to build the download links |
| Default language | `DEFAULT_LANG` | `en` | `en` or `pl` |
| Default theme | `DEFAULT_THEME` | `dark` | `dark` or `light` |
| Audit log file | `AUDIT_LOG` | `/var/log/bakdrop/audit.log` | Where audit events are written |
| Timezone | `TZ` | `Europe/Warsaw` | Timezone for timestamps |
