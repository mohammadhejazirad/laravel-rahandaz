#!/usr/bin/env bash

ops_snapshot_file() {
    local source="$1"
    local dest_dir="$OPS_SANDBOX/var/lib/rahandaz/snapshots/${OPS_EXECUTION_ID}"
    mkdir -p "$dest_dir"
    chmod 0700 "$dest_dir"
    if [[ -f "$source" && ! -L "$source" ]]; then
        cp -p "$source" "$dest_dir/$(basename "$source")"
    fi
    printf '%s' "$dest_dir"
}

ops_restore_snapshot() {
    local dest_dir="$1"
    local target="$2"
    local saved="$dest_dir/$(basename "$target")"
    if [[ -f "$saved" && ! -L "$saved" ]]; then
        cp -p "$saved" "$target"
    elif [[ -f "$target" ]]; then
        rm -f "$target"
    fi
}
