#!/usr/bin/env bash
# Public-IP country lookup. Chabokan first, then other free lookups. TLS stays on except the documented ip-api.com HTTP endpoint.

if [[ -n "${INSTALLER_LIB_GEO:-}" ]]; then
    return 0
fi
INSTALLER_LIB_GEO=1

# shellcheck source=download.sh
source "${INSTALLER_HOME}/lib/download.sh"

installer_geo_reset() {
    INSTALLER_GEO_STATUS="unknown"
    INSTALLER_GEO_IP=""
    INSTALLER_GEO_COUNTRY=""
    INSTALLER_GEO_CITY=""
    INSTALLER_GEO_ISP=""
    INSTALLER_GEO_ORG=""
    INSTALLER_GEO_ASN=""
    INSTALLER_GEO_SOURCE=""
}

installer_geo_public_ip() {
    local value="$1"
    python3 - "$value" <<'PY'
import ipaddress, sys
try:
    ip = ipaddress.ip_address(sys.argv[1].strip())
except ValueError:
    raise SystemExit(1)
if (
    ip.is_private or ip.is_loopback or ip.is_link_local or ip.is_multicast
    or ip.is_reserved or ip.is_unspecified
):
    raise SystemExit(1)
print(ip)
PY
}

installer_geo_fixture_body() {
    local url="$1"
    local dir="${INSTALLER_GEO_FIXTURE_DIR:-}"
    [[ -n "$dir" ]] || return 1
    python3 - "$dir" "$url" <<'PY'
import hashlib, pathlib, sys
directory = pathlib.Path(sys.argv[1])
name = hashlib.sha256(sys.argv[2].encode()).hexdigest()
path = directory / name
if not path.is_file():
    raise SystemExit(1)
sys.stdout.write(path.read_text(encoding="utf-8"))
PY
}

installer_geo_http_text() {
    local url="$1"
    local dest="$2"
    if [[ -n "${INSTALLER_GEO_FIXTURE_DIR:-}" ]]; then
        installer_geo_fixture_body "$url" >"$dest"
        return $?
    fi
    if [[ "$url" == http://ip-api.com/json/* ]]; then
        [[ "$url" =~ ^http://ip-api.com/json/[0-9a-fA-F:.]+$ ]] || return 1
        installer_curl --proto '=http' --proto-redir '=http' --fail --silent --show-error \
            --max-time 8 --max-redirs 0 --output "$dest" "$url"
        return $?
    fi
    installer_download "$url" "$dest"
}

installer_geo_apply_record() {
    local source="$1"
    local file="$2"
    python3 - "$source" "$file" <<'PY'
import json, sys
source, path = sys.argv[1], sys.argv[2]
data = json.load(open(path, encoding="utf-8"))
if source == "chabokan":
    country = str(data.get("country") or "")
    ip = str(data.get("ip") or "")
    city = str(data.get("city") or "")
    isp = str(data.get("isp") or "")
    org = str(data.get("org") or "")
    asn = str(data.get("asn") or "")
elif source == "ipwho":
    if data.get("success") is False:
        raise SystemExit(1)
    country = str(data.get("country_code") or "")
    ip = str(data.get("ip") or "")
    city = str(data.get("city") or "")
    connection = data.get("connection") if isinstance(data.get("connection"), dict) else {}
    isp = str(connection.get("isp") or "")
    org = str(connection.get("org") or "")
    asn = str(connection.get("asn") or "")
elif source == "ip-api":
    if str(data.get("status") or "") != "success":
        raise SystemExit(1)
    country = str(data.get("countryCode") or "")
    ip = str(data.get("query") or "")
    city = str(data.get("city") or "")
    isp = str(data.get("isp") or "")
    org = str(data.get("org") or "")
    asn = str(data.get("as") or "")
else:
    raise SystemExit(1)
for value in (country, ip, city, isp, org, asn):
    if any(char in value for char in "\n\r\t\x00"):
        raise SystemExit(1)
if len(country) != 2 or not country.isalpha():
    raise SystemExit(1)
print("\t".join([country.upper(), ip, city[:80], isp[:120], org[:120], asn[:40]]))
PY
}

installer_geo_store() {
    local dir file
    [[ -n "${INSTALLER_STATE_DIR:-}" ]] || return 0
    dir="$INSTALLER_STATE_DIR"
    mkdir -p "$dir" || return 0
    file="${dir}/location.json"
    python3 - "$file" <<'PY'
import json, os, sys
path = sys.argv[1]
payload = {
    "status": os.environ.get("INSTALLER_GEO_STATUS", "unknown"),
    "ip": os.environ.get("INSTALLER_GEO_IP", ""),
    "country": os.environ.get("INSTALLER_GEO_COUNTRY", ""),
    "city": os.environ.get("INSTALLER_GEO_CITY", ""),
    "isp": os.environ.get("INSTALLER_GEO_ISP", ""),
    "org": os.environ.get("INSTALLER_GEO_ORG", ""),
    "asn": os.environ.get("INSTALLER_GEO_ASN", ""),
    "source": os.environ.get("INSTALLER_GEO_SOURCE", ""),
}
temporary = path + ".tmp"
with open(temporary, "w", encoding="utf-8") as handle:
    json.dump(payload, handle, ensure_ascii=False)
    handle.write("\n")
os.chmod(temporary, 0o600)
os.replace(temporary, path)
PY
    if [[ -n "${INSTALLER_STATE_FILE:-}" && "$INSTALLER_GEO_COUNTRY" =~ ^[A-Z]{2}$ ]]; then
        installer_state_set location.country "$INSTALLER_GEO_COUNTRY" || true
        installer_state_set location.source "${INSTALLER_GEO_SOURCE//-/_}" || true
    fi
}

installer_geo_lookup() {
    local tmp ip url record country found_ip city isp org asn source
    installer_geo_reset
    tmp="$(mktemp -d "${TMPDIR:-/tmp}/rahandaz-geo.XXXXXX")"
    ip=""
    for url in https://api.ipify.org https://ifconfig.me/ip https://icanhazip.com; do
        if installer_geo_http_text "$url" "${tmp}/ip" && ip="$(installer_geo_public_ip "$(tr -d '[:space:]' <"${tmp}/ip")")"; then
            break
        fi
        ip=""
    done
    if [[ -z "$ip" ]]; then
        rm -rf "$tmp"
        installer_geo_store
        return 1
    fi
    INSTALLER_GEO_IP="$ip"
    for source in chabokan ipwho ip-api; do
        case "$source" in
            chabokan) url="https://chabokan.net/ip/?address=${ip}" ;;
            ipwho) url="https://ipwho.is/${ip}" ;;
            ip-api) url="http://ip-api.com/json/${ip}" ;;
        esac
        if installer_geo_http_text "$url" "${tmp}/body" && record="$(installer_geo_apply_record "$source" "${tmp}/body")"; then
            IFS=$'\t' read -r country found_ip city isp org asn <<<"$record"
            if [[ "$found_ip" == "$ip" || -z "$found_ip" ]]; then
                INSTALLER_GEO_STATUS="known"
                INSTALLER_GEO_COUNTRY="$country"
                INSTALLER_GEO_CITY="$city"
                INSTALLER_GEO_ISP="$isp"
                INSTALLER_GEO_ORG="$org"
                INSTALLER_GEO_ASN="$asn"
                INSTALLER_GEO_SOURCE="$source"
                rm -rf "$tmp"
                installer_geo_store
                return 0
            fi
        fi
    done
    rm -rf "$tmp"
    installer_geo_store
    return 1
}

installer_confirm() {
    local prompt="$1"
    local answer
    if [[ ! -r /dev/stdin || ! -t 0 ]]; then
        return 2
    fi
    while :; do
        printf '%s [y/n]: ' "$prompt" >&2
        IFS= read -r answer || return 2
        case "$answer" in
            y | Y) return 0 ;;
            n | N) return 1 ;;
            *) printf 'Please enter y or n.\n' >&2 ;;
        esac
    done
}

installer_decide_iranian_mirrors() {
    local answer prompt
    INSTALLER_MIRROR_CHOICE="ask"
    if [[ "${IRANIAN_MIRRORS:-}" == "yes" ]]; then
        PREFER_IRANIAN=1
        INSTALLER_MIRROR_CHOICE="yes"
        return 0
    fi
    if [[ "${IRANIAN_MIRRORS:-}" == "no" ]]; then
        PREFER_IRANIAN=0
        INSTALLER_MIRROR_CHOICE="no"
        return 0
    fi
    if [[ "${INSTALLER_OFFLINE:-}" == "1" || "$(installer_sysroot)" != "/" ]]; then
        installer_say "GEO skipped"
        return 0
    fi
    installer_geo_lookup || true
    if [[ "$INSTALLER_GEO_STATUS" == "known" && "$INSTALLER_GEO_COUNTRY" != "IR" ]]; then
        installer_say "GEO country ${INSTALLER_GEO_COUNTRY} source ${INSTALLER_GEO_SOURCE}"
        PREFER_IRANIAN=0
        INSTALLER_MIRROR_CHOICE="no"
        return 0
    fi
    if [[ "$INSTALLER_GEO_STATUS" == "known" ]]; then
        installer_say "GEO iran source ${INSTALLER_GEO_SOURCE}"
        prompt="The server was detected in Iran. Configure an Iranian Ubuntu mirror?"
    else
        installer_say "GEO unknown"
        prompt="The server country could not be detected. Configure an Iranian Ubuntu mirror?"
    fi
    if installer_confirm "$prompt"; then
        PREFER_IRANIAN=1
        INSTALLER_MIRROR_CHOICE="yes"
        return 0
    fi
    answer=$?
    if [[ "$answer" -eq 1 ]]; then
        PREFER_IRANIAN=0
        INSTALLER_MIRROR_CHOICE="no"
        return 0
    fi
    installer_error INPUT_REQUIRED "Mirror selection is required. Re-run with --iranian-mirrors yes or no."
    return 2
}
