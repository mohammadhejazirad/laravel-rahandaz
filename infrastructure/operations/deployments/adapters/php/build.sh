#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../../../lib/bootstrap.sh"
ops_init "deploy.adapter.php" "${1:-}"
ops_deploy_adapter php
