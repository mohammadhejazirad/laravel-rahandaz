#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../../../lib/bootstrap.sh"
ops_init "deploy.adapter.python" "${1:-}"
ops_deploy_adapter python
