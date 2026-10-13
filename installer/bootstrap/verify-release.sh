#!/usr/bin/env bash
# Verify a release manifest and artifact. This script does not execute the artifact.
set -euo pipefail

INSTALLER_HOME="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=../lib/common.sh
source "${INSTALLER_HOME}/lib/common.sh"
# shellcheck source=../lib/verify.sh
source "${INSTALLER_HOME}/lib/verify.sh"

manifest=""
signature=""
public_key=""
artifact=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --manifest) manifest="$2"; shift ;;
        --signature) signature="$2"; shift ;;
        --public-key) public_key="$2"; shift ;;
        --artifact) artifact="$2"; shift ;;
        *)
            installer_error INVALID_INPUT "Unknown argument."
            exit 2
            ;;
    esac
    shift
done

key="$(installer_public_key "$public_key")" || exit 1
installer_verify_manifest "$manifest" "$signature" "$key" "$artifact" || exit 1
printf '%s\n' "verified ${INSTALLER_RELEASE_VERSION}"
