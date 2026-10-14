#!/usr/bin/env bash
# Rahandaz self-update. Verification and recovery do not boot the Laravel release being replaced.

if [[ -n "${INSTALLER_LIB_UPDATE:-}" ]]; then
    return 0
fi
INSTALLER_LIB_UPDATE=1

installer_release_file() {
    local installed
    if [[ -n "${RAHANDAZ_RELEASE_FILE:-}" && -f "$RAHANDAZ_RELEASE_FILE" && ! -L "$RAHANDAZ_RELEASE_FILE" ]]; then
        printf '%s\n' "$RAHANDAZ_RELEASE_FILE"
        return 0
    fi
    installed="$(installer_root_path /var/lib/rahandaz/release.json)"
    if [[ -f "$installed" && ! -L "$installed" ]]; then
        printf '%s\n' "$installed"
        return 0
    fi
    printf '%s\n' "${INSTALLER_HOME}/release/development.json"
}

installer_release_field() {
    local file="$1"
    local field="$2"
    python3 - "$file" "$field" <<'PY'
import json, sys
with open(sys.argv[1], encoding="utf-8") as handle:
    data = json.load(handle)
value = data.get(sys.argv[2], "")
if not isinstance(value, str):
    raise SystemExit(1)
if "\n" in value or "\r" in value:
    raise SystemExit(1)
sys.stdout.write(value)
PY
}

installer_release_print() {
    local file version channel source
    file="$(installer_release_file)"
    version="$(installer_release_field "$file" version)"
    channel="$(installer_release_field "$file" channel)"
    source="$(basename "$file")"
    installer_say "product Rahandaz"
    installer_say "version ${version}"
    installer_say "channel ${channel}"
    installer_say "php ${INSTALLER_PHP_SERIES}"
    installer_say "source ${source}"
}

installer_cli_doctor() {
    local installed php_ok=0
    installer_php_key_ok || return 1
    installed="$(installer_root_path /var/lib/rahandaz/release.json)"
    if installer_php84_binary_ok; then
        installer_say "php_binary ok ${INSTALLER_PHP_BIN}"
        php_ok=1
    else
        installer_say "php_binary warn not_installed"
    fi
    if [[ -f "$installed" && "$php_ok" -ne 1 ]]; then
        installer_error PHP_BASELINE_UNAVAILABLE "An installed Rahandaz release requires /usr/bin/php8.4."
        return 1
    fi
    installer_say "doctor ok"
    return 0
}

installer_cli_status() {
    local file
    installer_release_print
    file="$(installer_release_file)"
    installer_say "release_id $(installer_release_field "$file" release_id)"
    installer_say "installer_version $(installer_release_field "$file" installer_version)"
    installer_say "updater_version $(installer_release_field "$file" updater_version)"
    if installer_php84_binary_ok; then
        installer_say "php_binary ok"
    else
        installer_say "php_binary not_installed"
    fi
    return 0
}

installer_update_normalize_version() {
    local raw="${1:-}"
    raw="${raw#v}"
    installer_semver_split "$raw" >/dev/null || {
        installer_error INVALID_VERSION "Version ${raw} is not a semantic version."
        return 1
    }
    printf '%s\n' "$raw"
}

installer_update_read_metadata() {
    local file="$1"
    local line key value
    UPDATE_META_VERSION=""
    UPDATE_META_CHANNEL=""
    UPDATE_META_PHP=""
    UPDATE_META_MIN=""
    UPDATE_META_SCHEMA=""
    UPDATE_META_ROLLBACK=""
    [[ -f "$file" && ! -L "$file" ]] || {
        installer_error UNVERIFIED_ARTIFACT "Update metadata is missing."
        return 1
    }
    line="$(sed -n '1p' "$file")"
    [[ "$line" == "rahandaz-update-metadata 1" ]] || {
        installer_error UNVERIFIED_ARTIFACT "Update metadata header is not supported."
        return 1
    }
    while IFS= read -r line || [[ -n "$line" ]]; do
        [[ "$line" == *=* ]] || continue
        key="${line%%=*}"
        value="${line#*=}"
        [[ "$key" =~ ^[a-z_]+$ ]] || {
            installer_error UNVERIFIED_ARTIFACT "Update metadata contains an invalid field."
            return 1
        }
        [[ "$value" =~ ^[A-Za-z0-9._-]+$ ]] || {
            installer_error UNVERIFIED_ARTIFACT "Update metadata contains an invalid value."
            return 1
        }
        case "$key" in
            version) UPDATE_META_VERSION="$value" ;;
            channel) UPDATE_META_CHANNEL="$value" ;;
            php) UPDATE_META_PHP="$value" ;;
            min_version) UPDATE_META_MIN="$value" ;;
            schema) UPDATE_META_SCHEMA="$value" ;;
            rollback) UPDATE_META_ROLLBACK="$value" ;;
        esac
    done <"$file"
    [[ "$UPDATE_META_CHANNEL" == "stable" || "$UPDATE_META_CHANNEL" == "beta" || "$UPDATE_META_CHANNEL" == "development" ]] || return 1
    [[ "$UPDATE_META_PHP" == "8.4" ]] || {
        installer_error UPDATE_UNSUPPORTED "The release is not built for PHP 8.4."
        return 1
    }
    [[ "$UPDATE_META_SCHEMA" == "forward" || "$UPDATE_META_SCHEMA" == "destructive" ]] || return 1
    [[ "$UPDATE_META_ROLLBACK" == "application" || "$UPDATE_META_ROLLBACK" == "manual" ]] || return 1
    installer_semver_split "$UPDATE_META_VERSION" >/dev/null || return 1
    installer_semver_split "$UPDATE_META_MIN" >/dev/null || return 1
}

installer_update_channel_allowed() {
    local channel="$1"
    local allowed="${RAHANDAZ_UPDATE_CHANNEL:-stable}"
    case "$allowed" in
        stable | beta | development) ;;
        *)
            installer_error INVALID_INPUT "Update channel is invalid."
            return 1
            ;;
    esac
    if [[ "$allowed" == "stable" && "$channel" != "stable" ]]; then
        installer_error UPDATE_UNSUPPORTED "The configured channel only accepts stable releases."
        return 1
    fi
    if [[ "$allowed" == "beta" && "$channel" == "development" ]]; then
        installer_error UPDATE_UNSUPPORTED "Development releases are outside the beta channel."
        return 1
    fi
    return 0
}

installer_update_state_dir() {
    if [[ -n "${INSTALLER_UPDATE_STATE_DIR:-}" ]]; then
        printf '%s\n' "$INSTALLER_UPDATE_STATE_DIR"
        return 0
    fi
    installer_root_path /var/lib/rahandaz/updater
}

installer_update_history_add() {
    local version="$1"
    local result="$2"
    local dir file
    dir="$(installer_update_state_dir)"
    mkdir -p "$dir"
    chmod 0700 "$dir"
    file="${dir}/history"
    touch "$file"
    chmod 0600 "$file"
    printf '%s %s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$version" "$result" >>"$file"
}

installer_update_history_show() {
    local file
    file="$(installer_update_state_dir)/history"
    if [[ ! -f "$file" ]]; then
        installer_say "history empty"
        return 0
    fi
    cat "$file" >&2
}

installer_update_lock() {
    local dir
    dir="$(installer_update_state_dir)"
    mkdir -p "$dir"
    chmod 0700 "$dir"
    UPDATE_LOCK_FILE="${dir}/update.lock"
    exec {UPDATE_LOCK_FD}>"$UPDATE_LOCK_FILE"
    if ! flock -n "$UPDATE_LOCK_FD"; then
        installer_error UPDATE_LOCKED "Another update is already running."
        return 1
    fi
}

installer_update_backup() {
    local dir plan refs path
    dir="$(installer_update_state_dir)/backup"
    mkdir -p "$dir"
    chmod 0700 "$dir"
    plan="${dir}/backup.plan"
    {
        printf '%s\n' 'database pg_dump --dbname=rahandaz'
        printf '%s\n' 'database_command sudo -u postgres pg_dump --no-password --dbname=rahandaz'
        printf '%s\n' 'scope rahandaz-owned-only'
        printf '%s\n' 'pg_dump not_run'
    } >"$plan"
    chmod 0600 "$plan"
    refs="${dir}/secret-references.txt"
    {
        printf '%s\n' 'path=/etc/rahandaz/master.key'
        printf '%s\n' 'path=/etc/rahandaz/canary.json'
        printf '%s\n' 'path=/var/lib/rahandaz/release.json'
    } >"$refs"
    chmod 0600 "$refs"
    for path in \
        /etc/nginx/sites-available/rahandaz.conf \
        /etc/systemd/system/rahandaz-php-fpm.service \
        /etc/systemd/system/rahandaz-queue.service \
        /etc/systemd/system/rahandaz-scheduler.service \
        /etc/systemd/system/rahandaz-scheduler.timer \
        /etc/systemd/system/redis-rahandaz-cache.service \
        /var/lib/rahandaz/release.json
    do
        if [[ -f "$(installer_root_path "$path")" && ! -L "$(installer_root_path "$path")" ]]; then
            mkdir -p "${dir}$(dirname "$path")"
            cp -a "$(installer_root_path "$path")" "${dir}${path}"
        fi
    done
    if [[ -f "$(installer_root_path /etc/rahandaz/master.key)" && ! -L "$(installer_root_path /etc/rahandaz/master.key)" ]]; then
        mkdir -p "${dir}/etc/rahandaz"
        cp -a "$(installer_root_path /etc/rahandaz/master.key)" "${dir}/etc/rahandaz/master.key"
        chmod 0600 "${dir}/etc/rahandaz/master.key"
    fi
    installer_say "BACKUP pg_dump not_run"
}

installer_update_prepare() {
    local version="$1"
    local artifact="$2"
    local dest
    dest="$(installer_root_path "/var/lib/rahandaz/releases/${version}")"
    if [[ -d "$dest" && -f "$dest/artisan" && -f "$dest/composer.lock" && -f "$dest/public/build/manifest.json" ]]; then
        installer_say "RELEASE ${version} already prepared"
        return 0
    fi
    installer_tar_members_safe "$artifact" || {
        installer_error UNVERIFIED_ARTIFACT "Artifact contains an unsafe path."
        return 1
    }
    mkdir -p "$dest"
    tar -xzf "$artifact" -C "$dest" --strip-components=1
    [[ -f "$dest/artisan" && -f "$dest/composer.lock" && -f "$dest/public/build/manifest.json" ]] || {
        installer_error ARTIFACT_INCOMPLETE "The release is missing artisan, composer.lock, or built assets."
        return 1
    }
}

installer_update_save_attempt() {
    local version="$1"
    local previous="$2"
    local dir file
    dir="$(installer_update_state_dir)"
    mkdir -p "$dir"
    chmod 0700 "$dir"
    file="${dir}/attempt.meta"
    {
        printf '%s\n' 'rahandaz-update-attempt 1'
        printf '%s\n' "version=${version}"
        printf '%s\n' "previous=${previous}"
        printf '%s\n' "schema=${UPDATE_META_SCHEMA}"
        printf '%s\n' "rollback=${UPDATE_META_ROLLBACK}"
    } >"$file"
    chmod 0600 "$file"
}

installer_update_verify_inputs() {
    local key version installed_file installed cmp
    [[ -n "${UPDATE_VERSION:-}" ]] || {
        installer_error INVALID_VERSION "Update requires --version."
        return 1
    }
    version="$(installer_update_normalize_version "$UPDATE_VERSION")" || return 1
    UPDATE_VERSION="$version"
    [[ -n "${UPDATE_ARTIFACT:-}" && -f "$UPDATE_ARTIFACT" && ! -L "$UPDATE_ARTIFACT" ]] || {
        installer_error UNVERIFIED_ARTIFACT "Update artifact is missing."
        return 1
    }
    [[ -n "${UPDATE_MANIFEST:-}" && -n "${UPDATE_SIGNATURE:-}" && -n "${UPDATE_PUBLIC_KEY:-}" ]] || {
        installer_error UNVERIFIED_ARTIFACT "Update requires a manifest, signature, and public key."
        return 1
    }
    [[ -n "${UPDATE_METADATA:-}" && -n "${UPDATE_METADATA_SIGNATURE:-}" ]] || {
        installer_error UNVERIFIED_ARTIFACT "Update requires signed metadata."
        return 1
    }
    key="$(installer_public_key "$UPDATE_PUBLIC_KEY")" || return 1
    installer_verify_manifest "$UPDATE_MANIFEST" "$UPDATE_SIGNATURE" "$key" "$UPDATE_ARTIFACT" || return 1
    if [[ "$INSTALLER_RELEASE_VERSION" != "$UPDATE_VERSION" ]]; then
        installer_error INVALID_VERSION "The manifest version does not match --version."
        return 1
    fi
    installer_verify_signature "$UPDATE_METADATA" "$UPDATE_METADATA_SIGNATURE" "$key" || return 1
    installer_update_read_metadata "$UPDATE_METADATA" || return 1
    if [[ "$UPDATE_META_VERSION" != "$UPDATE_VERSION" ]]; then
        installer_error INVALID_VERSION "The metadata version does not match --version."
        return 1
    fi
    installer_update_channel_allowed "$UPDATE_META_CHANNEL" || return 1
    installed_file="$(installer_release_file)"
    installed="$(installer_release_field "$installed_file" version)"
    cmp="$(installer_semver_compare "$installed" "$UPDATE_META_MIN")" || return 1
    if [[ "$cmp" == "-1" ]]; then
        installer_error UPDATE_UNSUPPORTED "The installed version is older than the release minimum."
        return 1
    fi
    cmp="$(installer_semver_compare "$UPDATE_VERSION" "$installed")" || return 1
    if [[ "$cmp" == "0" ]]; then
        installer_error ALREADY_INSTALLED "Version ${UPDATE_VERSION} is already installed."
        return 1
    fi
    if [[ "$cmp" == "-1" ]]; then
        installer_error UPDATE_UNSUPPORTED "The selected version is older than the installed version."
        return 1
    fi
    if [[ "$UPDATE_META_SCHEMA" == "destructive" && "${UPDATE_APPROVE_MIGRATIONS:-0}" != "1" ]]; then
        installer_error MIGRATION_REFUSED "This release declares a destructive schema change and needs --approve-migrations."
        return 1
    fi
    return 0
}

installer_update_check() {
    local cmp
    installer_say "channel ${RAHANDAZ_UPDATE_CHANNEL:-stable}"
    if [[ -z "${UPDATE_VERSION:-}" && -z "${UPDATE_METADATA:-}" ]]; then
        installer_say "update_available no"
        return 0
    fi
    if ! installer_update_verify_inputs; then
        if [[ "${INSTALLER_ERROR_CODE}" == "ALREADY_INSTALLED" || "${INSTALLER_ERROR_CODE}" == "UPDATE_UNSUPPORTED" ]]; then
            installer_say "update_available no"
            return 0
        fi
        return 1
    fi
    cmp="$(installer_semver_compare "$UPDATE_VERSION" "$(installer_release_field "$(installer_release_file)" version)")"
    if [[ "$cmp" == "1" ]]; then
        installer_say "update_available yes"
        installer_say "target ${UPDATE_VERSION}"
        return 0
    fi
    installer_say "update_available no"
}

installer_update_apply() {
    local installed previous current downloaded
    if [[ "${UPDATE_DRY_RUN:-0}" != "1" ]]; then
        installer_mutation_allowed || return 1
        installer_update_lock || return 1
        if [[ -n "${UPDATE_URL:-}" ]]; then
            downloaded="$(installer_update_state_dir)/artifact.download"
            installer_download "$UPDATE_URL" "$downloaded" || return 1
            UPDATE_ARTIFACT="$downloaded"
        fi
    fi
    installer_update_verify_inputs || return 1
    installer_say "PLAN verify signature checksum php 8.4 backup prepare health"
    installer_say "PLAN services rahandaz-php-fpm rahandaz-queue rahandaz-scheduler"
    if [[ "${UPDATE_DRY_RUN:-0}" == "1" ]]; then
        installer_say "RESULT dry_run"
        return 0
    fi
    installer_update_backup || return 1
    installed="$(installer_release_field "$(installer_release_file)" version)"
    installer_update_save_attempt "$UPDATE_VERSION" "$installed"
    installer_update_prepare "$UPDATE_VERSION" "$UPDATE_ARTIFACT" || return 1
    current="$(installer_root_path /var/lib/rahandaz/current)"
    previous="$(readlink -f "$current" 2>/dev/null || true)"
    if [[ "$(installer_sysroot)" != "/" || "${INSTALLER_OFFLINE:-}" == "1" || "${RAHANDAZ_UPDATE_RUN_MIGRATE:-0}" != "1" ]]; then
        installer_say "HEALTH not_run"
        installer_say "MIGRATION not_run"
        if [[ -n "$previous" ]]; then
            installer_say "CURRENT kept ${previous}"
        fi
        installer_update_history_add "$UPDATE_VERSION" incomplete
        installer_say "RESULT incomplete"
        return 10
    fi
    installer_error HEALTHCHECK_FAILED "Live health verification did not run in this environment."
    installer_update_history_add "$UPDATE_VERSION" incomplete
    return 10
}

installer_update_rollback() {
    local file version previous schema rollback current target
    if [[ "${UPDATE_APPROVE_DATABASE:-0}" == "1" ]]; then
        installer_error DATABASE_RECOVERY_REFUSED "Database recovery is not performed by the updater."
        installer_say "manual_recovery_required"
        return 1
    fi
    installer_mutation_allowed || return 1
    installer_update_lock || return 1
    file="$(installer_update_state_dir)/attempt.meta"
    [[ -f "$file" && ! -L "$file" ]] || {
        installer_error manual_recovery_required "No update attempt is recorded."
        installer_say "manual_recovery_required"
        return 1
    }
    version="$(awk -F= '$1 == "version" { print $2 }' "$file")"
    previous="$(awk -F= '$1 == "previous" { print $2 }' "$file")"
    schema="$(awk -F= '$1 == "schema" { print $2 }' "$file")"
    rollback="$(awk -F= '$1 == "rollback" { print $2 }' "$file")"
    if [[ "$schema" != "forward" || "$rollback" != "application" ]]; then
        installer_say "manual_recovery_required"
        installer_update_history_add "${version:-unknown}" manual_recovery_required
        return 1
    fi
    target="$(installer_root_path "/var/lib/rahandaz/releases/${previous}")"
    if [[ ! -f "$target/artisan" ]]; then
        installer_say "manual_recovery_required"
        installer_update_history_add "${version:-unknown}" manual_recovery_required
        return 1
    fi
    current="$(installer_root_path /var/lib/rahandaz/current)"
    ln -sfn "$target" "$current"
    installer_say "application_rollback ${previous}"
    installer_say "HEALTH not_run"
    installer_update_history_add "$previous" rollback
    installer_say "RESULT incomplete"
    return 10
}

installer_update_repair() {
    local status
    installer_mutation_allowed || return 1
    MODE="repair"
    HTTP_PORT="${HTTP_PORT:-80}"
    SERVER_NAME="${SERVER_NAME:-_}"
    INSTALLER_STATE_DIR="$(installer_update_state_dir)"
    installer_state_init
    set +e
    stage_11_nginx_php_fpm
    status=$?
    set -e
    [[ "$status" -eq 0 || "$status" -eq 2 ]] || return 1
    set +e
    stage_12_systemd_services
    status=$?
    set -e
    [[ "$status" -eq 0 || "$status" -eq 2 ]] || return 1
    installer_say "REPAIR rendered Rahandaz files only"
    return 0
}
