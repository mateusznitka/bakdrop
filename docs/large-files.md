# Large files and downloads

Bakdrop is built to hand out large files, including TB-scale backups.

- **Streaming** - downloads are streamed in chunks, so memory use stays low
  regardless of file size.
- **Resumable transfers** - HTTP range requests are supported, so an interrupted
  download can resume instead of starting over. A range request is logged with
  `status=partial`.
- **Folder shares** - a shared folder is streamed on the fly as a ZIP archive
  (stored, not compressed), so there is no temporary file and no wait to "build"
  the archive first.

## Reverse proxy and PHP-FPM

Bakdrop targets Apache with `mod_php`. If you put it behind [nginx](https://nginx.org/en/docs/) or run it under
[PHP-FPM](https://www.php.net/manual/en/install.fpm.php), watch for limits that can cut off long downloads:

- `request_terminate_timeout` in the FPM pool - keep it `0`, or long downloads
  get killed.
- Proxy buffering - set `fastcgi_buffering off;` for `download.php`, otherwise
  nginx may try to spool a multi-GB transfer to disk before sending it.
