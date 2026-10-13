#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
if [[ ! -t 0 ]]; then
    lab_die "lab-shell needs an interactive terminal."
fi
exec "${COMPOSE[@]}" exec "$(lab_service "$version")" bash
