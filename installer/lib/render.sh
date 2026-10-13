#!/usr/bin/env bash
# Render templates. Tokens are single-line and cannot carry shell metacharacters.

if [[ -n "${INSTALLER_LIB_RENDER:-}" ]]; then
    return 0
fi
INSTALLER_LIB_RENDER=1

installer_token_ok() {
    local value="$1"
    [[ -n "$value" ]] || return 1
    [[ "$value" != *$'\n'* ]] || return 1
    [[ "$value" != *'$'* && "$value" != *'`'* && "$value" != *';'* ]] || return 1
    return 0
}

installer_render_template() {
    local template="$1"
    local dest="$2"
    shift 2
    local content token value
    content="$(cat "$template")"
    while [[ "$#" -gt 0 ]]; do
        token="$1"
        value="$2"
        shift 2
        installer_token_ok "$value" || {
            installer_error INVALID_TEMPLATE "Refusing an unsafe template value."
            return 1
        }
        content="${content//@@${token}@@/${value}}"
    done
    if [[ "$content" == *"@@"* ]]; then
        installer_error INVALID_TEMPLATE "A template token was left unsubstituted."
        return 1
    fi
    mkdir -p "$(dirname "$dest")"
    printf '%s\n' "$content" >"$dest"
}
