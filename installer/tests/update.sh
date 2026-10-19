#!/usr/bin/env bash
# Self-update logic tests. They do not install packages or dump a database.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
INSTALLER_HOME="${ROOT}/installer"
CLI="${INSTALLER_HOME}/bin/rahandaz"
export INSTALLER_HOME
# shellcheck source=../lib/common.sh
source "${INSTALLER_HOME}/lib/common.sh"
# shellcheck source=../lib/semver.sh
source "${INSTALLER_HOME}/lib/semver.sh"

pass=0
fail=0
ok() { printf 'ok - %s\n' "$1"; pass=$((pass + 1)); }
bad() { printf 'not ok - %s\n' "$1"; fail=$((fail + 1)); }

if [[ "$(installer_semver_compare 1.10.0 1.9.0)" == "1" && "$(installer_semver_compare 1.0.0-alpha 1.0.0)" == "-1" ]]; then
    ok "semantic versions compare numerically"
else
    bad "semantic versions compare numerically"
fi

tmp="$(mktemp -d /tmp/rahandaz-update-test.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT

key="${tmp}/release.key"
pub="${tmp}/release.pub"
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "$key" >/dev/null 2>&1
openssl pkey -in "$key" -pubout -out "$pub" >/dev/null 2>&1

app="${tmp}/application"
mkdir -p "$app/public/build" "$app/vendor" "$app/installer/bin"
printf '<?php\n' >"$app/artisan"
printf '{}\n' >"$app/composer.json"
printf '{}\n' >"$app/composer.lock"
printf '{}\n' >"$app/public/build/manifest.json"
printf '<?php\n' >"$app/vendor/autoload.php"
printf '#!/usr/bin/env bash\nexit 10\n' >"$app/installer/bin/rahandaz-install"
mkdir -p "$app/infrastructure/operations/software"
for software_file in authorize.php catalog.json client.py helper.py provision.py; do
    cp "${INSTALLER_HOME}/../infrastructure/operations/software/$software_file" "$app/infrastructure/operations/software/$software_file"
done
archive="$(bash "${INSTALLER_HOME}/release/assemble.sh" --from "$app" --version 1.2.3 --output "${tmp}/pack")"
bash "${INSTALLER_HOME}/release/sign-manifest.sh" --key "$key" --artifact "$archive" --version 1.2.3 --output "${tmp}/signed" >/dev/null

sign_meta() {
    local version="$1"
    local channel="$2"
    local schema="$3"
    local rollback="$4"
    local min_version="$5"
    local dest="$6"
    mkdir -p "$dest"
    cat >"${dest}/update.metadata" <<EOF
rahandaz-update-metadata 1
version=${version}
channel=${channel}
php=8.4
min_version=${min_version}
schema=${schema}
rollback=${rollback}
EOF
    openssl dgst -sha256 -sign "$key" -out "${dest}/update.metadata.sig" "${dest}/update.metadata"
}
sign_meta 1.2.3 stable forward application 1.0.0 "${tmp}/signed"

release_file="${tmp}/installed.json"
cat >"$release_file" <<'EOF'
{
  "product": "Rahandaz",
  "version": "1.0.0",
  "channel": "stable",
  "release_id": "1.0.0",
  "git_commit": "",
  "installed_at": "",
  "updated_at": "",
  "manifest_digest": "",
  "schema_version": "forward",
  "installer_version": "1.0.0",
  "updater_version": "1.0.0",
  "php": "8.4"
}
EOF

sysroot="${tmp}/sys"
state="${tmp}/state"
mkdir -p "$sysroot/var/lib/rahandaz/releases/1.0.0" "$sysroot/etc/nginx/sites-available" "$sysroot/etc/php/8.3/fpm/pool.d"
printf '<?php\n' >"$sysroot/var/lib/rahandaz/releases/1.0.0/artisan"
ln -s "$sysroot/var/lib/rahandaz/releases/1.0.0" "$sysroot/var/lib/rahandaz/current"
printf 'customer-site\n' >"$sysroot/etc/nginx/sites-available/customer.conf"
printf 'customer-pool\n' >"$sysroot/etc/php/8.3/fpm/pool.d/customer.conf"
mkdir -p "$sysroot/etc/rahandaz"
printf 'not-a-real-key\n' >"$sysroot/etc/rahandaz/master.key"
chmod 0600 "$sysroot/etc/rahandaz/master.key"
printf 'keep-me\n' >"$sysroot/var/lib/rahandaz/user-data"

run_cli() {
    env \
        RAHANDAZ_INSTALLER_SYSROOT="$sysroot" \
        RAHANDAZ_INSTALLER_REFUSE_LIVE=1 \
        INSTALLER_OFFLINE=1 \
        INSTALLER_BACKOFF_SKIP=1 \
        RAHANDAZ_RELEASE_FILE="$release_file" \
        RAHANDAZ_UPDATE_CHANNEL="${CHANNEL:-stable}" \
        bash "$CLI" "$@"
}

common_args=(
    --state-dir "$state"
    --version=1.2.3
    --manifest "${tmp}/signed/release.manifest"
    --signature "${tmp}/signed/release.manifest.sig"
    --public-key "$pub"
    --artifact "${tmp}/signed/$(basename "$archive")"
    --metadata "${tmp}/signed/update.metadata"
    --metadata-signature "${tmp}/signed/update.metadata.sig"
)

set +e
run_cli version >"${tmp}/version.out" 2>"${tmp}/version.err"
version_status=$?
run_cli update check >"${tmp}/check.out" 2>"${tmp}/check.err"
check_status=$?
run_cli update --version=nope >"${tmp}/bad-version.out" 2>"${tmp}/bad-version.err"
bad_version_status=$?
run_cli update --dry-run --version=1.2.3 >"${tmp}/dry-missing.out" 2>"${tmp}/dry-missing.err"
dry_missing_status=$?
run_cli update --dry-run "${common_args[@]}" >"${tmp}/dry.out" 2>"${tmp}/dry.err"
dry_status=$?
CHANNEL=stable run_cli update check "${common_args[@]}" >"${tmp}/available.out" 2>"${tmp}/available.err"
available_status=$?
set -e

[[ "$version_status" -eq 0 ]] && grep -q 'version 1.0.0' "${tmp}/version.err" && grep -q 'channel stable' "${tmp}/version.err" \
    && ok "version reads the release record" || bad "version reads the release record"
[[ "$check_status" -eq 0 ]] && grep -q 'update_available no' "${tmp}/check.err" \
    && ok "update check reports no release when none was supplied" || bad "update check reports no release when none was supplied"
[[ "$bad_version_status" -eq 1 ]] && grep -q INVALID_VERSION "${tmp}/bad-version.err" \
    && ok "invalid version is rejected" || bad "invalid version is rejected"
[[ "$dry_missing_status" -eq 1 ]] && grep -q UNVERIFIED_ARTIFACT "${tmp}/dry-missing.err" \
    && ok "dry-run without an artifact fails" || bad "dry-run without an artifact fails"
[[ "$dry_status" -eq 0 ]] && grep -q 'RESULT dry_run' "${tmp}/dry.err" && [[ ! -d "$sysroot/var/lib/rahandaz/releases/1.2.3" ]] \
    && ok "dry-run verifies and does not prepare a release" || bad "dry-run verifies and does not prepare a release"
[[ "$available_status" -eq 0 ]] && grep -q 'update_available yes' "${tmp}/available.err" \
    && ok "update check reports a newer verified release" || bad "update check reports a newer verified release"

sign_meta 1.2.3 beta forward application 1.0.0 "${tmp}/beta"
cp "${tmp}/signed/release.manifest" "${tmp}/beta/release.manifest"
cp "${tmp}/signed/release.manifest.sig" "${tmp}/beta/release.manifest.sig"
cp "${tmp}/signed/$(basename "$archive")" "${tmp}/beta/$(basename "$archive")"
set +e
run_cli update --state-dir "${tmp}/beta-state" --version=1.2.3 \
    --manifest "${tmp}/beta/release.manifest" \
    --signature "${tmp}/beta/release.manifest.sig" \
    --public-key "$pub" \
    --artifact "${tmp}/beta/$(basename "$archive")" \
    --metadata "${tmp}/beta/update.metadata" \
    --metadata-signature "${tmp}/beta/update.metadata.sig" >"${tmp}/beta.out" 2>"${tmp}/beta.err"
beta_status=$?
set -e
[[ "$beta_status" -eq 1 ]] && grep -q UPDATE_UNSUPPORTED "${tmp}/beta.err" \
    && ok "stable channel rejects a beta release" || bad "stable channel rejects a beta release"

sign_meta 1.2.3 stable destructive application 1.0.0 "${tmp}/destructive"
set +e
run_cli update --state-dir "${tmp}/destructive-state" --version=1.2.3 \
    --manifest "${tmp}/signed/release.manifest" \
    --signature "${tmp}/signed/release.manifest.sig" \
    --public-key "$pub" \
    --artifact "${tmp}/signed/$(basename "$archive")" \
    --metadata "${tmp}/destructive/update.metadata" \
    --metadata-signature "${tmp}/destructive/update.metadata.sig" >"${tmp}/destructive.out" 2>"${tmp}/destructive.err"
destructive_status=$?
set -e
[[ "$destructive_status" -eq 1 ]] && grep -q MIGRATION_REFUSED "${tmp}/destructive.err" && [[ ! -d "$sysroot/var/lib/rahandaz/releases/1.2.3" ]] \
    && ok "destructive schema is refused before preparation" || bad "destructive schema is refused before preparation"

bad_sig="${tmp}/bad.sig"
python3 - "${tmp}/signed/update.metadata.sig" "$bad_sig" <<'PY'
import pathlib, sys
raw = pathlib.Path(sys.argv[1]).read_bytes()
raw = raw[:10] + bytes([raw[10] ^ 0xFF]) + raw[11:]
pathlib.Path(sys.argv[2]).write_bytes(raw)
PY
set +e
run_cli update --state-dir "${tmp}/badsig-state" --version=1.2.3 \
    --manifest "${tmp}/signed/release.manifest" \
    --signature "${tmp}/signed/release.manifest.sig" \
    --public-key "$pub" \
    --artifact "${tmp}/signed/$(basename "$archive")" \
    --metadata "${tmp}/signed/update.metadata" \
    --metadata-signature "$bad_sig" >"${tmp}/badsig.out" 2>"${tmp}/badsig.err"
badsig_status=$?
set -e
[[ "$badsig_status" -eq 1 ]] && grep -q SIGNATURE_INVALID "${tmp}/badsig.err" \
    && ok "invalid metadata signature is rejected" || bad "invalid metadata signature is rejected"

mkdir -p "${tmp}/tamper-artifact"
tampered="${tmp}/tamper-artifact/$(basename "$archive")"
cp -a "${tmp}/signed/$(basename "$archive")" "$tampered"
printf 'x\n' >>"$tampered"
set +e
run_cli update --state-dir "${tmp}/tamper-state" --version=1.2.3 \
    --manifest "${tmp}/signed/release.manifest" \
    --signature "${tmp}/signed/release.manifest.sig" \
    --public-key "$pub" \
    --artifact "$tampered" \
    --metadata "${tmp}/signed/update.metadata" \
    --metadata-signature "${tmp}/signed/update.metadata.sig" >"${tmp}/tamper.out" 2>"${tmp}/tamper.err"
tamper_status=$?
set -e
[[ "$tamper_status" -eq 1 ]] && grep -q CHECKSUM_MISMATCH "${tmp}/tamper.err" && [[ ! -f "$tampered" ]] \
    && ok "checksum mismatch deletes the artifact and does not activate" || bad "checksum mismatch deletes the artifact and does not activate"

mkdir -p "$state"
lock="${state}/update.lock"
# Keep the lock in this shell. Killing a flock wrapper may leave its sleep child
# holding the inherited descriptor in minimal containers and poison later cases.
exec {test_lock_fd}>"$lock"
flock -n "$test_lock_fd"
set +e
run_cli update "${common_args[@]}" >"${tmp}/locked.out" 2>"${tmp}/locked.err"
locked_status=$?
set -e
flock -u "$test_lock_fd"
exec {test_lock_fd}>&-
[[ "$locked_status" -eq 1 ]] && grep -q UPDATE_LOCKED "${tmp}/locked.err" && [[ ! -d "$sysroot/var/lib/rahandaz/releases/1.2.3" ]] \
    && ok "a second update cannot start while the lock is held" || bad "a second update cannot start while the lock is held"

set +e
run_cli update "${common_args[@]}" >"${tmp}/apply.out" 2>"${tmp}/apply.err"
apply_status=$?
run_cli update "${common_args[@]}" >"${tmp}/resume.out" 2>"${tmp}/resume.err"
resume_status=$?
set -e
current_target="$(readlink "$sysroot/var/lib/rahandaz/current")"
[[ "$apply_status" -eq 10 ]] && grep -q 'HEALTH not_run' "${tmp}/apply.err" && grep -q 'RESULT incomplete' "${tmp}/apply.err" \
    && [[ -f "$sysroot/var/lib/rahandaz/releases/1.2.3/artisan" ]] \
    && [[ "$current_target" == *"releases/1.0.0" ]] \
    && ok "offline update prepares the release and keeps the current symlink" || bad "offline update prepares the release and keeps the current symlink"
if [[ "$apply_status" != 10 ]]; then
    awk '/^ERROR / { print }' "${tmp}/apply.err"
fi
[[ "$resume_status" -eq 10 ]] && grep -q 'already prepared' "${tmp}/resume.err" \
    && ok "a repeated update resumes the prepared release" || bad "a repeated update resumes the prepared release"
[[ "$(stat -c '%a' "${state}/history")" == "600" ]] && grep -q '1.2.3 incomplete' "${state}/history" \
    && ! grep -q 'not-a-real-key' "${tmp}/apply.err" \
    && [[ "$(stat -c '%a' "${state}/backup/etc/rahandaz/master.key")" == "600" ]] \
    && ok "history and the key backup stay private" || bad "history and the key backup stay private"

# Replace the attempt recorded by the forward apply with one that cannot roll back safely.
cat >"${state}/attempt.meta" <<'EOF'
rahandaz-update-attempt 1
version=1.2.3
previous=1.0.0
schema=destructive
rollback=manual
EOF
chmod 0600 "${state}/attempt.meta"
set +e
run_cli update rollback --state-dir "$state" >"${tmp}/manual-rollback.out" 2>"${tmp}/manual-rollback.err"
manual_status=$?
set -e
[[ "$manual_status" -eq 1 ]] && grep -q 'manual_recovery_required' "${tmp}/manual-rollback.err" \
    && [[ "$(readlink "$sysroot/var/lib/rahandaz/current")" == *"releases/1.0.0" ]] \
    && ok "incompatible schema reports manual recovery" || bad "incompatible schema reports manual recovery"

cat >"${state}/attempt.meta" <<'EOF'
rahandaz-update-attempt 1
version=1.2.3
previous=1.0.0
schema=forward
rollback=application
EOF
chmod 0600 "${state}/attempt.meta"
ln -sfn "$sysroot/var/lib/rahandaz/releases/1.2.3" "$sysroot/var/lib/rahandaz/current"
set +e
run_cli update rollback --state-dir "$state" --approve-database-recovery >"${tmp}/db-rollback.out" 2>"${tmp}/db-rollback.err"
db_status=$?
set -e
[[ "$db_status" -eq 1 ]] && grep -q 'manual_recovery_required' "${tmp}/db-rollback.err" \
    && [[ -f "$sysroot/var/lib/rahandaz/user-data" ]] \
    && [[ "$(readlink "$sysroot/var/lib/rahandaz/current")" == *"releases/1.2.3" ]] \
    && ok "database recovery is not automatic" || bad "database recovery is not automatic"

set +e
run_cli update rollback --state-dir "$state" >"${tmp}/rollback.out" 2>"${tmp}/rollback.err"
rollback_status=$?
set -e
[[ "$rollback_status" -eq 10 ]] && grep -q 'application_rollback 1.0.0' "${tmp}/rollback.err" \
    && [[ "$(readlink "$sysroot/var/lib/rahandaz/current")" == *"releases/1.0.0" ]] \
    && ok "application rollback switches only the release symlink" || bad "application rollback switches only the release symlink"

customer_site="$(sha256sum "$sysroot/etc/nginx/sites-available/customer.conf" | awk '{ print $1 }')"
customer_pool="$(sha256sum "$sysroot/etc/php/8.3/fpm/pool.d/customer.conf" | awk '{ print $1 }')"
set +e
run_cli repair --state-dir "${tmp}/repair-state" >"${tmp}/repair.out" 2>"${tmp}/repair.err"
repair_status=$?
set -e
[[ "$repair_status" -eq 0 ]] \
    && grep -q 'REPAIR rendered Rahandaz files only' "${tmp}/repair.err" \
    && ! grep -E -q 'apt-get|update-alternatives' "${tmp}/repair.err" \
    && [[ "$(sha256sum "$sysroot/etc/nginx/sites-available/customer.conf" | awk '{ print $1 }')" == "$customer_site" ]] \
    && [[ "$(sha256sum "$sysroot/etc/php/8.3/fpm/pool.d/customer.conf" | awk '{ print $1 }')" == "$customer_pool" ]] \
    && grep -q '/usr/bin/php8.4' "$sysroot/etc/systemd/system/rahandaz-queue.service" \
    && ok "repair rewrites only Rahandaz files" || bad "repair rewrites only Rahandaz files"

mock="${tmp}/curl-resume"
cat >"$mock" <<'EOF'
#!/usr/bin/env bash
out=""
prev=""
resume=""
for arg in "$@"; do
    if [[ "$prev" == "--output" ]]; then
        out="$arg"
    fi
    if [[ "$prev" == "--continue-at" ]]; then
        resume="$arg"
    fi
    prev="$arg"
done
if [[ ! -s "$out" ]]; then
    printf 'PART' >"$out"
    exit 18
fi
if [[ "$resume" == "-" ]]; then
    printf 'IAL' >>"$out"
    exit 0
fi
exit 1
EOF
chmod 0755 "$mock"
# shellcheck source=../lib/download.sh
source "${INSTALLER_HOME}/lib/download.sh"
export INSTALLER_CURL="$mock"
export INSTALLER_BACKOFF_SKIP=1
if installer_download "https://example.com/rahandaz.tar.gz" "${tmp}/resumed" && [[ "$(cat "${tmp}/resumed")" == "PARTIAL" ]]; then
    ok "artifact download resumes without replacing the partial file"
else
    bad "artifact download resumes without replacing the partial file"
fi
unset INSTALLER_CURL

if grep -R -n -E 'apt-get[[:space:]]+upgrade|update-alternatives|systemctl[[:space:]]+restart[[:space:]]+nginx|systemctl[[:space:]]+restart[[:space:]]+php|migrate:fresh|DROP DATABASE' \
    "${INSTALLER_HOME}/lib/update.sh" "${INSTALLER_HOME}/bin/rahandaz" "${INSTALLER_HOME}/templates/systemd/rahandaz-php-fpm.service.tpl"; then
    bad "updater stays away from foreign services"
else
    ok "updater stays away from foreign services"
fi

printf 'update passed %s\nupdate failed %s\n' "$pass" "$fail"
[[ "$fail" -eq 0 ]]
