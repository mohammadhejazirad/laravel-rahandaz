#!/usr/bin/env bash

ops_automation_run_custom() {
    local uid script plan
    uid="$(ops_require_text uid)"
    ops_reject_root_uid "$uid"
    if [[ ! "$uid" =~ ^[1-9][0-9]{0,8}$ ]]; then
        ops_fail INVALID_INPUT "uid is not allowed."
    fi
    script="$(ops_require_text script_path)"
    if [[ "$script" == *".."* || "$script" == /etc/* || "$script" == /usr/* ]]; then
        ops_fail PATH_ESCAPE "Custom script path is not allowed."
    fi
    local timeout
    timeout="$(ops_require_text timeout_seconds)"
    if [[ ! "$timeout" =~ ^[0-9]+$ || "$timeout" -lt 1 || "$timeout" -gt 300 ]]; then
        ops_fail INVALID_INPUT "Custom script timeout must be between 1 and 300 seconds."
    fi
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"custom_script","uid":int(sys.argv[1]),"root":False,"sudo":False,"helper":False,"timeout_seconds":int(sys.argv[2])},separators=(",",":")))' "$uid" "$timeout")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    if [[ "${OPS_PRIVILEGE}" != "project" ]]; then
        ops_fail ROOT_SCRIPT_FORBIDDEN "Custom scripts stay on the project privilege level."
    fi
    local target
    target="$(ops_py_value inside "$OPS_SANDBOX" "$script")"
    if [[ ! -f "$target" || -L "$target" ]]; then
        ops_fail INVALID_INPUT "Custom script must be a regular file inside the sandbox."
    fi
    ops_exec systemd-run --uid="$uid" --property=CPUQuota=50% --property=MemoryMax=256M --property=TasksMax=64 --property=RuntimeMaxSec="$timeout" --property=NoNewPrivileges=yes "$target"
    ops_succeed "$plan" '{"privileged_helper":false}' true
}

ops_automation_refuse_cron() {
    ops_fail CRON_WRITE_FORBIDDEN "System crontab is not a scheduler for custom scripts."
}
