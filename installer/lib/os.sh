#!/usr/bin/env bash
# Read os-release without sourcing it.

if [[ -n "${INSTALLER_LIB_OS:-}" ]]; then
    return 0
fi
INSTALLER_LIB_OS=1

installer_os_release_file() {
    local file="${INSTALLER_OS_RELEASE:-/etc/os-release}"
    local root expected resolved
    if [[ -f "$file" && ! -L "$file" ]]; then
        printf '%s\n' "$file"
        return 0
    fi
    # Ubuntu publishes /etc/os-release as a symlink to /usr/lib/os-release.
    # Any other target is refused. The file is parsed, never sourced.
    if [[ -L "$file" ]]; then
        root="$(cd -P "$(dirname "$file")/.." && pwd -P)"
        root="${root%/}"
        expected="${root}/usr/lib/os-release"
        resolved="$(readlink -f "$file" 2>/dev/null || true)"
        if [[ "$resolved" == "$expected" && -f "$expected" && ! -L "$expected" ]]; then
            printf '%s\n' "$expected"
            return 0
        fi
    fi
    installer_error UNSUPPORTED_OS "os-release is missing."
    return 1
}

installer_detect_os() {
    local file line key value
    INSTALLER_OS_ID=""
    INSTALLER_OS_VERSION=""
    INSTALLER_OS_CODENAME=""
    file="$(installer_os_release_file)" || return 1
    while IFS= read -r line || [[ -n "$line" ]]; do
        [[ "$line" == *=* ]] || continue
        key="${line%%=*}"
        value="${line#*=}"
        value="${value#\"}"
        value="${value%\"}"
        case "$key" in
            ID) INSTALLER_OS_ID="$value" ;;
            VERSION_ID) INSTALLER_OS_VERSION="$value" ;;
            VERSION_CODENAME) INSTALLER_OS_CODENAME="$value" ;;
        esac
    done <"$file"
    if [[ "$INSTALLER_OS_ID" != "ubuntu" ]]; then
        installer_error UNSUPPORTED_OS "Only Ubuntu is supported."
        return 1
    fi
    case "$INSTALLER_OS_VERSION:$INSTALLER_OS_CODENAME" in
        22.04:jammy | 24.04:noble | 26.04:resolute) ;;
        *)
            installer_error UNSUPPORTED_OS "Ubuntu ${INSTALLER_OS_VERSION} ${INSTALLER_OS_CODENAME} is not a target release."
            return 1
            ;;
    esac
    INSTALLER_ARCH="$(uname -m)"
    case "$INSTALLER_ARCH" in
        x86_64 | amd64) INSTALLER_ARCH="amd64" ;;
        *)
            installer_error UNSUPPORTED_ARCHITECTURE "Architecture ${INSTALLER_ARCH} is not covered by an installer test."
            return 1
            ;;
    esac
    return 0
}

installer_php_baseline_available() {
    # Distro PHP on jammy is 8.1. The lab run on 2026-10-13 observed that.
    # No signed third-party PHP repository is approved, so 22.04 cannot host the panel.
    [[ "${INSTALLER_OS_CODENAME:-}" != "jammy" ]]
}
