#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../lib/bootstrap.sh"
ops_init "docker.stack.apply" "${1:-}"
slug="$(ops_require_text project_slug)"
ops_check docker-slug "$slug"
if ! ops_wants_apply; then
    ops_preview "$(python3 -c 'import json,sys; print(json.dumps({"action":"stack","slug":sys.argv[1]},separators=(",",":")))' "$slug")" '{"observed":false,"host_executed":false}'
fi
ops_require_approval
ops_fail ENV_BRIDGE_REQUIRED "Encrypted env cannot be opened by the shell contract yet."
