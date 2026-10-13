#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
"$(dirname "${BASH_SOURCE[0]}")/lab-up.sh" "$version"
service="$(lab_service "$version")"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
dir="$LAB_ROOT/reports/${version}-install-${stamp}"
mkdir -p "$dir"
set +e
"${COMPOSE[@]}" exec -T -e RAHANDAZ_LAB_PHASE=install "$service" python3 /opt/rahandaz-lab/scripts/run-suite.py >"$dir/stdout.json" 2>"$dir/suite.stderr"
rc=$?
set -e
if [[ ! -s "$dir/stdout.json" ]]; then
    lab_die "The installer did not return a report. See $dir/suite.stderr" 1
fi
digest="$(docker image inspect "ubuntu:${version}" --format '{{index .RepoDigests 0}}' 2>/dev/null || true)"
arch="$("${COMPOSE[@]}" exec -T "$service" uname -m)"
python3 "$LAB_ROOT/scripts/emit-report.py" --input "$dir/stdout.json" --output-dir "$dir" --digest "$digest" --architecture "$arch"
cid="$("${COMPOSE[@]}" ps -q "$service")"
docker cp "$cid:/run/rahandaz-lab/logs" "$dir/logs" >/dev/null 2>&1 || true
printf '%s\n' "$dir/summary.md"
exit "$rc"
