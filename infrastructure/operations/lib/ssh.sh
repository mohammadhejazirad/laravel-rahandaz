#!/usr/bin/env bash

ops_ssh_install_server() {
    local package="openssh"
    local plan='{"action":"install","package":"openssh","stop":false}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_fail PACKAGE_UNTESTED "OpenSSH install is untested and sshd must not be stopped."
}

ops_ssh_key_file() {
    local path value
    path="$OPS_SANDBOX/keys/provided.pub"
    mkdir -p "$OPS_SANDBOX/keys"
    chmod 0700 "$OPS_SANDBOX/keys"
    value="$(ops_require_text public_key)"
    if [[ "$value" == *"PRIVATE KEY"* ]]; then
        ops_fail PRIVATE_KEY_REJECTED "A private key was rejected."
    fi
    printf '%s\n' "$value" >"$path"
    chmod 0600 "$path"
    printf '%s' "$path"
}

ops_ssh_install_key() {
    local plan='{"action":"install_public_key","stores_private_key":false}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    local file fingerprint account dest
    file="$(ops_ssh_key_file)"
    fingerprint="$(ops_py_value fingerprint "$file")"
    account="$(ops_require_project_user)"
    dest="$OPS_SANDBOX/home/$account/.ssh/authorized_keys"
    mkdir -p "$(dirname "$dest")"
    chmod 0700 "$(dirname "$dest")"
    if [[ -f "$dest" ]] && grep -Fqx "$fingerprint" "$OPS_SANDBOX/home/$account/.ssh/fingerprints"; then
        ops_succeed "$plan" "{\"fingerprint\":\"${fingerprint}\",\"duplicate\":true}" false
    fi
    printf '%s\n' "$(tr -d '\r' <"$file")" >>"$dest"
    chmod 0600 "$dest"
    printf '%s\n' "$fingerprint" >>"$OPS_SANDBOX/home/$account/.ssh/fingerprints"
    chmod 0600 "$OPS_SANDBOX/home/$account/.ssh/fingerprints"
    ops_succeed "$plan" "{\"fingerprint\":\"${fingerprint}\",\"duplicate\":false}" true
}

ops_ssh_verify_key() {
    local plan='{"action":"verify_public_key"}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    local file fingerprint
    file="$(ops_ssh_key_file)"
    fingerprint="$(ops_py_value fingerprint "$file")"
    ops_succeed "$plan" "{\"fingerprint\":\"${fingerprint}\"}" false
}

ops_ssh_harden() {
    local plan='{"action":"harden_ssh","order":["key","sshd -t","reload","second connection","recovery","disable password","restrict root"],"stop":false}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    if ! ops_flag recovery_verified || ! ops_flag second_connection_verified; then
        ops_fail SSH_RECOVERY_REQUIRED "SSH hardening stops until a second connection and recovery path are verified."
    fi
    local config="$OPS_SANDBOX/etc/ssh/sshd_config.d/rahandaz.conf"
    mkdir -p "$(dirname "$config")"
    local snapshot
    snapshot="$(ops_snapshot_file "$config")"
    cat >"$config" <<'EOF'
# rahandaz-managed: ssh
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
EOF
    if ! ops_exec sshd -t -f "$config"; then
        ops_restore_snapshot "$snapshot" "$config"
        ops_fail VERIFICATION_FAILED "sshd -t failed. The previous file was restored."
    fi
    ops_exec systemctl reload ssh
    ops_succeed "$plan" '{"password_authentication":false,"permit_root_login":false,"sshd_stopped":false}' true
}

ops_ssh_rollback() {
    local plan='{"action":"rollback_ssh"}'
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_require_approval
    local config="$OPS_SANDBOX/etc/ssh/sshd_config.d/rahandaz.conf"
    local snapshot="$OPS_SANDBOX/var/lib/rahandaz/snapshots/${OPS_EXECUTION_ID}"
    ops_restore_snapshot "$snapshot" "$config"
    ops_succeed "$plan" '{"restored":true,"sshd_stopped":false}' true
}
