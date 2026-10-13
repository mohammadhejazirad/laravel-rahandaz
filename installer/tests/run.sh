#!/usr/bin/env bash
# Installer logic tests. They do not call apt or mutate the developer host.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
INSTALLER_HOME="${ROOT}/installer"
export INSTALLER_HOME
# shellcheck source=../lib/common.sh
source "${INSTALLER_HOME}/lib/common.sh"
# shellcheck source=../lib/state.sh
source "${INSTALLER_HOME}/lib/state.sh"
# shellcheck source=../lib/os.sh
source "${INSTALLER_HOME}/lib/os.sh"
# shellcheck source=../lib/verify.sh
source "${INSTALLER_HOME}/lib/verify.sh"
# shellcheck source=../lib/download.sh
source "${INSTALLER_HOME}/lib/download.sh"
# shellcheck source=../lib/mirror.sh
source "${INSTALLER_HOME}/lib/mirror.sh"
# shellcheck source=../lib/gate.sh
source "${INSTALLER_HOME}/lib/gate.sh"
# shellcheck source=../lib/render.sh
source "${INSTALLER_HOME}/lib/render.sh"
# shellcheck source=../lib/runtime.sh
source "${INSTALLER_HOME}/lib/runtime.sh"
# shellcheck source=../lib/engine.sh
source "${INSTALLER_HOME}/lib/engine.sh"

pass=0
fail=0
ok() { printf 'ok - %s\n' "$1"; pass=$((pass + 1)); }
bad() { printf 'not ok - %s\n' "$1"; fail=$((fail + 1)); }

while IFS= read -r script; do
    if bash -n "$script"; then
        ok "bash -n $(basename "$script")"
    else
        bad "bash -n $(basename "$script")"
    fi
done < <(find "${INSTALLER_HOME}" -name '*.sh' -type f | sort)

if grep -R -n -F --include='*.sh' --exclude-dir=tests 'curl -k' "${INSTALLER_HOME}" \
    || grep -R -n -F --include='*.sh' --exclude-dir=tests 'NOPASSWD: ALL' "${INSTALLER_HOME}" \
    || grep -R -n -E --include='*.sh' --exclude-dir=tests 'apt-get[^#]*--allow-unauthenticated' "${INSTALLER_HOME}"; then
    bad "insecure installer flag"
else
    ok "no insecure installer flag"
fi

if installer_package_allowed php-cli && ! installer_package_allowed nodejs && ! installer_package_allowed docker.io; then
    ok "control-plane package allowlist"
else
    bad "control-plane package allowlist"
fi

tmp="$(mktemp -d /tmp/rahandaz-installer-test.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT

os_noble="${tmp}/noble.os"
cat >"$os_noble" <<'EOF'
ID=ubuntu
VERSION_ID="24.04"
VERSION_CODENAME=noble
EOF
os_jammy="${tmp}/jammy.os"
cat >"$os_jammy" <<'EOF'
ID=ubuntu
VERSION_ID="22.04"
VERSION_CODENAME=jammy
EOF
mem="${tmp}/meminfo"
printf 'MemTotal:        2097152 kB\n' >"$mem"

MODE=plan
INSTALLER_OS_RELEASE="$os_noble"
INSTALLER_MEMINFO="$mem"
INSTALLER_DISK_AVAIL_KIB=20000000
if installer_detect_os && installer_php_baseline_available; then
    ok "noble baseline"
else
    bad "noble baseline"
fi
INSTALLER_OS_RELEASE="$os_jammy"
if installer_detect_os && ! installer_php_baseline_available; then
    ok "jammy php baseline blocked"
else
    bad "jammy php baseline blocked"
fi

os_link_root="${tmp}/os-link"
mkdir -p "${os_link_root}/usr/lib" "${os_link_root}/etc" "${os_link_root}/other"
cp "$os_noble" "${os_link_root}/usr/lib/os-release"
ln -s ../usr/lib/os-release "${os_link_root}/etc/os-release"
cp "$os_noble" "${os_link_root}/other/fake"
ln -s ../other/fake "${os_link_root}/etc/evil"
INSTALLER_OS_RELEASE="${os_link_root}/etc/os-release"
INSTALLER_ERROR_CODE=""
if installer_detect_os && [[ "$INSTALLER_OS_CODENAME" == "noble" ]]; then
    ok "ubuntu os-release symlink is accepted"
else
    bad "ubuntu os-release symlink is accepted"
fi
INSTALLER_OS_RELEASE="${os_link_root}/etc/evil"
INSTALLER_ERROR_CODE=""
if ! installer_detect_os; then
    ok "unexpected os-release symlink is refused"
else
    bad "unexpected os-release symlink is refused"
fi
unset INSTALLER_OS_RELEASE
INSTALLER_ERROR_CODE=""
if resolved="$(installer_os_release_file)" && [[ "$resolved" == "/usr/lib/os-release" ]]; then
    ok "live os-release symlink does not gain a double slash"
else
    bad "live os-release symlink does not gain a double slash"
fi
INSTALLER_OS_RELEASE="$os_jammy"
INSTALLER_ERROR_CODE=""

INSTALLER_STATE_DIR="${tmp}/state"
installer_state_init
if ! installer_state_set example password 2>"${tmp}/state.err"; then
    ok "state rejects a secret-like value"
else
    bad "state rejects a secret-like value"
fi

fixture="${tmp}/mirrors"
mkdir -p "$fixture"
printf 'timeout\n' >"$fixture/ubuntu-archive"
printf 'ok\n' >"$fixture/liara"
printf 'ok\n' >"$fixture/arvan"
printf 'ok\n' >"$fixture/iranserver"
printf 'ok\n' >"$fixture/chabokan"
export INSTALLER_MIRROR_MODE=fixture
export INSTALLER_MIRROR_FIXTURE_DIR="$fixture"
export INSTALLER_BACKOFF_SKIP=1
INSTALLER_OS_CODENAME=noble
INSTALLER_ERROR_CODE=""
installer_select_mirror noble "" 1 >"${tmp}/selected-iran"
selected="$(cat "${tmp}/selected-iran")"
if [[ "$selected" == "liara" && -z "${INSTALLER_ERROR_CODE:-}" ]]; then
    ok "iran profile selects a verified mirror"
else
    bad "iran profile selects a verified mirror (${selected:-none})"
fi

rm -rf "$fixture"
mkdir -p "$fixture"
printf 'signature\n' >"$fixture/ubuntu-archive"
printf 'ok\n' >"$fixture/liara"
INSTALLER_ERROR_CODE=""
INSTALLER_SELECTED_MIRROR=""
if ! installer_select_mirror noble ubuntu-archive 0; then
    if [[ "${INSTALLER_ERROR_CODE}" == "MIRROR_SIGNATURE_INVALID" && -z "${INSTALLER_SELECTED_MIRROR}" ]]; then
        ok "pinned signature failure does not fall through"
    else
        bad "pinned signature failure does not fall through"
    fi
else
    bad "pinned signature failure does not fall through"
fi

rm -rf "$fixture"
mkdir -p "$fixture"
for id in ubuntu-archive liara arvan iranserver chabokan; do
    printf 'signature\n' >"$fixture/$id"
done
INSTALLER_ERROR_CODE=""
if ! installer_select_mirror noble "" 0; then
    [[ "${INSTALLER_ERROR_CODE}" == "MIRROR_SIGNATURE_INVALID" ]] && ok "all signature failures stay closed" || bad "all signature failures stay closed"
else
    bad "all signature failures stay closed"
fi

if installer_url_ok "https://example.com/release.tar.gz" \
    && ! installer_url_ok "http://example.com/release.tar.gz" \
    && ! installer_url_ok "https://user:token@example.com/release.tar.gz" \
    && ! installer_url_ok "https://example.com/release.tar.gz?access_token=abc"; then
    ok "download urls reject credentials and plain http"
else
    bad "download urls reject credentials and plain http"
fi

mock="${tmp}/curl"
cat >"$mock" <<'EOF'
#!/usr/bin/env bash
url="${@: -1}"
out=""
prev=""
for arg in "$@"; do
    if [[ "$prev" == "--output" ]]; then
        out="$arg"
    fi
    prev="$arg"
done
count_file="${INSTALLER_CURL_COUNT}"
count=0
[[ -f "$count_file" ]] && count="$(cat "$count_file")"
count=$((count + 1))
printf '%s\n' "$count" >"$count_file"
if [[ "$url" == *"tls-fail"* ]]; then
    exit 60
fi
if [[ "$count" -lt 3 ]]; then
    exit 28
fi
printf 'body\n' >"$out"
exit 0
EOF
chmod 0755 "$mock"
export INSTALLER_CURL="$mock"
export INSTALLER_CURL_COUNT="${tmp}/curl.count"
if installer_download "https://example.com/release.tar.gz" "${tmp}/body" && [[ "$(cat "${tmp}/body")" == "body" ]]; then
    ok "download retries then resumes"
else
    bad "download retries then resumes"
fi
printf '0\n' >"$INSTALLER_CURL_COUNT"
if ! installer_download "https://example.com/tls-fail" "${tmp}/tls-body"; then
    [[ "${INSTALLER_ERROR_CODE}" == "TLS_VERIFY_FAILED" && ! -s "${tmp}/tls-body" ]] && ok "tls failure is not retried insecurely" || bad "tls failure is not retried insecurely"
else
    bad "tls failure is not retried insecurely"
fi
unset INSTALLER_CURL

key="${tmp}/release.key"
pub="${tmp}/release.pub"
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "$key" >/dev/null 2>&1
openssl pkey -in "$key" -pubout -out "$pub" >/dev/null 2>&1
app="${tmp}/application"
mkdir -p "$app/public/build"
printf '<?php\n' >"$app/artisan"
printf '{}\n' >"$app/composer.lock"
printf '{}\n' >"$app/public/build/manifest.json"
archive="$(bash "${INSTALLER_HOME}/release/assemble.sh" --from "$app" --version 1.0.0-test --output "${tmp}/pack")"
bash "${INSTALLER_HOME}/release/sign-manifest.sh" --key "$key" --artifact "$archive" --version 1.0.0-test --output "${tmp}/signed" >/dev/null
if bash "${INSTALLER_HOME}/bootstrap/verify-release.sh" \
    --manifest "${tmp}/signed/release.manifest" \
    --signature "${tmp}/signed/release.manifest.sig" \
    --public-key "$pub" \
    --artifact "${tmp}/signed/$(basename "$archive")" >"${tmp}/verify.out"; then
    ok "signed release verifies"
else
    bad "signed release verifies"
fi
mkdir -p "${tmp}/tamper"
tampered="${tmp}/tamper/$(basename "$archive")"
cp -a "${tmp}/signed/$(basename "$archive")" "$tampered"
printf 'x\n' >>"$tampered"
if ! bash "${INSTALLER_HOME}/bootstrap/verify-release.sh" \
    --manifest "${tmp}/signed/release.manifest" \
    --signature "${tmp}/signed/release.manifest.sig" \
    --public-key "$pub" \
    --artifact "$tampered" >"${tmp}/tamper.out" 2>"${tmp}/tamper.err"; then
    grep -q CHECKSUM_MISMATCH "${tmp}/tamper.err" && [[ ! -f "$tampered" ]] && ok "checksum mismatch deletes the artifact" || bad "checksum mismatch deletes the artifact"
else
    bad "checksum mismatch deletes the artifact"
fi
if ! bash "${INSTALLER_HOME}/bootstrap/verify-release.sh" \
    --manifest "${tmp}/signed/release.manifest" \
    --signature "${tmp}/signed/release.manifest.sig" \
    --artifact "${tmp}/signed/$(basename "$archive")" >"${tmp}/trust.out" 2>"${tmp}/trust.err"; then
    grep -q TRUST_ROOT_MISSING "${tmp}/trust.err" && ok "missing trust root fails closed" || bad "missing trust root fails closed"
else
    bad "missing trust root fails closed"
fi

printf 'APP_ENV=local\n' >"$app/.env"
if ! bash "${INSTALLER_HOME}/release/assemble.sh" --from "$app" --version 1.0.0-test --output "${tmp}/pack-env" >"${tmp}/env.out" 2>"${tmp}/env.err"; then
    ok "assemble refuses .env"
else
    bad "assemble refuses .env"
fi
rm -f "$app/.env"

python3 - "${tmp}/unsafe.tar.gz" <<'PY'
import tarfile, sys
with tarfile.open(sys.argv[1], "w:gz") as tar:
    info = tarfile.TarInfo("../etc/passwd")
    tar.addfile(info)
PY
if ! installer_tar_members_safe "${tmp}/unsafe.tar.gz"; then
    ok "unsafe archive paths are rejected"
else
    bad "unsafe archive paths are rejected"
fi

status_file="${tmp}/dpkg.status"
{
    for package in "${INSTALLER_PACKAGES[@]}"; do
        printf 'Package: %s\nStatus: install ok installed\n\n' "$package"
    done
} >"$status_file"

rm -rf "$fixture"
mkdir -p "$fixture"
for id in ubuntu-archive liara arvan iranserver chabokan; do
    printf 'ok\n' >"$fixture/$id"
done
sysroot="${tmp}/sys"
mkdir -p "$sysroot"
apply_env=(
    env
    INSTALLER_OS_RELEASE="$os_noble"
    INSTALLER_MEMINFO="$mem"
    INSTALLER_DISK_AVAIL_KIB=20000000
    INSTALLER_MIRROR_MODE=fixture
    INSTALLER_MIRROR_FIXTURE_DIR="$fixture"
    INSTALLER_BACKOFF_SKIP=1
    INSTALLER_OFFLINE=1
    INSTALLER_DPKG_STATUS="$status_file"
    RAHANDAZ_INSTALLER_SYSROOT="$sysroot"
    RAHANDAZ_INSTALLER_REFUSE_LIVE=1
)
ack=(--acknowledge-existing nginx --acknowledge-existing postgresql --acknowledge-existing redis-server --acknowledge-existing php-fpm --acknowledge-existing php-cli)
if "${apply_env[@]}" bash "${INSTALLER_HOME}/bin/rahandaz-install" --plan --state-dir "${tmp}/plan-state" >"${tmp}/plan.out" 2>"${tmp}/plan.err"; then
    grep -q '\[PLAN\] 14-health-verification' "${tmp}/plan.err" && ! grep -q '\[OK\]' "${tmp}/plan.err" && ok "plan does not mark work done" || bad "plan does not mark work done"
else
    bad "plan does not mark work done"
fi
if ! "${apply_env[@]}" env INSTALLER_OS_RELEASE="$os_jammy" bash "${INSTALLER_HOME}/bin/rahandaz-install" --plan --state-dir "${tmp}/jammy-state" >"${tmp}/jammy.out" 2>"${tmp}/jammy.err"; then
    grep -q PHP_BASELINE_UNAVAILABLE "${tmp}/jammy.err" \
        && grep -q '^error_code PHP_BASELINE_UNAVAILABLE$' "${tmp}/jammy-state/state" \
        && ok "jammy plan fails closed" || bad "jammy plan fails closed"
else
    bad "jammy plan fails closed"
fi

signed_artifact="${tmp}/signed/$(basename "$archive")"
run_apply() {
    "${apply_env[@]}" bash "${INSTALLER_HOME}/bin/rahandaz-install" \
        --apply \
        "${ack[@]}" \
        --state-dir "${tmp}/apply-state" \
        --artifact "$signed_artifact" \
        --manifest "${tmp}/signed/release.manifest" \
        --signature "${tmp}/signed/release.manifest.sig" \
        --public-key "$pub" \
        >"${tmp}/apply.out" 2>"${tmp}/apply.err"
}
set +e
run_apply
apply_status=$?
set -e
if [[ "$apply_status" -eq 10 ]]; then
    ok "offline apply is incomplete rather than success"
else
    bad "offline apply is incomplete rather than success (${apply_status})"
fi
key_file="${sysroot}/etc/rahandaz/master.key"
if [[ -f "$key_file" ]]; then
    python3 - "$key_file" <<'PY'
import base64, pathlib, sys
raw = base64.b64decode(pathlib.Path(sys.argv[1]).read_text().strip(), validate=True)
raise SystemExit(0 if len(raw) == 32 else 1)
PY
    key_hash="$(sha256sum "$key_file" | awk '{ print $1 }')"
    ok "master key has the production format"
else
    key_hash=""
    bad "master key has the production format"
fi
nginx_file="${sysroot}/etc/nginx/sites-available/rahandaz.conf"
if [[ -f "$nginx_file" ]] && grep -q 'location \^~ /rahandaz/' "$nginx_file" && grep -q 'deny all;' "$nginx_file" && grep -q 'rahandaz-managed: control-plane' "$nginx_file"; then
    ok "nginx site keeps the panel prefix and hidden files closed"
else
    bad "nginx site keeps the panel prefix and hidden files closed"
fi
if [[ -f "${sysroot}/var/lib/rahandaz/account.contract" ]] && ! grep -q 'password=' "${sysroot}/var/lib/rahandaz/account.contract"; then
    ok "account contract does not store a password"
else
    bad "account contract does not store a password"
fi
if [[ ! -f "${sysroot}/var/lib/rahandaz/welcome/index.html" ]]; then
    ok "welcome page is not published before health"
else
    bad "welcome page is not published before health"
fi
if [[ -n "$key_hash" ]] && ! grep -q "$key_hash" "${tmp}/apply.err" && ! grep -q "$(tr -d '\n' <"$key_file")" "${tmp}/apply.err"; then
    ok "install log does not contain the master key"
else
    bad "install log does not contain the master key"
fi
set +e
run_apply
second_status=$?
set -e
second_hash="$(sha256sum "$key_file" | awk '{ print $1 }')"
if [[ "$second_status" -eq 10 && "$second_hash" == "$key_hash" ]] && grep -q '\[SKIP\] 06-application-release' "${tmp}/apply.err"; then
    ok "resume keeps the release and the master key"
else
    bad "resume keeps the release and the master key"
fi

RAHANDAZ_INSTALLER_SYSROOT="${tmp}/sys"
if installer_mutation_allowed; then
    ok "test sysroot is mutable"
else
    bad "test sysroot is mutable"
fi
RAHANDAZ_INSTALLER_SYSROOT="/etc"
if ! installer_mutation_allowed; then
    ok "host paths outside the test root are refused"
else
    bad "host paths outside the test root are refused"
fi
RAHANDAZ_INSTALLER_SYSROOT="/"
if ! installer_mutation_allowed; then
    ok "unprivileged live apply is refused"
else
    bad "unprivileged live apply is refused"
fi

printf 'passed %s\nfailed %s\n' "$pass" "$fail"
[[ "$fail" -eq 0 ]]
