#!/usr/bin/env bash
# Assemble only the control-plane runtime, never the developer's working data.
set -euo pipefail

exec python3 "$(dirname "${BASH_SOURCE[0]}")/assemble.py" "$@"
