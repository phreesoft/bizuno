# Bizuno Accounting — WordPress plugin variant

This directory holds the WordPress-plugin glue. The published artifact —
the **bizuno-accounting** plugin on
[wordpress.org/plugins/bizuno-accounting](https://wordpress.org/plugins/bizuno-accounting/)
— is built from these files plus [`../../src/`](../../src/), the repo's
`scripts/` directory, and a composer-installed `vendor/`. The result is
a single self-contained zip; no sibling library plugin needed.

## What's here

| File | Purpose |
|---|---|
| `bizuno-accounting.php` | Plugin entry. WordPress reads its header for plugin metadata; the class body wires up admin menu, ajax, front-end /bizuno redirect, daily cron, activation/uninstall hooks. |
| `portalCFG.php` | Bizuno path + URL constants. Everything points inside the plugin (`<plugin>/src/`, `<plugin>/vendor/`, `<plugin>/scripts/`). Required `BIZUNO_DB_CREDS` come from WP's `DB_*` constants. |
| `portalAPI.php` | Direct file-serving entry point — reachable as `wp-content/plugins/bizuno-accounting/portalAPI.php`. Bizuno's `BIZUNO_URL_API` and `BIZUNO_URL_FS` constants point at it. Bootstraps WP (`wp-load.php`) and hands off to `portalCtl`. |
| `hostModel.php` | WP-specific host overrides — `portal_date()` (uses `wp_date()` for timezone correctness) and `hostMail` (sends through `wp_mail()`). Loaded by Bizuno's core when a WP-shaped host is detected. |
| `readme.txt` | wordpress.org plugin-directory readme. Stable tag, screenshots, FAQ, changelog. WP uses the Stable tag line to pick which version trunk users actually get. |
| `icon_16.png`, `bizuno.png` | Admin-menu and plugin-directory icons. |
| `build.sh` | Assembles the release zip. Stages a `bizuno-accounting/` tree containing all the above plus `src/`, `scripts/`, and a `composer install --no-dev` `vendor/`, then zips. |

## Target layout (inside the zip)

```
bizuno-accounting/                       ← unzipped plugin dir, matches wp.org slug
├── bizuno-accounting.php                ← plugin entry (header + WP hooks)
├── portalCFG.php                        ← Bizuno path/URL constants
├── portalAPI.php                        ← direct API entry point
├── hostModel.php                        ← WP-specific overrides
├── readme.txt                           ← wordpress.org-format readme
├── icon_16.png, bizuno.png              ← icons
├── LICENSE                              ← AGPL-3.0
├── src/                                 ← Bizuno PHP library (from ../../src/)
│   ├── bizunoCFG.php
│   ├── controllers/, model/, view/, locale/, portal/
│   └── VERSION
├── scripts/                             ← third-party UI assets (from ../../scripts/)
│   ├── jquery-easyui/
│   ├── jQueryUI/
│   ├── fancyfileuploader/
│   └── …
└── vendor/                              ← composer install --no-dev result
    ├── autoload.php
    └── …
```

## How it works at runtime

1. WP loads `bizuno-accounting.php` on every request. The plugin header
   gives WP the metadata it shows in the admin Plugins screen.
2. The `bizuno_accounting` class constructor wires up actions: `init`
   (boot Bizuno globals via `portalCFG.php`), `admin_menu` (Bizuno entry
   in WP sidebar), `template_redirect` (intercept `/bizuno` page and
   hand control to `\bizuno\portalCtl` for logged-in users), `wp_ajax_*`
   (Bizuno AJAX endpoint).
3. `portalCFG.php` runs on first `init` call. It defines the
   `BIZUNO_FS_LIBRARY`, `BIZUNO_FS_ASSETS`, `BIZUNO_DB_CREDS`, etc.
   constants and `require`s Bizuno's core (`src/portal/controller.php`).
4. From there Bizuno runs identically to the standalone install. The
   only WP-specific touchpoints are `hostMail` (outbound mail uses
   `wp_mail()`), `portal_date()` (timezone via WP), and the
   strip-slashes flag in `portalCFG.php`.

## Updates

WordPress.org's standard plugin-update channel. The release workflow
(`../../.github/workflows/release.yml`) builds the zip on every `v*`
tag push and mirrors it to `plugins.svn.wordpress.org/bizuno-accounting`
(both `trunk/` and a new `tags/<VERSION>/`); wordpress.org's update
crawler picks up the new Stable tag and serves updates to user sites
through WP admin → Updates.

The third-party `plugin-update-checker` library that earlier releases
bundled is no longer present — wp.org does the update notification
work directly.

## Local build / install loop

```bash
# Build the zip from the repo root:
bash build/wordpress/build.sh
# → build/output/wordpress/bizuno-accounting-VERSION.zip  (~13 MB)

# Install on a test WP site:
#   WP admin → Plugins → Add New → Upload Plugin → pick the zip → Install Now → Activate
#
# After activation:
#   - "Bizuno" appears in the WP admin sidebar.
#   - The /bizuno page is auto-created on first activation.
#   - First visit to /bizuno (or the AJAX endpoint) runs the installer wizard
#     to set up Bizuno's DB tables under wp_bizuno_*.
```

## Migrating from previous releases

Pre-7.3.9 installs used **two** plugins:

- `bizuno-wp/` (library + auto-updater)
- `bizuno-accounting/` (WP admin glue, depended on bizuno-wp)

7.3.9 collapses both into the single `bizuno-accounting` plugin. The
WordPress update flow takes care of the install side — users will see
the new bigger plugin update through WP admin and can deactivate the
old `bizuno-wp` plugin afterward. Existing Bizuno DB tables
(`wp_bizuno_*`) and `wp-uploads/bizuno/` data files are preserved
across the transition.

## SVN repository slug

`bizuno-accounting` — already approved on wordpress.org. The release
workflow pushes to `https://plugins.svn.wordpress.org/bizuno-accounting`
using the `WP_SVN_USER` / `WP_SVN_PASS` repository secrets.
