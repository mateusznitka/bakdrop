# Docker requirements

- Docker Engine
- Docker Compose (the `docker compose` plugin, included with current Docker installs)

If you do not have Docker yet, install it. The quickest way on most Linux
distributions is the official convenience script:

```bash
curl -fsSL https://get.docker.com | sudo sh
```

Or install it from your distribution's packages:

**Debian / Ubuntu**

```bash
sudo apt update
sudo apt install docker.io docker-compose-v2
```

**Fedora / RHEL / Rocky / AlmaLinux**

```bash
sudo dnf install docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

For the most current, distribution-specific steps (and Docker Desktop on macOS or
Windows), follow the official guide at <https://docs.docker.com/engine/install/>.

After installing, start Docker and, optionally, allow your user to run it without
`sudo`:

```bash
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"   # log out and back in for this to take effect
```

Verify it works:

```bash
docker run --rm hello-world
```

Next: [install Bakdrop with Docker](../installation/docker.md).
