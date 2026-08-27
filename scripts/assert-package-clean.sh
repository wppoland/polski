#!/usr/bin/env bash
#
# Gate a built wp.org package before it can be uploaded.
#
# Every rule here exists because the corresponding mistake already shipped or
# nearly shipped:
#
#   languages/     1.29.6 shipped polski-bel.mo, polski-es_ES.mo and
#                  polski-pl_PL.mo. .distignore excluded locales one by one and
#                  "polski-be_BY.*" does not match "polski-bel.mo". wordpress.org
#                  builds language packs from translate.wordpress.org, so a
#                  bundled catalogue is a duplicate at best; Reel was pended for
#                  exactly this.
#   dev artefacts  tiers was rejected for shipping tests/ and vendor dev tools.
#   *~             an editor backup in a package is a badly_named_files reject.
#   header drift   1.29.7 was about to be uploaded with "Tested up to: 7.0" in
#                  polski.php and 7.1 in readme.txt, which Plugin Check reports
#                  as an error. Same class: Version vs Stable tag.
#
# Usage: bash scripts/assert-package-clean.sh [package-dir]
#
set -euo pipefail

PKG="${1:-/tmp/polski-wporg-trunk}"
FAIL=0

fail() {
    echo "FAIL: $*" >&2
    FAIL=1
}

if [[ ! -d "${PKG}" ]]; then
    echo "Package directory not found: ${PKG}" >&2
    exit 1
fi

# 1. languages/ carries the .pot and nothing else.
if [[ -d "${PKG}/languages" ]]; then
    while IFS= read -r f; do
        case "${f}" in
            *.pot) ;;
            *) fail "languages/ may only contain .pot files, found: ${f#"${PKG}/"}" ;;
        esac
    done < <(find "${PKG}/languages" -type f)
fi

# 2. No translation catalogue anywhere else in the tree either.
while IFS= read -r f; do
    fail "translation catalogue in the package: ${f#"${PKG}/"}"
done < <(find "${PKG}" -type f \( -name '*.po' -o -name '*.mo' -o -name '*.l10n.php' \))

# 3. No development or test payload.
for path in tests .github .wordpress-org node_modules glotpress-import \
            phpunit.xml.dist phpcs.xml.dist phpstan.neon.dist composer.json \
            composer.lock package.json scripts docs; do
    if [[ -e "${PKG}/${path}" ]]; then
        fail "development payload in the package: ${path}"
    fi
done

while IFS= read -r f; do
    fail "dev tool left in vendor/: ${f#"${PKG}/"}"
done < <(find "${PKG}/vendor" -maxdepth 2 -type d \
    \( -name 'phpunit' -o -name 'phpstan' -o -name 'phpcs' -o -name 'squizlabs' \) 2>/dev/null)

# 4. No editor backups.
while IFS= read -r f; do
    fail "editor backup in the package: ${f#"${PKG}/"}"
done < <(find "${PKG}" -type f \( -name '*~' -o -name '*.bak' \))

# 5. The plugin header and the readme must agree with each other.
header_version="$(grep -m1 -E '^ \* Version:' "${PKG}/polski.php" | sed -E 's/.*Version:[[:space:]]*//')"
header_tested="$(grep -m1 -E '^ \* Tested up to:' "${PKG}/polski.php" | sed -E 's/.*Tested up to:[[:space:]]*//')"
readme_stable="$(grep -m1 -E '^Stable tag:' "${PKG}/readme.txt" | sed -E 's/^Stable tag:[[:space:]]*//')"
readme_tested="$(grep -m1 -E '^Tested up to:' "${PKG}/readme.txt" | sed -E 's/^Tested up to:[[:space:]]*//')"

[[ "${header_version}" == "${readme_stable}" ]] || \
    fail "Version (${header_version}) does not match Stable tag (${readme_stable})"
[[ "${header_tested}" == "${readme_tested}" ]] || \
    fail "header Tested up to (${header_tested}) does not match readme (${readme_tested})"

# 6. "Tested up to" must be the current WordPress release, or wordpress.org
#    hides the plugin from search. This is the value that silently rots.
if command -v curl >/dev/null 2>&1; then
    current_wp="$(curl -fsS --max-time 10 https://api.wordpress.org/core/version-check/1.7/ 2>/dev/null \
        | php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["offers"][0]["current"] ?? "";' 2>/dev/null)"
    if [[ -n "${current_wp}" ]]; then
        # Compare on major.minor only; wordpress.org accepts 7.1 for 7.1.2.
        if [[ "${current_wp%.*}" != "${header_tested}" && "${current_wp}" != "${header_tested}" ]]; then
            fail "Tested up to (${header_tested}) is behind the current WordPress release (${current_wp})"
        fi
    else
        echo "note: could not reach api.wordpress.org, skipped the Tested up to freshness check" >&2
    fi
fi

# 7. The changelog must lead with the version being shipped, in order.
first_entry="$(grep -m1 -E '^= [0-9]' "${PKG}/readme.txt" | sed -E 's/^= (.+) =$/\1/')"
[[ "${first_entry}" == "${header_version}" ]] || \
    fail "the newest changelog entry is ${first_entry}, but this package is ${header_version}"

if [[ "${FAIL}" -ne 0 ]]; then
    echo "" >&2
    echo "Package is NOT safe to upload: ${PKG}" >&2
    exit 1
fi

echo "Package clean: ${PKG} (${header_version}, tested up to ${header_tested})"
