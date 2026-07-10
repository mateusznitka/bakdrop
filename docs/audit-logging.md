# Audit logging

Bakdrop writes an audit trail of security-relevant actions to a plain text file.

- **Location:** `/var/log/bakdrop/audit.log` (configurable with `AUDIT_LOG`).
- **Format:** one event per line, in [logfmt](https://brandur.org/logfmt) (`key=value` pairs). This is easy to
  read by eye and easy to parse by a log shipper or SIEM.

## Line format

Every line starts with the same three fields, then event-specific fields:

```
2026-07-09T22:39:08+02:00 event=share_delete actor=admin ip=10.10.50.56 hash=7001ceebf7a02cdc file="file4.jpg"
```

- **timestamp** - ISO 8601 with timezone offset.
- **event** - the event type (see the catalog below).
- **actor** - who did it: the logged-in admin's username, `cli` for `manage.php`,
  `system` for the cleanup job, or `-` for anonymous end users downloading a link.
- **ip** - the client IP, or `-` when not applicable (CLI, cleanup).
- **ua** / **referer** - on `share_view` and `share_download`, the visitor's
  User-Agent and Referer header (also captured in the Apache access log).

Values containing spaces, quotes, or `=` are wrapped in double quotes. Control
characters and newlines are stripped, so a crafted filename cannot forge or split
a log line.

## Event catalog

| Event | Fields | When |
|---|---|---|
| `login` | `status=ok\|fail`, `actor=<username>` | An admin logs in (or fails to). |
| `share_create` | `hash`, `file`, `pw=yes\|no`, `expires=<time>\|-`, `del=after_download\|scheduled\|-` | An admin creates a share link. |
| `share_view` | `hash`, `file`, `ua`, `referer` | An end user opens a share download page. |
| `share_download` | `hash`, `file`, `status=complete\|aborted\|partial`, `ua`, `referer` | An end user downloads a shared link. |
| `share_pw` | `status=fail`, `hash` | A wrong password is entered for a protected link. |
| `share_delete` | `hash`, `file` | An admin deletes a share link. |
| `password_change` | `status=ok` | An admin changes their own password. |
| `file_delete` | `file`, `reason=manual\|after_download\|scheduled` (plus `hash` for the automatic reasons) | A file is deleted: manually by an admin, automatically after download, or on schedule by cleanup. |
| `user_create` | `actor=cli`, `target`, `path` | A new admin account is created via `manage.php`. |
| `user_reset` | `actor=cli`, `target` | An admin password is reset via `manage.php`. |
| `user_setpath` | `actor=cli`, `target`, `path` | An admin path is changed via `manage.php`. |
| `user_delete` | `actor=cli`, `target` | An admin account is deleted via `manage.php`. |

The `share_download` status tells you whether the transfer finished:
`complete` (fully sent), `partial` (a byte-range request, normal for resumable
downloads), or `aborted` (the client disconnected mid-transfer).

## How to view the logs

**Docker** - the log lives on the `bakdrop_logs` named volume:

```bash
# last 50 lines
docker exec bakdrop tail -n 50 /var/log/bakdrop/audit.log

# follow live
docker exec bakdrop tail -f /var/log/bakdrop/audit.log

# only failed logins
docker exec bakdrop grep 'event=login status=fail' /var/log/bakdrop/audit.log
```

You can also read the volume without going through the app container (the volume
name is `<project>_bakdrop_logs`; find it with `docker volume ls`):

```bash
docker run --rm -v bakdrop_bakdrop_logs:/logs alpine tail -n 50 /logs/audit.log
```

**Manual** - it is a normal file:

```bash
tail -n 50 /var/log/bakdrop/audit.log
tail -f  /var/log/bakdrop/audit.log
```

## Related log streams

The audit log is the application-level trail. Two other streams exist:

- **Apache access and error log** - HTTP traffic. In Docker it goes to the
  container stdout: `docker logs bakdrop`. In a manual install it is Apache's
  usual log location.
- **PHP errors** - written to `/var/log/php_errors.log` inside the container. The
  file only appears once there is an actual PHP error.

## Rotation

The app appends to a single file and never rotates it. On a busy install, add a
[logrotate](https://man7.org/linux/man-pages/man8/logrotate.8.html) rule:

```
/var/log/bakdrop/*.log {
    weekly
    rotate 12
    compress
    missingok
    notifempty
    create 0640 www-data www-data
}
```

## Shipping to syslog or a SIEM

Because the format is logfmt, any log shipper
([rsyslog](https://www.rsyslog.com/doc/) `imfile`,
[Vector](https://vector.dev/docs/),
[Fluent Bit](https://docs.fluentbit.io/manual),
[Promtail](https://grafana.com/docs/loki/latest/send-data/promtail/),
[Filebeat](https://www.elastic.co/beats/filebeat)) can tail `audit.log` and
forward it. No special parser is needed - the `key=value` pairs map directly to
structured fields.

## Blocking brute-force attempts (fail2ban)

Bakdrop does not throttle logins itself, but every failed attempt is written to
the audit log with the client IP, so [fail2ban](https://github.com/fail2ban/fail2ban)
can watch that log and ban abusive IPs at the firewall. This covers both admin
logins (`event=login status=fail`) and wrong passwords on protected links
(`event=share_pw status=fail`).

**1. Filter** - create `/etc/fail2ban/filter.d/bakdrop.conf`:

```ini
[Definition]
# Bakdrop writes ISO 8601 timestamps, which fail2ban auto-detects.
failregex = ^\S+ event=login actor=(?:"[^"]*"|\S+) ip=<HOST> status=fail
            ^\S+ event=share_pw actor=(?:"[^"]*"|\S+) ip=<HOST> status=fail
```

**2. Jail** - add to `/etc/fail2ban/jail.local`:

```ini
[bakdrop]
enabled  = true
filter   = bakdrop
logpath  = /var/log/bakdrop/audit.log
maxretry = 5
findtime = 10m
bantime  = 1h
```

Check the filter against your log with:

```bash
fail2ban-regex /var/log/bakdrop/audit.log /etc/fail2ban/filter.d/bakdrop.conf
```

> **Docker:** the audit log lives on the `bakdrop_logs` named volume, which host
> fail2ban cannot read directly. Bind-mount it to a host path instead - change the
> app service volume to `- /var/log/bakdrop:/var/log/bakdrop` - and point `logpath`
> there.
