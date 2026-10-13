#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
service="$(lab_service "$version")"
"${COMPOSE[@]}" ps "$service"
digest="$(docker image inspect "ubuntu:${version}" --format '{{index .RepoDigests 0}}' 2>/dev/null || true)"
printf 'image ubuntu:%s digest %s\n' "$version" "${digest:-unknown}"
