#!/usr/bin/env bash
# Geo, Chrome pin, Ubuntu 26 refusal, and control-plane prepare. No apt and no live download.
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
# shellcheck source=../lib/gate.sh
source "${INSTALLER_HOME}/lib/gate.sh"
# shellcheck source=../lib/download.sh
source "${INSTALLER_HOME}/lib/download.sh"
# shellcheck source=../lib/geo.sh
source "${INSTALLER_HOME}/lib/geo.sh"
# shellcheck source=../lib/chrome.sh
source "${INSTALLER_HOME}/lib/chrome.sh"
# shellcheck source=../lib/engine.sh
source "${INSTALLER_HOME}/lib/engine.sh"
# shellcheck source=../lib/continue.sh
source "${INSTALLER_HOME}/lib/continue.sh"

pass=0
fail=0
ok() { printf 'ok - %s\n' "$1"; pass=$((pass + 1)); }
bad() { printf 'not ok - %s\n' "$1"; fail=$((fail + 1)); }

tmp="$(mktemp -d /tmp/rahandaz-geo-chrome.XXXXXX)"
trap 'rm -rf "$tmp"' EXIT

fixture() {
    local url="$1"
    local body="$2"
    python3 - "$tmp/fixtures" "$url" "$body" <<'PY'
import hashlib, pathlib, sys
directory = pathlib.Path(sys.argv[1])
directory.mkdir(parents=True, exist_ok=True)
name = hashlib.sha256(sys.argv[2].encode()).hexdigest()
(directory / name).write_text(sys.argv[3], encoding="utf-8")
PY
}

INSTALLER_GEO_FIXTURE_DIR="${tmp}/fixtures"
INSTALLER_STATE_DIR="${tmp}/state"
installer_state_init
fixture "https://api.ipify.org" "1.1.1.1"
fixture "https://chabokan.net/ip/?address=1.1.1.1" '{"ip":"1.1.1.1","asn":"AS1","isp":"IR ISP","country":"IR","city":"Tehran","org":"IR Org"}'
if installer_geo_lookup && [[ "$INSTALLER_GEO_COUNTRY" == "IR" && "$INSTALLER_GEO_SOURCE" == "chabokan" && "$INSTALLER_GEO_ISP" == "IR ISP" ]]; then
    ok "chabokan identifies Iran"
else
    bad "chabokan identifies Iran"
fi

rm -rf "${tmp}/fixtures"
fixture "https://api.ipify.org" "8.8.8.8"
fixture "http://ip-api.com/json/8.8.8.8" '{"status":"success","countryCode":"DE","query":"8.8.8.8","city":"Berlin","isp":"Other","org":"Org","as":"AS9"}'
INSTALLER_GEO_FIXTURE_DIR="${tmp}/fixtures"
if installer_geo_lookup && [[ "$INSTALLER_GEO_COUNTRY" == "DE" && "$INSTALLER_GEO_SOURCE" == "ip-api" ]]; then
    ok "ip-api is the fallback when earlier lookups have no fixture"
else
    bad "ip-api is the fallback when earlier lookups have no fixture"
fi

rm -rf "${tmp}/fixtures"
fixture "https://api.ipify.org" "10.1.1.1"
fixture "https://ifconfig.me/ip" "127.0.0.1"
fixture "https://icanhazip.com" "not-an-ip"
if ! installer_geo_lookup && [[ "$INSTALLER_GEO_STATUS" == "unknown" ]]; then
    ok "private and invalid addresses do not become a country"
else
    bad "private and invalid addresses do not become a country"
fi

RAHANDAZ_INSTALLER_SYSROOT=/
INSTALLER_OFFLINE=0
IRANIAN_MIRRORS=yes
if installer_decide_iranian_mirrors && [[ "$INSTALLER_MIRROR_CHOICE" == "yes" && "$PREFER_IRANIAN" == "1" ]]; then
    ok "explicit mirror yes is kept"
else
    bad "explicit mirror yes is kept"
fi
unset IRANIAN_MIRRORS
INSTALLER_OFFLINE=1
PREFER_IRANIAN=0
if installer_decide_iranian_mirrors && [[ "$INSTALLER_MIRROR_CHOICE" == "ask" ]]; then
    ok "offline mirror choice stays a question"
else
    bad "offline mirror choice stays a question"
fi

if installer_chrome_pin_ok; then
    ok "chrome pin is well formed"
else
    bad "chrome pin is well formed"
fi
python3 - "$tmp/manifest.json" <<'PY'
import json, pathlib, sys
path = pathlib.Path(sys.argv[1])
path.write_text(json.dumps({
    "latest_stable": "158.0.8110.0",
    "builds": [{
        "version": "158.0.8110.0",
        "platform": "linux64",
        "sha256": "5deb7136a8a6cebc148929ed550b38218fc5c963f46a57121da64cd8c1cd0849",
        "split": True,
        "parts": ["chrome.zip.part-000", "chrome.zip.part-001", "chrome.zip.part-002"],
    }],
}), encoding="utf-8")
PY
if installer_chrome_manifest_ok "${tmp}/manifest.json"; then
    ok "pinned chrome manifest is accepted"
else
    bad "pinned chrome manifest is accepted"
fi
python3 - "$tmp/manifest.json" <<'PY'
import json, pathlib, sys
path = pathlib.Path(sys.argv[1])
data = json.loads(path.read_text(encoding="utf-8"))
data["builds"][0]["sha256"] = "0" * 64
path.write_text(json.dumps(data), encoding="utf-8")
PY
if ! installer_chrome_manifest_ok "${tmp}/manifest.json"; then
    ok "changed chrome checksum is rejected"
else
    bad "changed chrome checksum is rejected"
fi
if ! grep -R -n -E 'dl\.google\.com|curl -k|trusted=yes' "${INSTALLER_HOME}/lib/chrome.sh"; then
    ok "chrome installer does not use Google apt or insecure TLS"
else
    bad "chrome installer does not use Google apt or insecure TLS"
fi

os26="${tmp}/resolute.os"
cat >"$os26" <<'EOF'
ID=ubuntu
VERSION_ID="26.04"
VERSION_CODENAME=resolute
EOF
sys26="${tmp}/sys26"
mkdir -p "$sys26"
set +e
env INSTALLER_OS_RELEASE="$os26" INSTALLER_OFFLINE=1 RAHANDAZ_INSTALLER_SYSROOT="$sys26" \
    bash "${INSTALLER_HOME}/bin/rahandaz-install" --apply --state-dir "${tmp}/state26" \
    >"${tmp}/u26.out" 2>"${tmp}/u26.err"
status26=$?
set -e
if [[ "$status26" -eq 1 ]] && grep -q UBUNTU_NOT_RECOMMENDED "${tmp}/u26.err" && [[ ! -d "${sys26}/etc/apt" ]]; then
    ok "ubuntu 26 apply stops before changing the system"
else
    bad "ubuntu 26 apply stops before changing the system"
fi

release="${tmp}/installed.json"
printf '%s\n' '{"version":"1.0.0","channel":"stable","release_id":"test","installer_version":"1","updater_version":"1"}' >"$release"
artifact="${tmp}/next.tar.gz"
python3 - "$artifact" <<'PY'
import tarfile, pathlib, sys
archive = pathlib.Path(sys.argv[1])
root = archive.parent / "payload"
(root / "public" / "build").mkdir(parents=True)
(root / "artisan").write_text("#!/usr/bin/env php\n", encoding="utf-8")
(root / "composer.lock").write_text("{}\n", encoding="utf-8")
(root / "public" / "build" / "manifest.json").write_text("{}\n", encoding="utf-8")
with tarfile.open(archive, "w:gz") as tar:
    for path in root.rglob("*"):
        tar.add(path, arcname=str(path.relative_to(root.parent)))
PY
sysroot="${tmp}/refresh"
mkdir -p "${sysroot}/var/lib/postgresql/data" "${sysroot}/var/lib/rahandaz"
ln -s "${sysroot}/var/lib/rahandaz/releases/1.0.0" "${sysroot}/var/lib/rahandaz/current"
printf 'keep\n' >"${sysroot}/var/lib/postgresql/data/PG_VERSION"
INSTALLER_STATE_DIR="${tmp}/refresh-state"
installer_state_init
installer_state_set result success
MODE=apply
INSTALLER_RELEASE_VERSION=1.2.0
INSTALLER_ARTIFACT="$artifact"
RAHANDAZ_RELEASE_FILE="$release"
RAHANDAZ_INSTALLER_SYSROOT="$sysroot"
set +e
installer_route_existing
route=$?
set -e
if [[ "$route" -eq 10 ]] \
    && [[ -f "${sysroot}/var/lib/rahandaz/releases/1.2.0/artisan" ]] \
    && [[ "$(readlink "${sysroot}/var/lib/rahandaz/current")" == "${sysroot}/var/lib/rahandaz/releases/1.0.0" ]] \
    && [[ "$(cat "${sysroot}/var/lib/postgresql/data/PG_VERSION")" == "keep" ]]; then
    ok "newer release is prepared without switching current or deleting database data"
else
    bad "newer release is prepared without switching current or deleting database data"
fi
INSTALLER_RELEASE_VERSION=1.0.0
set +e
installer_route_existing
same=$?
set -e
if [[ "$same" -eq 20 && -f "${sysroot}/var/lib/postgresql/data/PG_VERSION" ]]; then
    ok "the same release does not install again"
else
    bad "the same release does not install again"
fi

printf 'passed %s\nfailed %s\n' "$pass" "$fail"
[[ "$fail" -eq 0 ]]
