#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../../lib/bootstrap.sh"
ops_init "db.mongodb.create_role" "${1:-}"
ops_db_create_role mongodb
