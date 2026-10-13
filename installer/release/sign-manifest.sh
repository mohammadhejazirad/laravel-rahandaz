#!/usr/bin/env bash
# Sign a release manifest with a key the operator already holds.
# This script does not generate a project key and does not publish a release.
set -euo pipefail

key=""
artifact=""
version=""
output=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --key) key="$2"; shift ;;
        --artifact) artifact="$2"; shift ;;
        --version) version="$2"; shift ;;
        --output) output="$2"; shift ;;
        *)
            printf '%s\n' "Unknown argument." >&2
            exit 2
            ;;
    esac
    shift
done

if [[ -z "$key" || -z "$artifact" || -z "$version" || -z "$output" ]]; then
    printf '%s\n' "Key, artifact, version, and output are required." >&2
    exit 2
fi
if [[ "$key" == *"/installer/trust/"* ]]; then
    printf '%s\n' "Refusing to sign with a key stored in installer/trust." >&2
    exit 1
fi
if [[ ! -f "$key" || -L "$key" || ! -f "$artifact" || -L "$artifact" ]]; then
    printf '%s\n' "Key and artifact must be regular files." >&2
    exit 1
fi
if [[ ! "$version" =~ ^[0-9A-Za-z][0-9A-Za-z._-]{0,63}$ ]]; then
    printf '%s\n' "Version is invalid." >&2
    exit 1
fi

name="$(basename "$artifact")"
digest="$(sha256sum "$artifact" | awk '{ print $1 }')"
mkdir -p "$output"
manifest="$output/release.manifest"
printf '%s\n' \
    "rahandaz-release-manifest 1" \
    "version=${version}" \
    "artifact=${name}" \
    "sha256=${digest}" >"$manifest"
cp -a "$artifact" "$output/$name"
openssl dgst -sha256 -sign "$key" -out "$output/release.manifest.sig" "$manifest"
printf '%s\n' "signed ${version}"
