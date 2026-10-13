#!/usr/bin/env bash

ops_deploy_root() {
    local root
    if [[ "$OPS_SANDBOX" == "/" ]]; then
        root=/srv/projects
    else
        root="$OPS_SANDBOX/srv/projects"
    fi
    mkdir -p "$root"
    chmod 0750 "$root"
    printf '%s' "$root"
}

ops_deploy_release() {
    local project release root dest
    project="$(ops_require_ident project)"
    release="$(ops_require_ident release)"
    root="$(ops_deploy_root)"
    dest="$(ops_py_value inside "$root" "$root/$project/releases/$release")"
    printf '%s' "$dest"
}

ops_deploy_prepare() {
    local plan dest
    if ! ops_wants_apply; then
        ops_preview '{"action":"prepare_release"}'
    fi
    ops_require_sandbox_apply
    ops_reject_root_uid "${RAHANDAZ_OPS_PROJECT_UID:-1}"
    dest="$(ops_deploy_release)"
    mkdir -p "$dest/shared"
    chmod 0750 "$dest"
    plan='{"action":"prepare_release","activation":"symlink"}'
    ops_succeed "$plan" '{"prepared":true}' true
}

ops_deploy_checkout() {
    local sha plan
    sha="$(ops_require_sha)"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"checkout","commit":sys.argv[1],"network":"blocked"},separators=(",",":")))' "$sha")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_fail PRECONDITION_FAILED "Source checkout does not open a network connection from this engine slice."
}

ops_deploy_build() {
    local framework plan
    framework="$(ops_require_text framework)"
    case "$framework" in
        laravel | php | nodejs | nextjs | nuxtjs | python | static) ;;
        *) ops_fail INVALID_INPUT "Framework adapter is not registered." ;;
    esac
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"build","framework":sys.argv[1],"uid":"project","root":False},separators=(",",":")))' "$framework")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_reject_root_uid "${RAHANDAZ_OPS_PROJECT_UID:-}"
    if [[ "${OPS_LAB:-}" == "1" ]]; then
        ops_fail UNSUPPORTED_IN_CONTAINER "Project builds use systemd-run limits and were not started without a VM systemd."
    fi
    ops_exec systemd-run --uid="${RAHANDAZ_OPS_PROJECT_UID}" --property=CPUQuota=50% --property=MemoryMax=512M --property=TasksMax=128 --property=RuntimeMaxSec="${OPS_TIMEOUT}" --property=NoNewPrivileges=yes echo build
    ops_succeed "$plan" '{"built":true,"privileged_helper":false}' true
}

ops_deploy_activate() {
    local project release root current plan
    if ! ops_wants_apply; then
        ops_preview '{"action":"activate","method":"atomic-symlink"}'
    fi
    ops_require_sandbox_apply
    project="$(ops_require_ident project)"
    release="$(ops_require_ident release)"
    root="$(ops_deploy_root)/$project"
    mkdir -p "$root/releases/$release"
    current="$root/current"
    ln -sfn "$root/releases/$release" "$current.tmp"
    mv -Tf "$current.tmp" "$current"
    plan='{"action":"activate","downtime":"conditional"}'
    if [[ "$(readlink "$current")" != "$root/releases/$release" ]]; then
        ops_fail VERIFICATION_FAILED "The active release symlink does not match the requested release."
    fi
    ops_succeed "$plan" '{"active":true}' true
}

ops_deploy_verify() {
    local url plan
    url="$(ops_local_health_url)"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"verify","health_url":sys.argv[1]},separators=(",",":")))' "$url")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    if ! ops_exec curl --fail --silent --max-time 5 --output /dev/null -- "$url"; then
        ops_fail VERIFICATION_FAILED "Loopback health check failed."
    fi
    ops_succeed "$plan" '{"healthy":true}' false
}

ops_deploy_rollback() {
    local project previous root current plan
    if ! ops_wants_apply; then
        ops_preview '{"action":"rollback","method":"previous-symlink"}'
    fi
    ops_require_sandbox_apply
    ops_require_approval
    project="$(ops_require_ident project)"
    previous="$(ops_require_ident previous_release)"
    root="$(ops_deploy_root)/$project"
    if [[ ! -d "$root/releases/$previous" ]]; then
        ops_fail PRECONDITION_FAILED "The previous release directory was not observed."
    fi
    current="$root/current"
    ln -sfn "$root/releases/$previous" "$current.tmp"
    mv -Tf "$current.tmp" "$current"
    plan='{"action":"rollback"}'
    ops_succeed "$plan" '{"rolled_back":true}' true
}

ops_deploy_adapter() {
    local framework="$1"
    local input_framework
    input_framework="$(ops_get framework || true)"
    if [[ "$input_framework" != "$framework" ]]; then
        ops_fail INVALID_INPUT "This adapter only accepts its own framework."
    fi
    ops_deploy_build
}
