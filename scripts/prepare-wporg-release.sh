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

rsync -a \
    --delete \
    --exclude-from="${ROOT_DIR}/.distignore" \
    "${ROOT_DIR}/" \
    "${DIST_DIR}/"

while IFS= read -r pattern; do
    if [[ -z "${pattern}" ]]; then
        continue
    fi

    rm -rf "${DIST_DIR}/${pattern}"
done < "${ROOT_DIR}/.distignore"

find "${DIST_DIR}" -type d -empty -delete

echo "Prepared WordPress.org release package in: ${DIST_DIR}"
