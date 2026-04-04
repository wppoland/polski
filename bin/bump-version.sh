#!/usr/bin/env bash

# Bump plugin version across all files that reference it.
#
# Usage:
#   bash bin/bump-version.sh 1.4.0
#   bash bin/bump-version.sh patch   # 1.3.0 -> 1.3.1
#   bash bin/bump-version.sh minor   # 1.3.0 -> 1.4.0
#   bash bin/bump-version.sh major   # 1.3.0 -> 2.0.0

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE="${ROOT_DIR}/polski.php"

# Read current version from plugin header.
CURRENT=$(grep -m1 "^ \* Version:" "$PLUGIN_FILE" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')

if [[ -z "$CURRENT" ]]; then
    echo "Could not read current version from ${PLUGIN_FILE}" >&2
    exit 1
fi

IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT"

case "${1:-}" in
    patch)  NEW_VERSION="${MAJOR}.${MINOR}.$((PATCH + 1))" ;;
    minor)  NEW_VERSION="${MAJOR}.$((MINOR + 1)).0" ;;
    major)  NEW_VERSION="$((MAJOR + 1)).0.0" ;;
    "")     echo "Usage: $0 <major|minor|patch|X.Y.Z>" >&2; exit 1 ;;
    *)      NEW_VERSION="$1" ;;
esac

echo "Bumping version: ${CURRENT} -> ${NEW_VERSION}"

# 1. Plugin header (polski.php)
sed -i '' "s/^ \* Version:.*/ * Version:           ${NEW_VERSION}/" "$PLUGIN_FILE"

# 2. PHP constant (polski.php)
sed -i '' "s/^const VERSION = '.*';/const VERSION = '${NEW_VERSION}';/" "$PLUGIN_FILE"

# 3. readme.txt stable tag
sed -i '' "s/^Stable tag:.*/Stable tag: ${NEW_VERSION}/" "${ROOT_DIR}/readme.txt"

# 4. package.json (if present)
if [[ -f "${ROOT_DIR}/package.json" ]]; then
    sed -i '' "s/\"version\": \".*\"/\"version\": \"${NEW_VERSION}\"/" "${ROOT_DIR}/package.json"
fi

# 5. block.json files
find "${ROOT_DIR}/build/blocks" -name "block.json" 2>/dev/null | while read -r f; do
    sed -i '' "s/\"version\": \".*\"/\"version\": \"${NEW_VERSION}\"/" "$f"
done

echo "Updated files:"
echo "  - polski.php (header + constant)"
echo "  - readme.txt (Stable tag)"
[[ -f "${ROOT_DIR}/package.json" ]] && echo "  - package.json"
echo ""
echo "Version is now: ${NEW_VERSION}"
echo ""
echo "Next: rebuild zip with 'bash bin/build-zip.sh'"
