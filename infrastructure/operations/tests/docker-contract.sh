#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
EXECUTION_ID="018f3a2e-7c3a-7b2a-8c1d-123456789abc"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
failures=0

pass() { printf 'ok %s\n' "$1"; }
fail() { printf 'not ok %s\n' "$1" >&2; failures=$((failures + 1)); }
assert_eq() {
    if [[ "$2" == "$3" ]]; then
        pass "$1"
    else
        fail "$1 expected [$2] actual [$3]"
    fi
}

run_op() {
    "$ROOT/bin/rahandaz-ops" "$1" <"$2"
}

for script in "$ROOT"/docker/*.sh "$ROOT"/lib/docker.sh; do
    if python3 -c 'import pathlib,sys; data=pathlib.Path(sys.argv[1]).read_bytes(); sys.exit(0 if all(b in (9,10) or 32<=b<=126 for b in data) else 1)' "$script"; then
        pass "ascii $(basename "$script")"
    else
        fail "ascii $script"
    fi
    bash -n "$script"
done

assert_eq "layout compose" "compose_only" "$(python3 "$ROOT/lib/opsjson.py" docker-layout compose.yml README.md)"
assert_eq "layout both" "compose_and_dockerfile" "$(python3 "$ROOT/lib/opsjson.py" docker-layout Dockerfile compose.yaml)"
assert_eq "layout dockerfile" "dockerfile_only" "$(python3 "$ROOT/lib/opsjson.py" docker-layout Dockerfile.prod)"

cat >"$tmp/status.json" <<EOF
{"execution_id":"$EXECUTION_ID","project_slug":"app-one","layout":"compose_only","compose_file":"compose.yml"}
EOF
status="$(run_op docker.compose.status "$tmp/status.json")"
assert_eq "compose status preview" "preview" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["status"])' "$status")"
assert_eq "compose status not mutated" "False" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["mutated"])' "$status")"

cat >"$tmp/build.json" <<EOF
{"execution_id":"$EXECUTION_ID","project_slug":"app-one","layout":"compose_only","approval":true}
EOF
set +e
built="$(run_op docker.image.build "$tmp/build.json" 2>"$tmp/build.err")"
build_rc=$?
set -e
assert_eq "compose-only build exit" "1" "$build_rc"
assert_eq "compose-only build code" "NO_DOCKERFILE" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$built")"

cat >"$tmp/up.json" <<EOF
{"execution_id":"$EXECUTION_ID","project_slug":"app-one","layout":"dockerfile_only","approval":true}
EOF
set +e
up="$(run_op docker.compose.up "$tmp/up.json" 2>"$tmp/up.err")"
up_rc=$?
set -e
assert_eq "dockerfile up exit" "1" "$up_rc"
assert_eq "dockerfile up code" "NO_COMPOSE_FILE" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$up")"

cat >"$tmp/reserved.json" <<EOF
{"execution_id":"$EXECUTION_ID","project_slug":"rahandaz-dev","layout":"compose_only"}
EOF
set +e
reserved="$(run_op docker.compose.status "$tmp/reserved.json" 2>"$tmp/reserved.err")"
reserved_rc=$?
set -e
assert_eq "reserved slug exit" "1" "$reserved_rc"
assert_eq "reserved slug code" "CONTROL_PLANE_PROJECT" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$reserved")"

cat >"$tmp/path.json" <<EOF
{"execution_id":"$EXECUTION_ID","project_slug":"app-one","layout":"compose_only","compose_file":"../../compose.yml"}
EOF
set +e
path="$(run_op docker.compose.status "$tmp/path.json" 2>"$tmp/path.err")"
path_rc=$?
set -e
assert_eq "path exit" "1" "$path_rc"
assert_eq "path code" "INVALID_INPUT" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$path")"

cat >"$tmp/reload.json" <<EOF
{"execution_id":"$EXECUTION_ID","unit":"php8.4-fpm"}
EOF
reload="$(run_op service.reload "$tmp/reload.json")"
assert_eq "php-fpm reload preview" "preview" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["status"])' "$reload")"

cat >"$tmp/mysql.json" <<EOF
{"execution_id":"$EXECUTION_ID","unit":"mysql"}
EOF
set +e
mysql="$(run_op service.reload "$tmp/mysql.json" 2>"$tmp/mysql.err")"
mysql_rc=$?
set -e
assert_eq "mysql reload exit" "1" "$mysql_rc"
assert_eq "mysql reload code" "INVALID_INPUT" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$mysql")"

if [[ "$failures" -ne 0 ]]; then
    printf 'failed %s\n' "$failures" >&2
    exit 1
fi
printf 'docker contract passed\n'
