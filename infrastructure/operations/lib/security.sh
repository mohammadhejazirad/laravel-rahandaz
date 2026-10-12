#!/usr/bin/env bash

ops_unit_class() {
    ops_py protected-unit "$1" 2>/dev/null || printf 'ordinary'
}

ops_reject_protected_stop() {
    local unit="$1"
    local class
    class="$(ops_unit_class "$unit")"
    if [[ "$class" == "protected" ]]; then
        ops_fail PROTECTED_SERVICE "Stop, disable, or removal of a protected service is refused."
    fi
    case "$unit" in
        ssh | ssh.service | sshd | sshd.service)
            ops_fail SSH_STOP_FORBIDDEN "sshd must not be stopped."
            ;;
    esac
}

ops_reject_root_uid() {
    local uid="$1"
    if [[ "$uid" == "0" || -z "$uid" ]]; then
        ops_fail ROOT_SCRIPT_FORBIDDEN "Custom code must not run as root."
    fi
}

ops_require_approval() {
    if ! ops_flag approval; then
        ops_fail APPROVAL_REQUIRED "This operation needs approval=true."
    fi
}
