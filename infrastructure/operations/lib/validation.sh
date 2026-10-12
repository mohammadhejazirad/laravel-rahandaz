#!/usr/bin/env bash

ops_require_text() {
    local key="$1"
    local value
    value="$(ops_get "$key" || true)"
    if [[ -z "$value" ]]; then
        ops_fail INVALID_INPUT "${key} is required."
    fi
    printf '%s' "$value"
}

ops_require_ident() {
    local key="$1"
    local value
    value="$(ops_require_text "$key")"
    ops_check ident "$value"
    printf '%s' "$value"
}

ops_require_unit() {
    local value
    value="$(ops_require_text unit)"
    ops_check unit "$value"
    printf '%s' "$value"
}

ops_require_domain() {
    local value
    value="$(ops_require_text domain)"
    ops_check domain "$value"
    printf '%s' "$value"
}

ops_require_package() {
    local value
    value="$(ops_require_text package)"
    ops_check package "$value"
    printf '%s' "$value"
}

ops_require_sha() {
    local value
    value="$(ops_require_text commit_sha)"
    ops_check sha "$value"
    printf '%s' "$value"
}

ops_require_project_user() {
    local value
    value="$(ops_require_text username)"
    ops_check project-user "$value"
    printf '%s' "$value"
}

ops_flag() {
    local key="$1"
    local value
    value="$(ops_get "$key" || true)"
    [[ "$value" == "true" ]]
}
