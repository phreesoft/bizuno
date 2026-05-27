#!/usr/bin/env bash
#
# Build the LAMP/WAMP drop-in zip artifact.
#
# Assembles a bizuno-VERSION-zip.zip containing the full repo source plus a
# composer-installed vendor/ directory, ready for users to unzip into their
# webroot and run via Apache/Nginx + PHP-FPM + MariaDB without ever touching
# composer themselves. The bundled vendor/ is the difference from the
# composer-create-project install path.
#
# Run from the repo root:
#   bash build/zip/build.sh
#
# Output: build/output/zip/bizuno-VERSION-zip.zip
#
# Environment / requirements:
#   - bash, composer, zip, rsync (or cp)
#   - composer.json + composer.lock checked into the repo
#   - src/VERSION file present (read for the version stamp)

set -euo pipefail

# Resolve repo root regardless of cwd
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

VERSION="$(cat src/VERSION)"
STAGING="$REPO_ROOT/build/output/zip-staging"
# Per-variant output dir (matches the pattern used by build/wordpress/build.sh
# and build/nextcloud/build.sh). The release.yml workflow's upload-artifact
# step expects each variant's artifacts under build/output/<variant>/* —
# putting the zip directly in build/output/ made it invisible to upload.
OUTPUT_DIR="$REPO_ROOT/build/output/zip"
ZIP_NAME="bizuno-${VERSION}-zip.zip"

echo "→ Building $ZIP_NAME from $REPO_ROOT (VERSION=$VERSION)"

# Clean slate — build/output/zip-staging is throwaway each run
rm -rf "$STAGING"
mkdir -p "$STAGING" "$OUTPUT_DIR"

# Files / dirs that get shipped to end users in the zip variant.
# This mirrors what a standalone install needs: the entry shim at root,
# the canonical src/, web-served static assets (scripts/, favicon, .htaccess),
# the install config template, composer manifest+lock, project metadata,
# and a top-level INSTALL.txt with the unzip-and-go instructions.
SHIP=(
    "src"
    "scripts"
    "index.php"
    "portalCFG-sample.php"
    ".htaccess"
    "favicon.ico"
    "composer.json"
    "composer.lock"
    "README.md"
    "LICENSE"
)

for entry in "${SHIP[@]}"; do
    if [ ! -e "$entry" ]; then
        echo "  ✗ missing source: $entry" >&2
        exit 1
    fi
    # Use cp -a so symlinks, perms, and timestamps survive
    cp -a "$entry" "$STAGING/"
done

# Composer install in the staging dir — pulls non-dev deps only.
# --optimize-autoloader: produces classmap → faster cold-start in prod.
# --no-progress: cleaner CI logs.
# --no-interaction: never prompts for the optional setasign/fpdi_pdf-parser auth.
echo "→ composer install (production)"
( cd "$STAGING" && composer install \
    --no-dev \
    --optimize-autoloader \
    --no-progress \
    --no-interaction \
    --prefer-dist )

# ─── Strip junk before zipping ───────────────────────────────────────────────
# These files don't break a LAMP install (Linux handles spaces + dotfiles
# fine) but they bloat the archive, look unprofessional, and would block
# wp.org publication of the same source — keep all variant outputs clean.
echo "→ trimming dev metadata + macOS leftovers"

# macOS / Windows / VCS metadata anywhere in the tree.
find "$STAGING"        -name '.DS_Store' -delete                       2>/dev/null || true
find "$STAGING"        -name 'Thumbs.db' -delete                       2>/dev/null || true
find "$STAGING"        -name '.git*'     -prune -exec rm -rf {} +      2>/dev/null || true

# Composer dep noise that bloats vendor/ without runtime value.
find "$STAGING/vendor" -name 'tests'     -type d -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING/vendor" -name 'docs'      -type d -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING/vendor" -name 'examples'  -type d -prune -exec rm -rf {} + 2>/dev/null || true

# macOS-Finder "Foo copy.json" duplicates accidentally committed in
# src/locale/en_US/reports/. The real files are the un-suffixed versions.
find "$STAGING/src" -type f -name '* copy.*' -delete                   2>/dev/null || true

# Vendor-supplied reference docs whose directory/file names contain
# spaces — XSD schemas for Walmart marketplace, spec docs for Amazon.
# Not loaded at runtime (the funnels construct XML directly), purely
# documentation that came with the SDK distributions.
rm -rf "$STAGING/src/controllers/api/funnels/ifWalmart/API-V2"         2>/dev/null || true
rm -rf "$STAGING/src/controllers/api/funnels/ifAmazon/source"          2>/dev/null || true

# Defensive: warn if any residual badly-named file survives in src/.
BAD=$(find "$STAGING/src" -name '* *' 2>/dev/null || true)
if [ -n "$BAD" ]; then
    echo "  ⚠ residual badly-named files in src/ (will still unzip fine but worth cleaning up):" >&2
    echo "$BAD" | sed 's|^|    |' >&2
fi

# Drop a user-facing install note at the staging root
cat > "$STAGING/INSTALL.txt" <<'INSTALL'
Bizuno ERP — LAMP/WAMP drop-in install
========================================

You're holding the "zip" variant. Dependencies are already installed —
no composer or command-line work required on your server.

QUICK START
-----------

1. Unzip this archive into your web server's document root, OR into a
   subdirectory that Apache/Nginx serves (e.g. /var/www/html/bizuno/).
2. Make sure the web server user (typically www-data, apache, or nginx)
   can WRITE to the directory — the installer creates portalCFG.php and
   a BIZUNO_DATA/ directory on first run.
3. Create an empty MySQL or MariaDB database with a dedicated user that
   has CREATE / ALTER / SELECT / INSERT / UPDATE / DELETE privileges
   on that database.
4. Point a browser at the install URL. You'll see the Bizuno installer
   wizard — fill in DB credentials, business info, and admin login.

That's it. Skip nothing in the wizard; some choices are permanent.

HARDENED INSTALL (optional)
---------------------------

To keep the PHP source out of any directory Apache can serve directly:

1. Unzip the archive to a private location, e.g. /var/www/.../private/bizuno/
2. Copy these files INTO your webroot:
     - index.php
     - portalCFG-sample.php  (the installer will copy this to portalCFG.php)
     - .htaccess
     - favicon.ico
     - scripts/  (entire directory; it has no PHP)
3. Edit portalCFG-sample.php (or write portalCFG.php directly) to add:
     define('BIZUNO_FS_LIBRARY', '/var/www/.../private/bizuno/src/');
     define('BIZUNO_FS_ASSETS',  '/var/www/.../private/bizuno/vendor/');
     define('BIZUNO_DATA',       '/var/www/.../private/bizuno/data/');
4. Run the installer as normal.

See https://bizuno.com/docs/ for full installation, configuration, and
operation guides.

SUPPORT
-------

  Documentation : https://bizuno.com/docs/
  Forum         : https://bizuno.com/forum/
  Issues / bugs : https://github.com/phreesoft/bizuno/issues
  Commercial    : https://phreesoft.com/support/

LICENSE
-------

Bizuno ERP is released under the GNU Affero General Public License v3.
See LICENSE for the full text.
INSTALL

# Zip it. We zip the staging contents (not the staging dir itself) so users
# get a flat tree on unzip.
echo "→ zipping → $OUTPUT_DIR/$ZIP_NAME"
( cd "$STAGING" && zip -rq "$OUTPUT_DIR/$ZIP_NAME" . -x '*.DS_Store' )

# Report size; useful for CI logs and for spotting accidental bloat
SIZE_BYTES=$(stat -f%z "$OUTPUT_DIR/$ZIP_NAME" 2>/dev/null || stat -c%s "$OUTPUT_DIR/$ZIP_NAME")
SIZE_MB=$(awk "BEGIN {printf \"%.1f\", $SIZE_BYTES / 1048576}")
echo "✓ built $OUTPUT_DIR/$ZIP_NAME ($SIZE_MB MB)"

# Clean up staging
rm -rf "$STAGING"
