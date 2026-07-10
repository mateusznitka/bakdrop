# Installation with Docker

**1. Download and extract** the latest release from
<https://github.com/mateusznitka/bakdrop/releases>. Use the release package, not
a git clone - the release ships with dependencies already bundled.

**2. Prepare the files directory on the host.**

Bakdrop serves files from a directory on the host (default `/bakdrop`). To copy
files into it without `sudo`, make the container run as your own user. Bind
mounts match by numeric UID and GID, so find yours first:

```bash
id -u   # e.g. 1000
id -g   # e.g. 1000
```

```bash
sudo mkdir /bakdrop
sudo chown 1000:1000 /bakdrop
chmod 750 /bakdrop
```

**3. Edit `compose.prod.yml`.** Set the build arguments and environment:

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

`PUID` / `PGID` should match the owner of your files directory. If you leave them
at the default (33 = www-data), you will need `sudo` to write into `/bakdrop`.

**4. Start it:**

```bash
docker compose -f compose.prod.yml up -d --build
```

This starts two containers:

- `bakdrop` - the app. Uses a self-signed certificate and listens on port 80
  (redirects to HTTPS) and 443 (HTTPS). Your browser will warn about the
  certificate, which is expected for internal use.
- `bakdrop-cleanup` - a sidecar that runs `cleanup.php` every hour to remove
  expired links and delete files scheduled for deletion. See
  [Automatic cleanup](../cleanup.md).

**5. Create the first admin:** open `https://your-ip-or-domain/setup.php` and
follow the prompts.

!!! warning "PUID / PGID are build arguments"
    Changing them later needs a rebuild and a one-time ownership fix on the
    existing volumes. See [Troubleshooting](../troubleshooting.md).
