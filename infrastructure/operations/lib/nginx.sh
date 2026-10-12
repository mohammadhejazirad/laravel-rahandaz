#!/usr/bin/env bash

ops_nginx_install() {
    local plan='{"action":"install","package":"nginx","verification":"untested"}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_fail PACKAGE_UNTESTED "Nginx install has no passing VM test."
}

ops_nginx_site_name() {
    local name
    name="$(ops_require_ident site)"
    printf '%s' "$name"
}

ops_nginx_paths() {
    local name="$1"
    NGINX_AVAILABLE="$OPS_SANDBOX/etc/nginx/sites-available/${name}.conf"
    NGINX_ENABLED="$OPS_SANDBOX/etc/nginx/sites-enabled/${name}.conf"
}

ops_nginx_apply_site() {
    local name content plan
    name="$(ops_nginx_site_name)"
    content="$(ops_require_text config)"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"apply_site","site":sys.argv[1]},separators=(",",":")))' "$name")"
    if [[ "$content" != *"# rahandaz-managed: ${name}"* || "$content" == *".."* || "$content" == *"load_module"* ]]; then
        ops_fail INVALID_INPUT "Nginx config must be a managed site without traversal or load_module."
    fi
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_nginx_paths "$name"
    mkdir -p "$(dirname "$NGINX_AVAILABLE")" "$(dirname "$NGINX_ENABLED")"
    ops_check inside "$OPS_SANDBOX" "$NGINX_AVAILABLE"
    local snapshot temp
    snapshot="$(ops_snapshot_file "$NGINX_AVAILABLE")"
    temp="$(mktemp "$OPS_SANDBOX/nginx.XXXXXX")"
    printf '%s\n' "$content" >"$temp"
    mv "$temp" "$NGINX_AVAILABLE"
    chmod 0644 "$NGINX_AVAILABLE"
    if ! ops_exec nginx -t -c "$NGINX_AVAILABLE"; then
        ops_restore_snapshot "$snapshot" "$NGINX_AVAILABLE"
        ops_fail NGINX_TEST_FAILED "nginx -t failed and the previous file was restored."
    fi
    ops_succeed "$plan" "{\"site\":\"${name}\",\"validated\":true}" true
}

ops_nginx_link() {
    local action="$1"
    local name plan
    name="$(ops_nginx_site_name)"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":sys.argv[1],"site":sys.argv[2]},separators=(",",":")))' "$action" "$name")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_nginx_paths "$name"
    if [[ ! -f "$NGINX_AVAILABLE" || -L "$NGINX_AVAILABLE" ]]; then
        ops_fail INVALID_INPUT "Only an existing managed site can be enabled or disabled."
    fi
    if [[ "$action" == "enable" ]]; then
        ln -s "$NGINX_AVAILABLE" "$NGINX_ENABLED"
    else
        rm -f "$NGINX_ENABLED"
    fi
    ops_succeed "$plan" "{\"site\":\"${name}\"}" true
}

ops_nginx_enable_site() { ops_nginx_link enable; }
ops_nginx_disable_site() { ops_nginx_link disable; }

ops_nginx_remove_site() {
    local name plan
    name="$(ops_nginx_site_name)"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"remove_site","site":sys.argv[1]},separators=(",",":")))' "$name")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_require_approval
    ops_nginx_paths "$name"
    if [[ -f "$NGINX_AVAILABLE" ]] && ! grep -q "# rahandaz-managed: ${name}" "$NGINX_AVAILABLE"; then
        ops_fail INVALID_INPUT "Refusing to remove a site the panel does not own."
    fi
    rm -f "$NGINX_ENABLED" "$NGINX_AVAILABLE"
    ops_succeed "$plan" "{\"site\":\"${name}\",\"removed\":true}" true
}

ops_nginx_validate() {
    local plan='{"action":"validate"}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    local name
    name="$(ops_nginx_site_name)"
    ops_nginx_paths "$name"
    if ! ops_exec nginx -t -c "$NGINX_AVAILABLE"; then
        ops_fail NGINX_TEST_FAILED "nginx -t failed."
    fi
    ops_succeed "$plan" '{"validated":true}' false
}

ops_nginx_reload() {
    local plan='{"action":"reload","unit":"nginx"}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    local name
    name="$(ops_nginx_site_name)"
    ops_nginx_paths "$name"
    if ! ops_exec nginx -t -c "$NGINX_AVAILABLE"; then
        ops_fail NGINX_TEST_FAILED "Reload was not attempted because nginx -t failed."
    fi
    ops_exec systemctl reload nginx
    ops_succeed "$plan" '{"reloaded":true}' true
}

ops_nginx_rollback() {
    local name plan
    name="$(ops_nginx_site_name)"
    plan='{"action":"rollback"}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_require_approval
    ops_nginx_paths "$name"
    ops_restore_snapshot "$OPS_SANDBOX/var/lib/rahandaz/snapshots/${OPS_EXECUTION_ID}" "$NGINX_AVAILABLE"
    ops_succeed "$plan" '{"restored":true}' true
}
