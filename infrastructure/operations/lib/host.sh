#!/usr/bin/env bash

ops_host_facts() {
    local observed plan
    observed="$(ops_read_os)"
    plan='{"action":"facts","mutates":false}'
    ops_succeed "$plan" "$observed" false
}

ops_host_resources() {
    ops_preview '{"action":"resources","source":"proc"}' '{"compatibility":"untested"}'
}

ops_host_network() {
    ops_preview '{"action":"network"}' '{"compatibility":"untested"}'
}

ops_host_services() {
    ops_preview '{"action":"service_inventory"}' '{"compatibility":"untested"}'
}

ops_host_health() {
    ops_preview '{"action":"health"}' '{"compatibility":"untested"}'
}

ops_host_updates_preview() {
    ops_preview '{"action":"updates_preview","apt_upgrade":false}' '{"compatibility":"untested"}'
}

ops_host_reboot_required() {
    ops_preview '{"action":"reboot_required_check","reboot":false}' '{"reboot_executed":false}'
}

ops_host_reboot_request() {
    local plan='{"action":"reboot","executed":false}'
    local key
    for key in owner_approval reauthentication active_jobs_idle deployment_idle backup_checked recovery_plan; do
        if ! ops_flag "$key"; then
            ops_fail APPROVAL_REQUIRED "Reboot is missing ${key}."
        fi
    done
    ops_fail REBOOT_NOT_EXECUTED "Reboot was not executed. Final state requires a later health check after reconnect."
}
