#!/usr/bin/env bash
# Line-oriented installer state. Values are single tokens. Secrets are rejected.

if [[ -n "${INSTALLER_LIB_STATE:-}" ]]; then
    return 0
fi
INSTALLER_LIB_STATE=1

installer_state_init() {
    local dir="${INSTALLER_STATE_DIR:?}"
    mkdir -p "$dir"
    chmod 0700 "$dir"
    INSTALLER_STATE_FILE="$dir/state"
    if [[ ! -f "$INSTALLER_STATE_FILE" ]]; then
        : >"$INSTALLER_STATE_FILE"
        chmod 0600 "$INSTALLER_STATE_FILE"
    fi
    if [[ -L "$INSTALLER_STATE_FILE" ]]; then
        installer_error STATE_CORRUPT "State file must be a regular file."
        return 1
    fi
}

installer_state_get() {
    local key="$1"
    [[ -n "${INSTALLER_STATE_FILE:-}" && -f "$INSTALLER_STATE_FILE" ]] || return 0
    awk -v k="$key" '$1 == k { print $2; exit }' "$INSTALLER_STATE_FILE"
}

installer_state_set() {
    local key="$1"
    local value="$2"
    local tmp
    [[ "$key" =~ ^[a-z0-9._-]+$ ]] || {
        installer_error STATE_CORRUPT "Refusing a state key that is not a token."
        return 1
    }
    [[ "$value" =~ ^[A-Za-z0-9_.:-]+$ ]] || {
        installer_error STATE_CORRUPT "Refusing a state value that is not a token."
        return 1
    }
    case "$value" in
        *password* | *secret* | *token* | *PRIVATE*)
            installer_error STATE_CORRUPT "Refusing to persist a secret-like state value."
            return 1
            ;;
    esac
    tmp="$(mktemp "${INSTALLER_STATE_FILE}.XXXXXX")"
    if [[ -s "$INSTALLER_STATE_FILE" ]]; then
        awk -v k="$key" '$1 != k { print }' "$INSTALLER_STATE_FILE" >"$tmp"
    fi
    printf '%s %s\n' "$key" "$value" >>"$tmp"
    chmod 0600 "$tmp"
    mv "$tmp" "$INSTALLER_STATE_FILE"
}

installer_stage_done() {
    local id="$1"
    [[ "$(installer_state_get "stage.${id}")" == "succeeded" ]]
}
