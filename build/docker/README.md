# Bizuno on Docker

Container image and dev-stack compose file for running Bizuno in Docker.
The image ships as `ghcr.io/phreesoft/bizuno:VERSION` (and `:latest`),
auto-built by [CI](../../.github/workflows/release.yml) on every `v*` tag
push.

## Quick start (development)

From the repo root:

```bash
docker compose -f build/docker/docker-compose.yml up -d
```

Then open <http://localhost:8080>. The Bizuno installer wizard runs on
first request — fill in business info, click through, you're done. DB
creds are pre-populated from the compose env vars; you can leave them as
the defaults.

To stop:

```bash
docker compose -f build/docker/docker-compose.yml down
```

To wipe everything and start clean (deletes the database + uploaded files):

```bash
docker compose -f build/docker/docker-compose.yml down -v
```

## Production deployment

The image is production-ready, but the bundled `docker-compose.yml` has
dev-friendly defaults that you should change:

1. **Set every secret explicitly** in your environment / `.env`:
   ```bash
   BIZUNO_DB_PASS=<32-char-random>
   BIZUNO_KEY=<16-char-random-alphanumeric>   # CRITICAL: never rotate after first install
   BIZUNO_BIZID=<6-char-business-id>
   MARIADB_ROOT_PASSWORD=<32-char-random>
   ```
2. **Don't run as root.** The image runs Apache as `www-data` already.
3. **Front it with HTTPS.** Either:
   - Put the compose stack behind nginx / Caddy / Traefik for TLS
     termination
   - Or run Apache inside the container with mod_ssl + your own certs
     mounted in
   The shipped image listens on plain HTTP port 80; production needs TLS.
4. **Back up the volumes.** `bizuno-data`, `bizuno-config`, and
   `mariadb-data` all need regular off-host snapshots — losing any of
   them is data loss.
5. **Use a managed DB** if you have one. Comment out the `mariadb`
   service and point `BIZUNO_DB_HOST` at your external database.

## Environment variables

Read by the [entrypoint](./entrypoint.sh) on container start. Only used
to generate `portalCFG.php` the first time the container comes up — once
the file exists (and the `bizuno-config` volume preserves it), env
changes are ignored.

| Variable | Required? | Default | Notes |
|---|---|---|---|
| `BIZUNO_DB_HOST` | yes | — | Database hostname (use the compose service name like `mariadb`) |
| `BIZUNO_DB_NAME` | yes | — | Database name |
| `BIZUNO_DB_USER` | yes | — | Database user |
| `BIZUNO_DB_PASS` | yes | — | Database password |
| `BIZUNO_DB_TYPE` | no | `mysql` | Only `mysql` is supported today |
| `BIZUNO_DB_PREFIX` | no | `""` | Table prefix, useful when sharing a DB with other apps |
| `BIZUNO_KEY` | no | random | **Pin this in prod.** 16 alphanumeric chars. Used to encrypt stored PII; rotating it makes existing encrypted data unrecoverable. |
| `BIZUNO_BIZID` | no | random | 6 alphanumeric chars. Used internally as a multi-business ID. |
| `BIZUNO_DATA_DIR` | no | `/var/www/html/data` | Override BIZUNO_DATA path inside the container. Volume mount must follow. |

If `BIZUNO_DB_HOST` / `_NAME` / `_USER` / `_PASS` are **all** absent, the
entrypoint skips portalCFG.php generation and the web installer runs on
first request. Useful for hand-driven dev stacks; not what you want in CI.

## Volumes

| Mount point | Purpose |
|---|---|
| `bizuno-data:/var/www/html/data` | `BIZUNO_DATA` — uploads, attachments, cache, `biz-instance-key.php`, backups. **Critical to back up.** |
| `bizuno-config:/var/www/html/portalCFG-config` | Reserved for future use (currently portalCFG.php lives at the webroot inside the container; the env-var approach keeps this clean). |
| `mariadb-data:/var/lib/mysql` | Database files. **Critical to back up.** |

## Hardened install (library outside webroot)

The container's `/var/www/html/src/` is technically inside Apache's
DocumentRoot, but Apache only serves files via PHP requests routed
through `index.php` — there's no direct file listing of `src/` exposed
to the web. So this is already a "library-effectively-private" install
by default.

For a tighter pattern (PHP source on a separate volume that Apache can't
serve directly even by mistake), you can layer your own bind mount:

```yaml
services:
  bizuno:
    image: ghcr.io/phreesoft/bizuno:latest
    volumes:
      # Override the image's bundled src/ with your own version on a
      # private volume. Useful when you maintain a local myExt/ tree
      # that you want sync'd into the container without rebuilding.
      - /var/lib/bizuno/src:/var/www/html/src:ro
      - /var/lib/bizuno/data:/var/www/html/data
    environment:
      BIZUNO_DATA_DIR: /var/www/html/data
```

## Building the image locally

The image gets built and pushed to ghcr.io by CI on tag pushes — you
rarely need to build locally. When you do (e.g. testing a local change
before tagging), run from the repo root:

```bash
bash build/docker/build.sh                    # tags as ghcr.io/phreesoft/bizuno:VERSION + :latest
bash build/docker/build.sh --push             # also pushes (needs `docker login ghcr.io`)
IMAGE_NAME=mybizuno bash build/docker/build.sh # custom local tag
```

The build context is the **repo root**, not `build/docker/`. The
Dockerfile pulls `src/`, `scripts/`, `composer.json`, etc. from the
parent directory.

## Available tags

| Tag | What it is |
|---|---|
| `ghcr.io/phreesoft/bizuno:7.4.0` | Specific version (recommended for prod — pinned for reproducibility) |
| `ghcr.io/phreesoft/bizuno:7.4` | Latest 7.4.x patch — auto-updates within minor version |
| `ghcr.io/phreesoft/bizuno:7` | Latest 7.x — be cautious; can include breaking minor bumps |
| `ghcr.io/phreesoft/bizuno:latest` | Most recent tagged release; fine for dev, NOT recommended for prod |

CI manages the `:7.4`, `:7`, and `:latest` tag aliases by re-pushing to
those tags on each release.

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| Container exits immediately on start | Check logs: `docker compose logs bizuno`. Most common: missing required env var. |
| Web installer keeps reappearing | Volume mount for `bizuno-data` is missing — installer's portalCFG.php is regenerated each container restart. |
| "Database connection failed" | Either DB env vars wrong, or `mariadb` service isn't healthy yet — check `docker compose ps` and the mariadb container logs. |
| Decrypted PII shows as garbage after restart | `BIZUNO_KEY` was regenerated. Either pin it via env, or persist `portalCFG.php` (which contains the key). |
| Permission denied writing to data dir | Volume came up with wrong ownership; entrypoint should fix this — if it doesn't, run `docker compose exec bizuno chown -R www-data:www-data /var/www/html/data` |
| `ghcr.io/phreesoft/bizuno: pull access denied` | Repository is public; if you see this with a private fork, run `docker login ghcr.io` with a PAT that has `read:packages`. |

For deeper issues, the trap/trace file in `BIZUNO_DATA` is the canonical
source of truth — `docker compose exec bizuno cat /var/www/html/data/trace.txt`.
