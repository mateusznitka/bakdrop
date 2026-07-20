# Automatic cleanup

Bakdrop relies on two background jobs. Both are set up for you in Docker; on a
manual install you add them to cron.

## Cleanup, every hour

`cleanup.php` does two things every time it runs:

1. Deletes expired share links (links past their expiration time).
2. Deletes files whose scheduled deletion time has passed (the "delete after some
   time" option on a share).

It must run on a schedule, otherwise expired links stay live and scheduled file
deletion never happens.

- **Docker:** the `bakdrop-cleanup` sidecar runs it every hour automatically, as
  `www-data`. Check it with `docker logs bakdrop-cleanup`.
- **Manual:** add the hourly cron job shown in
  [Manual installation](installation/manual.md), running as `www-data`.

Deleting a share link never deletes the underlying file. Files are only removed by
the two automatic paths above, or by the "delete after download" option, or when
an admin explicitly deletes a file from the web UI.

## Permissions, every minute

`bakdrop-fixperms` resets group ownership and mode across the files directory, so
that whatever lands there is readable, servable and deletable by the app.

This is not housekeeping, it is what makes the main use case work. Tools that write
files with the permissions of the source system leave them inaccessible to Bakdrop:
a Windows backup restored by an agent running as root arrives root-owned and `0600`,
and the app shows an empty folder. The same applies to `tar -p` and `cp -p`. The job
puts each of those back in the shared group within a minute, so an admin can restore
data with a backup agent and share it from the UI without ever touching a shell.

- **Docker:** the same `bakdrop-cleanup` sidecar runs it every minute, as root.
  Nothing to configure.
- **Manual:** add the root cron job shown in
  [Manual installation](installation/manual.md).

It must run as root, because only root can reset ownership of files written by
someone else. It is worth being precise about what that does and does not mean:
the job only ever runs two fixed commands over one directory. The web server never
invokes it and gains nothing from it, so there is no path from a compromised PHP
process to root through this. It is also safe against a planted symlink - `chown -R`
does a physical walk and `chmod -R` skips symbolic links, so neither can be
redirected outside the tree.

It is idempotent and cheap: metadata only, roughly 0.7 s per 100k files. The cost
scales with the number of files, not their size, so a multi-TB directory is no
different.
