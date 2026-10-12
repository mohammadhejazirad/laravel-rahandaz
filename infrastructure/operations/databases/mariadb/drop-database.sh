#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../../lib/bootstrap.sh"
ops_init "db.mariadb.drop_database" "${1:-}"
ops_db_drop_database mariadb
