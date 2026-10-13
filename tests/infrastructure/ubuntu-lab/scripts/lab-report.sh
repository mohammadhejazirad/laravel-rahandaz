#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
latest="$(find "$LAB_ROOT/reports" -type f -path "*${version}*/summary.md" | sort | tail -n 1)"
if [[ -z "$latest" ]]; then
    lab_die "No report for Ubuntu ${version} yet." 1
fi
printf '%s\n' "$latest"
