#!/usr/bin/env bash
#
# Release preflight for Polski. Run this GREEN before any wp.org SVN release.
# It is the local mirror of the CI gate (.github/workflows/ci.yml) plus a real
# WordPress Plugin Check on the built plugin.
#
# Order matters: cheap static checks first, then the runtime fatal smoke (the
# step that catches TypeErrors phpstan misses - e.g. the 1.22.4
# woocommerce_order_query / paginated-orders fatal), then Plugin Check.
#
# Usage:  bash scripts/preflight.sh
# Exits non-zero on the first failure, so a release script can gate on it:
#   bash scripts/preflight.sh && bash scripts/sync-wporg-svn.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> 1/11  boot timing and duplicated admin_post handlers"
php tests/boot-timing-check.php

echo "==> 2/11  every settings key has a reader"
php tests/settings-are-read-check.php

echo "==> 3/11  every module id has a card that can switch it on"
php tests/module-ids-are-reachable-check.php

echo "==> 4/11  readme.txt Changelog under the wp.org truncation limit"
php tests/readme-changelog-length-check.php

echo "==> 5/11  product meta fields are rendered and saved"
php tests/product-meta-render-save-check.php

echo "==> 6/11  public (nopriv) handlers are guarded by their module"
php tests/nopriv-handlers-are-guarded-check.php

echo "==> 7/11  phpcs"
vendor/bin/phpcs

echo "==> 8/11  phpstan (memory 2G)"
php -d memory_limit=2G vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress

echo "==> 9/11  runtime fatal smoke (wp-env)"
npx wp-env start >/dev/null 2>&1 || true
# PRO would gate the admin behind a Freemius license screen; the smoke exercises
# the FREE plugin's code directly, so deactivate PRO for a deterministic run.
npx wp-env run cli wp plugin deactivate polski-pro >/dev/null 2>&1 || true
npx wp-env run cli wp eval-file wp-content/plugins/polski/scripts/smoke-fatal-check.php

echo "==> 10/11  WordPress Plugin Check"
bash scripts/plugin-check.sh

echo "==> 11/11  package contents and header/readme agreement"
bash scripts/prepare-wporg-release.sh /tmp/polski-preflight-package >/dev/null
bash scripts/assert-package-clean.sh /tmp/polski-preflight-package
rm -rf /tmp/polski-preflight-package

echo ""
echo "✅ PREFLIGHT PASSED, safe to release to wp.org."
