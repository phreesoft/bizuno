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
# phpcs.xml at the plugin root — Plugin Check loads it for the PHPCS-based
# rule families (Security, Code Quality, etc.) and respects its
# exclude-pattern entries to skip src/, vendor/, scripts/. NOTE: must be
# named "phpcs.xml" (not phpcs.xml.dist) — PCP's No_Application_Files_Check
# flags *.dist files. PHPCS itself loads both names equivalently.
cp -a "$REPO_ROOT/build/wordpress/phpcs.xml"          "$STAGING/"
# .distignore is NOT shipped — PCP flags any file starting with a dot
# (hidden_files rule). The file stays in build/wordpress/ for future
# wp.org SVN-deploy automation but never lands in the user-facing zip.

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
# crumbs, oversized test fixtures, and filenames/paths Plugin Check would
# reject (hidden files, spaces in names, .xml.dist "application" files).
# Strip them before zipping.
echo "→ trimming dev metadata + Plugin-Check-blocking files"

# VCS + macOS metadata anywhere in the tree.
find "$STAGING"        -name '.git*'     -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING"        -name '.DS_Store'         -delete            2>/dev/null || true
find "$STAGING"        -name 'Thumbs.db'         -delete            2>/dev/null || true

# Composer dependency noise that bloats the zip without adding runtime value.
find "$STAGING/vendor" -name 'tests'     -type d -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING/vendor" -name 'docs'      -type d -prune -exec rm -rf {} + 2>/dev/null || true
find "$STAGING/vendor" -name 'examples'  -type d -prune -exec rm -rf {} + 2>/dev/null || true

# Hidden files inside vendor/ — composer packages ship their own .travis.yml,
# .editorconfig, .scrutinizer.yml, .phpdoc/, .agents/, etc. PCP flags every
# one of these. Vendor packages don't need any dotfiles to function at
# runtime, so strip them recursively.
find "$STAGING/vendor" -name '.*' -not -name '.' -not -name '..' -prune -exec rm -rf {} + 2>/dev/null || true

# Plugin Check / wp.org review-bot blockers inside src/:
#
# 1. macOS-Finder "Foo copy.json" duplicates accidentally committed into
#    locale/. Real source files are the un-suffixed versions.
find "$STAGING/src" -type f -name '* copy.*' -delete 2>/dev/null || true

# 2. Vendor-supplied reference XSD schemas with spaces in directory names.
#    These ship as documentation for the Walmart + Amazon marketplace
#    integrations but are NOT loaded at runtime — ifWalmart.php and
#    ifAmazon.php construct XML directly, they don't validate against the
#    bundled schemas. Strip the reference directories whose path contains
#    spaces; the funnels keep working.
rm -rf "$STAGING/src/controllers/api/funnels/ifWalmart/API-V2"      2>/dev/null || true
rm -rf "$STAGING/src/controllers/api/funnels/ifAmazon/source"       2>/dev/null || true

# 3. Defensive sweep: any file with spaces or special chars in the name
#    still left in src/ (PCP's "badly_named_files" rule). Log them so we
#    can decide whether to add a targeted strip rule next pass.
BAD=$(find "$STAGING/src" -name '* *' 2>/dev/null || true)
if [ -n "$BAD" ]; then
    echo "  ⚠ residual badly-named files in src/ (Plugin Check will flag):" >&2
    echo "$BAD" | sed 's|^|    |' >&2
fi

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
