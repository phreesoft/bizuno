# Bizuno WordPress plugin variant

This directory holds the WordPress-plugin glue. The published artifact —
the single Bizuno WordPress plugin distributed via wordpress.org — is
**built** from this directory plus [`../../src/`](../../src/) plus a
composer-installed `vendor/`. The repo root itself is NOT a working
WordPress plugin; that's by design (Phase 2 of the architecture
refactor).

## What's here

| File | Purpose |
|---|---|
| `bizuno-wp.php` | Plugin entry file. WordPress reads its header to register the plugin; the file's body bootstraps Bizuno against `src/`. |
| `readme.txt` | WordPress.org plugin-directory readme (their custom format). Stable tag, contributors, tags, description, FAQ. |
| `build.sh` *(TBD Phase 3)* | Assembles the plugin zip: copies `bizuno-wp.php` + `readme.txt` here, copies `src/` into a `src/` subdir inside the plugin, runs `composer install --no-dev` to populate `vendor/`, copies WP-only `yahniselsts/plugin-update-checker` (manually vendored), produces `build/output/wordpress/bizuno-wp-VERSION.zip`. |

## How the WordPress plugin assembles (target layout)

```
bizuno-wp/                                ← unzipped plugin dir
├── bizuno-wp.php                         ← WP plugin entry (from this dir)
├── readme.txt                            ← WordPress.org format (from this dir)
├── src/                                  ← copied from ../../src/
│   ├── bizunoCFG.php
│   ├── controllers/
│   ├── model/
│   ├── view/
│   ├── locale/
│   ├── portal/
│   └── VERSION
└── vendor/                               ← composer install --no-dev output
    ├── autoload.php
    └── ...
```

When WP loads `bizuno-wp.php`, the plugin defines path constants pointing
at its own `src/` and `vendor/` subdirectories, then delegates the rest
of bootstrap to `src/portal/controller.php` exactly the same way the
standalone install does.

## Auto-updates

`bizuno-wp.php` currently uses `yahniselsts/plugin-update-checker`
(manually vendored under `../../vendor/yahniselsts/`) to handle plugin
auto-updates from a Bizuno-hosted update JSON at
`https://bizuno.com/downloads/bizuno-wp.json`. After Phase 3, the build
script pulls that package into the plugin zip explicitly — it's WP-only,
not part of the standalone composer.json.

## Relationship to bizuno-accounting

`bizuno-accounting` (sibling repo) is a separate WP plugin that adds WP
admin UI integration on top of `bizuno-wp`. As of Phase 2, both remain
separate; the bizuno-accounting plugin continues to work against the
*existing* wordpress.org bizuno-wp release. When Phase 3 produces a new
bizuno-wp release built from this directory, bizuno-accounting's
`portalCFG.php` is updated in lockstep to point `BIZUNO_FS_LIBRARY` at
`WP_PLUGIN_DIR.'/bizuno-wp/src/'` (note the new `src/` subdir).

Eventually (Phase 2.1, after Phase 3 lands) bizuno-accounting can fold
into this build/ directory — one combined WP plugin instead of two.
