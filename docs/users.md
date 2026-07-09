# Users and the admin model

The first admin is created through the web setup page (`setup.php`). Everything
after that - adding admins, resetting passwords, changing paths, deleting
accounts - happens from the command line with `manage.php`, an interactive menu.
It is meant for a "super admin" who has SSH access to the server.

Run it **as the web server user** so the database keeps consistent ownership:

```bash
# Docker
docker exec -it -u www-data bakdrop php manage.php

# Manual installation
sudo -u www-data php manage.php
```

The menu:

```
=== Bakdrop user management ===
1. List users
2. Create user
3. Reset user password
4. Change user path
5. Delete user
6. Exit
```

- **List users** - shows every account, its path, language, theme, and creation date.
- **Create user** - asks for a username, path, language, theme, and password.
- **Reset user password** - sets a new password for an existing account.
- **Change user path** - moves an account to a different subfolder of `FILES_PATH`.
- **Delete user** - removes an account. The last remaining account cannot be
  deleted, and a deleted account's shares are kept and marked as "Deleted user".

## The admin model

Permissions are deliberately simple. Each admin is assigned a path within
`FILES_PATH`:

- An admin with an **empty path** sees all of `FILES_PATH` (the root).
- An admin assigned `finance` only sees and shares `/bakdrop/finance`.
- Two admins assigned the **same path** have equal rights over it - each sees the
  other's files and can delete the other's shares within that path.

That is the whole model. Account management is only possible from the CLI, so only
whoever has SSH access can add or remove admins. Regular admins manage their own
files and links through the web UI but cannot touch other accounts.
