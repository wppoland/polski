#!/usr/bin/env bash
# Run the official WordPress Plugin Check (PCP) against a clean release
# build of each plugin. PCP runs against the built output (post-.distignore),
# mirroring exactly what ships to wordpress.org / customers.
#
# Requires wp-env to be running (npm run env:start in polski/) and Docker.
# Installs and activates plugin-check automatically.
#
# Usage:
#   ./scripts/plugin-check.sh              # check both polski and polski-pro
#   ./scripts/plugin-check.sh polski       # check only free plugin
#   ./scripts/plugin-check.sh polski-pro   # check only pro plugin
#   SEVERITY=5 ./scripts/plugin-check.sh   # override severity (default 7 = errors only)

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "${ROOT_DIR}/.." && pwd)"
TARGET="${1:-both}"
SEVERITY="${SEVERITY:-7}"

run_cli() {
    ( cd "${ROOT_DIR}" && npx wp-env run cli wp "$@" )
}

find_container() {
    # Resolve the WordPress container that backs `npx wp-env run cli` from THIS
    # project. wp-env names containers <hash>-wordpress-1 where <hash> is the
    # basename of the install path, so deriving from install-path is the only
    # way to pick the correct instance when multiple wp-env projects are
    # running concurrently. A naive `docker ps | grep wordpress-1 | head -1`
    # picks an arbitrary instance and silently deploys to the wrong one.
    local install_path
    install_path="$( cd "${ROOT_DIR}" && npx wp-env install-path 2>/dev/null | tail -1 )"

    if [[ -z "${install_path}" ]]; then
        return 1
    fi

    local container="$(basename "${install_path}")-wordpress-1"

    if ! docker ps --format '{{.Names}}' | grep -Fxq "${container}"; then
        return 1
    fi

    echo "${container}"
}

ensure_plugin_check() {
    if ! run_cli plugin is-installed plugin-check >/dev/null 2>&1; then
        echo "Installing plugin-check..."
        run_cli plugin install plugin-check --activate
    elif ! run_cli plugin is-active plugin-check >/dev/null 2>&1; then
        run_cli plugin activate plugin-check
    fi
}

build_release() {
    local plugin="$1"
    local src_dir out_dir

    case "$plugin" in
        polski)
            src_dir="${REPO_ROOT}/polski"
            out_dir="/tmp/${plugin}-pcp-release"
            bash "${src_dir}/scripts/prepare-wporg-release.sh" "${out_dir}" >/dev/null
            ;;
        polski-pro)
            src_dir="${REPO_ROOT}/polski-pro"
            out_dir="/tmp/${plugin}-pcp-release"
            bash "${src_dir}/scripts/prepare-release.sh" "${out_dir}" >/dev/null
            ;;
        *)
            echo "Unknown plugin: ${plugin}" >&2
            exit 1
            ;;
    esac

    echo "${out_dir}"
}

install_release() {
    local plugin="$1"
    local release_dir="$2"
    local slug="${plugin}-pcp-check"
    local container

    container="$(find_container)"

    if [[ -z "${container}" ]]; then
        echo "Could not find running wp-env WordPress container." >&2
        exit 1
    fi

    docker exec "${container}" rm -rf "/var/www/html/wp-content/plugins/${slug}"
    docker cp "${release_dir}" "${container}:/var/www/html/wp-content/plugins/${slug}"

    # Sanity check: confirm the file we ship landed in the container that
    # `npx wp-env run cli` will actually scan. A mismatch here means
    # find_container picked the wrong instance and PCP is about to report
    # against stale code.
    local plugin_file="${release_dir}/${plugin}.php"
    if [[ -f "${plugin_file}" ]]; then
        local host_hash container_hash
        host_hash="$(md5 -q "${plugin_file}" 2>/dev/null || md5sum "${plugin_file}" | cut -d' ' -f1)"
        container_hash="$(docker exec "${container}" md5sum "/var/www/html/wp-content/plugins/${slug}/${plugin}.php" 2>/dev/null | cut -d' ' -f1)"
        if [[ -n "${host_hash}" && -n "${container_hash}" && "${host_hash}" != "${container_hash}" ]]; then
            echo "Plugin file hash mismatch between host build and container ${container}." >&2
            echo "  host:      ${host_hash}" >&2
            echo "  container: ${container_hash}" >&2
            exit 1
        fi
    fi

    echo "${slug}"
}

check_plugin() {
    local plugin="$1"

    echo ""
    echo "=== Plugin Check: ${plugin} (release build) ==="

    local release_dir slug extra_args
    release_dir="$(build_release "${plugin}")"
    slug="$(install_release "${plugin}" "${release_dir}")"

    # We install the release build under a *-pcp-check slug to avoid colliding
    # with the dev-mounted plugin, which triggers a false-positive textdomain
    # mismatch warning (not an error). PRO additionally ships outside
    # wordpress.org, so the custom updater is intentional and the
    # plugin_updater check does not apply.
    if [[ "${plugin}" == "polski-pro" ]]; then
        extra_args="--exclude-checks=plugin_updater"
    else
        extra_args=""
    fi

    run_cli plugin check "${slug}" --format=table --severity="${SEVERITY}" ${extra_args}

    # Leave the installed copy in place; it is harmless for subsequent runs.
}

ensure_plugin_check

case "$TARGET" in
    polski)
        check_plugin polski
        ;;
    polski-pro)
        check_plugin polski-pro
        ;;
    both|*)
        check_plugin polski
        check_plugin polski-pro
        ;;
esac
