---
title: Docker Install Walkthrough
category: Getting Started
order: 4
status: published
audience: [admin]
last-updated: 2026-05-27
---

# Docker Install Walkthrough

A complete, copy-pasteable walkthrough for deploying Bizuno on a
dedicated Linux server using the production Docker stack
([`build/docker/production/`](https://github.com/phreesoft/bizuno/tree/main/build/docker/production)).
This is the right path when:

- You're running Bizuno on a Linux server with **no other web app**
  competing for ports 80/443.
- You have (or can get) a **TLS cert** for the hostname Bizuno will
  serve at — either a wildcard cert from your CA or Let's Encrypt.
- You don't want or need a hosting control panel (ISPConfig, Plesk,
  cPanel) managing the box.

If you instead want Bizuno to share a server with other sites managed
by an existing control panel, see the reverse-proxy patterns in the
top-level [`build/docker/README.md`](https://github.com/phreesoft/bizuno/blob/main/build/docker/README.md)
instead.

End state: `https://your.hostname/` serves Bizuno, TLS terminated by
Caddy, MariaDB persisted on a named Docker volume, three containers
running under `docker compose`. Roughly 30 minutes start to finish on
a fresh server.

## What you'll need before you start

1. **A Linux server** — Debian 13 (Trixie) used in this walkthrough.
   Ubuntu 22.04+ / RHEL 9+ / Rocky 9+ work the same way with minor
   command tweaks. ~1GB RAM minimum, 4GB recommended; 10GB disk for
   the base + a year of light use.
2. **SSH access** as a user that can become root (`sudo` or the root
   password / key — we'll set up sudo if it's missing).
3. **A hostname** with a DNS A-record pointed at the server's public
   IP. (Or your local `/etc/hosts` override for pre-DNS testing — see
   the LAN/WAN trick under Phase 3.)
4. **A TLS cert + private key** for that hostname. A wildcard cert
   from any CA (Sectigo, DigiCert, etc.) covering the parent domain
   works. Or skip this and let Caddy provision Let's Encrypt
   automatically — see "Alternative: Let's Encrypt" at the end.
5. **Ports 80 + 443 reachable** from wherever you'll test from. (Your
   public internet for real users; your LAN for initial testing via
   `/etc/hosts` override.)

## Phase 1 — Server prep

SSH in as your user:

```bash
ssh youruser@your.server
```

### 1.1 Verify the OS + sudo

```bash
. /etc/os-release && echo "OS: $PRETTY_NAME  /  codename: $VERSION_CODENAME"
sudo -v && echo "sudo works"
```

If sudo errors with "may not run sudo" — you need root access to fix
this once. Either log in via `su -` (with the root password) or SSH
directly as root, then:

```bash
usermod -aG sudo youruser
exit
ssh youruser@your.server     # re-login so the group change applies
sudo -v && echo "sudo works"
```

> **Note:** `usermod` lives in `/usr/sbin/` which isn't on a regular
> user's `$PATH`. Always run it from a root shell, not from an
> unprivileged shell.

### 1.2 Refresh apt + install basic tooling

```bash
sudo apt update && sudo apt full-upgrade -y
sudo apt install -y ca-certificates curl gnupg git
```

### 1.3 Install Docker Engine + Compose

Add Docker's official apt repo (NOT the distro's old `docker.io`
package — it's years behind and ships the legacy `docker-compose`
Python binary instead of the modern `docker compose` subcommand):

```bash
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/debian/gpg \
    -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

echo "deb [arch=$(dpkg --print-architecture) \
    signed-by=/etc/apt/keyrings/docker.asc] \
    https://download.docker.com/linux/debian \
    $(. /etc/os-release && echo $VERSION_CODENAME) stable" | \
    sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io \
    docker-buildx-plugin docker-compose-plugin
```

> On Ubuntu, swap `linux/debian` → `linux/ubuntu` in both URLs.

### 1.4 Allow your user to run Docker without sudo

```bash
sudo usermod -aG docker youruser
exit                                       # close session so group change takes effect
ssh youruser@your.server                   # log back in
groups | tr ' ' '\n' | grep docker         # should print: docker
docker version                              # client + server versions
docker compose version                      # Compose v2.x
docker run --rm hello-world                 # smoke test
```

If the smoke test completes (pulls image, prints greeting, exits) →
Docker is working.

## Phase 2 — Free port 80

Fresh Debian (and many other distros) ships with **Apache** and
**CUPS** installed by default, both listening on ports. Caddy can't
bind to 80/443 while Apache holds them.

### 2.1 Check what's listening

```bash
sudo ss -tlnp | grep -E ':80|:443'
```

If you see `apache2`, `nginx`, or anything else on those ports, it
needs to go.

### 2.2 Confirm nothing important is being served

```bash
# Is ISPConfig or another control panel managing this server?
ls -la /usr/local/ispconfig 2>/dev/null && echo "ISPConfig present" || echo "no ISPConfig"
ls /var/www/clients/ 2>/dev/null | head

# What vhosts is apache2 serving?
sudo apache2ctl -S 2>&1 | head -20
```

If you see live sites in `/var/www/clients/` or ISPConfig present →
this server is in active use; **don't** proceed with removal. Move
sites elsewhere first or pick a different server.

If you see only Debian's default vhost serving `/var/www/html/`
("It works!" placeholder) → safe to remove.

### 2.3 Remove apache2 + cups

```bash
sudo systemctl stop apache2 cups
sudo systemctl disable apache2 cups
sudo apt remove -y apache2 apache2-data apache2-utils cups
sudo apt autoremove -y
sudo ss -tlnp | grep -E ':80|:443'      # should be empty now
```

### 2.4 Optional but recommended: firewall + auto-updates

```bash
sudo apt install -y unattended-upgrades fail2ban ufw
sudo dpkg-reconfigure --priority=low unattended-upgrades

sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow http
sudo ufw allow https
sudo ufw enable
sudo ufw status verbose
```

## Phase 3 — DNS + TLS cert

### 3.1 DNS A-record

Point your chosen hostname at the server's public IP. For example,
in your DNS provider's UI:

```
A    bizuno.your-domain.com    →   71.78.x.x
```

Verify from your local machine (not from the server itself — `ping`
on the server resolves via `127.0.1.1` due to Debian's default
`/etc/hosts`):

```bash
dig +short bizuno.your-domain.com
# expected: your server's public IP
```

### 3.2 Pre-DNS testing via `/etc/hosts` (optional but useful)

If DNS hasn't propagated yet, OR you want to test from inside your
LAN before flipping the public record, add a temporary entry to
your **local machine's** hosts file:

```bash
# On macOS or Linux client
sudo nano /etc/hosts
# Add:
#   192.168.x.x  bizuno.your-domain.com       (LAN IP if testing from LAN)
#   71.78.x.x    bizuno.your-domain.com       (WAN IP if testing from outside)

# Flush DNS cache (macOS):
sudo killall -HUP mDNSResponder

# Verify:
ping -c 1 bizuno.your-domain.com               # should hit the IP you set
```

Remove the override once real DNS is in place.

### 3.3 Upload the TLS cert

Two files on the server: the certificate (fullchain) and the private
key. Caddy reads both at startup.

```bash
# On the server, pre-create the directory with locked-down perms:
sudo mkdir -p /etc/ssl/docker
sudo chmod 700 /etc/ssl/docker
sudo chown root:root /etc/ssl/docker

# From your local machine, upload the cert + key:
scp wildcard.your-domain.com.pem wildcard.your-domain.com.key \
    youruser@your.server:/tmp/

# Back on the server:
sudo mv /tmp/wildcard.your-domain.com.* /etc/ssl/docker/
sudo chown root:root /etc/ssl/docker/wildcard.your-domain.com.*
sudo chmod 600 /etc/ssl/docker/wildcard.your-domain.com.*
ls -la /etc/ssl/docker/
```

The cert file should be the **fullchain** (your cert + intermediate
CA certs concatenated). If your CA gave you separate `cert.pem` and
`chain.pem` files, concatenate them: `cat cert.pem chain.pem > fullchain.pem`.

## Phase 4 — Build the Bizuno image

The Bizuno container image is built from the repo. There's no
prebuilt image to pull yet (the CI workflow pushes on `v*` tag pushes
which haven't happened for the current `main`), so we clone + build
locally.

```bash
cd ~
git clone https://github.com/phreesoft/bizuno.git bizuno-src
cd bizuno-src
bash build/docker/build.sh
```

The build takes ~5 minutes on a first run. It produces a multi-tagged
image:

```bash
docker images | grep phreesoft/bizuno
# ghcr.io/phreesoft/bizuno:7.x.x   <hash>   ...   1.06GB
# ghcr.io/phreesoft/bizuno:latest  <hash>   ...   1.06GB
```

Both tags point at the same image. The compose stack references
`:latest` by default but you can pin to the specific version via
`BIZUNO_VERSION` in `.env`.

## Phase 5 — Configure the compose stack

The repo ships a ready-to-use stack template at
[`build/docker/production/`](https://github.com/phreesoft/bizuno/tree/main/build/docker/production).
Copy it to your home directory:

```bash
mkdir -p ~/bizuno
cp ~/bizuno-src/build/docker/production/docker-compose.yml ~/bizuno/
cp ~/bizuno-src/build/docker/production/Caddyfile          ~/bizuno/
cp ~/bizuno-src/build/docker/production/.env.example       ~/bizuno/.env
chmod 600 ~/bizuno/.env
ls -la ~/bizuno/
# expect: .env  Caddyfile  docker-compose.yml
```

### 5.1 Generate secrets

The `.env` file needs strong values for every secret. Generate them
all at once:

```bash
cat <<EOF > ~/bizuno/.env
# Image
BIZUNO_VERSION=latest

# Hostname Bizuno serves at
BIZUNO_HOSTNAME=bizuno.your-domain.com

# TLS cert paths (must match where you put them in Phase 3.3)
CADDY_CERT_FILE=/etc/ssl/docker/wildcard.your-domain.com.pem
CADDY_KEY_FILE=/etc/ssl/docker/wildcard.your-domain.com.key

# Bizuno secrets — generated fresh:
BIZUNO_KEY=$(openssl rand -base64 16 | tr -dc 'A-Za-z0-9' | head -c16)
BIZUNO_BIZID=$(openssl rand -base64 6 | tr -dc 'A-Za-z0-9' | head -c6)

# Database
BIZUNO_DB_NAME=bizuno
BIZUNO_DB_USER=bizuno
BIZUNO_DB_PASS=$(openssl rand -base64 24)
MARIADB_ROOT_PASSWORD=$(openssl rand -base64 24)
EOF

chmod 600 ~/bizuno/.env
cat ~/bizuno/.env       # review the values — make sure nothing's blank
```

> **Critical: copy `BIZUNO_KEY` somewhere safe right now.** It
> encrypts every piece of stored PII in Bizuno. If you ever lose the
> Docker volumes, you need this key to decrypt anything you re-import.
> A password manager entry is the right place.

### 5.2 Review the compose file (optional)

The default `docker-compose.yml` is set up for the typical case (TLS
via mounted wildcard, MariaDB internal-only, Caddy on host ports
80/443). If you need a managed external database, want to publish
the MariaDB port for a GUI client, or otherwise change the topology,
edit `docker-compose.yml` now before bringing up. Inline comments
explain each section.

## Phase 6 — Bring up the stack

```bash
cd ~/bizuno
docker compose up -d                # boots all three containers in the background
docker compose ps                   # check status
```

Wait ~30–60 seconds for everything to become healthy. Expected:

```
NAME             IMAGE                             STATUS
bizuno           ghcr.io/phreesoft/bizuno:latest   Up 45s (healthy)
bizuno-caddy     caddy:2-alpine                    Up 45s
bizuno-mariadb   mariadb:11                        Up 55s (healthy)
```

If any container shows `restarting` or `unhealthy`, check its logs:

```bash
docker compose logs --tail=50 bizuno
docker compose logs --tail=50 mariadb
docker compose logs --tail=50 caddy
```

## Phase 7 — Verify + run the Bizuno installer

### 7.1 HTTPS probe

From your local machine (NOT from inside the server):

```bash
curl -I https://bizuno.your-domain.com/
```

Expected response:

```
HTTP/2 200
content-type: text/html; charset=UTF-8
server: Apache/2.4.67 (Debian)
via: 1.1 Caddy
set-cookie: PHPSESSID=...
strict-transport-security: max-age=31536000; includeSubDomains
x-powered-by: PHP/8.2.x
```

Key signals:

| Header | What it confirms |
|---|---|
| `HTTP/2 200` | Cert valid, TLS handshake completed, no redirect loops |
| `via: 1.1 Caddy` | Caddy is proxying (not just answering directly) |
| `server: Apache/...` | Request reached the Bizuno container, not just Caddy |
| `set-cookie: PHPSESSID=...` | PHP ran inside the container; Bizuno responded |

### 7.2 Browser walkthrough

Open `https://bizuno.your-domain.com/` in a browser. Bizuno's
first-run installer wizard appears:

1. **Welcome / DB check** — credentials from `.env` are pre-populated,
   you'll see a "connected" indicator on first load.
2. **Business info** — name, currency, timezone, fiscal year.
3. **Admin user** — email + password (your first login).
4. **Chart of accounts type** — retail / services / single-entity / etc.
5. **Click Install** — schema gets created in MariaDB, ~10 seconds.
6. **Page reloads** to the Bizuno dashboard, logged in as the admin
   you just created.

You're now running production Bizuno.

## Common gotchas

Real symptoms encountered during the first run of this walkthrough,
with fixes:

| Symptom | Cause | Fix |
|---|---|---|
| `psww05srv may not run sudo` | User wasn't added to `sudo` group at install time | Log in as root via `su -` or direct SSH; `usermod -aG sudo youruser` |
| `usermod: command not found` running as the unprivileged user | `usermod` is in `/usr/sbin/` which isn't on the regular-user `$PATH` | Must be run from a root shell, not under `sudo` from a regular shell |
| `docker compose pull` fails on the bizuno image with "denied" | The ghcr.io image hasn't been pushed for this version yet | Build locally (Phase 4) instead of pulling |
| `no configuration file provided: not found` | docker-compose.yml file has a typo — common: `docker-composer.yaml` (PHP-flavored muscle memory) | `mv docker-composer.yaml docker-compose.yml` |
| `HTTP/2 301` infinite redirect loop | Apache inside container sees `HTTPS=off` from Caddy, redirects to https, loops | Ensure your build includes the `.htaccess` + `portalCFG-sample.php` X-Forwarded-Proto fixes (commit `37b752b` or later). Rebuild image: `bash build/docker/build.sh && docker compose up -d --force-recreate bizuno` |
| `Could not resolve host` from the server itself | `/etc/hosts` on Debian has `127.0.1.1 <hostname>` as default; `ping` from the server hits localhost | Test from your local machine, not from the server. The chain still works internally. |
| Caddy fails with `tls: open /etc/ssl/docker/...: no such file or directory` | Cert files at wrong path | Confirm paths in `.env` match where you placed the cert files. `ls -la /etc/ssl/docker/` |
| Cert chain validation error from `curl` | The `.pem` file is just the cert, not the fullchain | Concatenate: `cat cert.pem chain.pem > fullchain.pem` and re-upload |

## Day-2: backups + updates

### Backup the three critical volumes

```bash
docker volume ls | grep bizuno_
# bizuno_bizuno-data       ← uploads, attachments, biz-instance-key.php
# bizuno_bizuno-config     ← portalCFG.php (contains BIZUNO_KEY)
# bizuno_mariadb-data      ← every Bizuno table
# bizuno_caddy-data        ← TLS state (regenerable)
# bizuno_caddy-config      ← Caddy runtime (regenerable)
```

The first three are critical. A simple backup script:

```bash
#!/usr/bin/env bash
# /home/youruser/bizuno/backup.sh
set -euo pipefail
cd /home/$USER/bizuno
TS=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR=/var/backups/bizuno
sudo mkdir -p "$BACKUP_DIR"
for vol in mariadb-data bizuno-data bizuno-config; do
    docker run --rm \
        -v bizuno_${vol}:/data:ro \
        -v "$BACKUP_DIR":/backup \
        alpine tar -czf /backup/${vol}-${TS}.tar.gz -C / data
done
echo "Wrote backups to $BACKUP_DIR with timestamp $TS"
```

Schedule via cron nightly, sync off-host weekly.

### Update Bizuno when a new version lands

```bash
cd ~/bizuno-src
git pull
bash build/docker/build.sh                          # rebuild image with new code

cd ~/bizuno
docker compose up -d --force-recreate bizuno        # restart only bizuno; mariadb + caddy untouched
docker compose logs -f bizuno                       # tail until you see Apache start cleanly
```

If the update changes the database schema, Bizuno runs migrations
automatically on first request after restart. Watch the logs for the
migration messages.

### Restart after `.env` or Caddyfile changes

```bash
cd ~/bizuno
docker compose up -d                                # picks up env / file changes
```

### Stop / wipe everything (destructive)

```bash
docker compose down                                 # stop, preserves volumes
docker compose down -v                              # ALSO deletes the volumes — total wipe
```

## Alternative: Let's Encrypt instead of a wildcard cert

If you'd rather have Caddy provision + renew certs automatically via
ACME, remove the `tls` directive from the Caddyfile entirely:

```caddy
{$BIZUNO_HOSTNAME} {
    # tls {$CADDY_CERT_FILE} {$CADDY_KEY_FILE}      ← delete or comment this line
    reverse_proxy bizuno:80 {
        header_up X-Forwarded-Proto https
        ...
    }
    ...
}
```

Caddy will use its built-in ACME client on first start. Requires:

- The hostname's A-record already pointing at this server (Let's
  Encrypt validates by HTTP-01 challenge on port 80, which this
  stack already exposes).
- Outbound HTTPS allowed (to reach `acme-v02.api.letsencrypt.org`).

You can then drop `CADDY_CERT_FILE` / `CADDY_KEY_FILE` from `.env`
and the cert mount from `docker-compose.yml`.

## See also

- [`build/docker/production/README.md`](https://github.com/phreesoft/bizuno/blob/main/build/docker/production/README.md) — reference docs for the stack files themselves
- [`build/docker/README.md`](https://github.com/phreesoft/bizuno/blob/main/build/docker/README.md) — image-level documentation, env var reference
- [`build/docker/Dockerfile`](https://github.com/phreesoft/bizuno/blob/main/build/docker/Dockerfile) — image definition
- [`build/docker/entrypoint.sh`](https://github.com/phreesoft/bizuno/blob/main/build/docker/entrypoint.sh) — how `portalCFG.php` is generated from env vars on first boot
- [Three Ways to Run It](02-three-ways-to-run-it.md) — the high-level deployment-options overview
- [Administration → Backup and Restore](../05-administration/03-backup-and-restore.md) — Bizuno-level backup mechanics
