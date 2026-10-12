#!/usr/bin/env bash
# Shared entry for every Rahandaz operation. Source this file; do not execute it.

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    printf '%s\n' "bootstrap.sh is a library." >&2
    exit 1
fi

ops_init() {
    local operation="$1"
    local input="${2:-}"
    local lib_dir
    lib_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    OPS_ROOT="$(cd "$lib_dir/.." && pwd)"
    OPS_OPERATION="$operation"
    # shellcheck source=common.sh
    source "$lib_dir/common.sh"
    # Result JSON uses fd 3 so a failure inside $(...) still reaches the caller.
    if [[ "${OPS_RESULT_READY:-}" != "1" ]]; then
        exec 3>&1
        OPS_RESULT_READY=1
    fi
    ops_load_contract
    if [[ -z "$input" || ! -f "$input" || -L "$input" ]]; then
        ops_fail INVALID_INPUT "Operation input must be a regular JSON file."
    fi
    OPS_INPUT="$input"
    ops_require_execution_id
}

ops_load_contract() {
    local row
    if ! row="$(python3 "$OPS_ROOT/lib/opsjson.py" registry "$OPS_ROOT/contracts/registry.json" "$OPS_OPERATION" 2>/dev/null)"; then
        OPS_OPERATION_VERSION="0"
        OPS_EXECUTION_ID="00000000-0000-7000-8000-000000000000"
        ops_fail UNKNOWN_OPERATION "Operation is not registered."
    fi
    OPS_OPERATION_VERSION="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["version"])' "$row")"
    OPS_PRIVILEGE="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["privilege"])' "$row")"
    OPS_TIMEOUT="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["timeout_seconds"])' "$row")"
    OPS_MUTATING="$(python3 -c 'import json,sys; print("true" if json.loads(sys.argv[1])["mutating"] else "false")' "$row")"
    OPS_LOCK="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1]).get("lock") or "")' "$row")"
}
