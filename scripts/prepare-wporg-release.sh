#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${1:-/tmp/polski-wporg-trunk}"

if ! command -v rsync >/dev/null 2>&1; then
    echo "rsync is required to prepare a WordPress.org release package." >&2
    exit 1
fi

rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}"

# Ship a PRODUCTION-only vendor. A developer's working vendor/ carries dev tools
# (phpstan, phpunit, phpcs, ~5k files) that .distignore does not enumerate, so a
# plain rsync would bundle them into the customer package. Temporarily prune the
# working vendor to prod-only (no download for already-installed prod deps),
# rsync, then restore the dev deps for continued local work.
RESTORE_DEV=0
if command -v composer >/dev/null 2>&1 && [[ -f "${ROOT_DIR}/composer.json" ]]; then
    if ( cd "${ROOT_DIR}" && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts >/dev/null 2>&1 ); then
        RESTORE_DEV=1
    fi
fi

rsync -a \
    --delete \
    --exclude-from="${ROOT_DIR}/.distignore" \
    "${ROOT_DIR}/" \
    "${DIST_DIR}/"

if [[ "${RESTORE_DEV}" == "1" ]]; then
    ( cd "${ROOT_DIR}" && composer install --no-interaction >/dev/null 2>&1 ) || true
fi

while IFS= read -r pattern; do
    if [[ -z "${pattern}" ]]; then
        continue
    fi

    rm -rf "${DIST_DIR}/${pattern}"
done < "${ROOT_DIR}/.distignore"

find "${DIST_DIR}" -type d -empty -delete

echo "Prepared WordPress.org release package in: ${DIST_DIR}"
