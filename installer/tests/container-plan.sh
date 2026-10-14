#!/usr/bin/env bash
# Run the installer plan inside the existing Ubuntu lab containers.
# Plan does not install packages and does not certify a production install.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
INSTALLER="${ROOT}/installer"
fail=0

for version in 22.04 24.04 26.04; do
    short="${version%%.*}"
    container="rahandaz-ubuntu-lab-ubuntu-${short}-1"
    if ! docker inspect "$container" >/dev/null 2>&1; then
        printf 'not_run %s container_absent\n' "$version"
        fail=1
        continue
    fi
    docker exec "$container" rm -rf /tmp/rahandaz-installer /tmp/rahandaz-plan
    docker cp "$INSTALLER" "$container:/tmp/rahandaz-installer" >/dev/null
    set +e
    docker exec "$container" bash /tmp/rahandaz-installer/bin/rahandaz-install \
        --plan --state-dir /tmp/rahandaz-plan >"/tmp/rahandaz-plan-${version}.out" 2>"/tmp/rahandaz-plan-${version}.err"
    status=$?
    set -e
    printf 'plan %s exit %s\n' "$version" "$status"
    if [[ "$status" -eq 0 ]] \
        && grep -q 'RESULT plan' "/tmp/rahandaz-plan-${version}.err" \
        && grep -q 'php8.4' "/tmp/rahandaz-plan-${version}.err" \
        && ! grep -q 'update-alternatives' "/tmp/rahandaz-plan-${version}.err"; then
        printf 'ok %s plan\n' "$version"
    else
        printf 'not ok %s\n' "$version"
        fail=1
    fi
done

exit "$fail"
