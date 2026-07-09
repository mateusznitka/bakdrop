# Automatic cleanup

`cleanup.php` does two things every time it runs:

1. Deletes expired share links (links past their expiration time).
2. Deletes files whose scheduled deletion time has passed (the "delete after some
   time" option on a share).

It must run on a schedule, otherwise expired links stay live and scheduled file
deletion never happens.

- **Docker:** the `bakdrop-cleanup` sidecar container runs it every hour
  automatically. It uses the same image and data volumes as the app, only the
  process differs. Check it with `docker logs bakdrop-cleanup`.
- **Manual:** add the hourly cron job shown in
  [Manual installation](install-manual.md), running as `www-data`.

Deleting a share link never deletes the underlying file. Files are only removed by
the two automatic paths above, or by the "delete after download" option, or when
an admin explicitly deletes a file from the web UI.
