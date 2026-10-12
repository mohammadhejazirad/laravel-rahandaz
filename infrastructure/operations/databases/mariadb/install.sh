#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../../lib/bootstrap.sh"
ops_init "db.mariadb.install" "${1:-}"
ops_db_install mariadb
