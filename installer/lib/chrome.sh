#!/usr/bin/env bash
# Chrome for Testing from the pinned GitHub mirror. The zip checksum is the trust check. Google's apt repository is not used.

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
    local commit version sha parts
    commit="$(installer_chrome_pin_get commit)" || return 1
    version="$(installer_chrome_pin_get version)" || return 1
    sha="$(installer_chrome_pin_get sha256)" || return 1
    parts="$(installer_chrome_pin_get parts)" || return 1
    [[ "$commit" =~ ^[0-9a-f]{40}$ ]] || return 1
    [[ "$version" =~ ^[0-9]+(\.[0-9]+){3}$ ]] || return 1
    [[ "$sha" =~ ^[0-9a-f]{64}$ ]] || return 1
    [[ "$parts" =~ ^[1-9][0-9]*$ ]] || return 1
    [[ "$(installer_chrome_pin_get platform)" == "linux64" ]] || return 1
    [[ "$(installer_chrome_pin_get repo)" == "mohammadhejazirad/install-googleCrome-server" ]] || return 1
}

installer_chrome_manifest_ok() {
    local manifest="$1"
    installer_chrome_pin_ok || return 1
    python3 - "$manifest" "$(installer_chrome_pin_get version)" "$(installer_chrome_pin_get sha256)" "$(installer_chrome_pin_get parts)" <<'PY'
import json, re, sys
manifest, version, digest, parts = sys.argv[1:]
data = json.load(open(manifest, encoding="utf-8"))
if str(data.get("latest_stable")) != version:
    raise SystemExit(1)
builds = [item for item in data.get("builds", []) if item.get("version") == version and item.get("platform") == "linux64"]
if len(builds) != 1:
    raise SystemExit(1)
build = builds[0]
if str(build.get("sha256")) != digest or build.get("split") is not True:
    raise SystemExit(1)
names = build.get("parts")
if not isinstance(names, list) or len(names) != int(parts):
    raise SystemExit(1)
if any(not re.fullmatch(r"chrome\.zip\.part-[0-9]{3}", str(name)) for name in names):
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
