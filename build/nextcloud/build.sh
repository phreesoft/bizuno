#!/usr/bin/env bash
#
# Build the Bizuno NextCloud app tarball.
#
# Produces:
#   build/output/nextcloud/bizuno-<VERSION>.tar.gz
#
# The tarball contains a single top-level directory `bizuno/` matching
# the app id (NextCloud rejects archives with any other layout). The
# archive is ready to:
#   - install manually: tar -xzf … -C <nextcloud>/apps/
#   - upload to apps.nextcloud.com via the developer portal
#   - attach to a GitHub Release (CI does this automatically on tag push)
#
# Usage (from the repo root, but cwd doesn't matter — paths self-resolve):
#   bash build/nextcloud/build.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

VERSION="$(cat src/VERSION)"
APP_ID="bizuno"
SOURCE_DIR="${REPO_ROOT}/build/nextcloud/${APP_ID}"
OUT_DIR="${REPO_ROOT}/build/output/nextcloud"
STAGE_DIR="$(mktemp -d -t bizuno-nc-build.XXXXXX)"

# Cleanup the stage dir regardless of exit status — no half-built mess
# left in /tmp on failure.
trap 'rm -rf "$STAGE_DIR"' EXIT

if [ ! -d "$SOURCE_DIR" ]; then
    echo "ERROR: NextCloud app source missing at $SOURCE_DIR" >&2
    exit 1
fi

echo "→ Building NextCloud app: ${APP_ID} v${VERSION}"
echo "  source: ${SOURCE_DIR}"
echo "  stage:  ${STAGE_DIR}"

# Stage the app skeleton untouched, then mutate the staged copy. The
# repo's source tree is read-only from the script's POV.
cp -a "$SOURCE_DIR" "$STAGE_DIR/"

# Inject the canonical version (src/VERSION) into info.xml. The XML
# rewrite uses sed against a known-shape <version>…</version> line —
# robust enough given we control the source template.
INFO_XML="${STAGE_DIR}/${APP_ID}/appinfo/info.xml"
sed -i.bak -E "s|<version>[^<]*</version>|<version>${VERSION}</version>|" "$INFO_XML"
rm "${INFO_XML}.bak"
echo "  ✓ stamped version ${VERSION} into info.xml"

# Ship the AGPL licence text alongside the app — NextCloud app store
# manual mentions this as expected. The repo's LICENSE file is the
# authoritative AGPL-3.0 text; alias it as COPYING (NC convention).
if [ -f "${REPO_ROOT}/LICENSE" ]; then
    cp "${REPO_ROOT}/LICENSE" "${STAGE_DIR}/${APP_ID}/COPYING"
else
    echo "  ! WARN: ${REPO_ROOT}/LICENSE missing — COPYING not bundled"
fi

# Strip editor / VCS metadata that may have crept in. None of these
# should reach a release.
find "${STAGE_DIR}/${APP_ID}" \( \
    -name '.DS_Store'   -o \
    -name '.git'        -o \
    -name '.gitignore'  -o \
    -name '*.swp'       -o \
    -name '*.bak'       -o \
    -name 'Thumbs.db'      \
\) -prune -exec rm -rf {} + 2>/dev/null || true

# Bake the archive.
mkdir -p "$OUT_DIR"
TARBALL="${OUT_DIR}/${APP_ID}-${VERSION}.tar.gz"
rm -f "$TARBALL"
tar -czf "$TARBALL" -C "$STAGE_DIR" "$APP_ID"

SIZE_KB=$(du -k "$TARBALL" | cut -f1)
echo
echo "✓ Built ${TARBALL} (${SIZE_KB}K)"
echo
echo "Install locally:"
echo "    tar -xzf $(basename "$TARBALL") -C <nextcloud-root>/apps/"
echo "    sudo -u www-data php <nextcloud-root>/occ app:enable ${APP_ID}"
echo
echo "Publish to apps.nextcloud.com:"
echo "    Upload at https://apps.nextcloud.com/developer/apps/releases/new"
