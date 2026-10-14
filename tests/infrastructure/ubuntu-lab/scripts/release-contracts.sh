#!/usr/bin/env bash
# Run signed-release logic in an already-running, non-privileged Ubuntu lab container.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
service="$(lab_service "$version")"
cid="$("${COMPOSE[@]}" ps -q "$service")"
[[ -n "$cid" ]] || lab_die 'Start the selected Ubuntu lab first; no host tests will be substituted.' 1
[[ "$(docker inspect --format '{{.HostConfig.Privileged}}' "$cid")" == false ]] \
    || lab_die 'Refusing a privileged test container.' 1
test_root="$(docker exec "$cid" mktemp -d /tmp/rahandaz-release-contracts.XXXXXX)"
[[ "$test_root" =~ ^/tmp/rahandaz-release-contracts\.[A-Za-z0-9]{6}$ ]] || lab_die 'Invalid temporary test root.' 1
trap 'docker exec "$cid" rm -rf -- "$test_root" >/dev/null 2>&1 || true' EXIT
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
report="$LAB_ROOT/reports/release-${version}-${stamp}"
mkdir -p "$report"
docker cp "$LAB_ROOT/../../../installer" "$cid:$test_root/installer" >/dev/null
set +e
docker exec -e RAHANDAZ_INSTALLER_REFUSE_LIVE=1 -e PYTHONDONTWRITEBYTECODE=1 "$cid" \
    bash "$test_root/installer/tests/run.sh" >"$report/installer.log" 2>&1
installer_status=$?
docker exec -e RAHANDAZ_INSTALLER_REFUSE_LIVE=1 -e PYTHONDONTWRITEBYTECODE=1 "$cid" \
    python3 "$test_root/installer/tests/release_test.py" >"$report/release.log" 2>&1
release_status=$?
set -e
printf 'Ubuntu %s container-lab: installer=%s release=%s\nEvidence: %s\n' \
    "$version" "$installer_status" "$release_status" "$report"
[[ "$installer_status" == 0 && "$release_status" == 0 ]]
