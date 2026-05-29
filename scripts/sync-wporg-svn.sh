#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE="${ROOT_DIR}/polski.php"
PACKAGE_DIR="${1:-/tmp/polski-wporg-trunk}"
SVN_DIR="${2:-/tmp/polski-svn}"

if [[ ! -f "${PLUGIN_FILE}" ]]; then
    echo "Could not find plugin bootstrap file: ${PLUGIN_FILE}" >&2
    exit 1
fi

if [[ ! -d "${PACKAGE_DIR}" ]]; then
    echo "Prepared package directory not found: ${PACKAGE_DIR}" >&2
    exit 1
fi

if [[ ! -d "${SVN_DIR}/.svn" ]]; then
    echo "SVN checkout not found in: ${SVN_DIR}" >&2
    echo "Run: svn checkout https://plugins.svn.wordpress.org/polski ${SVN_DIR}" >&2
    exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
    echo "rsync is required." >&2
    exit 1
fi

VERSION="$(php -r '$content = file_get_contents($argv[1]); if (! preg_match("/^ \\* Version:\\s*(.+)$/mi", $content, $m)) { fwrite(STDERR, "Could not read plugin version.\n"); exit(1); } echo trim($m[1]);' "${PLUGIN_FILE}")"

if [[ -z "${VERSION}" ]]; then
    echo "Could not determine plugin version." >&2
    exit 1
fi

mkdir -p "${SVN_DIR}/trunk" "${SVN_DIR}/tags"

rsync -a --delete "${PACKAGE_DIR}/" "${SVN_DIR}/trunk/"
rm -rf "${SVN_DIR}/tags/${VERSION}"
cp -R "${SVN_DIR}/trunk" "${SVN_DIR}/tags/${VERSION}"

echo "Synced WordPress.org SVN working copy."
echo "Version: ${VERSION}"
echo "SVN dir: ${SVN_DIR}"
echo
echo "Next steps:"
echo "  cd ${SVN_DIR}"
echo "  svn status"
echo "  svn add --force trunk tags assets --auto-props --parents"
echo "  svn commit -m \"Release ${VERSION}\""
