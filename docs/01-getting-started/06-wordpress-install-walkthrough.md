---
title: WordPress Install Walkthrough
category: Getting Started
order: 6
status: published
audience: [admin]
last-updated: 2026-05-28
related:
  - 01-getting-started/02-three-ways-to-run-it.md
  - 01-getting-started/05-lamp-install-walkthrough.md
  - 07-integration/01-woocommerce-via-bizuno-api.md
since: 7.3.0
---

# WordPress Install Walkthrough

A complete walkthrough for running Bizuno **inside WordPress** via the
`bizuno-accounting` plugin. This is the right path when:

- You already have (or want) a **WordPress site** — especially with
  **WooCommerce** — and want accounting/ERP to share the same login,
  database, and admin.
- You'd rather click "Install Plugin" than provision a vhost and run
  `composer install`.
- You want the
  [WooCommerce ↔ Bizuno bridge](../07-integration/01-woocommerce-via-bizuno-api.md)
  (orders, inventory, customers, prices syncing both ways).

End state: a **Bizuno** entry in your WordPress admin, the app running
full-screen at `https://your-site.com/bizuno`, using WordPress's own
MySQL database (Bizuno tables prefixed `wp_bizuno_`).

## How the two plugins fit together

Bizuno-on-WordPress is **two** plugins, by design:

| Plugin | Role | Where it comes from |
|---|---|---|
| **Bizuno Accounting** (`bizuno-accounting`) | Thin boot loader — adds the WP menu, wires WP's DB creds into Bizuno, runs the portal. ~250 lines. | [WordPress.org plugin directory](https://wordpress.org/plugins/bizuno-accounting/) |
| **Bizuno** library (`bizuno-wp`) | The actual ERP — 95% of the code, bundled with its `vendor/` dependencies. | Downloaded automatically by the first plugin (from `bizuno.com`), **not** on WordPress.org |

The reason for the split: the full library + `vendor/` is too large
and updates too often to live comfortably in the WordPress.org SVN
directory. So the small loader ships there, and on activation it
offers to fetch the library plugin for you. **No license key
required** — the library is publicly downloadable.

## What you'll need before you start

1. A working **WordPress 6.5+** site you can log into as an admin.
2. **PHP 8.0+** on the host (8.2 recommended).
3. The usual WP MySQL/MariaDB database (Bizuno reuses it — no separate
   database to create).
4. Outbound HTTPS from the server (so the loader can download the
   library zip from `bizuno.com`).

## Phase 1 — Install the loader plugin

In WP admin:

1. **Plugins → Add New**.
2. Search **"Bizuno Accounting"**.
3. **Install Now**, then **Activate**.

On activation the plugin:

- Schedules a daily cron event (`bizuno_daily_event`) — used for
  period auto-roll.
- Creates a reserved WordPress **page** with slug `bizuno`. This is
  the URL the app runs at; logged-in authorized users get the
  full-screen app, everyone else sees a "reserved for authorized
  users" placeholder.

## Phase 2 — Pull in the Bizuno library

After activation you'll see an admin notice and a **GET BIZUNO** menu
item (because the library plugin isn't present yet):

1. Click **GET BIZUNO** in the admin menu (or the link in the notice).
2. On that page click **Get Bizuno Now**.
3. The loader downloads `bizuno-wp.zip` from `bizuno.com`, installs it
   as a plugin, and activates it.

When it finishes you get a **"Go to Bizuno Dashboard →"** button and
the admin menu changes from **GET BIZUNO** to **Bizuno**.

> **If the automatic download fails** (locked-down host, no outbound
> HTTPS): download `bizuno-wp.zip` manually from
> [bizuno.com/downloads/latest/bizuno-wp.zip](https://bizuno.com/downloads/latest/bizuno-wp.zip),
> then **Plugins → Add New → Upload Plugin**, choose the zip, install
> and activate. The result is identical.

## Phase 3 — Run the database installer

With both plugins active, WP admin shows a notice:

> "You're just about ready to get started… Click HERE to open a new
> page to run the database installer script."

1. Click that link (it opens `https://your-site.com/bizuno`).
2. The Bizuno install wizard appears. Unlike the standalone path, the
   **database fields are skipped** — Bizuno already has WordPress's DB
   credentials (`DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASSWORD` from
   `wp-config.php`) and creates its tables with a `wp_bizuno_` prefix
   right inside the WordPress database.
3. Enter:
   - **Admin email + password** — your first Bizuno login.
   - **Business title** and **base currency**.
4. **Click Install.**

Bizuno creates the schema and logs you in to the dashboard. Done.

> **Bookmark `https://your-site.com/bizuno`** — that's the app URL.
> Authorized, logged-in users can also reach it from the **Bizuno
> Accounting** item in the WordPress admin-bar profile (top-right)
> dropdown.

## Phase 4 — Authorize users

Being a WordPress user doesn't grant Bizuno access — you opt each user
in explicitly:

1. **Users → All Users**, edit the user (or your own profile).
2. Check the **"Allow access to: \<Your Business\>"** box.
3. Pick a Bizuno role, **Update**.

The user then sees **Bizuno Accounting** in their admin-bar profile
menu after logging in.

> Non-admin users only see the admin bar if WordPress's "Show Toolbar
> when viewing site" is enabled for them — that's how they reach the
> Bizuno link.

## What the plugin wires up (for the curious)

The loader's `portalCFG.php` maps WordPress concepts onto Bizuno's
config constants:

| Bizuno constant | Bound to |
|---|---|
| `BIZUNO_DB_CREDS` | WP's `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASSWORD` |
| `BIZUNO_DB_PREFIX` | `{$wpdb->prefix}bizuno_` (e.g. `wp_bizuno_`) |
| `BIZUNO_DATA` | `wp-content/uploads/bizuno/` |
| `BIZUNO_URL_AJAX` | WP's `admin-ajax.php?action=bizuno_ajax` |
| `BIZUNO_URL_PORTAL` | `home_url()/bizuno` |

Because Bizuno shares WordPress's database and uploads directory, a
standard **WordPress backup** (database + `wp-content/uploads/`)
already captures your Bizuno data. No separate backup job needed —
though you should still verify your WP backup includes both.

## Common gotchas

| Symptom | Cause | Fix |
|---|---|---|
| Stuck on **GET BIZUNO**, notice keeps saying the library is required | `bizuno-wp` didn't download/activate | Use the manual-upload fallback in Phase 2 |
| **Get Bizuno Now** errors: "Failed to download" | Host blocks outbound HTTPS | Download the zip on another machine and upload via **Add New → Upload Plugin** |
| Visiting `/bizuno` shows the "reserved for authorized users" text | You're not logged in, or your user isn't authorized | Log in; then authorize the user (Phase 4) |
| "Setup required" page listing missing PHP extensions | Host lacks `gd` / `zip` / `curl` etc. | Ask the host to enable them, then revisit `/bizuno` |
| Bizuno menu missing entirely after activating both | Caching plugin served a stale admin page | Clear WP + object cache, hard-refresh admin |
| Bizuno URLs come out `http://` on an HTTPS site | Reverse proxy not forwarding the protocol | Ensure the proxy/LB sets `X-Forwarded-Proto: https` |

## Uninstalling (destructive)

Deleting the **Bizuno Accounting** plugin through WP runs its uninstall
hook, which **drops every `wp_bizuno_*` table and deletes
`wp-content/uploads/bizuno/`** — a full wipe of your accounting data.
Back up first if there's any chance you'll want it back. Merely
*deactivating* the plugin leaves the data intact.

## See also

- [Three Ways to Run It](./02-three-ways-to-run-it.md) — pick the right path
- [LAMP / LEMP Install Walkthrough](./05-lamp-install-walkthrough.md) — standalone, no WordPress
- [WooCommerce via bizuno-api](../07-integration/01-woocommerce-via-bizuno-api.md) — the WP/WooCommerce bridge
- [First-hour walkthrough](./03-first-hour-walkthrough.md) — what to do once you're logged in
- [Administration → Roles and Security](../05-administration/01-roles-and-security.md)
