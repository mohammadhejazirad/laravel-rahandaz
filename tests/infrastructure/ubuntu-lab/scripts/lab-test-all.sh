#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
mode="${RAHANDAZ_LAB_MODE:-sequential}"
if [[ "${1:-}" == "--parallel" ]]; then
    mode=parallel
fi
if [[ "$mode" == "parallel" ]]; then
    if ! lab_require_memory 6291456; then
        for version in 22.04 24.04 26.04; do
            lab_write_blocked "$version" "Parallel mode needs about 6GiB available." >/dev/null
        done
        lab_die "blocked_insufficient_resources" 3
    fi
    status=0
    pids=()
    for version in 22.04 24.04 26.04; do
        "$(dirname "${BASH_SOURCE[0]}")/lab-test.sh" "$version" &
        pids+=("$!")
    done
    for pid in "${pids[@]}"; do
        wait "$pid" || status=1
    done
    exit "$status"
fi
status=0
for version in 22.04 24.04 26.04; do
    "$(dirname "${BASH_SOURCE[0]}")/lab-test.sh" "$version" || status=1
done
exit "$status"
