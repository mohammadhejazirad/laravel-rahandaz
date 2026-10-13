#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
target="${1:-}"
if [[ "$target" == "all" ]]; then
    "${COMPOSE[@]}" stop ubuntu-22 ubuntu-24 ubuntu-26
    exit 0
fi
version="$(lab_version "$target")"
"${COMPOSE[@]}" stop "$(lab_service "$version")"
