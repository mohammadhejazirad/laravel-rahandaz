#!/usr/bin/env bash
# Shared helpers for the Ubuntu lab. These scripts never target the developer host SSH or firewall.
set -euo pipefail

LAB_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE=(docker compose --project-name rahandaz-ubuntu-lab --file "$LAB_ROOT/compose.yaml")

lab_die() {
    printf '%s\n' "$1" >&2
    exit "${2:-2}"
}

lab_version() {
    case "${1:-}" in
        22.04 | 24.04 | 26.04) printf '%s' "$1" ;;
        *) lab_die "Ubuntu version must be 22.04, 24.04, or 26.04." ;;
    esac
}

lab_service() {
    case "$1" in
        22.04) printf 'ubuntu-22' ;;
        24.04) printf 'ubuntu-24' ;;
        26.04) printf 'ubuntu-26' ;;
    esac
}

lab_memory_kb() {
    awk '/MemAvailable:/ {print $2}' /proc/meminfo
}

lab_require_memory() {
    local need_kb="$1"
    local avail
    avail="$(lab_memory_kb)"
    if [[ -z "$avail" || "$avail" -lt "$need_kb" ]]; then
        return 1
    fi
}

lab_write_blocked() {
    local version="$1" reason="$2"
    local dir stamp
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    dir="$LAB_ROOT/reports/${version}-${stamp}"
    mkdir -p "$dir"
    python3 - "$dir/result.json" "$version" "$reason" <<'PY'
import json, sys
path, version, reason = sys.argv[1:]
report = {
    "schema_version": "1.0",
    "ubuntu_version": version,
    "image": "ubuntu:" + version,
    "image_digest": None,
    "architecture": None,
    "environment_type": "container-lab",
    "status": "blocked",
    "error_code": "blocked_insufficient_resources",
    "detail": reason,
    "cases": [{
        "suite": "lab",
        "name": "resources",
        "started_at": None,
        "duration_seconds": 0,
        "status": "blocked",
        "error_code": "blocked_insufficient_resources",
        "log_ref": None,
        "environment_type": "container-lab",
        "detail": reason,
    }],
}
open(path, "w", encoding="utf-8").write(json.dumps(report, indent=2) + "\n")
PY
    printf '%s\n' "$dir/result.json"
}

lab_confirm_reset() {
    local version="$1"
    shift || true
    local arg
    for arg in "$@"; do
        if [[ "$arg" == "--yes" ]]; then
            return 0
        fi
    done
    if [[ "${RAHANDAZ_LAB_CONFIRM:-}" == "reset" ]]; then
        return 0
    fi
    if [[ -t 0 ]]; then
        printf 'This removes only the Ubuntu %s lab container. Type reset to continue: ' "$version" >&2
        local answer
        read -r answer
        [[ "$answer" == "reset" ]] || lab_die "Reset cancelled."
        return 0
    fi
    lab_die "Refusing to reset a non-interactive shell without RAHANDAZ_LAB_CONFIRM=reset or --yes."
}
