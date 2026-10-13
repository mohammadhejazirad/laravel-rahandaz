#!/usr/bin/env bash

ops_ssl_plan() {
    python3 -c 'import json,sys; print(json.dumps({"action":sys.argv[1],"domain":sys.argv[2],"tool":"certbot","verification":"untested"},separators=(",",":")))' "$1" "$2"
}

ops_ssl_issue() {
    local domain plan
    domain="$(ops_require_domain)"
    plan="$(ops_ssl_plan issue "$domain")"
    if ! ops_flag dns_checked; then
        ops_fail PRECONDITION_FAILED "DNS was not checked."
    fi
    local method
    method="$(ops_require_text challenge)"
    case "$method" in
        http-01 | dns-01) ;;
        *) ops_fail INVALID_INPUT "Challenge method is not supported." ;;
    esac
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_fail CERTIFICATE_UNTESTED "Certificate issuance has no passing VM test."
}

ops_ssl_renew() { ops_ssl_issue; }
ops_ssl_install() { ops_ssl_issue; }
ops_ssl_assign() { ops_ssl_issue; }

ops_ssl_verify() {
    local domain plan
    domain="$(ops_require_domain)"
    plan="$(ops_ssl_plan verify "$domain")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_fail CERTIFICATE_UNTESTED "Certificate verification has no passing VM test."
}

ops_ssl_remove() {
    local domain plan
    domain="$(ops_require_domain)"
    plan="$(ops_ssl_plan remove "$domain")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_require_approval
    if ! ops_flag dependency_checked; then
        ops_fail PRECONDITION_FAILED "A shared certificate cannot be removed before a dependency check."
    fi
    local path="$OPS_SANDBOX/etc/rahandaz/certs/$domain"
    ops_sandbox_path "$path" >/dev/null
    rm -rf "$path"
    ops_succeed "$plan" '{"private_key_logged":false,"revocation":"not-requested"}' true
}
