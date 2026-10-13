#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
printf 'Container stdout is the sleep entrypoint. Suite diagnostics are in tests/infrastructure/ubuntu-lab/reports.\n' >&2
exec "${COMPOSE[@]}" logs --tail 200 "$(lab_service "$version")"
