---
title: Four Ways to Run It
category: Getting Started
order: 2
status: published
audience: [admin]
last-updated: 2026-05-28
related:
  - 01-getting-started/04-docker-install-walkthrough.md
  - 01-getting-started/05-lamp-install-walkthrough.md
  - 01-getting-started/06-wordpress-install-walkthrough.md
since: 7.3.0
---

# Four Ways to Run It

The same Bizuno code runs four different ways. This is one of Bizuno's
real differentiators — most ERPs lock you to a single delivery model
(usually their SaaS). Bizuno is the same AGPL library underneath
whether you self-host it, ride it inside WordPress, containerize it,
or let PhreeSoft host it. You can start one way and move later.

This page helps you pick. Each path has a full walkthrough linked from
its section.

## The four paths at a glance

| Path | You manage | Best for | Walkthrough |
|---|---|---|---|
| **PhreeSoft Cloud** | Nothing — login and use | Anyone who wants zero ops | _(managed — see below)_ |
| **WordPress plugin** | A WordPress site | Sites already on WP / WooCommerce | [WordPress walkthrough](./06-wordpress-install-walkthrough.md) |
| **Standalone (LAMP/LEMP)** | A Linux + PHP + MySQL host | Full control, no WordPress | [LAMP walkthrough](./05-lamp-install-walkthrough.md) |
| **Docker** | A Linux box with Docker | Dedicated server, repeatable deploys | [Docker walkthrough](./04-docker-install-walkthrough.md) |

## Which one is for you?

**Just want it to work, no servers?** → **PhreeSoft Cloud.** You get a
login at `my.bizuno.com`, PhreeSoft runs the infrastructure, backups,
and upgrades. Pick this unless you have a specific reason to self-host
(data-residency rules, deep customization, an existing server you want
to use).

**Already run WordPress — especially WooCommerce?** → **WordPress
plugin.** Bizuno shares WordPress's database, login, and admin. One
set of users, one backup, and the
[WooCommerce bridge](../07-integration/01-woocommerce-via-bizuno-api.md)
syncs orders/inventory/customers both ways. Least-effort self-host
path.

**Want full control and don't use WordPress?** → **Standalone
LAMP/LEMP.** Bizuno runs on its own (sub)domain served directly by
Apache or Nginx. Most flexibility for hardening (source + data outside
the webroot) and for hosts where you already have a LAMP stack or
cPanel/Plesk.

**Deploying to a dedicated server and want it reproducible?** →
**Docker.** Three containers (Bizuno + MariaDB + a TLS-terminating
Caddy proxy) brought up with one `docker compose up`. Best when the
box is Bizuno's alone and you want clean, repeatable rebuilds.

## Hard requirements per path

| | PhreeSoft Cloud | WordPress | Standalone | Docker |
|---|---|---|---|---|
| **PHP** | — (hosted) | 8.0+ (8.2 rec.) | 8.2+ (Composer floor) | bundled in image |
| **Database** | — (hosted) | WP's MySQL/MariaDB | MySQL 5.6+ / MariaDB 10.2+ | bundled MariaDB 11 |
| **Web server** | — (hosted) | WordPress's | Apache or Nginx | bundled (Caddy → Apache) |
| **You provide** | a browser | a WP 6.5+ site | Linux host + shell | Linux host + Docker |
| **TLS** | included | WordPress's cert | Let's Encrypt / your cert | Caddy auto or mounted cert |
| **Ops burden** | none | low | medium | medium |

All self-host paths need the same PHP extensions — `mbstring`,
`pdo_mysql`, `json`, `curl`, `openssl`, `zip`, `gd`. If any are
missing, Bizuno's installer shows a "setup required" page naming
exactly which.

## Moving between paths

Bizuno's data is a MySQL database plus a data directory (uploads,
attachments, cache). Because every path stores the same shape of data,
moving is mostly a database + files migration — not a re-implementation.

**Easy moves (same data model, just relocate):**

- **Standalone ↔ Docker** — dump the database, copy the data
  directory, restore into the other. Identical schema.
- **WordPress ↔ Standalone** — Bizuno's own tables are the same; the
  WordPress install just prefixes them (`wp_bizuno_`) and shares WP's
  database. Export the `*bizuno_*` tables and the
  `wp-content/uploads/bizuno/` directory, import into a standalone DB +
  data dir.
- **Cloud → self-host** — PhreeSoft can hand you a database dump + data
  archive to restore into any self-hosted path.

**Mind these when migrating:**

- **`BIZUNO_KEY`** encrypts stored PII; **`biz-instance-key.php`** (in
  the data directory) signs session cookies. Carry both to the new
  home or encrypted data won't decrypt and sessions reset. They travel
  with `portalCFG.php` + the data directory respectively.
- **Table prefix** differs between WordPress (`wp_bizuno_`) and
  standalone (none by default) — set `BIZUNO_DB_PREFIX` to match the
  dump you're importing.

**Not easy:** exporting *out* of Bizuno back into QuickBooks or
similar. Bizuno imports *from* those (see
[Migration & Upgrade](../08-migration-and-upgrade/)); the reverse is a
manual report-and-reenter job, same as leaving any ERP.

## PhreeSoft Cloud, briefly

The cloud option is multi-tenant managed hosting run by PhreeSoft.
You log in at the public `my.bizuno.com`, which routes you to whichever
server holds your database and files. There's nothing to install,
patch, or back up — that's the point. Sign up at
[phreesoft.com](https://www.phreesoft.com/). Everything else in this
manual still applies once you're logged in; only the install chapter
is skipped.

## Related

- [What is Bizuno](./01-what-is-bizuno.md)
- [Docker install walkthrough](./04-docker-install-walkthrough.md)
- [LAMP / LEMP install walkthrough](./05-lamp-install-walkthrough.md)
- [WordPress install walkthrough](./06-wordpress-install-walkthrough.md)
- [First-hour walkthrough](./03-first-hour-walkthrough.md)
- [Migration & Upgrade](../08-migration-and-upgrade/)
