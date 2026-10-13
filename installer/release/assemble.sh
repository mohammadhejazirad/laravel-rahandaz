#!/usr/bin/env bash
# Pack an already built application tree. Do not include .env or a private key.
set -euo pipefail

from=""
version=""
output=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --from) from="$2"; shift ;;
        --version) version="$2"; shift ;;
        --output) output="$2"; shift ;;
        *)
            printf '%s\n' "Unknown argument." >&2
            exit 2
            ;;
    esac
    shift
done

if [[ -z "$from" || -z "$version" || -z "$output" ]]; then
    printf '%s\n' "Source, version, and output are required." >&2
    exit 2
fi
if [[ -e "$from/.env" ]]; then
    printf '%s\n' "Refusing to pack a tree that contains .env." >&2
    exit 1
fi
if [[ ! -f "$from/artisan" || ! -f "$from/composer.lock" || ! -f "$from/public/build/manifest.json" ]]; then
    printf '%s\n' "The tree is missing artisan, composer.lock, or built assets." >&2
    exit 1
fi
if find "$from" \( -name '*.pem' -o -name 'id_rsa' -o -name 'master.key' \) | grep -q .; then
    printf '%s\n' "Refusing to pack a private key." >&2
    exit 1
fi

name="rahandaz-${version}.tar.gz"
mkdir -p "$output"
tar -C "$(dirname "$from")" -czf "$output/$name" "$(basename "$from")"
printf '%s\n' "$output/$name"
