# Bizuno on NextCloud

A small NextCloud app that adds **Bizuno** to your NextCloud
navigation. Clicking it loads your Bizuno installation inside a
sandboxed iframe — so users get one launching point for their daily
NextCloud + ERP workflow.

## What this app is

- ~200 lines of glue: a navigation entry, an iframe page, and an
  admin-settings field for the Bizuno URL.
- A standard NextCloud-app-store-shaped tarball produced by
  `build.sh` on every release.

## What it is *not*

- **It does not include Bizuno itself.** Bizuno runs as its own
  service (the [official Docker image](../docker/README.md) is the
  shortest path). This app only frames it.
- **No SSO.** NextCloud users still see Bizuno's login page on first
  request; logging out of NextCloud doesn't log out of Bizuno. Single
  sign-on is a roadmap item — see "What's next" below.
- **No data integration.** Files, contacts, calendars in NextCloud
  are not synced to or from Bizuno. The two systems run side by side.

## Quick start

### Production install (when published)

1. NextCloud admin → Apps → search **Bizuno**.
2. Click *Download and enable*.
3. Settings → Administration → **Bizuno ERP** → set the URL of your
   Bizuno install (e.g. `https://bizuno.example.com/`).
4. Users will now see *Bizuno* in their top navigation.

### Manual install (now)

```bash
bash build/nextcloud/build.sh
# produces  build/output/nextcloud/bizuno-<VERSION>.tar.gz  (~20K)

# Drop into NextCloud's apps directory:
tar -xzf build/output/nextcloud/bizuno-*.tar.gz -C /var/www/nextcloud/apps/

# Enable and configure:
sudo -u www-data php /var/www/nextcloud/occ app:enable bizuno
sudo -u www-data php /var/www/nextcloud/occ config:app:set \
    bizuno bizuno_url --value="https://bizuno.example.com/"
```

## Full test stack (recommended for development)

`docker-compose.test.yml` in this directory brings up NextCloud +
Bizuno together — convenient when iterating on the launcher app or
just trying it out:

```bash
docker compose -f build/nextcloud/docker-compose.test.yml up -d

# Wait ~30s for both stacks to come up healthy.
# Then build + install the launcher:
bash build/nextcloud/build.sh
docker cp build/output/nextcloud/bizuno-*.tar.gz nc-test:/tmp/
docker exec nc-test bash -c 'tar -xzf /tmp/bizuno-*.tar.gz -C /var/www/html/apps/'
docker exec -u www-data nc-test php occ app:enable bizuno
docker exec -u www-data nc-test php occ config:app:set \
    bizuno bizuno_url --value="http://localhost:8080/"
```

Then:

- **NextCloud**: <http://localhost:8090> (admin / `bizuno-nc-admin-pw`)
- **Bizuno**: <http://localhost:8080> (web installer on first request)

Click *Bizuno* in the NextCloud top nav and you'll see it framed.

Reset everything: `docker compose -f build/nextcloud/docker-compose.test.yml down -v`.

## How it works

```
┌────────────────────────────┐
│  NextCloud (apps/bizuno)   │
│                            │
│  PageController::index() ──┼──► <iframe src="https://bizuno.example.com/">
│                            │
│  CSP allows framing the    │
│  configured Bizuno domain  │
└────────────────────────────┘
```

The PHP is intentionally minimal:

| File | What it does |
|---|---|
| `appinfo/info.xml` | App store manifest. Lists nav entry, settings, version, deps. |
| `appinfo/routes.php` | Single GET route for the iframe page. |
| `lib/AppInfo/Application.php` | App bootstrap. Currently empty — placeholder for future hooks. |
| `lib/Controller/PageController.php` | Renders the iframe template; rebuilds CSP to allow framing the configured Bizuno domain. |
| `lib/Settings/Admin.php` | Renders the URL config form. |
| `lib/Settings/AdminSection.php` | Adds *Bizuno ERP* entry to admin sidebar. |
| `templates/main.php` | The iframe (or empty-state placeholder). |
| `templates/settings/admin.php` | URL config field with live-save JS. |
| `css/main.css` | Stretches iframe to fill the content area, hides the per-app sidebar Bizuno doesn't need. |
| `js/admin.js` | Persists URL changes via `OCP.AppConfig.setValue`. |
| `img/app.svg`, `img/app-dark.svg` | Nav + admin-sidebar icons (themed / fixed-dark). |

## Distribution

CI (`.github/workflows/release.yml`) builds the tarball on every `v*`
tag push and attaches it to the GitHub Release. Two delivery paths:

1. **GitHub Releases** — users download the `.tar.gz` and install via
   `occ`. Always current.
2. **apps.nextcloud.com** — for "one-click install" inside NextCloud,
   we upload the tarball to the developer portal at
   <https://apps.nextcloud.com/developer/apps/releases/new>. Currently
   a manual step per release; can be automated via the [app store
   API](https://nextcloudappstore.readthedocs.io/en/latest/restapi.html)
   later if release cadence picks up.

The app store also supports GPG-signed automated releases — we can
wire that in once a signing key is in place.

## NextCloud version compatibility

| NC version | Status |
|---|---|
| 28 | Targeted minimum — `info.xml` declares `min-version="28"` |
| 29, 30 | Targeted — used during development |
| 31 (when shipped) | Bump `max-version` after verifying iframe + CSP still work |

Older NextCloud versions (≤27) won't install — the dependency
declaration in `info.xml` makes the app store hide them.

## What's next (roadmap)

These are deliberate omissions from the launcher; flagged so users
know what to expect:

- **Single sign-on.** Two practical paths:
  - NextCloud's `user_oidc` + Bizuno as OIDC client (preferred — uses
    standard protocols)
  - Custom shared-secret token in the iframe URL, validated by Bizuno
    on a hook (faster to ship; less standard)
- **NextCloud Files ↔ Bizuno attachments.** Mount the user's
  Bizuno data dir as an external storage in NextCloud, or have
  Bizuno write attachments through NextCloud's WebDAV.
- **Contacts/Calendar sync.** Two-way sync between NextCloud Contacts
  and Bizuno's contacts module via CardDAV/CalDAV.

Each is its own scoped piece of work, not bundled into this initial
launcher.

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| "Bizuno" nav entry not visible | App not enabled. `occ app:list \| grep bizuno` — if it's under "Disabled:", enable it. |
| Iframe shows "refused to connect" | NextCloud's CSP blocking the frame. Confirm `bizuno_url` is set and matches the exact origin Bizuno is served from (including trailing slash, https vs http). |
| Iframe shows blank with console error about X-Frame-Options | Bizuno itself sending `X-Frame-Options: DENY`. Check your reverse proxy / Apache config for the Bizuno install — it shouldn't set this if you want NC to frame it. |
| Bizuno session immediately drops on link click | Browser blocking 3rd-party cookies. Bizuno on a different origin than NextCloud is a 3rd-party context. Move Bizuno to a subdomain of NextCloud (`bizuno.example.com` if NextCloud is at `cloud.example.com`) and the cookie becomes 1st-party. |
| Admin URL field doesn't save | Check browser console — `OCP.AppConfig` should be available. If not, NC version is too old (need 28+). |
