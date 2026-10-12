#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../lib/bootstrap.sh"
ops_init "host.updates_preview" "${1:-}"
ops_host_updates_preview
