#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
shift || true
lab_confirm_reset "$version" "$@"
service="$(lab_service "$version")"
"${COMPOSE[@]}" stop "$service"
"${COMPOSE[@]}" rm -f "$service"
printf 'removed ubuntu %s lab container\n' "$version"
