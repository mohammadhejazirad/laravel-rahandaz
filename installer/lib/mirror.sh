#!/usr/bin/env bash
# APT mirror selection. A mirror is used only after Ubuntu's archive signature verifies.
# Third-party operators are not a trust root. trusted=yes is not emitted.

if [[ -n "${INSTALLER_LIB_MIRROR:-}" ]]; then
    return 0
fi
INSTALLER_LIB_MIRROR=1

installer_mirror_catalog() {
    printf '%s\n' "${INSTALLER_MIRROR_CATALOG:-${INSTALLER_HOME}/manifests/mirrors.v1}"
}

installer_mirror_field() {
    local id="$1"
    local field="$2"
    local catalog
    catalog="$(installer_mirror_catalog)"
    awk -F'|' -v id="$id" -v field="$field" '
        $1 ~ /^#/ || NF < 10 { next }
        $1 == id { print $field; exit }
    ' "$catalog"
}

installer_mirror_ids() {
    local catalog prefer="$1"
    catalog="$(installer_mirror_catalog)"
    awk -F'|' -v prefer="$prefer" '
        $1 ~ /^#/ || NF < 10 || $2 != "yes" || $3 != "apt" { next }
        { print $1 }
    ' "$catalog" | {
        if [[ "$prefer" == "1" ]]; then
            awk 'BEGIN { order[1] = "liara"; order[2] = "arvan"; order[3] = "iranserver"; order[4] = "chabokan" }
                { seen[$0] = 1; extra[++n] = $0 }
                END {
                    for (i = 1; i <= 4; i++) if (seen[order[i]]) print order[i]
                    for (i = 1; i <= n; i++) {
                        skip = 0
                        for (j = 1; j <= 4; j++) if (extra[i] == order[j]) skip = 1
                        if (!skip) print extra[i]
                    }
                }'
        else
            cat
        fi
    }
}

installer_probe_consume() {
    local id="$1"
    local file="${INSTALLER_MIRROR_FIXTURE_DIR}/${id}"
    local line count_file count
    if [[ ! -f "$file" ]]; then
        printf '%s\n' "missing"
        return 0
    fi
    count_file="${INSTALLER_MIRROR_FIXTURE_DIR}/.${id}.count"
    count=0
    if [[ -f "$count_file" ]]; then
        count="$(cat "$count_file")"
    fi
    count=$((count + 1))
    printf '%s\n' "$count" >"$count_file"
    line="$(awk -v n="$count" 'NR == n { print; exit }' "$file")"
    if [[ -z "$line" ]]; then
        line="$(awk 'NF { value = $0 } END { print value }' "$file")"
    fi
    printf '%s\n' "$line"
}

installer_probe_mirror() {
    local id="$1"
    local codename="$2"
    local status
    if [[ "${INSTALLER_MIRROR_MODE:-live}" == "fixture" ]]; then
        status="$(installer_probe_consume "$id")"
        case "$status" in
            ok) return 0 ;;
            timeout | dns | http-403 | http-429 | http-500) return 1 ;;
            tls) return 100 ;;
            signature) return 101 ;;
            *) return 1 ;;
        esac
    fi
    installer_probe_mirror_live "$id" "$codename"
}

installer_probe_mirror_live() {
    local id="$1"
    local codename="$2"
    local base suite_url body keyring
    base="$(installer_mirror_field "$id" 5)"
    base="${base%/}"
    suite_url="${base}/dists/${codename}/InRelease"
    installer_url_ok "$suite_url" || return 100
    keyring="${INSTALLER_APT_KEYRING:-/usr/share/keyrings/ubuntu-archive-keyring.gpg}"
    [[ -f "$keyring" ]] || return 1
    body="$(mktemp)"
    if ! installer_curl --fail --silent --show-error --location --max-time 20 --output "$body" "$suite_url"; then
        rm -f "$body"
        return 1
    fi
    if ! awk -F': ' -v suite="$codename" '$1 == "Suite" && $2 == suite { found = 1 } END { exit !found }' "$body"; then
        rm -f "$body"
        return 101
    fi
    if ! gpg --batch --no-default-keyring --keyring "$keyring" --verify "$body" >/dev/null 2>&1; then
        rm -f "$body"
        return 101
    fi
    rm -f "$body"
    return 0
}

installer_select_mirror() {
    local codename="$1"
    local pinned="${2:-}"
    local prefer="${3:-0}"
    local id status attempts codenames security=0 tls=0
    local -a ids=()
    if [[ -n "$pinned" ]]; then
        if [[ "$(installer_mirror_field "$pinned" 2)" != "yes" ]]; then
            installer_error MIRROR_UNAVAILABLE "The requested mirror is not a verified selectable mirror."
            return 1
        fi
        ids=("$pinned")
    else
        mapfile -t ids < <(installer_mirror_ids "$prefer")
    fi
    if [[ "${#ids[@]}" -eq 0 ]]; then
        installer_error MIRROR_UNAVAILABLE "No selectable mirror is catalogued."
        return 1
    fi
    for id in "${ids[@]}"; do
        codenames=",$(installer_mirror_field "$id" 7),"
        [[ "$codenames" == *",${codename},"* ]] || continue
        attempts=1
        while ((attempts <= 3)); do
            set +e
            installer_probe_mirror "$id" "$codename"
            status=$?
            set -e
            if [[ "$status" -eq 0 ]]; then
                INSTALLER_ERROR_CODE=""
                INSTALLER_SELECTED_MIRROR="$id"
                printf '%s\n' "$id"
                return 0
            fi
            if [[ "$status" -eq 100 ]]; then
                tls=1
                installer_error TLS_VERIFY_FAILED "Mirror ${id} failed TLS verification."
                [[ -n "$pinned" ]] && return 1
                break
            fi
            if [[ "$status" -eq 101 ]]; then
                security=1
                installer_error MIRROR_SIGNATURE_INVALID "Mirror ${id} failed Ubuntu archive signature verification."
                [[ -n "$pinned" ]] && return 1
                break
            fi
            if ((attempts == 3)); then
                break
            fi
            installer_backoff "$attempts"
            attempts=$((attempts + 1))
        done
    done
    if [[ "$security" -eq 1 ]]; then
        installer_error MIRROR_SIGNATURE_INVALID "Every reachable mirror failed signature verification."
        return 1
    fi
    if [[ "$tls" -eq 1 ]]; then
        installer_error TLS_VERIFY_FAILED "Every reachable mirror failed TLS verification."
        return 1
    fi
    installer_error MIRROR_UNAVAILABLE "No healthy signed mirror was reachable."
    return 1
}

installer_write_apt_sources() {
    local id="$1"
    local codename="$2"
    local dest="$3"
    local archive security
    archive="$(installer_mirror_field "$id" 5)"
    security="$(installer_mirror_field "$id" 6)"
    archive="${archive%/}"
    security="${security%/}"
    cat >"$dest" <<EOF
# rahandaz-managed: apt
Types: deb
URIs: ${archive}
Suites: ${codename} ${codename}-updates ${codename}-backports
Components: main restricted universe multiverse
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg

Types: deb
URIs: ${security}
Suites: ${codename}-security
Components: main restricted universe multiverse
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg
EOF
    # The installer umask is private. APT lists must stay world-readable or command-not-found warns.
    chmod 0644 "$dest"
}
