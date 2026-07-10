<p style="text-align: center">
  <img src="assets/logo.png" alt="Bakdrop" style="max-width: 500px; width: 100%">
</p>

Bakdrop is a small, self-hosted web app for sharing files that already sit on a
server through unique, self-expiring download links. This site is the full
reference: installation, configuration, the audit log, and troubleshooting.

For a quick start, see the [project README](https://github.com/mateusznitka/bakdrop).

## What Bakdrop is

Bakdrop is a small, self-hosted web app for one job: handing a file (or a folder)
that already sits on a server to someone else through a single download link. The
link can expire after a while, require a password, and delete the file once it has
been downloaded.

It is deliberately minimal. There are no user accounts for recipients, no folder
permissions to manage, no sync client, no web upload. An admin logs in, picks a
file that is already on the host, and gets a link to send. The person on the other
end clicks it and downloads. That is the whole product.

## The problem it solves

The app was built for one recurring situation: sharing data restored from backups.

When you restore from a backup, [you often cannot restore straight to the machine
that needs the data](https://mtnt.pl/blog/en/posts/bakdrop-sharing-backups/). You
may not have an agent on it, or credentials, or network
access to it. So you restore the files *somewhere* you do control, and then you
still have to get them to the person or system that asked for them. Email
attachments are too small, a shared network drive may not reach them, and standing
up a full file server for a one-off handover is overkill.

Bakdrop is that "somewhere and somehow". You restore the data onto the Bakdrop
host, share it with a link, and the link carries its own rules: expire it, protect
it with a password, or wipe the file the moment it is picked up. When the transfer
is done, nothing lingers.

## Typical use cases

- **Backup restores.** Restore files onto the Bakdrop host, then hand them to the
  requester with a link that expires or self-deletes. This is the main use case.
- **One-off large transfers.** Ship a multi-GB or TB-scale file to someone without
  setting up an account for them or splitting it into pieces.
- **Password-gated handovers.** Share something mildly sensitive with a per-link
  password, and an audit record of when it was downloaded.
- **Time-boxed access.** Publish a file that must stop being reachable after a set
  time, without having to remember to take it down yourself.
- **Ad hoc internal sharing.** Any time a file is already on a server and you just
  need to get it to a browser, without reaching for a heavier tool.

## How you use it

1. **Put the file on the host.** Bakdrop shares what is already in its files
   directory (`/bakdrop` by default). Get files there however you like: a backup
   restore, rsync, scp, a mounted share. There is no upload button in the app
   on purpose.
2. **Log in and pick the file.** An admin opens the web UI and browses the files
   directory. Folders can be shared too - they are streamed as a ZIP on the fly.
3. **Create a link.** Optionally set an expiration, a password, and whether the
   file should be deleted after download (or after some time). You get a unique,
   unguessable URL.
4. **Send the link.** The recipient opens it in any browser, enters the password
   if there is one, and downloads. They need no account and no software.
5. **Cleanup happens on its own.** Expired links and files scheduled for deletion
   are removed automatically by the hourly cleanup job. Every step above is written
   to the [audit log](audit-logging.md).

## What Bakdrop is not

- **Not a sync tool** (no Dropbox / Nextcloud style clients or shared folders).
- **Not an upload service** (recipients cannot send you files back; there is no
  upload form at all).
- **Not a multi-tenant portal.** Admins are trusted operators with server access,
  not self-service end users. The recipient side is just a download page.

If you want a general file server or a collaboration suite, Bakdrop is the wrong
tool by design. If you want to hand someone a file and have it clean up after
itself, it fits.
