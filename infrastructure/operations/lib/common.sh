#!/usr/bin/env bash
# Orchestration helpers. External commands go through ops_exec.

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    printf '%s\n' "common.sh is a library." >&2
    exit 1
fi

set -euo pipefail

ops_py() {
    python3 "$OPS_ROOT/lib/opsjson.py" "$@"
}

ops_publish() {
    : "${OPS_OPERATION:=}"
    : "${OPS_OPERATION_VERSION:=0}"
    : "${OPS_EXECUTION_ID:=00000000-0000-7000-8000-000000000000}"
    : "${OPS_STATUS:=failed}"
    : "${OPS_ERROR_CODE:=}"
    if [[ -z "${OPS_OBSERVED:-}" ]]; then
        OPS_OBSERVED="{}"
    fi
    if [[ -z "${OPS_PLAN:-}" ]]; then
        OPS_PLAN="{}"
    fi
    : "${OPS_MUTATED:=false}"
    : "${OPS_MESSAGE:=}"
    export OPS_OPERATION OPS_OPERATION_VERSION OPS_EXECUTION_ID OPS_STATUS OPS_ERROR_CODE
    export OPS_OBSERVED OPS_PLAN OPS_MUTATED OPS_MESSAGE
}

ops_py_value() {
    local value rc err code
    err="$(mktemp)"
    set +e
    value="$(ops_py "$@" 2>"$err")"
    rc=$?
    set -e
    if [[ "$rc" -eq 0 ]]; then
        rm -f "$err"
        printf '%s' "$value"
        return 0
    fi
    code="$(tail -n 1 "$err" 2>/dev/null || true)"
    rm -f "$err"
    ops_fail "${code:-INVALID_INPUT}" "Validation failed."
}

ops_fail() {
    local code="$1"
    local message="$2"
    OPS_STATUS="failed"
    OPS_ERROR_CODE="$code"
    OPS_EXECUTION_ID="${OPS_EXECUTION_ID:-00000000-0000-7000-8000-000000000000}"
    OPS_OPERATION_VERSION="${OPS_OPERATION_VERSION:-0}"
    OPS_OBSERVED="{}"
    OPS_PLAN="{}"
    OPS_MUTATED="false"
    OPS_MESSAGE="$message"
    ops_publish
    ops_py emit-result >&3
    printf '%s\n' "$message" >&2
    exit 1
}

ops_get() {
    local value rc err code
    err="$(mktemp)"
    set +e
    value="$(ops_py get "$OPS_INPUT" "$1" 2>"$err")"
    rc=$?
    set -e
    if [[ "$rc" -eq 0 ]]; then
        rm -f "$err"
        printf '%s' "$value"
        return 0
    fi
    if [[ "$rc" -eq 2 ]]; then
        rm -f "$err"
        printf ''
        return 2
    fi
    code="$(tail -n 1 "$err" 2>/dev/null || true)"
    rm -f "$err"
    ops_fail "${code:-INVALID_INPUT}" "Input could not be read."
}

ops_require_execution_id() {
    local execution_id
    execution_id="$(ops_get execution_id || true)"
    if [[ -z "$execution_id" ]]; then
        ops_fail MISSING_EXECUTION_ID "execution_id is required."
    fi
    if ! ops_py uuid "$execution_id" >/dev/null; then
        ops_fail MISSING_EXECUTION_ID "execution_id must be a UUID v7."
    fi
    OPS_EXECUTION_ID="$execution_id"
}

ops_check() {
    local command="$1"
    shift
    local err code
    err="$(mktemp)"
    if ! ops_py "$command" "$@" >/dev/null 2>"$err"; then
        code="$(tail -n 1 "$err" 2>/dev/null || true)"
        rm -f "$err"
        ops_fail "${code:-INVALID_INPUT}" "Validation failed."
    fi
    rm -f "$err"
}

ops_wants_apply() {
    [[ "${RAHANDAZ_OPS_APPLY:-}" == "1" ]]
}

ops_runtime_home() {
    if [[ "${OPS_LAB:-}" == "1" ]]; then
        printf '%s' /run/rahandaz-lab/tmp
        return 0
    fi
    printf '%s' "${OPS_SANDBOX:-/tmp}"
}

ops_systemd_running() {
    [[ -d /run/systemd/system ]] || return 1
    local comm
    comm="$(tr -d '\0' </proc/1/comm 2>/dev/null || true)"
    [[ "$comm" == "systemd" ]]
}

ops_refuse_without_systemd() {
    if ! ops_systemd_running; then
        ops_fail UNSUPPORTED_IN_CONTAINER "systemd is not pid 1 here, so the unit was not changed."
    fi
}

ops_sandbox_path() {
    local candidate="$1"
    if [[ "${OPS_LAB:-}" == "1" ]]; then
        ops_py_value lab-path "$candidate"
        return 0
    fi
    ops_py_value inside "$OPS_SANDBOX" "$candidate"
}

ops_lab_enter() {
    if [[ "${RAHANDAZ_OPS_LAB:-}" != "1" ]]; then
        return 1
    fi
    if [[ "${RAHANDAZ_OPS_HELPER_SOCKET:-}" == "1" ]]; then
        ops_fail LIVE_MUTATION_BLOCKED "The lab marker is not a privileged helper socket."
    fi
    local err code
    err="$(mktemp)"
    if ! ops_py lab-ok >/dev/null 2>"$err"; then
        code="$(tail -n 1 "$err" 2>/dev/null || true)"
        rm -f "$err"
        ops_fail "${code:-LIVE_MUTATION_BLOCKED}" "Container lab gate refused this host."
    fi
    rm -f "$err"
    OPS_LAB=1
    OPS_SANDBOX=/
    mkdir -p /run/rahandaz-lab/tmp /run/rahandaz-lab/locks /run/rahandaz-lab/sql
    chmod 0700 /run/rahandaz-lab/tmp /run/rahandaz-lab/locks /run/rahandaz-lab/sql
    if [[ "$OPS_PRIVILEGE" == "project" ]]; then
        if [[ "${RAHANDAZ_OPS_HELPER_CONFIRMED:-}" == "1" ]]; then
            ops_fail ROOT_SCRIPT_FORBIDDEN "A project operation cannot enter the privileged helper."
        fi
        if [[ "${RAHANDAZ_OPS_PROJECT_CONFIRMED:-}" != "1" ]]; then
            ops_fail APPROVAL_REQUIRED "Project confirmation is required before apply."
        fi
    fi
    if [[ -n "$OPS_LOCK" ]]; then
        ops_lock "$OPS_LOCK"
    fi
    return 0
}

ops_require_sandbox_apply() {
    if ops_lab_enter; then
        return 0
    fi
    if [[ "$OPS_PRIVILEGE" == "project" ]]; then
        if [[ "${RAHANDAZ_OPS_HELPER_CONFIRMED:-}" == "1" ]]; then
            ops_fail ROOT_SCRIPT_FORBIDDEN "A project operation cannot enter the privileged helper."
        fi
        if [[ "${RAHANDAZ_OPS_PROJECT_CONFIRMED:-}" != "1" ]]; then
            ops_fail APPROVAL_REQUIRED "Project confirmation is required before apply."
        fi
    elif [[ "${RAHANDAZ_OPS_HELPER_CONFIRMED:-}" != "1" ]]; then
        ops_fail APPROVAL_REQUIRED "Helper confirmation is required before apply."
    fi
    local sandbox="${RAHANDAZ_OPS_SANDBOX:-}"
    local err code
    if [[ -z "$sandbox" ]]; then
        ops_fail LIVE_MUTATION_BLOCKED "Live host mutation is blocked until a disposable VM workflow is approved."
    fi
    err="$(mktemp)"
    if ! OPS_SANDBOX="$(ops_py sandbox-ok "$sandbox" 2>"$err")"; then
        code="$(tail -n 1 "$err" 2>/dev/null || true)"
        rm -f "$err"
        ops_fail "${code:-LIVE_MUTATION_BLOCKED}" "Sandbox is not a safe directory."
    fi
    rm -f "$err"
    if [[ -n "${RAHANDAZ_OPS_BIN_DIR:-}" ]]; then
        ops_check inside "$OPS_SANDBOX" "${RAHANDAZ_OPS_BIN_DIR}"
    else
        ops_fail LIVE_MUTATION_BLOCKED "Mutating commands need a sandboxed command directory."
    fi
    if [[ -n "$OPS_LOCK" ]]; then
        ops_lock "$OPS_LOCK"
    fi
}

ops_preview() {
    OPS_STATUS="preview"
    OPS_ERROR_CODE=""
    OPS_OBSERVED="${2:-{\}}"
    OPS_PLAN="$1"
    OPS_MUTATED="false"
    OPS_MESSAGE=""
    ops_publish
    ops_py emit-result >&3
    exit 0
}

ops_succeed() {
    OPS_STATUS="succeeded"
    OPS_ERROR_CODE=""
    OPS_PLAN="$1"
    OPS_OBSERVED="${2:-{\}}"
    OPS_MUTATED="${3:-false}"
    OPS_MESSAGE=""
    ops_publish
    ops_py emit-result >&3
    exit 0
}

ops_resolve() {
    local name="$1"
    local dir candidate
    if [[ -n "${RAHANDAZ_OPS_BIN_DIR:-}" ]]; then
        candidate="${RAHANDAZ_OPS_BIN_DIR}/$name"
        if [[ -x "$candidate" && ! -L "$candidate" ]]; then
            printf '%s' "$candidate"
            return 0
        fi
        ops_fail TOOL_MISSING "Allowlisted command ${name} is not in the sandbox."
    fi
    for dir in /usr/bin /bin /usr/sbin /sbin; do
        candidate="${dir}/$name"
        if [[ -x "$candidate" && ! -L "$candidate" ]]; then
            printf '%s' "$candidate"
            return 0
        fi
    done
    ops_fail TOOL_MISSING "Allowlisted command ${name} was not found."
}

ops_exec_clean() {
    local bin="$1"
    shift
    local home path
    home="$(ops_runtime_home)"
    if [[ "${OPS_LAB:-}" == "1" ]]; then
        path="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
        local rc
        set +e
        env -i \
            PATH="$path" \
            HOME="$home" \
            LC_ALL=C \
            LANG=C \
            TMPDIR="$home" \
            DEBIAN_FRONTEND=noninteractive \
            NEEDRESTART_MODE=a \
            "$bin" "$@"
        rc=$?
        set -e
        if [[ "$rc" -ne 0 ]]; then
            return "$rc"
        fi
        return 0
    fi
    path="$(dirname "$bin")"
    env -i \
        PATH="$path" \
        HOME="$home" \
        LC_ALL=C \
        LANG=C \
        TMPDIR="$home" \
        "$bin" "$@"
}

ops_exec() {
    local name="$1"
    shift
    local bin
    bin="$(ops_resolve "$name")"
    ops_exec_clean "$bin" "$@"
}

ops_exec_stdin() {
    local name="$1"
    shift
    local bin payload
    bin="$(ops_resolve "$name")"
    payload="$(cat)"
    ops_exec_clean "$bin" "$@" <<<"$payload"
}

ops_lock() {
    local name="$1"
    local dir
    if [[ "${OPS_LAB:-}" == "1" ]]; then
        dir=/run/rahandaz-lab/locks
    else
        dir="${OPS_SANDBOX}/locks"
    fi
    mkdir -p "$dir"
    chmod 0700 "$dir"
    exec 9>"$dir/${name//\//_}.lock"
    if ! flock -n 9; then
        ops_fail LOCK_HELD "Another operation holds this lock."
    fi
}

# shellcheck source=logging.sh
source "$(dirname "${BASH_SOURCE[0]}")/logging.sh"
# shellcheck source=validation.sh
source "$(dirname "${BASH_SOURCE[0]}")/validation.sh"
# shellcheck source=security.sh
source "$(dirname "${BASH_SOURCE[0]}")/security.sh"
# shellcheck source=os-detection.sh
source "$(dirname "${BASH_SOURCE[0]}")/os-detection.sh"
# shellcheck source=locking.sh
source "$(dirname "${BASH_SOURCE[0]}")/locking.sh"
# shellcheck source=json-output.sh
source "$(dirname "${BASH_SOURCE[0]}")/json-output.sh"
# shellcheck source=packages.sh
source "$(dirname "${BASH_SOURCE[0]}")/packages.sh"
# shellcheck source=rollback.sh
source "$(dirname "${BASH_SOURCE[0]}")/rollback.sh"
# shellcheck source=healthcheck.sh
source "$(dirname "${BASH_SOURCE[0]}")/healthcheck.sh"
# shellcheck source=account.sh
source "$(dirname "${BASH_SOURCE[0]}")/account.sh"
# shellcheck source=ssh.sh
source "$(dirname "${BASH_SOURCE[0]}")/ssh.sh"
# shellcheck source=service.sh
source "$(dirname "${BASH_SOURCE[0]}")/service.sh"
# shellcheck source=nginx.sh
source "$(dirname "${BASH_SOURCE[0]}")/nginx.sh"
# shellcheck source=database.sh
source "$(dirname "${BASH_SOURCE[0]}")/database.sh"
# shellcheck source=ssl.sh
source "$(dirname "${BASH_SOURCE[0]}")/ssl.sh"
# shellcheck source=deploy.sh
source "$(dirname "${BASH_SOURCE[0]}")/deploy.sh"
# shellcheck source=firewall.sh
source "$(dirname "${BASH_SOURCE[0]}")/firewall.sh"
# shellcheck source=host.sh
source "$(dirname "${BASH_SOURCE[0]}")/host.sh"
# shellcheck source=backup.sh
source "$(dirname "${BASH_SOURCE[0]}")/backup.sh"
# shellcheck source=automation.sh
source "$(dirname "${BASH_SOURCE[0]}")/automation.sh"
