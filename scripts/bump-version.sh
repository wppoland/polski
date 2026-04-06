#!/usr/bin/env bash
# Bump release version across plugin header, npm metadata, blocks, i18n script, and PHPStan stubs.
# Usage (from polski/): ./scripts/bump-version.sh 1.7.0
set -euo pipefail

NEW="${1:?Usage: $0 X.Y.Z}"

if [[ ! "${NEW}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Expected semver X.Y.Z (e.g. 1.7.0)" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

OLD_HEADER=$(perl -ne 'print $1 if /^\s*\*\s*Version:\s*([0-9.]+)\s*$/' polski.php | head -1)
if [[ -z "${OLD_HEADER}" ]]; then
  echo "Could not read current Version from polski.php header" >&2
  exit 1
fi

echo "Bumping ${OLD_HEADER} -> ${NEW}"

perl -pi -e "s/^([[:space:]]*\\*[[:space:]]*Version:[[:space:]]*)${OLD_HEADER}([[:space:]]*)\$/\${1}${NEW}\${2}/" polski.php
perl -pi -e "s/^const VERSION = '[^']+'/const VERSION = '${NEW}'/" polski.php

while IFS= read -r -d '' f; do
  perl -pi -e 's/"version": "[^"]+"/"version": "'"${NEW}"'"/' "${f}"
done < <(find resources/js/blocks -name block.json -print0 2>/dev/null)

perl -pi -e "s/Project-Id-Version: Polski for WooCommerce [0-9.]+/Project-Id-Version: Polski for WooCommerce ${NEW}/" scripts/generate-translations.php
perl -pi -e "s/^const VERSION = '[^']+'/const VERSION = '${NEW}'/" tests/phpstan/polski-plugin-constants.php

if command -v npm >/dev/null 2>&1; then
  npm version "${NEW}" --no-git-tag-version --allow-same-version
else
  echo "npm not found; update package.json and package-lock.json versions manually." >&2
fi

echo "Done. Review with git diff, then run npm run i18n:pot and commit."
