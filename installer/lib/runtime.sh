#!/usr/bin/env bash
# Package allowlist, secret files, and resource checks.

if [[ -n "${INSTALLER_LIB_RUNTIME:-}" ]]; then
    return 0
fi
INSTALLER_LIB_RUNTIME=1

INSTALLER_PACKAGES=(
    sudo
    ufw
    ca-certificates
    curl
    gnupg
    ubuntu-keyring
    git
    unzip
    tar
    openssl
    python3
    acl
    postgresql
    postgresql-client
    redis-server
    nginx
)

installer_package_allowed() {
    local name="$1"
    local item
    for item in "${INSTALLER_PACKAGES[@]}"; do
        [[ "$item" == "$name" ]] && return 0
    done
    return 1
}

installer_pkg_installed() {
    local name="$1"
    local status
    if [[ -n "${INSTALLER_DPKG_STATUS:-}" ]]; then
        awk -v pkg="$name" '
            $0 == "Package: " pkg { found = 1 }
            found && $0 == "Status: install ok installed" { ok = 1 }
            found && $0 == "" { exit }
            END { exit !ok }
        ' "$INSTALLER_DPKG_STATUS"
        return $?
    fi
    status="$(dpkg-query -W -f '${Status}' "$name" 2>/dev/null || true)"
    [[ "$status" == *"install ok installed"* ]]
}

installer_mem_kib() {
    local file="${INSTALLER_MEMINFO:-/proc/meminfo}"
    awk '/^MemTotal:/ { print $2; exit }' "$file"
}

installer_disk_avail_kib() {
    local path="${1:-/}"
    if [[ -n "${INSTALLER_DISK_AVAIL_KIB:-}" ]]; then
        printf '%s\n' "$INSTALLER_DISK_AVAIL_KIB"
        return 0
    fi
    df -Pk "$path" | awk 'NR == 2 { print $4 }'
}

installer_check_resources() {
    local mem disk
    mem="$(installer_mem_kib)"
    disk="$(installer_disk_avail_kib "${RAHANDAZ_INSTALLER_SYSROOT:-/}")"
    [[ "$mem" =~ ^[0-9]+$ ]] || {
        installer_error LOW_MEMORY "Memory total could not be read."
        return 1
    }
    if ((mem < 1048576)); then
        installer_error LOW_MEMORY "At least 1 GiB of RAM is required."
        return 1
    fi
    if ((mem < 2097152)); then
        installer_say "WARN RAM is under 2 GiB. Installation can still continue."
    fi
    [[ "$disk" =~ ^[0-9]+$ ]] || {
        installer_error DISK_FULL "Free disk space could not be read."
        return 1
    }
    if ((disk < 5242880)); then
        installer_error DISK_FULL "At least 5 GiB of free disk is required."
        return 1
    fi
    return 0
}

installer_acknowledged() {
    local name="$1"
    case ",${INSTALLER_ACKNOWLEDGE:-}," in
        *",${name},"*) return 0 ;;
    esac
    return 1
}

installer_check_conflicts() {
    local blocked=0
    local name
    for name in nginx postgresql redis-server php8.4-fpm php8.4-cli; do
        if installer_pkg_installed "$name" && ! installer_acknowledged "$name"; then
            installer_say "CONFLICT ${name} is already installed. Re-run with --acknowledge-existing ${name} to keep it and configure Rahandaz beside it."
            blocked=1
        fi
    done
    if [[ "$blocked" -eq 1 && "$MODE" != "plan" ]]; then
        installer_error EXISTING_SERVICE "Existing services were not acknowledged."
        return 1
    fi
    return 0
}

installer_create_secret_once() {
    local path="$1"
    local dir tmp
    if [[ -f "$path" && ! -L "$path" && -s "$path" ]]; then
        return 0
    fi
    if [[ -e "$path" ]]; then
        installer_error KEY_EXISTS "Refusing to replace ${path}."
        return 1
    fi
    dir="$(dirname "$path")"
    mkdir -p "$dir"
    chmod 0700 "$dir"
    tmp="$(mktemp "${dir}/.secret.XXXXXX")"
    chmod 0600 "$tmp"
    openssl rand -base64 32 | tr -d '\n' >"$tmp"
    printf '\n' >>"$tmp"
    chmod 0600 "$tmp"
    mv -n "$tmp" "$path"
    chmod 0600 "$path"
    rm -f "$tmp"
}

installer_env_get() {
    local file="$1"
    local key="$2"
    awk -F= -v k="$key" '$1 == k { sub(/^[^=]*=/, ""); print; exit }' "$file" 2>/dev/null || true
}

# shellcheck source=php-runtime.sh
source "${INSTALLER_HOME}/lib/php-runtime.sh"
INSTALLER_PACKAGES+=("${INSTALLER_PHP_PACKAGES[@]}")
