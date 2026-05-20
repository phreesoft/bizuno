#!/usr/bin/env bash
#
# Build the Bizuno Accounting WordPress plugin artifact.
#
# Produces a single, fully self-contained zip:
#   build/output/wordpress/bizuno-accounting-VERSION.zip
#
# Contents:
#   bizuno-accounting/bizuno-accounting.php  — main plugin file (WP-admin hooks)
#   bizuno-accounting/portalCFG.php          — Bizuno path/URL constants
#   bizuno-accounting/portalAPI.php          — direct file-serving entry point
#   bizuno-accounting/hostModel.php          — WP-specific host overrides
#   bizuno-accounting/readme.txt             — wordpress.org plugin readme
#   bizuno-accounting/icon_16.png, bizuno.png — admin menu icons
#   bizuno-accounting/src/                   — Bizuno PHP library (copied verbatim)
#   bizuno-accounting/scripts/               — third-party UI assets (jQuery EasyUI, …)
#   bizuno-accounting/vendor/                — composer install --no-dev result
#
# Distribution: WordPress.org plugin directory (slug bizuno-accounting). The
# release workflow pushes the unpacked plugin to plugins.svn.wordpress.org
# automatically on each `v*` tag; wordpress.org handles user updates from
# there. No third-party update-checker library is bundled.
#
# Run from the repo root:
#   bash build/wordpress/build.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

VERSION="$(cat src/VERSION)"
PLUGIN_SLUG="bizuno-accounting"
STAGING_PARENT="$REPO_ROOT/build/output/wordpress-staging"
STAGING="$STAGING_PARENT/$PLUGIN_SLUG"        # MUST match the WP slug exactly
OUTPUT_DIR="$REPO_ROOT/build/output/wordpress"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

echo "→ Building $ZIP_NAME from $REPO_ROOT (VERSION=$VERSION)"

# Clean staging — never reuse a previous attempt's tree.
rm -rf "$STAGING_PARENT"
mkdir -p "$STAGING" "$OUTPUT_DIR"

# ─── Plugin-root files ────────────────────────────────────────────────────────
# These come from build/wordpress/ and sit at the plugin root inside the zip.
cp -a "$REPO_ROOT/build/wordpress/${PLUGIN_SLUG}.php" "$STAGING/"
cp -a "$REPO_ROOT/build/wordpress/portalCFG.php"      "$STAGING/"
cp -a "$REPO_ROOT/build/wordpress/portalAPI.php"      "$STAGING/"
cp -a "$REPO_ROOT/build/wordpress/hostModel.php"      "$STAGING/"
cp -a "$REPO_ROOT/build/wordpress/readme.txt"         "$STAGING/"
cp -a "$REPO_ROOT/build/wordpress/icon_16.png"        "$STAGING/"
cp -a "$REPO_ROOT/build/wordpress/bizuno.png"         "$STAGING/"
# Plugin Check / wp.org SVN exclusion + PHPCS config. Tells PCP and the
# review team's automation which files to skip (bundled library + vendor)
# vs. which are this plugin's own code to review.
cp -a "$REPO_ROOT/build/wordpress/.distignore"        "$STAGING/"
cp -a "$REPO_ROOT/build/wordpress/phpcs.xml.dist"     "$STAGING/"

# Bizuno library + UI assets — both required at runtime, both come from the
# repo root. scripts/ holds vendor-y UI bundles (jquery-easyui, jQuery UI,
# zebra-browser-print, …) that are too big / nested to ship via composer.
cp -a "$REPO_ROOT/src"     "$STAGING/"
cp -a "$REPO_ROOT/scripts" "$STAGING/"

# AGPL licence — wordpress.org wants this present in the plugin tree.
[ -f "$REPO_ROOT/LICENSE" ] && cp -a "$REPO_ROOT/LICENSE" "$STAGING/"

# ─── Stamp the version into the plugin header ────────────────────────────────
# WP reads the `Version:` line from the plugin file to drive the update UI;
# keep it locked to whatever src/VERSION says at build time so the release
# tag, src/VERSION, and the plugin header all agree.
sed -i.bak -E "s/^( \* Version:[[:space:]]+).*$/\\1${VERSION}/" "$STAGING/${PLUGIN_SLUG}.php"
rm -f "$STAGING/${PLUGIN_SLUG}.php.bak"

# Same for readme.txt's "Stable tag:" line, which WP uses to pick the
# version users actually receive from the wp.org SVN trunk.
sed -i.bak -E "s/^(Stable tag:[[:space:]]+).*$/\\1${VERSION}/" "$STAGING/readme.txt"
rm -f "$STAGING/readme.txt.bak"

# ─── Composer install ─────────────────────────────────────────────────────────
# Run composer at the staging root so vendor/ lands at <plugin>/vendor/.
# --no-dev keeps Parsedown and friends out of the user-facing release.
cp -a "$REPO_ROOT/composer.json" "$STAGING/"
cp -a "$REPO_ROOT/composer.lock" "$STAGING/"
echo "→ composer install (production, no dev deps)"
( cd "$STAGING" && composer install \
    --no-dev \
    --optimize-autoloader \
    --no-progress \
    --no-interaction \
    --prefer-dist )

# ─── Trim noise ───────────────────────────────────────────────────────────────
# WordPress.org's plugin-directory review dislikes VCS metadata, editor
# crumbs, and oversized test fixtures. Strip them before zipping.
echo "→ trimming dev metadata from the plugin"
find "$STAGING"        -name '.git*'    -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING"        -name '.DS_Store'        -delete           2>/dev/null || true
find "$STAGING/vendor" -name 'tests'    -type d -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING/vendor" -name 'docs'     -type d -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING/vendor" -name 'examples' -type d -prune -exec rm -rf {} + 2>/dev/null || true

# Defensive — portalCFG.php inside src/ would only matter for the standalone
# install, but belt and suspenders: never let a per-site config slip into
# a user-facing zip.
rm -f "$STAGING/portalCFG-sample.php"
# (Plugin's own portalCFG.php at the staging root is intentional and stays.)

# ─── Zip ──────────────────────────────────────────────────────────────────────
echo "→ zipping → $OUTPUT_DIR/$ZIP_NAME"
( cd "$STAGING_PARENT" && zip -rq "$OUTPUT_DIR/$ZIP_NAME" "$PLUGIN_SLUG" -x '*.DS_Store' )

SIZE_BYTES=$(stat -f%z "$OUTPUT_DIR/$ZIP_NAME" 2>/dev/null || stat -c%s "$OUTPUT_DIR/$ZIP_NAME")
SIZE_MB=$(awk "BEGIN {printf \"%.1f\", $SIZE_BYTES / 1048576}")
echo "✓ built $OUTPUT_DIR/$ZIP_NAME ($SIZE_MB MB)"

rm -rf "$STAGING_PARENT"
