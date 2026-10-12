#!/usr/bin/env bash

ops_account_plan() {
    python3 -c 'import json,sys; print(json.dumps({"action":sys.argv[1],"account":sys.argv[2],"shell":"/usr/sbin/nologin","groups":[],"sudo":False},separators=(",",":")))' "$1" "$2"
}

ops_account_create_rahandaz() {
    local plan
    plan="$(ops_account_plan create rahandaz)"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    local home="$OPS_SANDBOX/var/lib/rahandaz"
    local secret="$OPS_SANDBOX/root/rahandaz-account.secret"
    mkdir -p "$OPS_SANDBOX/root" "$home"
    chmod 0700 "$OPS_SANDBOX/root"
    if [[ ! -e "$OPS_SANDBOX/var/lib/rahandaz/account.stamp" ]]; then
        ops_exec useradd --create-home --home-dir "$home" --shell /usr/sbin/nologin --user-group --comment "Rahandaz orchestration" rahandaz
        ops_py_value password-file "$secret" >/dev/null
        ops_exec passwd --lock rahandaz >/dev/null
        printf '%s\n' 'created' >"$OPS_SANDBOX/var/lib/rahandaz/account.stamp"
        chmod 0600 "$OPS_SANDBOX/var/lib/rahandaz/account.stamp"
    fi
    ops_succeed "$plan" '{"account":"rahandaz","login":"locked","sudo":false}' true
}

ops_account_verify_rahandaz() {
    local plan
    plan="$(ops_account_plan verify rahandaz)"
    if ! ops_wants_apply; then
        ops_preview "$plan" '{"compatibility":"untested"}'
    fi
    ops_require_sandbox_apply
    if [[ ! -f "$OPS_SANDBOX/var/lib/rahandaz/account.stamp" ]]; then
        ops_fail VERIFICATION_FAILED "The rahandaz account stamp was not observed."
    fi
    ops_succeed "$plan" '{"account":"rahandaz","observed":true}' false
}

ops_account_rotate_password() {
    local plan secret
    plan="$(ops_account_plan rotate rahandaz)"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_require_approval
    secret="$OPS_SANDBOX/root/rahandaz-account-rotated.secret"
    mkdir -p "$OPS_SANDBOX/root"
    chmod 0700 "$OPS_SANDBOX/root"
    ops_py_value password-file "$secret" >/dev/null
    ops_exec passwd --lock rahandaz >/dev/null
    ops_succeed "$plan" '{"account":"rahandaz","login":"locked"}' true
}

ops_account_create_project() {
    local user plan home
    user="$(ops_require_project_user)"
    plan="$(ops_account_plan create "$user")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    home="$OPS_SANDBOX/var/lib/rahandaz/projects/$user"
    mkdir -p "$home"
    chmod 0750 "$home"
    ops_exec useradd --create-home --home-dir "$home" --shell /usr/sbin/nologin --user-group "$user"
    ops_succeed "$plan" "{\"account\":\"${user}\",\"sudo\":false}" true
}
