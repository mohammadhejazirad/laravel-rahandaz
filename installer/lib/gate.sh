#!/usr/bin/env bash
# Live mutation gate. A sysroot other than / never touches the developer host.
# Container apply requires the Ubuntu lab marker and does not open the operations engine.

if [[ -n "${INSTALLER_LIB_GATE:-}" ]]; then
    return 0
fi
INSTALLER_LIB_GATE=1

installer_sysroot() {
    printf '%s\n' "${RAHANDAZ_INSTALLER_SYSROOT:-/}"
}

installer_pid1_name() {
    local comm
    comm="$(tr -d ' \n' <"${INSTALLER_PROC_ROOT:-/proc}/1/comm" 2>/dev/null || true)"
    printf '%s\n' "$comm"
}

installer_lab_marker_ok() {
    local marker="${INSTALLER_LAB_MARKER:-/run/rahandaz-lab/marker}"
    local token="${RAHANDAZ_OPS_LAB_TOKEN:-}"
    local dockerenv="${INSTALLER_DOCKERENV:-/.dockerenv}"
    local mode owner line1 line2
    [[ -n "$token" ]] || return 1
    [[ -f "$marker" && ! -L "$marker" ]] || return 1
    [[ -f "$dockerenv" && ! -L "$dockerenv" ]] || return 1
    mode="$(stat -c '%a' "$marker" 2>/dev/null || true)"
    owner="$(stat -c '%u' "$marker" 2>/dev/null || true)"
    [[ "$mode" == "600" && "$owner" == "0" ]] || return 1
    line1="$(sed -n '1p' "$marker")"
    line2="$(sed -n '2p' "$marker")"
    [[ "$line1" == "rahandaz-lab-v1" ]] || return 1
    [[ ${#token} -ge 32 && "$token" =~ ^[0-9a-f]+$ ]] || return 1
    python3 - "$line2" "$token" <<'PY'
import secrets, sys
sys.exit(0 if secrets.compare_digest(sys.argv[1], sys.argv[2]) else 1)
PY
}

installer_mutation_allowed() {
    local root
    root="$(installer_sysroot)"
    if [[ "$root" != "/" ]]; then
        case "$root" in
            /tmp/* | /run/rahandaz-installer-test/*) return 0 ;;
        esac
        installer_error LIVE_APPLY_REFUSED "Sysroot must be / or a test directory under /tmp."
        return 1
    fi
    if [[ "$(id -u)" -ne 0 ]]; then
        installer_error PRIVILEGE_REQUIRED "Apply on / requires root."
        return 1
    fi
    if [[ "${RAHANDAZ_INSTALLER_REFUSE_LIVE:-}" == "1" ]]; then
        installer_error LIVE_APPLY_REFUSED "Live apply is refused in this environment."
        return 1
    fi
    local pid1
    pid1="$(installer_pid1_name)"
    if [[ "$pid1" != "systemd" ]]; then
        if installer_lab_marker_ok; then
            INSTALLER_ENVIRONMENT="container-lab"
            return 0
        fi
        installer_error LIVE_APPLY_REFUSED "Refusing to mutate a container that is not the Rahandaz lab."
        return 1
    fi
    INSTALLER_ENVIRONMENT="host"
    return 0
}
