#!/usr/bin/env bash
# Installer network contract v1. Persist one validated origin/port before package changes.
if [[ -n "${INSTALLER_LIB_NETWORK:-}" ]]; then return 0; fi
INSTALLER_LIB_NETWORK=1

installer_detect_panel_origin() {
    python3 "${INSTALLER_HOME}/lib/network.py" detect
}

installer_configure_network() {
    local config saved='' candidate default_port answer
    config="${INSTALLER_STATE_DIR}/network.json"
    if [[ "$MODE" != plan && ( -e "$config" || -L "$config" ) ]]; then
        saved="$(python3 "${INSTALLER_HOME}/lib/network.py" load "$config")" || return 1
        if [[ -z "$PUBLIC_URL" ]]; then
            PUBLIC_URL="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["origin"])' "$saved")"
        fi
        if [[ -z "$HTTP_PORT" ]]; then
            HTTP_PORT="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["port"])' "$saved")"
        fi
        if [[ "$SERVER_NAME" == _ ]]; then
            SERVER_NAME="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["server_name"])' "$saved")"
        fi
    fi
    if [[ -z "$PUBLIC_URL" ]]; then
        if [[ "$MODE" == plan ]]; then
            PUBLIC_URL=http://127.0.0.1
        else
            PUBLIC_URL="$(installer_detect_panel_origin)" || {
                installer_error ADDRESS_DETECTION_FAILED 'The server IPv4 address could not be determined. Provide --public-url explicitly and retry.'
                return 1
            }
        fi
    fi
    default_port="$(python3 "${INSTALLER_HOME}/lib/network.py" default "$PUBLIC_URL")" || return 1
    if [[ -z "$HTTP_PORT" ]]; then
        if [[ "$MODE" == plan ]]; then
            HTTP_PORT="$default_port"
        else
            if ! { exec 9<>/dev/tty; } 2>/dev/null; then
                installer_error INVALID_INPUT 'Provide --http-port without an interactive terminal.'
                return 1
            fi
            while true; do
                printf 'Panel port [%s]: ' "$default_port" >&9
                if ! IFS= read -r answer <&9; then
                    exec 9>&-
                    installer_error INVALID_INPUT 'Panel port input was interrupted; no packages were changed.'
                    return 1
                fi
                answer="${answer:-$default_port}"
                if python3 "${INSTALLER_HOME}/lib/network.py" port "$answer" 2>/dev/null; then
                    HTTP_PORT="$answer"
                    break
                fi
                printf '%s\n' 'Invalid port. Use 1024..65535 and avoid browser-restricted ports.' >&9
            done
            exec 9>&-
        fi
    fi
    candidate="$(python3 "${INSTALLER_HOME}/lib/network.py" resolve "$PUBLIC_URL" "$HTTP_PORT" "$SERVER_NAME")" || return 1
    if [[ -n "$saved" && "$candidate" != "$saved" ]]; then
        installer_error NETWORK_CONFIG_CHANGED 'Resume must keep the original origin and port. Change the port from panel settings after installation.'
        return 1
    fi
    PUBLIC_URL="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["origin"])' "$candidate")"
    HTTP_PORT="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["port"])' "$candidate")"
    SERVER_NAME="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["server_name"])' "$candidate")"
    PANEL_PUBLIC_IPV4="$(python3 -c 'import json,sys; print(int(json.loads(sys.argv[1])["public_ipv4"]))' "$candidate")"
    INSTALLER_NETWORK_JSON="$candidate"
    installer_say "PANEL_ORIGIN ${PUBLIC_URL}"
    installer_say "PANEL_PORT ${HTTP_PORT}"
}

installer_save_network() {
    [[ "$MODE" != plan ]] || return 0
    local config="${INSTALLER_STATE_DIR}/network.json" tmp
    [[ ! -e "$config" ]] || return 0
    installer_network_preflight || return 1
    tmp="$(mktemp "${config}.XXXXXX")" || return 1
    printf '%s\n' "$INSTALLER_NETWORK_JSON" >"$tmp"
    chmod 0600 "$tmp"
    mv "$tmp" "$config"
}

installer_network_preflight() {
    [[ "$(installer_sysroot)" == / && "${INSTALLER_OFFLINE:-}" != 1 ]] || return 0
    python3 "${INSTALLER_HOME}/lib/network.py" free "$HTTP_PORT" || {
        installer_error PORT_IN_USE 'The selected panel port cannot be bound. Free it before retrying the same installation.'
        return 1
    }
}

installer_enroll_panel_listener() {
    [[ "${PANEL_PUBLIC_IPV4:-0}" == 1 ]] || return 0
    local provision args=()
    provision=/var/lib/rahandaz/current/infrastructure/operations/panel/provision.py
    if [[ -e /etc/rahandaz/panel-listener.json || -e /etc/systemd/system/rahandaz-panel-helper.socket ]]; then args+=(--upgrade); fi
    /usr/bin/python3 -I "$provision" --host "$SERVER_NAME" --current-port "$HTTP_PORT" --allow-public-http "${args[@]}"
}
