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

| Path | Status | Distribution channel |
|---|---|---|
| [`wordpress/`](./wordpress/) | **active** | GitHub Releases + WordPress.org SVN (auto-mirrored) |
| [`zip/`](./zip/) | **active** | GitHub Releases |
| composer | n/a | Packagist — `composer create-project phreesoft/bizuno`. No build script needed; the git repo itself IS the artifact. Tag a release and packagist picks it up. |
| `docker/` (TBD) | Phase 4 | Docker Hub / ghcr.io |
| `nextcloud/` (TBD) | Phase 5 | NextCloud app store |

## Build outputs

Per-variant `build.sh` scripts write to `build/output/<variant>/` which is
in `.gitignore`. Each script is self-contained — run from anywhere:

```bash
bash build/zip/build.sh        # → build/output/bizuno-VERSION-zip.zip
bash build/wordpress/build.sh  # → build/output/wordpress/bizuno-wp-VERSION.zip
```

Both scripts run `composer install --no-dev --optimize-autoloader` against
a fresh staging directory; nothing in your working tree is modified.

## Release workflow

CI builds happen automatically on `git tag v*` push via
[`../.github/workflows/release.yml`](../.github/workflows/release.yml).
The workflow:

1. Resolves the version from the pushed tag (strips the leading `v`)
2. Verifies `src/VERSION` matches the tag (fails fast otherwise)
3. Builds zip and wordpress variants in parallel
4. Creates a GitHub Release with generated changelog notes, attaches both artifacts
5. (Optional) Pushes the WordPress plugin to wordpress.org SVN if `WP_SVN_USER` / `WP_SVN_PASS` secrets are configured

To release:

```bash
# bump src/VERSION first
echo "7.4.0" > src/VERSION
git commit -am "Release 7.4.0"
git push origin main

# tag and push
git tag v7.4.0
git push origin v7.4.0
# … CI runs, ~3 minutes to artifacts, ~5 minutes to wordpress.org if SVN push enabled
```

## Adding a new variant

1. `mkdir build/<variant-name>/`
2. Drop in variant-specific glue (entry-point shim, manifest files, etc.)
3. Write `build/<variant-name>/build.sh` that assembles `src/` + glue +
   composer-installed vendor/ into the target's expected layout under
   `build/output/<variant-name>/`
4. Add a `<variant-name>` entry to the release workflow's matrix
5. Document in `build/<variant-name>/README.md`
