#!/usr/bin/env bash
# Download a release over HTTPS. The caller verifies it before execution.
set -euo pipefail

INSTALLER_HOME="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=../lib/common.sh
source "${INSTALLER_HOME}/lib/common.sh"
# shellcheck source=../lib/download.sh
source "${INSTALLER_HOME}/lib/download.sh"

url_manifest=""
url_signature=""
url_artifact=""
dest=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --url-manifest) url_manifest="$2"; shift ;;
        --url-signature) url_signature="$2"; shift ;;
        --url-artifact) url_artifact="$2"; shift ;;
        --dest) dest="$2"; shift ;;
        *)
            installer_error INVALID_INPUT "Unknown argument."
            exit 2
            ;;
    esac
    shift
done

if [[ -z "$url_manifest" || -z "$url_signature" || -z "$url_artifact" || -z "$dest" ]]; then
    installer_error INVALID_INPUT "Manifest, signature, artifact, and destination are required."
    exit 2
fi

mkdir -p "$dest"
installer_download "$url_manifest" "$dest/release.manifest" || exit 1
installer_download "$url_signature" "$dest/release.manifest.sig" || exit 1
installer_download "$url_artifact" "$dest/$(basename "$url_artifact")" || exit 1
printf '%s\n' "downloaded"
