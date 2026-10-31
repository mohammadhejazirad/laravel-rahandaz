#!/usr/bin/env bash
# Chrome for Testing mirror identity. The installer never downloads Chrome.
# The panel helper reads latest_stable from this repository at install time.

if [[ -n "${INSTALLER_LIB_CHROME:-}" ]]; then
    return 0
fi
INSTALLER_LIB_CHROME=1
# shellcheck source=geo.sh
source "${INSTALLER_HOME}/lib/geo.sh"

installer_chrome_pin_file() {
    printf '%s\n' "${INSTALLER_CHROME_PIN:-${INSTALLER_HOME}/manifests/chrome-mirror.v1}"
}

installer_chrome_pin_get() {
    local key="$1"
    local file
    file="$(installer_chrome_pin_file)"
    [[ -f "$file" && ! -L "$file" ]] || return 1
    awk -F= -v k="$key" '$1 == k { print substr($0, index($0, "=") + 1); exit }' "$file"
}

installer_chrome_pin_ok() {
    local schema repo branch platform
    schema="$(installer_chrome_pin_get schema)" || return 1
    repo="$(installer_chrome_pin_get repo)" || return 1
    branch="$(installer_chrome_pin_get branch)" || return 1
    platform="$(installer_chrome_pin_get platform)" || return 1
    [[ "$schema" == "2" ]] || return 1
    [[ "$repo" == "mohammadhejazirad/install-google-chrome-server" ]] || return 1
    [[ "$branch" == "main" ]] || return 1
    [[ "$platform" == "linux64" ]] || return 1
    # Version, commit, and checksum move with the mirror manifest.
    [[ -z "$(installer_chrome_pin_get version)" ]] || return 1
    [[ -z "$(installer_chrome_pin_get commit)" ]] || return 1
    [[ -z "$(installer_chrome_pin_get sha256)" ]] || return 1
    [[ -z "$(installer_chrome_pin_get parts)" ]] || return 1
}

installer_chrome_manifest_ok() {
    local manifest="$1"
    installer_chrome_pin_ok || return 1
    python3 - "$manifest" <<'PY'
import json, re, sys
data = json.load(open(sys.argv[1], encoding="utf-8"))
version = str(data.get("latest_stable") or "")
if not re.fullmatch(r"[0-9]+(?:\.[0-9]+){3}", version):
    raise SystemExit(1)
builds = [item for item in data.get("builds", []) if item.get("version") == version and item.get("platform") == "linux64"]
if len(builds) != 1:
    raise SystemExit(1)
build = builds[0]
if not re.fullmatch(r"[0-9a-f]{64}", str(build.get("sha256") or "")) or build.get("split") is not True:
    raise SystemExit(1)
names = build.get("parts")
if not isinstance(names, list) or not names or len(names) > 20:
    raise SystemExit(1)
expected = [f"chrome.zip.part-{index:03d}" for index in range(len(names))]
if names != expected:
    raise SystemExit(1)
PY
}

# Compatibility guard: installer code must never install a project browser.
installer_chrome_step() {
    installer_error CHROME_PANEL_ONLY "Chrome installation belongs to the panel Operations Engine."
    return 2
}

installer_chrome_install() {
    installer_chrome_step
}
