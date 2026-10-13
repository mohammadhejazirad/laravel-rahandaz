#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
service="$(lab_service "$version")"
if ! lab_require_memory 1843200; then
    lab_write_blocked "$version" "MemAvailable is below 1800MiB." >/dev/null
    lab_die "blocked_insufficient_resources" 3
fi
"${COMPOSE[@]}" up -d --build --no-deps "$service"
for _ in $(seq 1 60); do
    if "${COMPOSE[@]}" exec -T "$service" test -f /run/rahandaz-lab/marker; then
        printf 'ubuntu %s lab is up\n' "$version"
        exit 0
    fi
    sleep 1
done
lab_die "The lab marker was not created." 1
