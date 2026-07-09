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
docker compose -f compose.prod.yml up -d --build
```

And if the app already ran once, also run the `chown` from the previous item -
the existing `bakdrop_data` and `bakdrop_logs` volumes still belong to the old UID.

## Files restored as root are unreadable (e.g. Commvault)

If your restore tool preserves the original owners and permissions, some files may
end up unreadable by Bakdrop. Either disable "restore permissions / ACLs" in the
tool, or normalize ownership after a restore:

```bash
sudo chown -R $(id -u):$(id -g) /bakdrop/restored-folder
```

## The favicon or a style change does not update

Browsers cache favicons and static assets aggressively. Do a hard reload
(Ctrl+Shift+R) to see changes.

## Browser warns about the certificate

The Docker image uses a self-signed certificate. This warning is expected for
internal use. To use a trusted certificate instead, see
[TLS certificates (HTTPS)](tls.md).
