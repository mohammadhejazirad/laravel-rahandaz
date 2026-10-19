#!/usr/bin/env bash
# Contract fixtures run only inside disposable Ubuntu containers.
set -Eeuo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
repo="$(cd "$LAB_ROOT/../../.." && pwd)"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
report="$LAB_ROOT/reports/panel-port-${version}-${stamp}"
mkdir -p "$report"
set +e
docker run --rm --cpus=1 --memory=1g --pids-limit=128 --network=none \
    --volume "$repo:/opt/rahandaz:ro" --workdir /opt/rahandaz \
    --entrypoint /bin/bash "rahandaz-ubuntu-lab:$version" -c '
    set -Eeuo pipefail
    bash -n infrastructure/operations/bin/rahandaz-ops
    python3 infrastructure/operations/panel/test_listener.py
    bash -n tests/infrastructure/ubuntu-lab/scripts/test-panel-port.sh
    set +e
    output=$(printf "{}" | bash infrastructure/operations/bin/rahandaz-ops panel.port.update)
    result=$?
    set -e
    [[ "$result" -eq 1 ]]
    [[ "$output" == *INVALID_INPUT* ]]
    echo "PANEL_DISPATCHER_INVALID_INPUT_REJECTED"
    # The full PHP process-adapter harness runs on the PHP-equipped host and CI.
    
    ' >"$report/output.log" 2>&1
status=$?
set -e
{
    echo "exit_code=$status"
    echo "ubuntu=$version"
    echo 'environment=container-lab'
    echo 'vm_certification=not_run'
} >"$report/result.txt"
cat "$report/result.txt"
exit "$status"
