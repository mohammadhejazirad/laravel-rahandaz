#!/usr/bin/env bash

ops_local_health_url() {
    local url
    url="$(ops_require_text health_url)"
    case "$url" in
        http://127.0.0.1:*/* | http://127.0.0.1:* )
            printf '%s' "$url"
            ;;
        *)
            ops_fail INVALID_INPUT "Health checks must use the loopback HTTP address."
            ;;
    esac
}
