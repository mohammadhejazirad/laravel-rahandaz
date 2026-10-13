#!/usr/bin/env bash
# Shared installer helpers. Source this file; do not execute it.

if [[ -n "${INSTALLER_LIB_COMMON:-}" ]]; then
    return 0
fi
INSTALLER_LIB_COMMON=1

installer_say() {
    local message
    message="$(installer_redact "$*")"
    printf '%s\n' "$message" >&2
    if [[ -n "${INSTALLER_LOG:-}" ]]; then
        printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$message" >>"$INSTALLER_LOG"
    fi
}

installer_redact() {
    local line="$1"
    line="$(printf '%s\n' "$line" | sed -E \
        -e 's/(APP_KEY=).*/\1[redacted]/g' \
        -e 's/(DB_PASSWORD=).*/\1[redacted]/g' \
        -e 's/((REDIS_|DATABASE_)?[A-Z0-9_]*PASSWORD=).*/\1[redacted]/g' \
        -e 's/(password[:=])[^[:space:]]*/\1[redacted]/Ig' \
        -e 's/-----BEGIN [A-Z ]*PRIVATE KEY-----/[redacted-key]/g')"
    if [[ -n "${INSTALLER_REDACT_EXTRA:-}" ]]; then
        line="${line//${INSTALLER_REDACT_EXTRA}/[redacted]}"
    fi
    printf '%s' "$line"
}

installer_error() {
    INSTALLER_ERROR_CODE="$1"
    installer_say "ERROR $1: $2"
    if [[ -n "${INSTALLER_STATE_FILE:-}" && -f "$INSTALLER_STATE_FILE" ]]; then
        installer_state_set error_code "$1" || true
    fi
}

installer_url_ok() {
    local url="$1"
    local rest hostport
    [[ "$url" =~ ^https://[A-Za-z0-9._:-]+(/|$) ]] || return 1
    [[ "$url" != *" "* ]] || return 1
    [[ "$url" != *$'\n'* ]] || return 1
    rest="${url#https://}"
    hostport="${rest%%/*}"
    [[ "$hostport" != *"@"* ]] || return 1
    [[ "${url,,}" != *"access_token="* ]] || return 1
    [[ "$url" != *"ghp_"* ]] || return 1
    [[ "$url" != *"?token="* && "$url" != *"&token="* ]] || return 1
    return 0
}

installer_backoff() {
    local attempt="$1"
    local exp=1
    local step jitter
    local i
    if [[ "${INSTALLER_BACKOFF_SKIP:-}" == "1" ]]; then
        return 0
    fi
    for ((i = 1; i < attempt; i++)); do
        exp=$((exp * 2))
        if ((exp > 16)); then
            exp=16
            break
        fi
    done
    jitter=$((RANDOM % 500))
    step="$(awk -v e="$exp" -v j="$jitter" 'BEGIN { printf "%.3f", e + (j / 1000) }')"
    sleep "$step"
}

installer_retry() {
    local max="$1"
    shift
    local attempt=1
    local status
    while ((attempt <= max)); do
        set +e
        "$@"
        status=$?
        set -e
        if [[ "$status" -eq 0 ]]; then
            return 0
        fi
        if [[ "$status" -ge 100 ]]; then
            return "$status"
        fi
        if ((attempt == max)); then
            return "$status"
        fi
        installer_backoff "$attempt"
        attempt=$((attempt + 1))
    done
    return 1
}

installer_require_cmd() {
    command -v "$1" >/dev/null 2>&1
}
