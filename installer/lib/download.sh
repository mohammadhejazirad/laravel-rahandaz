#!/usr/bin/env bash
# HTTPS downloads with resume. TLS verification stays on. Insecure TLS is not a fallback.

if [[ -n "${INSTALLER_LIB_DOWNLOAD:-}" ]]; then
    return 0
fi
INSTALLER_LIB_DOWNLOAD=1

installer_curl() {
    if [[ -n "${INSTALLER_CURL:-}" ]]; then
        "$INSTALLER_CURL" "$@"
        return $?
    fi
    command curl "$@"
}

installer_download() {
    local url="$1"
    local dest="$2"
    local attempt=1
    local status
    installer_url_ok "$url" || {
        installer_error URL_REJECTED "Download URL is not an https URL without credentials."
        return 100
    }
    mkdir -p "$(dirname "$dest")"
    while ((attempt <= 3)); do
        set +e
        installer_curl --proto '=https' --proto-redir '=https' --tlsv1.2 \
            --fail --location --show-error --silent \
            --retry 0 \
            --continue-at - \
            --max-time 120 \
            --output "$dest" \
            "$url"
        status=$?
        set -e
        if [[ "$status" -eq 0 ]]; then
            return 0
        fi
        # curl TLS failures are 35 and 60. Do not retry into an insecure mode.
        if [[ "$status" -eq 35 || "$status" -eq 60 ]]; then
            installer_error TLS_VERIFY_FAILED "TLS verification failed for the download."
            return 100
        fi
        if ((attempt == 3)); then
            installer_error DOWNLOAD_FAILED "Download failed after 3 attempts."
            return 1
        fi
        installer_backoff "$attempt"
        attempt=$((attempt + 1))
    done
    return 1
}
