#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
EXECUTION_ID="018f3a2e-7c3a-7b2a-8c1d-123456789abc"
failures=0

pass() { printf 'ok %s\n' "$1"; }
fail() { printf 'not ok %s\n' "$1" >&2; failures=$((failures + 1)); }

assert_eq() {
    local name="$1" expected="$2" actual="$3"
    if [[ "$expected" == "$actual" ]]; then
        pass "$name"
    else
        fail "$name expected [$expected] actual [$actual]"
    fi
}

run_op() {
    local operation="$1" input="$2"
    shift 2
    env "$@" "$ROOT/bin/rahandaz-ops" "$operation" <"$input"
}

while IFS= read -r script; do
    bash -n "$script"
done < <(find "$ROOT" -name '*.sh' -type f | sort)
pass "bash -n"
python3 "$ROOT/panel/test_listener.py"
pass "panel listener contract and recovery"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
mkdir -p "$tmp/bin" "$tmp/os"
cat >"$tmp/os/os-release" <<'EOF'
NAME="Ubuntu"
ID=ubuntu
VERSION_ID="22.04"
VERSION_CODENAME=jammy
EOF

cat >"$tmp/input-facts.json" <<EOF
{"execution_id":"$EXECUTION_ID"}
EOF
facts="$(RAHANDAZ_OPS_OS_RELEASE="$tmp/os/os-release" run_op host.facts "$tmp/input-facts.json")"
assert_eq "facts status" "succeeded" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["status"])' "$facts")"
assert_eq "facts compatibility" "untested" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["observed_state"]["compatibility"])' "$facts")"
assert_eq "facts recognized" "True" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["observed_state"]["recognized"])' "$facts")"

for release in "24.04 noble" "26.04 resolute"; do
    version="${release%% *}"
    codename="${release##* }"
    cat >"$tmp/os/os-release" <<EOF
NAME="Ubuntu"
ID=ubuntu
VERSION_ID="${version}"
VERSION_CODENAME=${codename}
EOF
    release_facts="$(RAHANDAZ_OPS_OS_RELEASE="$tmp/os/os-release" run_op host.facts "$tmp/input-facts.json")"
    assert_eq "facts ${version} compatibility" "untested" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["observed_state"]["compatibility"])' "$release_facts")"
    assert_eq "facts ${version} recognized" "True" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["observed_state"]["recognized"])' "$release_facts")"
done

cat >"$tmp/input-private.json" <<EOF
{"execution_id":"$EXECUTION_ID","public_key":"-----BEGIN OPENSSH PRIVATE KEY-----"}
EOF
set +e
private="$(run_op ssh.install_public_key "$tmp/input-private.json" 2>"$tmp/private.err")"
private_rc=$?
set -e
assert_eq "private key exit" "1" "$private_rc"
assert_eq "private key code" "PRIVATE_KEY_REJECTED" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$private")"

cat >"$tmp/input-stop.json" <<EOF
{"execution_id":"$EXECUTION_ID","unit":"nginx"}
EOF
set +e
stopped="$(run_op service.stop "$tmp/input-stop.json" 2>"$tmp/stop.err")"
stop_rc=$?
set -e
assert_eq "protected stop exit" "1" "$stop_rc"
assert_eq "protected stop code" "PROTECTED_SERVICE" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$stopped")"

cat >"$tmp/input-reboot.json" <<EOF
{"execution_id":"$EXECUTION_ID","owner_approval":true,"reauthentication":true,"active_jobs_idle":true,"deployment_idle":true,"backup_checked":true,"recovery_plan":true}
EOF
cat >"$tmp/bin/reboot" <<'EOF'
#!/bin/sh
touch "$TMPDIR/reboot-called"
exit 0
EOF
chmod 755 "$tmp/bin/reboot"
set +e
reboot="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op host.reboot_request "$tmp/input-reboot.json" 2>"$tmp/reboot.err")"
reboot_rc=$?
set -e
assert_eq "reboot exit" "1" "$reboot_rc"
assert_eq "reboot code" "REBOOT_NOT_EXECUTED" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$reboot")"
if [[ -e "$tmp/reboot-called" ]]; then
    fail "reboot command was executed"
else
    pass "reboot command was not executed"
fi

cat >"$tmp/input-package.json" <<EOF
{"execution_id":"$EXECUTION_ID","package":"nginx"}
EOF
cat >"$tmp/bin/apt-get" <<'EOF'
#!/bin/sh
echo apt-called >>"$TMPDIR/apt.mark"
exit 0
EOF
chmod 755 "$tmp/bin/apt-get"
set +e
package="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op package.install "$tmp/input-package.json" 2>"$tmp/package.err")"
package_rc=$?
set -e
assert_eq "untested package exit" "1" "$package_rc"
assert_eq "untested package code" "PACKAGE_UNTESTED" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$package")"
if [[ -e "$tmp/apt.mark" ]]; then
    fail "apt-get ran for an untested package"
else
    pass "apt-get did not run for an untested package"
fi

cat >"$tmp/bin/useradd" <<'EOF'
#!/bin/sh
printf '%s\n' "$*" >>"$TMPDIR/account.mark"
exit 0
EOF
cat >"$tmp/bin/passwd" <<'EOF'
#!/bin/sh
printf '%s\n' "$*" >>"$TMPDIR/account.mark"
exit 0
EOF
chmod 755 "$tmp/bin/useradd" "$tmp/bin/passwd"
cat >"$tmp/input-account.json" <<EOF
{"execution_id":"$EXECUTION_ID"}
EOF
account="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op account.create_rahandaz_user "$tmp/input-account.json")"
assert_eq "account status" "succeeded" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["status"])' "$account")"
assert_eq "account mutated" "True" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["mutated"])' "$account")"
if grep -q nologin "$tmp/account.mark" && grep -q "rahandaz-svc$" "$tmp/account.mark" && ! grep -q sudo "$tmp/account.mark"; then
    pass "rahandaz-svc useradd is nologin without sudo"
else
    fail "rahandaz-svc useradd arguments"
fi
secret="$(cat "$tmp/root/rahandaz-account.secret")"
mode="$(stat -c '%a' "$tmp/root/rahandaz-account.secret")"
assert_eq "secret mode" "600" "$mode"
if [[ "$account" == *"$secret"* ]]; then
    fail "account secret appeared in stdout"
else
    pass "account secret stayed out of stdout"
fi

rm -f "$tmp/account.mark"
account_again="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op account.create_rahandaz_user "$tmp/input-account.json")"
assert_eq "account rerun status" "succeeded" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["status"])' "$account_again")"
assert_eq "account rerun mutated" "False" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["mutated"])' "$account_again")"
if [[ -e "$tmp/account.mark" ]]; then
    fail "account rerun invoked useradd"
else
    pass "account rerun did not invoke useradd"
fi
unset secret

rm -f "$tmp/account.mark"
set +e
live="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op account.create_rahandaz_user "$tmp/input-account.json" 2>"$tmp/live.err")"
live_rc=$?
set -e
assert_eq "live mutation exit" "1" "$live_rc"
assert_eq "live mutation code" "LIVE_MUTATION_BLOCKED" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$live")"
if [[ -e "$tmp/account.mark" ]]; then
    fail "live mutation invoked useradd"
else
    pass "live mutation did not invoke useradd"
fi

rm -f "$tmp/account.mark"
set +e
lab_fake="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_LAB=1 RAHANDAZ_OPS_LAB_TOKEN=0123456789abcdef0123456789abcdef RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op account.create_rahandaz_user "$tmp/input-account.json" 2>"$tmp/lab-fake.err")"
lab_fake_rc=$?
set -e
assert_eq "fake lab exit" "1" "$lab_fake_rc"
assert_eq "fake lab code" "LIVE_MUTATION_BLOCKED" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$lab_fake")"
if [[ -e "$tmp/account.mark" ]]; then
    fail "fake lab invoked useradd"
else
    pass "fake lab did not invoke useradd"
fi
set +e
python3 "$ROOT/lib/opsjson.py" lab-ok >/dev/null 2>"$tmp/lab-ok.err"
lab_ok_rc=$?
set -e
assert_eq "lab-ok refuses host" "3" "$lab_ok_rc"

cat >"$tmp/input-drop.json" <<EOF
{"execution_id":"$EXECUTION_ID","database":"rahandaz"}
EOF
set +e
drop="$(run_op db.postgresql.drop_database "$tmp/input-drop.json" 2>"$tmp/drop.err")"
drop_rc=$?
set -e
assert_eq "protected database exit" "1" "$drop_rc"
assert_eq "protected database code" "PROTECTED_DATABASE" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$drop")"

cat >"$tmp/input-root-script.json" <<EOF
{"execution_id":"$EXECUTION_ID","uid":"0","script_path":"script.sh","timeout_seconds":"30"}
EOF
set +e
custom="$(run_op automation.custom_script "$tmp/input-root-script.json" 2>"$tmp/custom.err")"
custom_rc=$?
set -e
assert_eq "root script exit" "1" "$custom_rc"
assert_eq "root script code" "ROOT_SCRIPT_FORBIDDEN" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$custom")"

set +e
bad="$(printf '%s' '{}' | "$ROOT/bin/rahandaz-ops" 'package.install;id' 2>"$tmp/bad.err")"
bad_rc=$?
set -e
assert_eq "dispatcher injection exit" "1" "$bad_rc"

set +e
helper="$("$ROOT/../privilege_helper/bin/rahandaz-helper" automation.custom_script <"$tmp/input-facts.json" 2>"$tmp/helper.err")"
helper_rc=$?
set -e
assert_eq "helper refuses custom script" "1" "$helper_rc"

cat >"$tmp/bin/nginx" <<'EOF'
#!/bin/sh
exit 0
EOF
chmod 755 "$tmp/bin/nginx"
cat >"$tmp/input-nginx.json" <<EOF
{"execution_id":"$EXECUTION_ID","site":"app","config":"# rahandaz-managed: app\nserver { listen 80; }"}
EOF
nginx="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op nginx.apply_site "$tmp/input-nginx.json")"
assert_eq "nginx status" "succeeded" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["status"])' "$nginx")"
if grep -q 'rahandaz-managed: app' "$tmp/etc/nginx/sites-available/app.conf"; then
    pass "nginx site written inside the sandbox"
else
    fail "nginx site missing"
fi

cat >"$tmp/input-escape.json" <<EOF
{"execution_id":"$EXECUTION_ID","site":"app","config":"# rahandaz-managed: app\ninclude ../secret;"}
EOF
set +e
escape="$(run_op nginx.apply_site "$tmp/input-escape.json" 2>"$tmp/escape.err")"
escape_rc=$?
set -e
assert_eq "nginx traversal exit" "1" "$escape_rc"
assert_eq "nginx traversal code" "INVALID_INPUT" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$escape")"

cat >"$tmp/input-cron.json" <<EOF
{"execution_id":"$EXECUTION_ID"}
EOF
set +e
cron="$(run_op automation.refuse_cron "$tmp/input-cron.json" 2>"$tmp/cron.err")"
cron_rc=$?
set -e
assert_eq "cron exit" "1" "$cron_rc"
assert_eq "cron code" "CRON_WRITE_FORBIDDEN" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["error_code"])' "$cron")"

json_field() {
    python3 -c 'import json,sys; print(json.loads(sys.argv[1])[sys.argv[2]])' "$1" "$2"
}

cat >"$tmp/input-empty.json" <<'EOF'
{}
EOF
set +e
missing="$(run_op host.facts "$tmp/input-empty.json" 2>"$tmp/missing.err")"
missing_rc=$?
set -e
assert_eq "missing execution exit" "1" "$missing_rc"
assert_eq "missing execution code" "MISSING_EXECUTION_ID" "$(json_field "$missing" error_code)"

set +e
unknown="$(printf '%s' '{}' | "$ROOT/bin/rahandaz-ops" missing.operation 2>"$tmp/unknown.err")"
unknown_rc=$?
set -e
assert_eq "unknown operation exit" "1" "$unknown_rc"
if grep -q UNKNOWN_OPERATION "$tmp/unknown.err"; then
    pass "unknown operation code"
else
    fail "unknown operation code"
fi

set +e
flush="$(printf '%s' '{}' | "$ROOT/bin/rahandaz-ops" firewall.flush 2>"$tmp/flush.err")"
flush_rc=$?
set -e
assert_eq "firewall flush exit" "1" "$flush_rc"
if grep -q UNKNOWN_OPERATION "$tmp/flush.err"; then
    pass "firewall flush is not an operation"
else
    fail "firewall flush is not an operation"
fi

ln -s "$tmp/input-facts.json" "$tmp/linked.json"
set +e
linked="$(bash "$ROOT/host/facts.sh" "$tmp/linked.json" 2>"$tmp/linked.err")"
linked_rc=$?
set -e
assert_eq "symlink input exit" "1" "$linked_rc"
assert_eq "symlink input code" "INVALID_INPUT" "$(json_field "$linked" error_code)"

ln -s "$tmp" "$tmp/link-sandbox"
set +e
linked_sandbox="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp/link-sandbox" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op account.create_rahandaz_user "$tmp/input-account.json" 2>"$tmp/link-sandbox.err")"
linked_sandbox_rc=$?
set -e
assert_eq "symlink sandbox exit" "1" "$linked_sandbox_rc"
assert_eq "symlink sandbox code" "SYMLINK_REJECTED" "$(json_field "$linked_sandbox" error_code)"

cat >"$tmp/input-ssh.json" <<EOF
{"execution_id":"$EXECUTION_ID"}
EOF
preview_ssh="$(run_op ssh.harden "$tmp/input-ssh.json")"
assert_eq "ssh preview status" "preview" "$(json_field "$preview_ssh" status)"
set +e
ssh_apply="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op ssh.harden "$tmp/input-ssh.json" 2>"$tmp/ssh.err")"
ssh_rc=$?
set -e
assert_eq "ssh recovery exit" "1" "$ssh_rc"
assert_eq "ssh recovery code" "SSH_RECOVERY_REQUIRED" "$(json_field "$ssh_apply" error_code)"
if [[ -e "$tmp/etc/ssh/sshd_config.d/rahandaz.conf" ]]; then
    fail "ssh config was written without recovery"
else
    pass "ssh config was not written without recovery"
fi

cat >"$tmp/catalog.json" <<'EOF'
{"verification":"vm-passed","packages":{"nginx":{"apt":"nginx"}}}
EOF
cat >"$tmp/bin/dpkg-query" <<'EOF'
#!/bin/sh
printf '%s\n' "install ok installed"
exit 0
EOF
chmod 755 "$tmp/bin/dpkg-query"
rm -f "$tmp/apt.mark"
fixture="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" RAHANDAZ_OPS_CATALOG="$tmp/catalog.json" run_op package.install "$tmp/input-package.json")"
assert_eq "fixture package status" "succeeded" "$(json_field "$fixture" status)"
if [[ -e "$tmp/apt.mark" ]]; then
    pass "fixture catalog reached the apt mock"
else
    fail "fixture catalog did not reach the apt mock"
fi

cat >"$tmp/catalog-container.json" <<'EOF'
{"verification":"container-lab","packages":{"nginx":{"apt":"nginx"}}}
EOF
rm -f "$tmp/apt.mark"
set +e
container_catalog="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" RAHANDAZ_OPS_CATALOG="$tmp/catalog-container.json" run_op package.install "$tmp/input-package.json" 2>"$tmp/container-catalog.err")"
container_catalog_rc=$?
set -e
assert_eq "container catalog outside lab exit" "1" "$container_catalog_rc"
assert_eq "container catalog outside lab code" "PACKAGE_UNTESTED" "$(json_field "$container_catalog" error_code)"
if [[ -e "$tmp/apt.mark" ]]; then
    fail "container-lab catalog called apt outside the lab"
else
    pass "container-lab catalog did not call apt outside the lab"
fi

cat >"$tmp/bin/dpkg-query" <<'EOF'
#!/bin/sh
printf '%s\n' "deinstall ok config-files"
exit 0
EOF
chmod 755 "$tmp/bin/dpkg-query"
cat >"$tmp/input-remove.json" <<EOF
{"execution_id":"$EXECUTION_ID","package":"nginx","approval":true}
EOF
rm -f "$tmp/apt.mark"
removed="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" RAHANDAZ_OPS_CATALOG="$tmp/catalog.json" run_op package.remove "$tmp/input-remove.json")"
assert_eq "remove status" "succeeded" "$(json_field "$removed" status)"
assert_eq "remove absent" "True" "$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["observed_state"]["absent"])' "$removed")"
if [[ -e "$tmp/apt.mark" ]]; then
    pass "remove reached the apt mock"
else
    fail "remove did not reach the apt mock"
fi

cat >"$tmp/input-drop-open.json" <<EOF
{"execution_id":"$EXECUTION_ID","database":"appdb"}
EOF
cat >"$tmp/bin/psql" <<'EOF'
#!/bin/sh
touch "$TMPDIR/psql.mark"
exit 0
EOF
chmod 755 "$tmp/bin/psql"
set +e
drop_open="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op db.postgresql.drop_database "$tmp/input-drop-open.json" 2>"$tmp/drop-open.err")"
drop_open_rc=$?
set -e
assert_eq "drop approval exit" "1" "$drop_open_rc"
assert_eq "drop approval code" "APPROVAL_REQUIRED" "$(json_field "$drop_open" error_code)"
if [[ -e "$tmp/psql.mark" ]]; then
    fail "drop ran psql without approval"
else
    pass "drop did not run psql without approval"
fi

cat >"$tmp/input-activate.json" <<EOF
{"execution_id":"$EXECUTION_ID","project":"app","release":"rel1"}
EOF
activated="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_PROJECT_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op deploy.activate "$tmp/input-activate.json")"
assert_eq "activate status" "succeeded" "$(json_field "$activated" status)"
assert_eq "activate target" "$tmp/srv/projects/app/releases/rel1" "$(readlink "$tmp/srv/projects/app/current")"

cat >"$tmp/work-script.sh" <<'EOF'
#!/bin/sh
exit 0
EOF
chmod 755 "$tmp/work-script.sh"
cat >"$tmp/bin/systemd-run" <<'EOF'
#!/bin/sh
printf '%s\n' "$*" >>"$TMPDIR/systemd.mark"
exit 0
EOF
chmod 755 "$tmp/bin/systemd-run"
cat >"$tmp/input-custom.json" <<EOF
{"execution_id":"$EXECUTION_ID","uid":"1000","script_path":"$tmp/work-script.sh","timeout_seconds":"30"}
EOF
set +e
custom_root="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_PROJECT_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op automation.custom_script "$tmp/input-custom.json" 2>"$tmp/custom-root.err")"
custom_root_rc=$?
set -e
assert_eq "custom helper exit" "1" "$custom_root_rc"
assert_eq "custom helper code" "ROOT_SCRIPT_FORBIDDEN" "$(json_field "$custom_root" error_code)"
custom_ok="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_PROJECT_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op automation.custom_script "$tmp/input-custom.json")"
assert_eq "custom status" "succeeded" "$(json_field "$custom_ok" status)"
if grep -q -- '--uid=1000' "$tmp/systemd.mark"; then
    pass "custom script requested a non-root uid"
else
    fail "custom script did not request a non-root uid"
fi

cat >"$tmp/bin/nginx" <<'EOF'
#!/bin/sh
exit 1
EOF
cat >"$tmp/input-nginx-bad.json" <<EOF
{"execution_id":"$EXECUTION_ID","site":"other","config":"# rahandaz-managed: other\nserver { listen 80; }"}
EOF
set +e
nginx_bad="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_HELPER_CONFIRMED=1 RAHANDAZ_OPS_SANDBOX="$tmp" RAHANDAZ_OPS_BIN_DIR="$tmp/bin" run_op nginx.apply_site "$tmp/input-nginx-bad.json" 2>"$tmp/nginx-bad.err")"
nginx_bad_rc=$?
set -e
assert_eq "nginx test failure exit" "1" "$nginx_bad_rc"
assert_eq "nginx test failure code" "NGINX_TEST_FAILED" "$(json_field "$nginx_bad" error_code)"
if [[ -e "$tmp/etc/nginx/sites-available/other.conf" ]]; then
    fail "failed nginx config was left in place"
else
    pass "failed nginx config was removed"
fi

cat >"$tmp/input-checkout.json" <<EOF
{"execution_id":"$EXECUTION_ID","commit_sha":"0123456789abcdef0123456789abcdef01234567"}
EOF
set +e
checkout="$(RAHANDAZ_OPS_APPLY=1 RAHANDAZ_OPS_PROJECT_CONFIRMED=1 run_op deploy.checkout "$tmp/input-checkout.json" 2>"$tmp/checkout.err")"
checkout_rc=$?
set -e
assert_eq "checkout exit" "1" "$checkout_rc"
assert_eq "checkout code" "PRECONDITION_FAILED" "$(json_field "$checkout" error_code)"

if [[ -x /usr/bin/php ]]; then
    if /usr/bin/php "$ROOT/tests/php-process.php"; then
        pass "php process adapter"
    else
        fail "php process adapter"
    fi
else
    fail "host php is required for the process adapter check"
fi

if [[ "$failures" -gt 0 ]]; then
    printf '%s\n' "$failures test(s) failed" >&2
    exit 1
fi
printf '%s\n' "operations engine tests passed"
