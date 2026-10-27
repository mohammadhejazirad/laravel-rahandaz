#!/usr/bin/env bash
# PHP 8.4 for the Rahandaz panel only. Other PHP versions on the server stay installed.

if [[ -n "${INSTALLER_LIB_PHP_RUNTIME:-}" ]]; then
    return 0
fi
INSTALLER_LIB_PHP_RUNTIME=1

INSTALLER_PHP_SERIES="8.4"
INSTALLER_PHP_BIN="/usr/bin/php8.4"
INSTALLER_PHP_FPM_BIN="/usr/sbin/php-fpm8.4"
INSTALLER_PHP_FINGERPRINT="15058500A0235D97F5D10063B188E2B695BD4743"
INSTALLER_PHP_ORIGIN="packages.sury.org"
INSTALLER_PHP_REPO="https://packages.sury.org/php"
INSTALLER_PHP_PACKAGES=(
    php8.4-common
    php8.4-cli
    php8.4-fpm
    php8.4-pgsql
    php8.4-mbstring
    php8.4-xml
    php8.4-curl
    php8.4-zip
    php8.4-bcmath
    php8.4-intl
    php8.4-gd
    php8.4-opcache
    php8.4-readline
    php8.4-redis
)

# php8.4-* does not match php-common. Ubuntu's copy is too old on 22.04/24.04 and
# Breaks third-party php8.4-common on 26.04. jammy's libgd3 is below the GD requirement.
# Every other package from this origin stays blocked.
installer_php_allowed_packages() {
    local allowed='php8.4-* php-common'
    if [[ "${INSTALLER_OS_CODENAME:-}" == jammy ]]; then
        allowed+=' libgd3'
    fi
    printf '%s\n' "$allowed"
}

installer_php_key_fingerprint() {
    local file="$1"
    [[ -f "$file" && ! -L "$file" ]] || return 1
    if grep -q 'PRIVATE KEY' "$file"; then
        return 1
    fi
    gpg --show-keys --with-colons "$file" 2>/dev/null | awk -F: '$1 == "fpr" { print $10; exit }'
}

installer_php_key_ok() {
    local file="${INSTALLER_HOME}/trust/sury-php.asc"
    local pinned="${INSTALLER_HOME}/trust/sury-php.fingerprint"
    local recorded actual
    if [[ ! -f "$file" || -L "$file" || ! -s "$file" || ! -f "$pinned" || -L "$pinned" ]]; then
        installer_error PHP84_KEY_UNVERIFIED "The PHP repository key could not be read."
        return 1
    fi
    if grep -q 'PRIVATE KEY' "$file"; then
        installer_error PHP84_KEY_UNVERIFIED "The PHP repository key file is not a public key."
        return 1
    fi
    recorded="$(tr -d '[:space:]' <"$pinned")"
    if [[ "$recorded" != "$INSTALLER_PHP_FINGERPRINT" ]]; then
        installer_error PHP84_KEY_UNVERIFIED "The PHP repository fingerprint pin does not match."
        return 1
    fi
    if command -v gpg >/dev/null 2>&1; then
        actual="$(installer_php_key_fingerprint "$file")" || {
            installer_error PHP84_KEY_UNVERIFIED "The PHP repository key could not be parsed."
            return 1
        }
        if [[ "$actual" != "$INSTALLER_PHP_FINGERPRINT" ]]; then
            installer_error PHP84_KEY_UNVERIFIED "The PHP repository key fingerprint does not match the pin."
            return 1
        fi
        return 0
    fi
    if [[ "${MODE:-}" == "plan" ]]; then
        installer_say "WARN gpg is not available. The pinned PHP fingerprint matches, and the key was not parsed."
        return 0
    fi
    installer_error PHP84_KEY_UNVERIFIED "gpg is required to verify the PHP repository key."
    return 1
}

installer_write_php_repository() {
    local root codename keyring source prefs
    root="$(installer_sysroot)"
    codename="${INSTALLER_OS_CODENAME:-}"
    case "$codename" in
        jammy | noble | resolute) ;;
        *)
            installer_error PHP84_REPOSITORY_UNAVAILABLE "PHP 8.4 has no verified repository suite for this Ubuntu release."
            return 1
            ;;
    esac
    installer_php_key_ok || return 1
    keyring="$(installer_root_path /usr/share/keyrings/rahandaz-php-sury.gpg)"
    source="$(installer_root_path /etc/apt/sources.list.d/rahandaz-php.sources)"
    prefs="$(installer_root_path /etc/apt/preferences.d/rahandaz-php)"
    mkdir -p "$(dirname "$keyring")" "$(dirname "$source")" "$(dirname "$prefs")"
    if [[ -e "$keyring" && -L "$keyring" ]]; then
        installer_error PHP84_KEY_UNVERIFIED "The PHP keyring path is a symlink."
        return 1
    fi
    gpg --dearmor --yes --output "$keyring" "${INSTALLER_HOME}/trust/sury-php.asc"
    chmod 0644 "$keyring"
    # Signed-By is the path inside the target root. The file itself is written under the sysroot.
    cat >"$source" <<EOF
Types: deb
URIs: ${INSTALLER_PHP_REPO}
Suites: ${codename}
Components: main
Signed-By: /usr/share/keyrings/rahandaz-php-sury.gpg
EOF
    cat >"$prefs" <<EOF
Package: $(installer_php_allowed_packages)
Pin: origin ${INSTALLER_PHP_ORIGIN}
Pin-Priority: 500

Package: *
Pin: origin ${INSTALLER_PHP_ORIGIN}
Pin-Priority: -1
EOF
    if grep -E -q 'trusted=yes|allow-unauthenticated|Acquire::https::Verify-Peer false' "$source" "$prefs"; then
        rm -f "$source" "$prefs"
        installer_error PHP84_KEY_UNVERIFIED "Refusing a PHP source that disables authentication."
        return 1
    fi
    # command-not-found reads these as the calling user. A private umask must not hide them.
    chmod 0644 "$source" "$prefs"
    return 0
}

installer_php84_binary_ok() {
    [[ -x "$INSTALLER_PHP_BIN" && ! -L "$INSTALLER_PHP_BIN" ]] || return 1
    "$INSTALLER_PHP_BIN" -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 ? 0 : 1);'
}

installer_write_release_record() {
    local version="$1"
    local channel="$2"
    local file
    file="$(installer_root_path /var/lib/rahandaz/release.json)"
    mkdir -p "$(dirname "$file")"
    python3 - "$file" "$version" "$channel" "${INSTALLER_HOME}/VERSION" <<'PY'
import json, pathlib, sys
path, version, channel, installer_version = sys.argv[1:]
installer_version = pathlib.Path(installer_version).read_text(encoding="utf-8").strip()
payload = {
    "product": "Rahandaz",
    "version": version,
    "channel": channel,
    "release_id": version,
    "git_commit": "",
    "installed_at": "",
    "updated_at": "",
    "manifest_digest": "",
    "schema_version": "forward",
    "installer_version": installer_version,
    "updater_version": installer_version,
    "php": "8.4",
}
pathlib.Path(path).write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
PY
    chmod 0644 "$file"
}
