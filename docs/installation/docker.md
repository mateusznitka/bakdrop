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

**3. Edit `compose.yml`.** Set the build arguments and environment:

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

??? note "Alternative: several admins uploading files"
    The setup above lets one host user (`PUID`) write to `/bakdrop`. If several
    people need to drop files in, bind the directory to a **shared group** instead
    and point the container's `PGID` at it.

    On the host, create the group, add each admin, and grant the group access to
    `/bakdrop` with an ACL (so it also covers files added later):

    ```bash
    sudo groupadd bakdrop                 # shared group
    sudo usermod -aG bakdrop admin1       # add each admin
    sudo usermod -aG bakdrop admin2
    getent group bakdrop                  # note the GID, e.g. bakdrop:x:1500:

    sudo chown -R root:bakdrop /bakdrop
    sudo chmod -R 2775 /bakdrop
    sudo setfacl -R -m   g:bakdrop:rwX /bakdrop
    sudo setfacl -R -d -m g:bakdrop:rwX /bakdrop
    ```

    Then set `PGID` in `compose.yml` to that group's GID and rebuild
    (`docker compose up -d --build`):

    ```yaml
    build:
      args:
        PGID: 1500     # the shared 'bakdrop' group GID (PUID can stay as-is)
    ```

    Every admin in the group can now copy into `/bakdrop` as themselves, and the
    app - which runs in that group - serves and deletes all of it, no matter who
    added it. Admins must log out and back in after being added to the group. (ACLs
    need `setfacl` from the `acl` package and a filesystem that supports them, such
    as ext4 or xfs.)

**4. Start it:**

```bash
docker compose up -d --build
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
