# TLS certificates (HTTPS)

Out of the box the Docker image serves HTTPS with a **self-signed** certificate
generated at build time. That is fine for an internal tool, but browsers will show
a warning because nobody trusts a self-signed certificate. To get rid of the
warning, use your own certificate. You have three options.

## Docker: mount your own certificate

The image already serves HTTPS - it just uses a self-signed certificate, read from
two fixed paths inside the container:

- certificate: `/etc/ssl/certs/bakdrop.crt`
- private key: `/etc/ssl/private/bakdrop.key`

To use your own certificate, mount your files over those two paths.

**1. Put your certificate and key on the host**, for example in a `certs/` folder
next to `compose.prod.yml`.

**2. Mount them** by adding two lines to the app service's `volumes:` in
`compose.prod.yml` (keep the existing volumes, just append the last two):

```yaml
volumes:
  - bakdrop_data:/var/lib/bakdrop
  - bakdrop_logs:/var/log/bakdrop
  - /bakdrop:/bakdrop
  - ./certs/bakdrop.crt:/etc/ssl/certs/bakdrop.crt:ro   # your certificate
  - ./certs/bakdrop.key:/etc/ssl/private/bakdrop.key:ro   # your private key
```

**3. Recreate the container** (note: no `--build`):

```bash
docker compose -f compose.prod.yml up -d
```

Notes:

- Both files must be PEM encoded. `bakdrop.crt` should be the **full chain** (your
  certificate followed by any intermediate certificates), otherwise some clients
  will not trust it.
- The key must have no passphrase. Apache in the container starts unattended and
  cannot prompt for one.
- Keep the key readable only where it needs to be. Mounting it read-only (`:ro`) as
  above is a good default.

If you obtained the certificate from [Let's Encrypt](https://letsencrypt.org/), `fullchain.pem` is the
certificate file and `privkey.pem` is the key file:

```yaml
  - /etc/letsencrypt/live/your-domain/fullchain.pem:/etc/ssl/certs/bakdrop.crt:ro
  - /etc/letsencrypt/live/your-domain/privkey.pem:/etc/ssl/private/bakdrop.key:ro
```

Remember to recreate the container after each renewal, or automate it in your
renewal hook, so the container picks up the new files.

## Docker: terminate TLS in front

Instead of putting the certificate in the container, you can run a reverse proxy
(nginx, [Traefik](https://doc.traefik.io/traefik/), [Caddy](https://caddyserver.com/docs/), a load balancer) in front that terminates TLS, and let it
talk to Bakdrop. This is often the easiest path if you already run a proxy that
manages certificates for you (for example automatic Let's Encrypt in Traefik or
Caddy). Point the proxy at the container's HTTPS port and let it handle the public
certificate. If you go this way, keep the streaming and buffering advice from
[Large files and downloads](large-files.md) in mind so long downloads
are not cut off or spooled to disk.

## Manual installation

In a manual Apache install, TLS is configured in Apache, not in Bakdrop. Point the
standard SSL directives at your certificate and key in your HTTPS virtual host:

```apache
SSLEngine on
SSLCertificateFile      /etc/ssl/certs/your-domain.crt
SSLCertificateKeyFile   /etc/ssl/private/your-domain.key
# If your CA gives a separate chain file:
# SSLCertificateChainFile /etc/ssl/certs/your-domain-chain.crt
```

The easiest way to get and auto-renew a trusted certificate is [Certbot](https://certbot.eff.org/):

```bash
sudo apt install certbot python3-certbot-apache   # Debian / Ubuntu
sudo certbot --apache -d your-domain
```

Certbot edits your Apache config and sets up automatic renewal for you.
