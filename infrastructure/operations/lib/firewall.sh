#!/usr/bin/env bash

ops_fw_plan() {
    python3 -c 'import json,sys; print(json.dumps({"action":sys.argv[1],"flush":False},separators=(",",":")))' "$1"
}

ops_fw_install() {
    local plan
    plan="$(ops_fw_plan install)"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_fail PACKAGE_UNTESTED "UFW install has no passing VM test."
}

ops_fw_status() {
    local plan
    plan="$(ops_fw_plan status)"
    ops_preview "$plan" '{"mutated":false}'
}

ops_fw_list() {
    ops_fw_status
}

ops_fw_add() {
    local plan port proto
    port="$(ops_require_text port)"
    proto="$(ops_require_text protocol)"
    case "$port" in
        22 | 80 | 443 | 8080) ;;
        *) ops_fail INVALID_INPUT "Port is not in the allowlist." ;;
    esac
    case "$proto" in
        tcp | udp) ;;
        *) ops_fail INVALID_INPUT "Protocol is not allowed." ;;
    esac
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"add","port":int(sys.argv[1]),"protocol":sys.argv[2],"flush":False},separators=(",",":")))' "$port" "$proto")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_require_approval
    printf '%s/%s\n' "$port" "$proto" >>"$OPS_SANDBOX/ufw.rules"
    chmod 0600 "$OPS_SANDBOX/ufw.rules"
    ops_succeed "$plan" '{"added":true}' true
}

ops_fw_remove() {
    ops_require_approval
    local plan
    plan="$(ops_fw_plan remove)"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    : >"$OPS_SANDBOX/ufw.removed"
    ops_succeed "$plan" '{"removed_managed_only":true}' true
}

ops_fw_enable() {
    local plan
    plan="$(ops_fw_plan enable)"
    if ! ops_flag ssh_rule_present || ! ops_flag panel_rule_present || ! ops_flag recovery_verified; then
        ops_fail PRECONDITION_FAILED "SSH, the panel path, and recovery must be present before a deny policy."
    fi
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_require_approval
    printf '%s\n' 'enabled' >"$OPS_SANDBOX/ufw.state"
    ops_succeed "$plan" '{"enabled":true,"flushed":false}' true
}

ops_fw_disable() {
    local plan
    plan="$(ops_fw_plan disable)"
    if ! ops_flag recovery_verified; then
        ops_fail PRECONDITION_FAILED "Disabling the firewall needs a verified recovery path."
    fi
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_require_approval
    printf '%s\n' 'disabled' >"$OPS_SANDBOX/ufw.state"
    ops_succeed "$plan" '{"disabled":true,"flushed":false}' true
}
