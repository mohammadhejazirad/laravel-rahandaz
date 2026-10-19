#!/usr/bin/env bash
# Prerequisite contract v1. Reconcile actual dpkg/service state on every invocation.
# Does not install application code, migrate databases, create secrets or open execution gates.

prereq_fail() {
    printf 'ERROR %s: %s\n' "$1" "$2" >&2
    return 1
}

prereq_confirm() {
    local answer
    while :; do
        printf '%s [y/n]: ' "$1" >&2
        IFS= read -r answer || { prereq_fail INPUT_REQUIRED 'No answer received. Nothing further will be changed.'; return 1; }
        case "$answer" in
            y|Y) return 0 ;;
            n|N) return 1 ;;
            *) printf 'Please enter y or n.\n' >&2 ;;
        esac
    done
}

prereq_resources() {
    local model
    printf '\nRahandaz server prerequisites - contract v1\n'
    printf 'OS: Ubuntu %s (%s)\nArchitecture: %s\nKernel: %s\n' \
        "$INSTALLER_OS_VERSION" "$INSTALLER_OS_CODENAME" "$INSTALLER_ARCH" "$(uname -r)"
    model="$(awk -F: '/model name/ {sub(/^[ \t]+/, "", $2); print $2; exit}' /proc/cpuinfo)"
    printf 'CPU: %s\nAvailable logical CPUs: %s\n' "${model:-unknown}" "$(nproc)"
    awk '/^MemTotal:/ {printf "RAM total: %.2f GiB\n", $2/1048576}
         /^MemAvailable:/ {printf "RAM available: %.2f GiB\n", $2/1048576}
         /^SwapTotal:/ {printf "Swap total: %.2f GiB\n", $2/1048576}' /proc/meminfo
    printf 'Filesystem capacity (KiB; repeated filesystems are not added together):\n'
    df -Pk / /var /tmp
    printf 'Load averages: %s\n' "$(awk '{print $1, $2, $3}' /proc/loadavg)"
    printf 'PID 1: %s\n' "$(installer_pid1_name)"
    printf 'Container /proc values can describe the host; this report is not a cgroup capacity certificate.\n'
}

prereq_capacity() {
    local mem free path
    mem="$(awk '/^MemTotal:/ {print $2; exit}' /proc/meminfo)"
    [[ "$mem" =~ ^[0-9]+$ ]] && (( mem >= 1048576 )) \
        || { prereq_fail LOW_MEMORY 'At least 1 GiB RAM is required; 2 GiB is the target minimum profile.'; return 1; }
    for path in / /var /tmp; do
        free="$(df -Pk "$path" | awk 'NR==2 {print $4}')"
        [[ "$free" =~ ^[0-9]+$ ]] && (( free >= 5242880 )) \
            || { prereq_fail LOW_DISK "At least 5 GiB free is required on the filesystem containing ${path}."; return 1; }
    done
}

prereq_php_default_guard() {
    # php8.4-cli's maintainer scripts may change an automatic global PHP selection.
    # Do not run update-alternatives or silently change an existing runtime.
    local name
    if prereq_package_ok php8.4-cli; then return 0; fi
    for name in php phar phar.phar; do
        if [[ -e "/usr/bin/$name" ]]; then
            if [[ ! -f "/var/lib/dpkg/alternatives/$name" || "$(head -n 1 "/var/lib/dpkg/alternatives/$name")" != manual ]]; then
                prereq_fail PHP_DEFAULT_CONFLICT "Existing global ${name} is not protected by a manual alternatives selection. Review and preserve its selection before installing PHP 8.4. This script will not switch global alternatives."
                return 1
            fi
        fi
    done
}

prereq_audit() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$1" >>"$PREREQ_STATE/audit.log"
    printf '%s\n' "$1" >"$PREREQ_STATE/status.new"
    mv -f "$PREREQ_STATE/status.new" "$PREREQ_STATE/status"
    printf '%s\n' "$1"
}

prereq_on_error() {
    local status="$1"
    prereq_audit "failed_exit=$status" || true
    printf '%s\n' 'Installation incomplete. Fix the error above and rerun the same command; keep the state directory. No application success is claimed.' >&2
    exit 1
}

prereq_secure_dir() {
    local path="$1" part current=''
    local -a parts=()
    [[ "$path" == /* ]] || return 1
    IFS=/ read -ra parts <<<"$path"
    for part in "${parts[@]}"; do
        [[ -n "$part" ]] || continue
        current="${current}/${part}"
        [[ ! -L "$current" ]] || { prereq_fail UNSAFE_PATH "Refusing symbolic link: ${current}"; return 1; }
        if [[ -e "$current" ]]; then
            [[ -d "$current" && "$(stat -c %u "$current")" == 0 ]] \
                || { prereq_fail UNSAFE_PATH "Expected root-owned directory: ${current}"; return 1; }
            (( (8#$(stat -c %a "$current") & 0022) == 0 )) \
                || { prereq_fail UNSAFE_PATH "Directory is writable by other users: ${current}"; return 1; }
        else
            mkdir -m 0700 "$current"
        fi
    done
}

prereq_init() {
    prereq_secure_dir "$PREREQ_STATE"
    chmod 0700 "$PREREQ_STATE"
    local file
    for file in audit.log status status.new lock policy.expected; do
        [[ ! -e "$PREREQ_STATE/$file" && ! -L "$PREREQ_STATE/$file" || -f "$PREREQ_STATE/$file" && ! -L "$PREREQ_STATE/$file" ]] \
            || { prereq_fail UNSAFE_PATH 'Invalid state file.'; return 1; }
    done
    exec {PREREQ_LOCK}>"$PREREQ_STATE/lock"
    flock -n "$PREREQ_LOCK" || { prereq_fail INSTALLER_BUSY 'Another prerequisite installer is running.'; return 1; }
    prereq_secure_dir "$PREREQ_APT_DIR"
    prereq_secure_dir "$PREREQ_APT_DIR/lists"
    prereq_secure_dir "$PREREQ_APT_DIR/archives"
    prereq_secure_dir "$PREREQ_STATE/owned"
    PREREQ_APT=( -o "Dir::Etc::sourcelist=$PREREQ_APT_DIR/ubuntu.sources"
        -o "Dir::Etc::sourceparts=$PREREQ_APT_DIR/parts"
        -o "Dir::State::lists=$PREREQ_APT_DIR/lists"
        -o "Dir::Cache::archives=$PREREQ_APT_DIR/archives"
        -o DPkg::Lock::Timeout=120 -o Acquire::Retries=3
        -o Acquire::http::Timeout=30 -o Acquire::https::Timeout=30
        -o APT::Update::Error-Mode=any -o APT::Get::AllowUnauthenticated=false
        -o Acquire::AllowInsecureRepositories=false -o Acquire::AllowDowngradeToInsecureRepositories=false
        -o Acquire::Check-Valid-Until=true -o Acquire::Check-Date=true
        -o Acquire::https::Verify-Peer=true -o Acquire::https::Verify-Host=true
        -o "Dir::Etc::preferences=$PREREQ_APT_DIR/preferences"
        -o "Dir::Etc::preferencesparts=$PREREQ_APT_DIR/preferences.d" )
    prereq_secure_dir "$PREREQ_APT_DIR/parts"
    prereq_secure_dir "$PREREQ_APT_DIR/preferences.d"
    chmod 0755 "$PREREQ_APT_DIR" "$PREREQ_APT_DIR/parts" "$PREREQ_APT_DIR/preferences.d" "$PREREQ_APT_DIR/lists" "$PREREQ_APT_DIR/archives"
    : >"$PREREQ_APT_DIR/preferences"
    chmod 0644 "$PREREQ_APT_DIR/preferences"
}

prereq_policy_acquire() {
    # Block maintainer-script service starts until explicit service validation.
    printf '%s\n' '#!/bin/sh' '# rahandaz-prerequisites-policy-v1' 'exit 101' >"$PREREQ_STATE/policy.expected"
    if [[ -e "$PREREQ_POLICY" || -L "$PREREQ_POLICY" ]]; then
        [[ -f "$PREREQ_POLICY" && ! -L "$PREREQ_POLICY" ]] || { prereq_fail POLICY_CONFLICT 'Unsafe policy-rc.d path.'; return 1; }
        [[ "$(stat -c %u "$PREREQ_POLICY")" == "$(id -u)" ]] && (( (8#$(stat -c %a "$PREREQ_POLICY") & 0022) == 0 )) \
            || { prereq_fail POLICY_CONFLICT 'policy-rc.d has unsafe ownership or permissions.'; return 1; }
        if cmp -s "$PREREQ_STATE/policy.expected" "$PREREQ_POLICY"; then
            chmod 0755 "$PREREQ_POLICY"
            PREREQ_OWNS_POLICY=1 # Recover the exact policy left by an interrupted run.
            return 0
        fi
        # The Ubuntu lab images already have this minimal deny policy. Preserve it.
        if [[ -x "$PREREQ_POLICY" && "$(cat "$PREREQ_POLICY")" == $'#!/bin/sh\nexit 101' ]]; then
            return 0
        fi
        prereq_fail POLICY_CONFLICT 'An external policy-rc.d exists. Review it before installation; it was not overwritten.'
        return 1
    fi
    (set -o noclobber; cat "$PREREQ_STATE/policy.expected" >"$PREREQ_POLICY")
    chmod 0755 "$PREREQ_POLICY"
    PREREQ_OWNS_POLICY=1
}

prereq_cleanup() {
    if [[ "${PREREQ_OWNS_POLICY:-0}" == 1 ]] && cmp -s "$PREREQ_STATE/policy.expected" "$PREREQ_POLICY"; then
        rm -f "$PREREQ_POLICY"
    fi
}

prereq_apt() {
    # Keep locally edited conffiles, reject removals. APT owns its locks; never delete them.
    DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l apt-get "${PREREQ_APT[@]}" \
        -o Dpkg::Options::=--force-confold "$@"
}

prereq_write_source() {
    local archive="$1" security="$2" destination="$3"
    cat >"$destination" <<EOF
# rahandaz prerequisite sources v1; scoped to this installer, not system-wide
Types: deb
URIs: ${archive}
Suites: ${INSTALLER_OS_CODENAME} ${INSTALLER_OS_CODENAME}-updates
Components: main universe restricted multiverse
Architectures: amd64
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg

Types: deb
URIs: ${security}
Suites: ${INSTALLER_OS_CODENAME}-security
Components: main universe restricted multiverse
Architectures: amd64
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg
EOF
}

prereq_select_sources() {
    local id selectable resource provider archive security codenames rest candidate
    local -a candidates=(ubuntu-archive)
    if [[ "$PREREQ_IRAN" == yes ]]; then
        candidates=(liara arvan iranserver chabokan)
        printf 'Only Iranian catalog mirrors will be tried. No silent upstream fallback.\n'
    fi
    # Only known installer-owned source files are removed; external APT sources are untouched.
    rm -f "$PREREQ_APT_DIR/parts/php.sources"
    for candidate in "${candidates[@]}"; do
        while IFS='|' read -r id selectable resource provider archive security codenames rest; do
            [[ "$id" == "$candidate" && "$selectable" == yes && "$resource" == apt && ",$codenames," == *",$INSTALLER_OS_CODENAME,"* ]] || continue
            [[ "$archive" == https://* && "$security" == https://* ]] || continue
            prereq_write_source "$archive" "$security" "$PREREQ_APT_DIR/ubuntu.sources"
            chmod 0644 "$PREREQ_APT_DIR/ubuntu.sources"
            printf 'Checking signed archive, updates and security indexes: %s\n' "$id"
            if prereq_apt update && prereq_freshness; then
                prereq_audit "mirror_selected=$id"
                return 0
            fi
            printf 'Mirror %s failed. Trying the next allowed mirror without disabling signature or TLS checks.\n' "$id"
        done <"$INSTALLER_HOME/manifests/mirrors.v1"
    done
    prereq_fail MIRROR_UNAVAILABLE 'No allowed mirror passed APT validation. Fix connectivity and rerun the same command.'
}

prereq_freshness() {
    # APT already verified these InRelease files. Bound staleness even if Valid-Until is absent.
    # The base release is intentionally old; only moving updates/security suites have an age limit.
    local suite file issued timestamp now limit found
    now="$(date -u +%s)"
    for suite in security updates; do
        found=0
        limit=604800
        [[ "$suite" == updates ]] && limit=1209600
        for file in "$PREREQ_APT_DIR/lists/"*"_dists_${INSTALLER_OS_CODENAME}-${suite}_InRelease"; do
            [[ -f "$file" ]] || continue
            found=1
            issued="$(sed -n 's/^Date: //p' "$file" | head -n 1)"
            [[ -n "$issued" ]] || { prereq_fail METADATA_DATE_MISSING 'Signed moving metadata has no release date.'; return 1; }
            timestamp="$(date -u -d "$issued" +%s 2>/dev/null)" || return 1
            if (( timestamp > now + 300 || now - timestamp > limit )); then
                printf 'Rejected stale or future-dated %s metadata. Check mirror freshness and server clock.\n' "$suite" >&2
                return 1
            fi
        done
        (( found == 1 )) || { prereq_fail METADATA_MISSING "Signed ${suite} InRelease metadata was not found."; return 1; }
    done
}

prereq_package_ok() {
    [[ "$(dpkg-query -W -f '${Status}' "$1" 2>/dev/null || true)" == 'install ok installed' ]]
}

prereq_repair_packages() {
    local audit
    audit="$(dpkg --audit)"
    if [[ -z "$audit" ]] && prereq_apt check; then return 0; fi
    printf 'The package database is incomplete or dependencies are broken.\n%s\n' "$audit"
    printf 'Recovery configures pending system packages and repairs dependencies; it can run their maintainer scripts.\n'
    if [[ "$PREREQ_APPROVE_REPAIR" != 1 ]]; then
        [[ "$PREREQ_YES" != 1 ]] && prereq_confirm 'Allow package database recovery?' \
            || { prereq_fail REPAIR_APPROVAL_REQUIRED 'Review the package state and rerun with --approve-package-repair, or answer interactively.'; return 1; }
    fi
    prereq_audit package_repair_started
    # Never force pending package configuration past another package-manager lock.
    if ! DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l dpkg --force-confold --configure -a; then
        printf 'Pending configuration needs dependency repair.\n'
    fi
    prereq_apt --simulate --no-remove --fix-broken install
    prereq_apt -y --no-remove --fix-broken install
    DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l dpkg --force-confold --configure -a
    [[ -z "$(dpkg --audit)" ]] && prereq_apt check \
        || { prereq_fail PACKAGE_REPAIR_FAILED 'Package state is still unhealthy. Preserve the state directory and rerun after fixing the reported error.'; return 1; }
    prereq_audit package_repair_verified
}

prereq_install_packages() {
    local package
    local -a missing=()
    for package in "$@"; do
        if prereq_package_ok "$package"; then
            printf '[SKIP] Package is fully configured: %s\n' "$package"
        else
            printf '[INSTALL] Package is absent or incomplete: %s\n' "$package"
            missing+=("$package")
        fi
    done
    if (( ${#missing[@]} )); then
        prereq_apt --simulate --no-remove install "${missing[@]}"
        prereq_apt -y --no-remove install "${missing[@]}"
    fi
    for package in "$@"; do
        prereq_package_ok "$package" || { prereq_fail PACKAGE_INCOMPLETE "Package did not reach installed/configured state: $package"; return 1; }
    done
}

prereq_php_source() {
    local keyring="$PREREQ_APT_DIR/php.gpg" source="$PREREQ_APT_DIR/parts/php.sources" allowed
    allowed="$(installer_php_allowed_packages)"
    installer_php_key_ok
    gpg --batch --yes --dearmor --output "$keyring" "$INSTALLER_HOME/trust/sury-php.asc"
    cat >"$source" <<EOF
Types: deb
URIs: ${INSTALLER_PHP_REPO}
Suites: ${INSTALLER_OS_CODENAME}
Components: main
Architectures: amd64
Signed-By: ${keyring}
EOF
    cat >"$PREREQ_APT_DIR/preferences" <<EOF
Package: ${allowed}
Pin: origin ${INSTALLER_PHP_ORIGIN}
Pin-Priority: 500

Package: *
Pin: origin ${INSTALLER_PHP_ORIGIN}
Pin-Priority: -1
EOF
    chmod 0644 "$keyring" "$source" "$PREREQ_APT_DIR/preferences"
    printf 'PHP 8.4 uses its separately pinned repository; Ubuntu mirrors do not mirror PHP automatically.\n'
    prereq_apt update
}

prereq_mark_new_services() {
    local package binary
    for package in nginx postgresql redis-server; do
        binary="$package"
        [[ "$package" == postgresql ]] && binary=pg_lsclusters
        # A binary or unit installed outside this metapackage is also an existing service.
        if command -v "$binary" >/dev/null || [[ -e "/lib/systemd/system/${package}.service" || -e "/etc/systemd/system/${package}.service" ]]; then
            continue
        fi
        if ! dpkg-query -W -f '${Status}' "$package" 2>/dev/null | grep -q 'install ok installed'; then
            # An unpacked/half-configured package from outside this script is an existing installation.
            if ! dpkg-query -W -f '${Status}' "$package" 2>/dev/null | grep -Eq ' (installed|unpacked|half-installed|half-configured|triggers-awaited|triggers-pending)$'; then
                : >"$PREREQ_STATE/owned/$package"
            fi
        fi
    done
}

prereq_verify_runtime() {
    nginx -t
    /usr/bin/php8.4 -r '
        if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 4) { exit(1); }
        foreach (["pdo_pgsql", "redis", "sodium", "zip", "fileinfo", "mbstring", "curl", "xml", "bcmath", "intl", "gd"] as $extension) {
            if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: ".$extension."\n"); exit(1); }
        }
    '
    /usr/sbin/php-fpm8.4 -t
    command -v pg_isready >/dev/null
    command -v redis-cli >/dev/null
}

prereq_service() {
    local package="$1" unit="$2" port="$3" state
    state="$(systemctl is-enabled "$unit" 2>/dev/null || true)"
    [[ "$state" != masked && "$state" != masked-runtime ]] \
        || { prereq_fail SERVICE_MASKED "${unit} is masked. It was not unmasked."; return 1; }
    if ! systemctl is-active --quiet "$unit"; then
        if [[ ! -f "$PREREQ_STATE/owned/$package" ]]; then
            [[ "$PREREQ_YES" != 1 ]] && prereq_confirm "Existing ${unit} is inactive. Allow starting and enabling it?" \
                || { prereq_fail EXISTING_SERVICE "Activation of existing ${unit} needs interactive approval."; return 1; }
        fi
        if ss -H -lnt "sport = :$port" | grep -q .; then
            prereq_fail PORT_CONFLICT "Port ${port} is already listening while ${unit} is inactive. No process was stopped."
            return 1
        fi
        systemctl start "$unit"
        systemctl enable "$unit"
    else
        printf '[SKIP] Service is already active; no restart or enable-state change: %s\n' "$unit"
    fi
    systemctl is-active --quiet "$unit" || { prereq_fail SERVICE_UNHEALTHY "${unit} is not active."; return 1; }
}

prereq_health() {
    prereq_service nginx nginx.service 80
    prereq_service postgresql postgresql.service 5432
    prereq_service redis-server redis-server.service 6379
    # postgresql.service can be active (exited); verify actual clusters rather than its aggregate unit.
    local version cluster port status owner directory logfile found=0
    while read -r version cluster port status owner directory logfile; do
        [[ -n "$version" ]] || continue
        found=1
        [[ "$status" == online* ]] && runuser -u postgres -- pg_isready -q -h /var/run/postgresql -p "$port" \
            || { prereq_fail POSTGRES_UNHEALTHY 'A PostgreSQL cluster is offline or not accepting connections. Existing cluster configuration was preserved.'; return 1; }
    done < <(pg_lsclusters --no-header)
    (( found == 1 )) || { prereq_fail POSTGRES_UNHEALTHY 'No PostgreSQL cluster exists. No existing data directory was initialized or replaced.'; return 1; }
    [[ "$(redis-cli -h 127.0.0.1 -p 6379 ping)" == PONG ]] \
        || { prereq_fail REDIS_UNHEALTHY 'Redis did not return PONG on loopback:6379. Existing authentication/configuration was preserved.'; return 1; }
    curl --noproxy '*' --fail --silent --show-error --max-time 10 http://127.0.0.1/ >/dev/null \
        || { prereq_fail NGINX_UNHEALTHY 'Nginx did not pass a local HTTP check. Existing sites were preserved; inspect their listen/address settings.'; return 1; }
}

prereq_main() {
    local mode=apply arg
    PREREQ_YES=0 PREREQ_IRAN='' PREREQ_APPROVE_REPAIR=0 PREREQ_OWNS_POLICY=0
    # No environment override can redirect privileged state or policy paths.
    PREREQ_STATE=/var/lib/rahandaz-prerequisites-v1
    PREREQ_APT_DIR=/var/lib/rahandaz-prerequisites-apt-v1
    PREREQ_POLICY=/usr/sbin/policy-rc.d
    while (( $# )); do
        arg="$1"; shift
        case "$arg" in
            --plan) mode=plan ;;
            --apply) mode=apply ;;
            --yes) PREREQ_YES=1 ;;
            --iranian-mirrors)
                (( $# )) && [[ "$1" == yes || "$1" == no ]] || { prereq_fail INVALID_INPUT '--iranian-mirrors requires yes or no.'; return 2; }
                PREREQ_IRAN="$1"; shift ;;
            --approve-package-repair) PREREQ_APPROVE_REPAIR=1 ;;
            --help|-h)
                printf '%s\n' 'Rahandaz prerequisites v1' \
                    'Usage: sudo bash installer/bin/rahandaz-prerequisites [--apply]' \
                    '       bash installer/bin/rahandaz-prerequisites --plan' \
                    'Automation: --yes --iranian-mirrors yes|no [--approve-package-repair]' \
                    'Targets: Ubuntu 22.04 and 24.04 LTS amd64. Ubuntu 26.04 is not an install target. VM certification is pending.' \
                    'Installs prerequisites only. No application installation or update is performed.'
                return 0 ;;
            *) prereq_fail INVALID_INPUT 'Unknown option. Use --help.'; return 2 ;;
        esac
    done
    installer_detect_os
    if [[ "$mode" != plan ]]; then
        installer_os_install_target || return 1
    fi
    prereq_resources
    if [[ "$mode" == plan ]]; then
        printf '\nPLAN ONLY: no prompts, downloads, package changes or service activation.\n'
        printf 'Apply will confirm this resource report, then ask about Iranian mirrors.\n'
        printf 'Packages: ca-certificates curl gnupg python3 acl iproute2 nginx postgresql postgresql-client redis-server and PHP 8.4 extensions.\n'
        printf 'Mirror policy: %s (yes means Iranian catalog only; no means official Ubuntu).\n' "${PREREQ_IRAN:-ask on apply}"
        printf 'Existing packages/configuration are preserved; incomplete package recovery needs separate approval.\n'
        return 0
    fi
    installer_mutation_allowed
    [[ "$(installer_sysroot)" == / ]] || { prereq_fail LIVE_APPLY_REFUSED 'This entry point does not apply to a synthetic sysroot.'; return 1; }
    prereq_capacity
    if [[ "$PREREQ_YES" != 1 ]]; then
        prereq_confirm 'Is this server resource report correct and do you want to continue?' || return 1
    fi
    if [[ -z "$PREREQ_IRAN" ]]; then
        [[ "$PREREQ_YES" != 1 ]] || { prereq_fail INPUT_REQUIRED '--yes requires an explicit --iranian-mirrors yes or no.'; return 2; }
        installer_decide_iranian_mirrors || return $?
        case "${INSTALLER_MIRROR_CHOICE:-ask}" in
            yes) PREREQ_IRAN=yes ;;
            no) PREREQ_IRAN=no ;;
            *)
                local answer
                while :; do
                    printf 'Is your server located in Iran and do you want Iranian Ubuntu mirrors? [y/n]: ' >&2
                    IFS= read -r answer || { prereq_fail INPUT_REQUIRED 'Mirror selection is required.'; return 1; }
                    case "$answer" in y|Y) PREREQ_IRAN=yes; break ;; n|N) PREREQ_IRAN=no; break ;; *) printf 'Please enter y or n.\n' >&2 ;; esac
                done
                ;;
        esac
    fi
    printf '\nWill install/verify Nginx, PostgreSQL, Redis and PHP 8.4 with scoped signed APT sources.\n'
    printf 'Existing files are preserved. New services will be started only after validation.\n'
    printf 'Iranian Ubuntu mirrors: %s. PHP uses its separate repository.\n' "$PREREQ_IRAN"
    [[ "$PREREQ_YES" == 1 ]] || prereq_confirm 'Proceed with prerequisite installation?' || return 1
    prereq_php_default_guard
    for arg in apt-get dpkg dpkg-query flock gpgv ss; do
        # ss is installed below on minimal images, rather than required before installation.
        [[ "$arg" == ss ]] && continue
        command -v "$arg" >/dev/null || { prereq_fail BOOTSTRAP_MISSING "Required Ubuntu base tool is missing: $arg"; return 1; }
    done
    [[ -s /usr/share/keyrings/ubuntu-archive-keyring.gpg && -s /etc/ssl/certs/ca-certificates.crt ]] \
        || { prereq_fail BOOTSTRAP_TRUST_MISSING 'Ubuntu archive keyring and trusted CA certificates are required for secure bootstrap.'; return 1; }
    prereq_init
    trap 'prereq_cleanup' EXIT
    trap 'prereq_audit interrupted; exit 130' INT
    trap 'prereq_audit interrupted; exit 143' TERM
    trap 'prereq_on_error "$?"' ERR
    prereq_audit started
    prereq_policy_acquire
    prereq_select_sources
    prereq_mark_new_services
    prereq_repair_packages
    prereq_install_packages ca-certificates curl gnupg python3 acl iproute2 sudo ufw
    prereq_audit base_packages_verified
    prereq_php_source
    prereq_audit php_repository_verified
    # Refuse silently introducing a new PostgreSQL major alongside a pre-existing cluster.
    if command -v pg_lsclusters >/dev/null && [[ -n "$(pg_lsclusters --no-header)" ]] && ! prereq_package_ok postgresql; then
        prereq_fail EXISTING_POSTGRES 'Existing PostgreSQL clusters were found without the distribution metapackage. Review version compatibility before installing a new default major.'
        return 1
    fi
    prereq_install_packages nginx postgresql postgresql-client redis-server "${INSTALLER_PHP_PACKAGES[@]}"
    prereq_audit runtime_packages_verified
    prereq_verify_runtime
    prereq_cleanup
    if [[ "$(installer_pid1_name)" != systemd ]]; then
        prereq_audit packages_verified_services_require_vm
        printf 'INCOMPLETE: packages verified, but service activation and health require a systemd VM.\n'
        trap - ERR
        return 10
    fi
    prereq_health
    prereq_audit prerequisites_verified
    printf 'Prerequisites verified. The Rahandaz application has not been installed.\n'
    if [[ "$PREREQ_YES" != 1 ]] && prereq_confirm 'Continue with administrator and private database security bootstrap?'; then
        bash "${INSTALLER_HOME}/bin/rahandaz-security" --apply
    else
        printf 'Security bootstrap pending: sudo bash installer/bin/rahandaz-security --apply\n'
    fi
}
