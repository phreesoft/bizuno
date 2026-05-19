# Bizuno build / distribution variants

This directory holds the per-variant glue used to produce Bizuno
distributions from the canonical source in [`../src/`](../src/).

The repo's `src/` directory is identical across every variant — it's the
Bizuno application itself. What changes between variants is the *entry
point* and the *delivery format*:

| Variant | Entry point | Delivery |
|---|---|---|
| **Standalone** (current repo root) | `../index.php` | `git clone` or composer create-project |
| **Composer** | `../index.php` | `composer create-project phreesoft/bizuno` |
| **Zip** (LAMP/WAMP drop-in) | `../index.php` | release zip with vendor/ baked in |
| **WordPress plugin** | `wordpress/bizuno-wp.php` | upload via WP admin, or wordpress.org |
| **Docker** (future) | container `ENTRYPOINT` | Docker Hub / ghcr.io image |
| **NextCloud app** (future) | NextCloud's app loader | NextCloud app zip |

The standalone install is the **default** — it's what the repo at HEAD
serves to anyone running `git clone phreesoft/bizuno && cd bizuno && composer install`
and pointing a web server at the directory.

All other variants are **build artifacts** produced by CI on tag pushes,
described per-subdirectory below.

## Variant directories

| Path | Purpose |
|---|---|
| [`wordpress/`](./wordpress/) | WordPress plugin glue. Plugin header file, WordPress.org readme, banner/icon assets, build script (TBD Phase 3). |
| `composer/` (TBD) | Composer-create-project artifact builder. Phase 3. |
| `zip/` (TBD) | LAMP/WAMP zip builder. Bundles vendor/. Phase 3. |
| `docker/` (TBD) | Dockerfile, docker-compose, entrypoint. Phase 4. |
| `nextcloud/` (TBD) | NextCloud app manifest + glue. Phase 5. |

## Build outputs

Future per-variant `build.sh` scripts write to `build/output/<variant>/`
which is `.gitignore`d. Release builds run via CI on tag push (see
[`../.github/workflows/`](../.github/workflows/) — release workflow TBD).

## Adding a new variant

1. `mkdir build/<variant-name>/`
2. Drop in variant-specific glue (entry-point shim, manifest files, etc.)
3. Write `build/<variant-name>/build.sh` that assembles `src/` + glue +
   composer-installed vendor/ into the target's expected layout under
   `build/output/<variant-name>/`
4. Add a `<variant-name>` entry to the release workflow's matrix
5. Document in `build/<variant-name>/README.md`
