#!/usr/bin/env bash
#
# Build the WordPress plugin artifact.
#
# Assembles bizuno-wp-VERSION.zip — a standalone WordPress plugin containing:
#   - bizuno-wp.php at the plugin root (WP plugin header + auto-update wiring)
#   - readme.txt at the plugin root (WordPress.org plugin-directory format)
#   - src/ — full Bizuno PHP source (controllers, model, view, locale, portal, …)
#   - vendor/ — composer-installed dependencies, including the WP-only
#     yahniselsts/plugin-update-checker for auto-updates from GitHub Releases
#
# The resulting zip is what users install via WP admin → Plugins → Upload, and
# what gets attached to GitHub Releases for the auto-updater to discover.
#
# Run from the repo root:
#   bash build/wordpress/build.sh
#
# Output: build/output/wordpress/bizuno-wp-VERSION.zip

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

VERSION="$(cat src/VERSION)"
STAGING_PARENT="$REPO_ROOT/build/output/wordpress-staging"
STAGING="$STAGING_PARENT/bizuno-wp"  # MUST be named bizuno-wp — WP slug
OUTPUT_DIR="$REPO_ROOT/build/output/wordpress"
ZIP_NAME="bizuno-wp-${VERSION}.zip"

echo "→ Building $ZIP_NAME from $REPO_ROOT (VERSION=$VERSION)"

# Clean staging
rm -rf "$STAGING_PARENT"
mkdir -p "$STAGING" "$OUTPUT_DIR"

# Plugin entry + readme
# These come from build/wordpress/ and land at the plugin root
cp -a "$REPO_ROOT/build/wordpress/bizuno-wp.php" "$STAGING/"
cp -a "$REPO_ROOT/build/wordpress/readme.txt"    "$STAGING/"

# Sync the plugin's Version: header line to src/VERSION so WP's update check
# compares against the right number. The release workflow tags the repo
# with v$VERSION; here we ensure the plugin file agrees.
sed -i.bak -E "s/^( \* Version:[[:space:]]+).*$/\\1${VERSION}/" "$STAGING/bizuno-wp.php"
rm -f "$STAGING/bizuno-wp.php.bak"

# Mirror src/ into the plugin
cp -a "$REPO_ROOT/src" "$STAGING/"

# Composer install at the plugin root so vendor/ lands at bizuno-wp/vendor/.
# The build needs composer.json + composer.lock present in $STAGING to do this;
# copy them, install, then leave them in place (they're harmless for users
# and useful for anyone who wants to re-run composer install themselves).
cp -a "$REPO_ROOT/composer.json" "$STAGING/"
cp -a "$REPO_ROOT/composer.lock" "$STAGING/"
echo "→ composer install (production, no dev deps)"
( cd "$STAGING" && composer install \
    --no-dev \
    --optimize-autoloader \
    --no-progress \
    --no-interaction \
    --prefer-dist )

# The auto-updater package (yahniselsts/plugin-update-checker) isn't in
# composer.json — it's WordPress-only and was historically vendored manually.
# Pull it from the repo's vendor/ if present (Phase 1+ kept it there) into
# the plugin's vendor/yahniselsts/ so the require_once in bizuno-wp.php
# resolves at runtime.
if [ -d "$REPO_ROOT/vendor/yahniselsts" ]; then
    echo "→ copying yahniselsts/plugin-update-checker into plugin vendor/"
    cp -a "$REPO_ROOT/vendor/yahniselsts" "$STAGING/vendor/"
else
    echo "  ⚠ vendor/yahniselsts not found in repo — auto-updates will be inert" >&2
    echo "    fetch from https://github.com/YahnisElsts/plugin-update-checker and drop into vendor/" >&2
fi

# Trim things WP plugin users don't need (and that WordPress.org's plugin-
# directory review actively dislikes — *.md files, hidden dirs, .gitignore, etc.)
echo "→ trimming dev metadata from the plugin"
find "$STAGING" -name '.git*' -prune -exec rm -rf {} +    2>/dev/null || true
find "$STAGING" -name '.DS_Store' -delete                  2>/dev/null || true
find "$STAGING/vendor" -name 'tests' -type d -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING/vendor" -name 'docs'  -type d -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING/vendor" -name 'examples' -type d -prune -exec rm -rf {} + 2>/dev/null || true

# WP plugins must NOT carry the per-install config — that's site-specific.
# (Defensive — these wouldn't normally be in src/ but belt-and-suspenders.)
rm -f "$STAGING/portalCFG.php"

# Zip — name the contained directory bizuno-wp so it unzips correctly into wp-content/plugins/
echo "→ zipping → $OUTPUT_DIR/$ZIP_NAME"
( cd "$STAGING_PARENT" && zip -rq "$OUTPUT_DIR/$ZIP_NAME" bizuno-wp -x '*.DS_Store' )

SIZE_BYTES=$(stat -f%z "$OUTPUT_DIR/$ZIP_NAME" 2>/dev/null || stat -c%s "$OUTPUT_DIR/$ZIP_NAME")
SIZE_MB=$(awk "BEGIN {printf \"%.1f\", $SIZE_BYTES / 1048576}")
echo "✓ built $OUTPUT_DIR/$ZIP_NAME ($SIZE_MB MB)"

rm -rf "$STAGING_PARENT"
