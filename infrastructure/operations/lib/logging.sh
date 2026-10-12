#!/usr/bin/env bash

ops_diag() {
    local text="$1"
    text="$(printf '%s' "$text" | python3 -c 'import re,sys; t=sys.stdin.read(); t=re.sub(r"(?i)(password|secret|token)\s*[=:]\s*\S+","\\1=[redacted]",t); t=re.sub(r"PRIVATE KEY[\\s\\S]*","[redacted private key]",t); sys.stdout.write(t)')"
    printf 'rahandaz-ops: %s\n' "$text" >&2
}
