#!/usr/bin/env bash
# Fresh disposable containers need an init process to reap package-postinstall children.
set -Eeuo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
shift
repo="$(cd "$LAB_ROOT/../../.." && pwd)"
docker run --rm --init --cpus=1 --memory=2g --pids-limit=512 \
    --volume "$repo/infrastructure/operations:/opt/rahandaz-src/infrastructure/operations:ro" \
    --volume "$LAB_ROOT/scripts/inside:/opt/rahandaz-lab/scripts:ro" \
    --entrypoint python3 "rahandaz-ubuntu-lab:$version" /opt/rahandaz-lab/scripts/software-catalog.py "$@"
