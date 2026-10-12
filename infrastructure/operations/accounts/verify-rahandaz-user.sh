#!/usr/bin/env bash
set -euo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../lib/bootstrap.sh"
ops_init "account.verify_rahandaz_user" "${1:-}"
ops_account_verify_rahandaz
