# Installation with Docker

**1. Download and extract** the latest release from
<https://github.com/mateusznitka/bakdrop/releases>. Use the release package, not
a git clone - the release ships with dependencies already bundled.

**2. Prepare the files directory on the host.**

Bakdrop serves files from a directory on the host (default `/bakdrop`):

```bash
sudo mkdir -p /bakdrop
```

That is all it needs. The maintenance sidecar takes the tree over from inside the
container and keeps it that way, so you never set permissions there by hand.

What you do have to get right is **which identity the container runs as**, because
that is the identity you will share the directory with on the host. Bind mounts
match by numeric UID and GID, so find yours:

```bash
id -u   # e.g. 1000
id -g   # e.g. 1000
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

`PUID` / `PGID` are the identity the app runs as, and the sidecar hands the files
directory to that **group** (`PGID`) every minute. Setting `PGID` to your own group
is what lets you write into `/bakdrop` without `sudo`. Leave them at the default
(33 = www-data) and every copy will need `sudo`.

!!! tip "Restores are handled for you"
    A backup agent restoring as root drops a root-owned, unreadable tree into
    `/bakdrop`. The sidecar picks it up within a minute and the files just appear in
    the UI. Nothing to run by hand, and the person doing the restore needs no shell
    access to the host at all.

??? note "Alternative: several admins uploading files"
    `PGID` is a group, so it can just as well be a **shared** one. On the host,
    create it and add each admin:

    ```bash
    sudo groupadd bakdrop                 # shared group
    sudo usermod -aG bakdrop admin1       # add each admin
    sudo usermod -aG bakdrop admin2
    getent group bakdrop                  # note the GID, e.g. bakdrop:x:1500:
    ```

    Then point `PGID` at that GID and rebuild (`docker compose up -d --build`):

    ```yaml
    build:
      args:
        PGID: 1500     # the shared 'bakdrop' group GID (PUID can stay as-is)
    ```

    Every admin in the group can now copy into `/bakdrop` as themselves, and the
    app - which runs in that group - serves and deletes all of it, no matter who
    added it.

    Group membership is only read at login, so each admin has to log out and back in
    (a fresh SSH session, not a new terminal tab) before they can write. `id` should
    list `bakdrop`; if it does not, the session is still the old one.

**4. Start it:**

```bash
docker compose up -d --build
```

This starts two containers:

- `bakdrop` - the app. Uses a self-signed certificate and listens on port 80
  (redirects to HTTPS) and 443 (HTTPS). Your browser will warn about the
  certificate, which is expected for internal use.
- `bakdrop-cleanup` - a maintenance sidecar with two jobs: every minute it keeps
  the files directory accessible to the app (so restored files show up on their
  own), and every hour it runs `cleanup.php` to remove expired links and files
  scheduled for deletion. See [Automatic cleanup](../cleanup.md).

**5. Create the first admin:** open `https://your-ip-or-domain/setup.php` and
follow the prompts.

!!! warning "PUID / PGID are build arguments"
    Changing them later needs a rebuild and a one-time ownership fix on the
    existing volumes. See [Troubleshooting](../troubleshooting.md).
