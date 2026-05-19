#!/usr/bin/env bash
#
# Build the Bizuno Docker image locally.
#
# Produces a multi-tagged image:
#   bizuno:VERSION   (semver, e.g. 7.4.0 — matches src/VERSION)
#   bizuno:latest    (always present, points at the most recent build)
#
# CI builds & pushes to ghcr.io via .github/workflows/release.yml. This
# script is for local development and image-inspection workflows.
#
# Run from the repo root or anywhere — paths are resolved against the
# Dockerfile's expected build context (the repo root):
#
#   bash build/docker/build.sh             # tagged with VERSION + latest
#   bash build/docker/build.sh --push      # also pushes to ghcr.io (needs `docker login`)
#   IMAGE_NAME=bizuno-test bash build/docker/build.sh   # custom local tag

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

VERSION="$(cat src/VERSION)"
IMAGE_NAME="${IMAGE_NAME:-ghcr.io/phreesoft/bizuno}"
PUSH=false

# arg parsing
for arg in "$@"; do
    case "$arg" in
        --push) PUSH=true ;;
        *) echo "unknown arg: $arg" >&2; exit 1 ;;
    esac
done

# Metadata for OCI image labels
VCS_REF="$(git rev-parse --short HEAD 2>/dev/null || echo unknown)"
BUILD_DATE="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"

echo "→ Building ${IMAGE_NAME}:${VERSION}"
echo "  VCS ref:    ${VCS_REF}"
echo "  Build date: ${BUILD_DATE}"

docker build \
    --file build/docker/Dockerfile \
    --tag "${IMAGE_NAME}:${VERSION}" \
    --tag "${IMAGE_NAME}:latest" \
    --build-arg "VERSION=${VERSION}" \
    --build-arg "VCS_REF=${VCS_REF}" \
    --build-arg "BUILD_DATE=${BUILD_DATE}" \
    "$REPO_ROOT"

echo "✓ built ${IMAGE_NAME}:${VERSION} and ${IMAGE_NAME}:latest"

if [ "$PUSH" = true ]; then
    echo "→ pushing to registry"
    docker push "${IMAGE_NAME}:${VERSION}"
    docker push "${IMAGE_NAME}:latest"
    echo "✓ pushed"
fi
