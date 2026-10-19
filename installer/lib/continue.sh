#!/usr/bin/env bash
# Same install command resumes a partial run and prepares a newer control-plane release without deleting local data.

if [[ -n "${INSTALLER_LIB_CONTINUE:-}" ]]; then
    return 0
fi
INSTALLER_LIB_CONTINUE=1

# shellcheck source=semver.sh
source "${INSTALLER_HOME}/lib/semver.sh"
# shellcheck source=update.sh
source "${INSTALLER_HOME}/lib/update.sh"

installer_stage_ids() {
    printf '%s\n' \
        01-preflight 02-repository-setup 03-system-packages 04-runtime-installation \
        05-operational-account 06-application-release 07-postgresql-setup 08-redis-setup \
        09-laravel-configuration 10-database-migration 11-nginx-php-fpm 12-systemd-services \
        13-first-boot 14-health-verification
}

installer_has_progress() {
    local id state
    while IFS= read -r id; do
        state="$(installer_state_get "stage.${id}")"
        [[ "$state" == "succeeded" || "$state" == "failed" || "$state" == "planned" ]] && return 0
    done < <(installer_stage_ids)
    return 1
}

installer_control_plane_prepare() {
    local version="$1"
    local artifact="$2"
    local marker current
    marker="$(installer_root_path /var/lib/postgresql)"
    current="$(installer_root_path /var/lib/rahandaz/current)"
    [[ -e "$marker" && ! -d "$marker" ]] && marker=""
    installer_update_prepare "$version" "$artifact" || return 1
    if [[ -n "$marker" && ! -d "$marker" ]]; then
        installer_error STATE_CORRUPT "The database directory marker disappeared."
        return 1
    fi
    if [[ -L "$current" || -d "$current" ]]; then
        installer_say "CURRENT kept"
    fi
    installer_state_set update.prepared "$version" || return 1
    installer_say "UPDATE prepared ${version}"
    installer_say "DATA kept. Projects, deployment releases, PostgreSQL and Redis data were not removed."
}

installer_route_existing() {
    local installed cmp
    if [[ "${MODE:-}" == "plan" || "${MODE:-}" == "doctor" ]]; then
        return 0
    fi
    if installer_has_progress; then
        installer_say "CONTINUE the same install command keeps succeeded stages and retries the unfinished ones."
    fi
    [[ "$(installer_state_get result)" == "success" ]] || return 0
    [[ -n "${INSTALLER_RELEASE_VERSION:-}" && -n "${INSTALLER_ARTIFACT:-}" ]] || return 0
    installed="$(installer_release_field "$(installer_release_file)" version)" || return 0
    [[ -n "$installed" ]] || return 0
    cmp="$(installer_semver_compare "$INSTALLER_RELEASE_VERSION" "$installed")" || return 1
    if [[ "$cmp" == "0" ]]; then
        installer_say "ALREADY_CURRENT ${installed}"
        installer_say "RESULT success"
        return 20
    fi
    if [[ "$cmp" == "-1" ]]; then
        installer_error UPDATE_UNSUPPORTED "The selected release is older than the installed release."
        return 1
    fi
    installer_control_plane_prepare "$INSTALLER_RELEASE_VERSION" "$INSTALLER_ARTIFACT" || return 1
    installer_say "RESULT incomplete"
    installer_say "ACTIVATION waits for a VM healthcheck. The current symlink and database were not switched."
    return 10
}
