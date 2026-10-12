#!/usr/bin/env bash

ops_backup_engine() {
    local engine="$1"
    ops_db_backup "$engine"
}

ops_restore_engine() {
    local engine="$1"
    ops_db_restore "$engine"
}
