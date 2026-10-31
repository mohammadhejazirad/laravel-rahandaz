#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../lib/bootstrap.sh"
# shellcheck source=../lib/docker.sh
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../lib/docker.sh"
ops_init "docker.compose.restart" "${1:-}"
ops_docker_action restart
