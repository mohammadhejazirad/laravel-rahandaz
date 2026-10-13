#!/usr/bin/env bash
# Release manifest verification. The signed bytes are the manifest file itself.

if [[ -n "${INSTALLER_LIB_VERIFY:-}" ]]; then
    return 0
fi
INSTALLER_LIB_VERIFY=1

installer_public_key() {
    local key="${1:-}"
    if [[ -z "$key" ]]; then
        key="${INSTALLER_HOME}/trust/rahandaz-release.pub"
    fi
    if [[ ! -f "$key" || -L "$key" || ! -s "$key" ]]; then
        installer_error TRUST_ROOT_MISSING "No release public key is available."
        return 1
    fi
    if grep -q 'EXAMPLE' "$key"; then
        installer_error TRUST_ROOT_MISSING "The public key file is a placeholder."
        return 1
    fi
    printf '%s\n' "$key"
}

installer_verify_manifest() {
    local manifest="$1"
    local signature="$2"
    local pubkey="$3"
    local artifact="$4"
    local line version name digest actual
    [[ -f "$manifest" && ! -L "$manifest" ]] || {
        installer_error UNVERIFIED_ARTIFACT "Manifest is missing."
        return 1
    }
    [[ -f "$signature" && ! -L "$signature" ]] || {
        installer_error SIGNATURE_INVALID "Signature file is missing."
        return 1
    }
    if ! openssl dgst -sha256 -verify "$pubkey" -signature "$signature" "$manifest" >/dev/null 2>&1; then
        installer_error SIGNATURE_INVALID "Release manifest signature did not verify."
        return 1
    fi
    mapfile -t lines <"$manifest"
    [[ "${#lines[@]}" -eq 4 ]] || {
        installer_error UNVERIFIED_ARTIFACT "Manifest must be exactly four lines."
        return 1
    }
    [[ "${lines[0]}" == "rahandaz-release-manifest 1" ]] || {
        installer_error UNVERIFIED_ARTIFACT "Manifest version header is not supported."
        return 1
    }
    [[ "${lines[1]}" =~ ^version=[0-9A-Za-z][0-9A-Za-z._-]{0,63}$ ]] || {
        installer_error UNVERIFIED_ARTIFACT "Manifest version is invalid."
        return 1
    }
    [[ "${lines[2]}" =~ ^artifact=[A-Za-z0-9._-]+$ ]] || {
        installer_error UNVERIFIED_ARTIFACT "Manifest artifact name is invalid."
        return 1
    }
    [[ "${lines[3]}" =~ ^sha256=[0-9a-f]{64}$ ]] || {
        installer_error UNVERIFIED_ARTIFACT "Manifest checksum is invalid."
        return 1
    }
    version="${lines[1]#version=}"
    name="${lines[2]#artifact=}"
    digest="${lines[3]#sha256=}"
    if [[ "$(basename "$artifact")" != "$name" ]]; then
        installer_error CHECKSUM_MISMATCH "Artifact file name does not match the signed manifest."
        return 1
    fi
    if [[ ! -f "$artifact" || -L "$artifact" ]]; then
        installer_error UNVERIFIED_ARTIFACT "Artifact file is missing."
        return 1
    fi
    actual="$(sha256sum "$artifact" | awk '{print $1}')"
    if [[ "$actual" != "$digest" ]]; then
        installer_error CHECKSUM_MISMATCH "Artifact checksum does not match the signed manifest."
        rm -f "$artifact"
        return 1
    fi
    INSTALLER_RELEASE_VERSION="$version"
    INSTALLER_RELEASE_ARTIFACT_NAME="$name"
    return 0
}
