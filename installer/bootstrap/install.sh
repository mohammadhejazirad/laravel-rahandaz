#!/usr/bin/env bash
# Verify a local or downloaded release, then exec the installer. Nothing is piped to a shell.
set -euo pipefail

INSTALLER_HOME="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=../lib/common.sh
source "${INSTALLER_HOME}/lib/common.sh"
# shellcheck source=../lib/download.sh
source "${INSTALLER_HOME}/lib/download.sh"

manifest=""
signature=""
public_key=""
artifact=""
dest=""
url_manifest=""
url_signature=""
url_artifact=""
forward=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --manifest) manifest="$2"; shift ;;
        --signature) signature="$2"; shift ;;
        --public-key) public_key="$2"; shift ;;
        --artifact) artifact="$2"; shift ;;
        --dest) dest="$2"; shift ;;
        --url-manifest) url_manifest="$2"; shift ;;
        --url-signature) url_signature="$2"; shift ;;
        --url-artifact) url_artifact="$2"; shift ;;
        --) shift; forward+=("$@"); break ;;
        *) forward+=("$1") ;;
    esac
    shift
done

if [[ -n "$url_manifest" || -n "$url_signature" || -n "$url_artifact" ]]; then
    [[ -n "$dest" ]] || dest="$(mktemp -d /tmp/rahandaz-release.XXXXXX)"
    bash "${INSTALLER_HOME}/bootstrap/download-release.sh" \
        --url-manifest "$url_manifest" \
        --url-signature "$url_signature" \
        --url-artifact "$url_artifact" \
        --dest "$dest"
    manifest="$dest/release.manifest"
    signature="$dest/release.manifest.sig"
    artifact="$dest/$(basename "$url_artifact")"
fi

bash "${INSTALLER_HOME}/bootstrap/verify-release.sh" \
    --manifest "$manifest" \
    --signature "$signature" \
    --public-key "$public_key" \
    --artifact "$artifact"

exec bash "${INSTALLER_HOME}/bin/rahandaz-install" \
    --manifest "$manifest" \
    --signature "$signature" \
    --public-key "$public_key" \
    --artifact "$artifact" \
    "${forward[@]}"
