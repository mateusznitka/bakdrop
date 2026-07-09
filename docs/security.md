# Security notes

- **Authentication** - admin sessions use HTTP-only, `SameSite=Strict` cookies.
  State-changing requests are protected with CSRF tokens.
- **Link secrecy** - share URLs use long random hashes that are not guessable and
  are never listed publicly.
- **Password-protected links** - optional per-link password; wrong attempts are
  recorded as `share_pw status=fail` in the audit log.
- **No web upload** - Bakdrop never accepts uploads, which removes a whole class
  of attack surface. Files arrive on the host by other means.
- **Audit trail** - see [Audit logging](audit-logging.md) for the full record of
  logins, shares, downloads, and account changes.
- **Self-signed TLS by default** - fine for an internal tool; front it with a real
  certificate for anything exposed more widely.
