# Troubleshooting

## Changes do not save, deleted links reappear

Symptom: you delete a share link, it disappears, then comes back on refresh. Or
new shares are not saved.

Cause: the database file is not writable by the web server user, so writes fail
silently. This usually happens after you change `PUID` / `PGID` in Docker - the
existing volume still belongs to the old UID.

Fix (Docker):

```bash
docker exec -u root bakdrop chown -R www-data:www-data /var/lib/bakdrop /var/log/bakdrop
```

Fix (manual): make sure `/var/lib/bakdrop` and the database file are owned by
`www-data`.

## Changing PUID / PGID has no effect

`PUID` and `PGID` are **build arguments**, baked into the image at build time. A
plain `docker compose up -d` will not pick up a change. Rebuild:

```bash
docker compose up -d --build
```

And if the app already ran once, also run the `chown` from the previous item -
the existing `bakdrop_data` and `bakdrop_logs` volumes still belong to the old UID.

## Shares show empty folders after a restore (e.g. Commvault)

Symptom: you restore data into the files directory, but Bakdrop lists the folder as
empty, or a share downloads nothing.

Cause: the restore agent ran as root and wrote the tree with the permissions of the
source system. A Windows backup typically lands as `drwx------` directories and
`-rw-------` files owned by `root`, which the app cannot read. Note that turning off
"restore permissions / ACLs" in the backup tool does **not** avoid this - the files
still get a restrictive Unix mode.

This is exactly what the `bakdrop-fixperms` job exists for, so the real question is
why it did not run. Check the directory:

```bash
ls -ld /bakdrop/restored-folder
```

Healthy output has the shared group and group access, e.g.
`drwxrws--- root bakdrop`.

**If the directory already looks healthy** (`drwxrws--- root bakdrop`) but the app
still lists it empty, the problem is not the files, it is the web server: its
process is not in the `bakdrop` group yet. Group membership is read only when the
process starts, so after adding `www-data` to the group Apache must be **restarted**
(a reload keeps the old group):

```bash
sudo systemctl restart apache2
grep Groups /proc/$(pgrep -x apache2 | head -1)/status   # the bakdrop GID must be listed
```

Do not trust `sudo -u www-data id` here - it starts a fresh process and always shows
the group, even while the running Apache lacks it. In Docker the app container picks
the group up on `docker compose up -d`, so this only bites manual installs.

**If instead you see `drwx------ root root`**, the `bakdrop-fixperms` job that should
have fixed this did not run. Run it by hand to see the error:

```bash
sudo /usr/local/sbin/bakdrop-fixperms      # manual install
docker exec bakdrop-cleanup bakdrop-fixperms   # Docker
```

Common causes, in order:

- **The cron job was never added.** See step 6 of
  [Manual installation](installation/manual.md). In Docker this cannot happen - the
  sidecar does it - so check `docker logs bakdrop-cleanup` instead.
- **`FILES_PATH` was changed** in `config.php` but not passed to the job, so it is
  fixing the wrong directory. Pass the path as an argument.
- **The group does not exist**, or `www-data` was never added to it. The script says
  so if you run it by hand.
- **Docker: `PGID` does not match.** The sidecar hands the files to the app's group;
  if that is still the default 33, admins on the host cannot write there.

## Browser warns about the certificate

The Docker image uses a self-signed certificate. This warning is expected for
internal use. To use a trusted certificate instead, see
[TLS certificates (HTTPS)](tls.md).
