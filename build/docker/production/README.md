# Bizuno production Docker stack

A drop-in three-container stack — **bizuno + mariadb + caddy** — for
deploying Bizuno on a dedicated Docker host with TLS termination and a
wildcard / per-site cert from an external CA.

This is the right pattern when:

- You're running Bizuno on a Linux server with no other web app
  competing for ports 80/443.
- You have (or can get) a TLS cert for the hostname Bizuno will serve at.
- You don't want or need ISPConfig / Plesk / cPanel managing the host.

If you want Bizuno to share a server with other sites managed by an
existing control panel, see the reverse-proxy patterns in
`build/docker/README.md` instead.

## Architecture

```
Internet ──:443──→ Caddy (TLS termination)
                     │
                     │ http://bizuno:80
                     ↓
                   Bizuno (Apache + PHP 8.2)
                     │
                     │ pdo_mysql
                     ↓
                   MariaDB 11
```

- Caddy is the only container exposing ports to the public internet.
- Bizuno + MariaDB communicate over Docker's internal `bizuno-net` network.
- TLS happens at the Caddy edge; Bizuno itself never speaks HTTPS.
- `X-Forwarded-Proto: https` flows from Caddy to Bizuno so URL
  construction works correctly.

## Prerequisites

- A Linux host with Docker Engine + Compose plugin installed
  (Debian 12+, Ubuntu 22.04+, RHEL 9+, etc.)
- A hostname pointed at the host's public IP (DNS A-record)
- A TLS cert + key for that hostname (or for a wildcard covering it)
- Ports 80 + 443 reachable from the public internet (firewall + NAT)
- ~1GB RAM minimum, 4GB recommended; ~10GB disk for the base + a year
  of light use

## Setup

```bash
# 1. Copy this directory to wherever you want Bizuno's stack to live.
#    /home/<user>/bizuno/ is a common choice; /opt/bizuno/ is the more
#    formal Linux convention.
cp -a build/docker/production /home/$USER/bizuno
cd /home/$USER/bizuno

# 2. Place your TLS cert + key files on the host. Match the paths in
#    .env.example (default: /etc/ssl/docker/your.cert.pem + .key).
sudo mkdir -p /etc/ssl/docker
sudo chmod 700 /etc/ssl/docker
sudo chown root:root /etc/ssl/docker
# upload your cert + key into /etc/ssl/docker/ (scp, your CA's
# delivery mechanism, certbot --webroot, etc.)
sudo chmod 600 /etc/ssl/docker/*.{pem,key}

# 3. Generate secrets + fill in .env.
cp .env.example .env
$EDITOR .env
#   - Set BIZUNO_HOSTNAME to your public FQDN
#   - Set CADDY_CERT_FILE / CADDY_KEY_FILE to the host paths from step 2
#   - Generate BIZUNO_KEY (16 alphanumeric):
#       openssl rand -base64 16 | tr -dc 'A-Za-z0-9' | head -c16
#   - Generate BIZUNO_BIZID (6 alphanumeric):
#       openssl rand -base64 6 | tr -dc 'A-Za-z0-9' | head -c6
#   - Generate BIZUNO_DB_PASS and MARIADB_ROOT_PASSWORD (24+ chars each):
#       openssl rand -base64 24
#   - Pick a stable BIZUNO_VERSION (e.g. 7.4.0) instead of `latest` for
#     prod reproducibility

# 4. Bring up the stack.
docker compose pull          # fetches the bizuno + mariadb + caddy images
docker compose up -d         # starts all three containers in the background

# 5. Verify.
docker compose ps            # all three should be "running (healthy)" within ~30s
curl -fsS -o /dev/null -w "%{http_code}\n" https://$BIZUNO_HOSTNAME/
                             # should print 200 (the installer wizard renders)
```

Open `https://<your-hostname>/` in a browser. The Bizuno installer
wizard runs on first request — DB credentials are pre-populated from
the .env values you set, just click through.

## Day-to-day

```bash
# View logs (live, follow mode):
docker compose logs -f                # all containers
docker compose logs -f bizuno         # just bizuno
docker compose logs -f caddy          # just caddy (HTTP access log, errors)

# Restart after .env or Caddyfile changes:
docker compose up -d                  # recreates only what changed

# Pull a new Bizuno image after a release:
docker compose pull bizuno
docker compose up -d bizuno

# Stop everything (data persists on volumes):
docker compose down

# Stop AND wipe all data (destructive — kills DB + uploads + config):
docker compose down -v
```

## Backups

Five named volumes hold all persistent state:

| Volume          | Contents                                      | Critical? |
|-----------------|-----------------------------------------------|-----------|
| `mariadb-data`  | Database files                                | **Yes**   |
| `bizuno-data`   | Uploads, attachments, cache, biz-instance-key | **Yes**   |
| `bizuno-config` | `portalCFG.php` (contains BIZUNO_KEY!)        | **Yes**   |
| `caddy-data`    | TLS state, ACME data (if using Caddy ACME)    | No (regenerable) |
| `caddy-config`  | Caddy's runtime state                         | No        |

A simple backup script:

```bash
#!/usr/bin/env bash
# /home/<user>/bizuno/backup.sh
set -euo pipefail
cd /home/$USER/bizuno
TS=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR=/var/backups/bizuno
mkdir -p "$BACKUP_DIR"
for vol in mariadb-data bizuno-data bizuno-config; do
    docker run --rm \
        -v bizuno_${vol}:/data:ro \
        -v "$BACKUP_DIR":/backup \
        alpine tar -czf /backup/${vol}-${TS}.tar.gz -C / data
done
echo "Wrote backups to $BACKUP_DIR with timestamp $TS"
```

Run via cron nightly; off-host every week.

## Hardening checklist

The stack as shipped is reasonably secure but worth verifying:

- **`/etc/ssl/docker/` owner = root:root, mode 700.** Caddy reads as
  root inside its container; non-root users on the host shouldn't be
  able to copy out your private key.
- **`.env` mode 600, owned by your unprivileged user.** It contains
  every secret in the stack.
- **`ufw` allows only 22, 80, 443 inbound.** No reason for the host
  to listen on anything else. (MariaDB explicitly doesn't publish a
  port — only reachable via the docker network.)
- **`unattended-upgrades` installed + enabled.** Debian / Ubuntu
  security patches auto-apply.
- **Pinned `BIZUNO_VERSION`.** `latest` is fine for testing; pinned
  for production so a bad release doesn't auto-deploy.

## TLS cert renewal

If you're using a wildcard cert from an external CA (Sectigo, DigiCert,
your in-house ACME, etc.), renewal happens outside this stack. When
the new cert files are in place:

```bash
docker compose restart caddy
```

Caddy re-reads the cert files on start. Restart takes ~2 seconds.

If you'd rather have Caddy provision Let's Encrypt certs automatically,
remove the `tls` directive from the Caddyfile entirely. Caddy will use
its built-in ACME client. Requires the hostname's A-record to point at
this server BEFORE first start.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `docker compose up` errors `BIZUNO_KEY must be set in .env` | The `:?` syntax in docker-compose.yml is rejecting an empty value. Fill in .env. |
| Caddy fails with `tls: open /etc/ssl/docker/...: no such file or directory` | Cert / key files not at the path in .env. `ls -la /etc/ssl/docker/`. |
| Caddy fails with `bind: address already in use` | Port 80 or 443 occupied — usually leftover apache2/nginx. `sudo ss -tlnp` to find it. |
| Bizuno container reports "Database connection failed" | Wait 30s on first start (MariaDB initializes its data dir on the first boot). If it persists, check `docker compose logs mariadb`. |
| HTTPS works but Bizuno emits http:// URLs in HTML | Caddy isn't sending `X-Forwarded-Proto: https`. Should be in the Caddyfile already; check it didn't get overwritten. |
| `502 Bad Gateway` from Caddy | bizuno container is unhealthy. `docker compose logs bizuno`, fix, `docker compose restart bizuno`. |
| Need to inspect the DB directly | `docker compose exec mariadb mysql -u bizuno -p<password from .env> bizuno` |
| Need to inspect Bizuno's filesystem | `docker compose exec bizuno bash` — drops you into a shell as www-data in the container. |

## See also

- `build/docker/README.md` — image-level documentation, env var reference, the dev-flavored compose
- `build/docker/Dockerfile` — the image definition itself
- `build/docker/entrypoint.sh` — how portalCFG.php is generated from env vars on first boot
