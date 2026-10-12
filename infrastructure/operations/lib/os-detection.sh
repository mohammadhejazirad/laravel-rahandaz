#!/usr/bin/env bash

ops_os_file() {
    if [[ -n "${RAHANDAZ_OPS_OS_RELEASE:-}" ]]; then
        if [[ "$(basename "$RAHANDAZ_OPS_OS_RELEASE")" != "os-release" || -L "$RAHANDAZ_OPS_OS_RELEASE" || ! -f "$RAHANDAZ_OPS_OS_RELEASE" ]]; then
            ops_fail INVALID_INPUT "OS release override must be a regular os-release file."
        fi
        printf '%s' "$RAHANDAZ_OPS_OS_RELEASE"
        return 0
    fi
    printf '%s' "/etc/os-release"
}

ops_read_os() {
    local file
    file="$(ops_os_file)"
    ops_py_value os-release "$file"
}
