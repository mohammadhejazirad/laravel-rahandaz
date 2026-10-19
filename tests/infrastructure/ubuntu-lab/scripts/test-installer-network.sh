#!/usr/bin/env bash
# Installer contracts and optional real Nginx smoke run only in disposable lab containers.
set -Eeuo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
mode="${2:---fixtures}"
case "$mode" in --fixtures|--live-nginx) ;; *) lab_die 'Use --fixtures or --live-nginx.' ;; esac
repo="$(cd "$LAB_ROOT/../../.." && pwd)"
report="$LAB_ROOT/reports/installer-network-${version}-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$report"
set +e
docker run --rm --cpus=1 --memory=2g --pids-limit=512 \
    --volume "$repo:/opt/rahandaz:ro" --workdir /opt/rahandaz \
    --entrypoint /bin/bash "rahandaz-ubuntu-lab:$version" -c '
    set -Eeuo pipefail
    export TMPDIR=/tmp
    mkdir -p /run/rahandaz-lab
    token=$(python3 -c "import secrets; print(secrets.token_hex(32))")
    printf "rahandaz-lab-v1\n%s\n" "$token" >/run/rahandaz-lab/marker
    chmod 0600 /run/rahandaz-lab/marker
    export RAHANDAZ_OPS_LAB_TOKEN="$token"
    RAHANDAZ_INSTALLER_REFUSE_LIVE=1 bash installer/tests/run.sh
    python3 installer/tests/release_test.py
    python3 infrastructure/operations/panel/test_listener.py
    if [[ "$1" == --live-nginx ]]; then
        apt-get update
        apt-get install -y --no-install-recommends nginx
        python3 tests/infrastructure/ubuntu-lab/scripts/inside/installer-network-nginx.py
    fi
    ' lab "$mode" >"$report/output.log" 2>&1
status=$?
set -e
printf 'exit_code=%s\nubuntu=%s\nmode=%s\nenvironment=container-lab\nvm_certification=not_run\n' "$status" "$version" "$mode" >"$report/result.txt"
cat "$report/result.txt"
printf 'Report: %s\n' "$report/output.log"
exit "$status"
