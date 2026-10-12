#!/usr/bin/env bash

ops_service_plan() {
    python3 -c 'import json,sys; print(json.dumps({"action":sys.argv[1],"unit":sys.argv[2]},separators=(",",":")))' "$1" "$2"
}

ops_service_reloadable() {
    case "$1" in
        nginx | nginx.service | ssh | ssh.service | postgresql | postgresql.service)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

ops_service_action() {
    local action="$1"
    local unit plan
    unit="$(ops_require_unit)"
    plan="$(ops_service_plan "$action" "$unit")"
    case "$action" in
        stop | disable | remove)
            ops_reject_protected_stop "$unit"
            ;;
        reload)
            if ! ops_service_reloadable "$unit"; then
                ops_fail INVALID_INPUT "This unit has no reload capability in the catalog."
            fi
            ;;
    esac
    if [[ "$action" == "status" ]]; then
        if ops_wants_apply; then
            ops_require_sandbox_apply
        fi
        if [[ -n "${RAHANDAZ_OPS_BIN_DIR:-}" ]]; then
            local state
            state="$(ops_exec systemctl show --property=ActiveState --value -- "$unit" || true)"
            ops_succeed "$plan" "{\"unit\":\"${unit}\",\"active_state\":\"${state}\"}" false
        fi
        ops_preview "$plan" '{"observed":false}'
    fi
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    case "$action" in
        start) ops_exec systemctl start -- "$unit" ;;
        stop) ops_exec systemctl stop -- "$unit" ;;
        restart) ops_exec systemctl restart -- "$unit" ;;
        reload) ops_exec systemctl reload -- "$unit" ;;
        enable) ops_exec systemctl enable -- "$unit" ;;
        disable) ops_exec systemctl disable -- "$unit" ;;
        install | remove | verify)
            ops_fail PACKAGE_UNTESTED "Service install and removal stay untested."
            ;;
        *)
            ops_fail INVALID_INPUT "Unknown service action."
            ;;
    esac
    local state
    state="$(ops_exec systemctl show --property=ActiveState --value -- "$unit")"
    if [[ "$action" == "start" || "$action" == "restart" || "$action" == "reload" ]] && [[ "$state" != "active" ]]; then
        ops_fail VERIFICATION_FAILED "The unit was not active after the operation."
    fi
    ops_succeed "$plan" "{\"unit\":\"${unit}\",\"active_state\":\"${state}\"}" true
}
