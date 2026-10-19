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

installer_chrome_step() {
    if [[ "${MODE:-plan}" == "plan" ]]; then
        installer_say "PLAN chrome-for-testing from the pinned mirror for Ubuntu 22.04 and 24.04. Google apt is not used. Iran and other countries use the same verified zip."
        return 0
    fi
    case "${INSTALLER_OS_CODENAME:-}" in
        jammy | noble) ;;
        *)
            installer_say "CHROME skipped on this Ubuntu release"
            return 0
            ;;
    esac
    if [[ "${INSTALL_CHROME:-}" == "no" ]]; then
        installer_say "CHROME skipped"
        return 0
    fi
    if [[ "${INSTALL_CHROME:-}" != "yes" ]]; then
        if installer_confirm "Google Chrome برای رندر سرور نصب شود؟"; then
            INSTALL_CHROME="yes"
        else
            installer_say "CHROME skipped"
            return 0
        fi
    fi
    if [[ "$(installer_sysroot)" != "/" || "${INSTALLER_OFFLINE:-}" == "1" ]]; then
        installer_say "CHROME not_run"
        return 0
    fi
    installer_chrome_install
}

installer_chrome_libraries() {
    local -a packages=(
        ca-certificates fonts-liberation fonts-noto-color-emoji
        libatk-bridge2.0-0 libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1
        libgbm1 libglib2.0-0 libgtk-3-0 libnspr4 libnss3 libpango-1.0-0
        libx11-6 libx11-xcb1 libxcb1 libxcomposite1 libxdamage1 libxext6
        libxfixes3 libxrandr2 xdg-utils
    )
    command -v apt-get >/dev/null 2>&1 || return 0
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "${packages[@]}" || return 1
    if apt-cache show libasound2t64 >/dev/null 2>&1; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends libasound2t64 || return 1
    elif apt-cache show libasound2 >/dev/null 2>&1; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends libasound2 || return 1
    fi
}

installer_chrome_install() {
    local commit version digest base tmp part zip actual root target backup
    installer_chrome_pin_ok || {
        installer_error UNVERIFIED_ARTIFACT "The Chrome mirror pin is invalid."
        return 1
    }
    commit="$(installer_chrome_pin_get commit)"
    version="$(installer_chrome_pin_get version)"
    digest="$(installer_chrome_pin_get sha256)"
    base="https://raw.githubusercontent.com/mohammadhejazirad/install-googleCrome-server/${commit}"
    tmp="$(mktemp -d /tmp/rahandaz-chrome.XXXXXX)"
    installer_download "${base}/manifest.json" "${tmp}/manifest.json" || return 1
    installer_chrome_manifest_ok "${tmp}/manifest.json" || {
        installer_error UNVERIFIED_ARTIFACT "The Chrome manifest does not match the pinned checksum."
        rm -rf "$tmp"
        return 1
    }
    zip="${tmp}/chrome.zip"
    : >"$zip"
    local index=0
    while ((index < $(installer_chrome_pin_get parts))); do
        part="$(printf 'chrome.zip.part-%03d' "$index")"
        installer_download "${base}/chrome/${version}/linux64/${part}" "${tmp}/${part}" || {
            rm -rf "$tmp"
            return 1
        }
        cat "${tmp}/${part}" >>"$zip"
        index=$((index + 1))
    done
    actual="$(sha256sum "$zip" | awk '{ print $1 }')"
    if [[ "$actual" != "$digest" ]]; then
        installer_error UNVERIFIED_ARTIFACT "Chrome zip checksum did not match the pin."
        rm -rf "$tmp"
        return 1
    fi
    unzip -t "$zip" >/dev/null || {
        installer_error UNVERIFIED_ARTIFACT "Chrome zip failed its integrity test."
        rm -rf "$tmp"
        return 1
    }
    unzip -q "$zip" -d "${tmp}/extract"
    [[ -f "${tmp}/extract/chrome-linux64/chrome" ]] || {
        installer_error ARTIFACT_INCOMPLETE "The Chrome archive has no linux64 binary."
        rm -rf "$tmp"
        return 1
    }
    chmod 0755 "${tmp}/extract/chrome-linux64/chrome"
    root="/opt/chrome-for-testing"
    target="${root}/chrome-linux64"
    backup="${root}/chrome-linux64.backup"
    mkdir -p "$root"
    rm -rf "$backup"
    if [[ -e "$target" ]]; then
        mv "$target" "$backup"
    fi
    if ! mv "${tmp}/extract/chrome-linux64" "$target"; then
        [[ -e "$backup" ]] && mv "$backup" "$target" || true
        rm -rf "$tmp"
        installer_error STAGE_FAILED "Chrome could not be moved into place."
        return 1
    fi
    if ! "$target/chrome" --version >/dev/null 2>&1; then
        installer_chrome_libraries || true
    fi
    if ! "$target/chrome" --version >/dev/null 2>&1; then
        rm -rf "$target"
        [[ -e "$backup" ]] && mv "$backup" "$target" || true
        rm -rf "$tmp"
        installer_error HEALTHCHECK_FAILED "The Chrome binary did not report its version."
        return 1
    fi
    rm -rf "$backup" "$tmp"
    ln -sfn "${target}/chrome" /usr/local/bin/chrome-for-testing
    installer_say "CHROME ${version} pinned ${commit}"
}
