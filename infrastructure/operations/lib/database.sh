#!/usr/bin/env bash

ops_db_name() {
    local engine="$1"
    local name
    name="$(ops_require_ident database)"
    ops_check protected-database "$engine" "$name"
    printf '%s' "$name"
}

ops_db_plan() {
    python3 -c 'import json,sys; print(json.dumps({"engine":sys.argv[1],"action":sys.argv[2],"database":sys.argv[3] or None},separators=(",",":")))' "$1" "$2" "${3:-}"
}

ops_db_sql_file() {
    local body="$1"
    local file="$OPS_SANDBOX/sql/${OPS_EXECUTION_ID}.sql"
    mkdir -p "$OPS_SANDBOX/sql"
    chmod 0700 "$OPS_SANDBOX/sql"
    printf '%s\n' "$body" >"$file"
    chmod 0600 "$file"
    printf '%s' "$file"
}

ops_db_client() {
    case "$1" in
        postgresql) printf 'psql' ;;
        mysql) printf 'mysql' ;;
        mariadb) printf 'mariadb' ;;
        mongodb) printf 'mongosh' ;;
        *) ops_fail INVALID_INPUT "Unknown database engine." ;;
    esac
}

ops_db_apply_sql() {
    local engine="$1"
    local sql="$2"
    local client file
    client="$(ops_db_client "$engine")"
    file="$(ops_db_sql_file "$sql")"
    case "$engine" in
        postgresql)
            ops_exec "$client" --no-psqlrc -v ON_ERROR_STOP=1 -d postgres -f "$file"
            ;;
        mysql | mariadb)
            ops_exec "$client" --protocol=socket --batch -e "source ${file}"
            ;;
        mongodb)
            ops_exec "$client" --quiet --file "$file"
            ;;
    esac
    rm -f "$file"
}

ops_db_mutate() {
    local engine="$1"
    local action="$2"
    local name="${3:-}"
    local sql="$4"
    local plan
    plan="$(ops_db_plan "$engine" "$action" "$name")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    if [[ "$action" == "drop_database" || "$action" == "drop_role" || "$action" == "restore" ]]; then
        ops_require_approval
        if ! ops_flag backup_verified; then
            ops_fail APPROVAL_REQUIRED "A verified backup is required before this operation."
        fi
    fi
    ops_db_apply_sql "$engine" "$sql"
    ops_succeed "$plan" "{\"engine\":\"${engine}\",\"verified\":true}" true
}

ops_db_create_database() {
    local engine="$1"
    local name quoted
    name="$(ops_db_name "$engine")"
    quoted="$(ops_py_value quote-ident "$name")"
    case "$engine" in
        postgresql) ops_db_mutate "$engine" create_database "$name" "CREATE DATABASE ${quoted};" ;;
        mysql | mariadb) ops_db_mutate "$engine" create_database "$name" "CREATE DATABASE ${quoted};" ;;
        mongodb) ops_db_mutate "$engine" create_database "$name" "use ${name}; db.createCollection(\"meta\");" ;;
    esac
}

ops_db_create_role() {
    local engine="$1"
    local role
    role="$(ops_require_ident role)"
    if [[ "$role" == "rahandaz" || "$role" == "postgres" || "$role" == "root" ]]; then
        ops_fail PROTECTED_DATABASE "That role name is reserved."
    fi
    local quoted
    quoted="$(ops_py_value quote-ident "$role")"
    case "$engine" in
        postgresql) ops_db_mutate "$engine" create_role "$role" "CREATE ROLE ${quoted} LOGIN;" ;;
        mysql | mariadb) ops_db_mutate "$engine" create_role "$role" "CREATE USER ${quoted}@'localhost';" ;;
        mongodb) ops_db_mutate "$engine" create_role "$role" "db.getSiblingDB(\"admin\").createUser({user:\"${role}\",roles:[]});" ;;
    esac
}

ops_db_change_password() {
    local engine="$1"
    local role material
    role="$(ops_require_ident role)"
    material="$(ops_require_text material_path)"
    local plan
    plan="$(ops_db_plan "$engine" change_password "$role")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_check inside "$OPS_SANDBOX" "$material"
    if [[ -L "$material" || ! -f "$material" ]]; then
        ops_fail INVALID_INPUT "Credential file must be a regular file."
    fi
    local mode
    mode="$(stat -c '%a' "$material")"
    if [[ "$mode" != "600" ]]; then
        ops_fail INVALID_INPUT "Credential file mode must be 0600."
    fi
    local passfile="$OPS_SANDBOX/sql/${OPS_EXECUTION_ID}.material"
    cp "$material" "$passfile"
    chmod 0600 "$passfile"
    ops_exec "$(ops_db_client "$engine")" --defaults-extra-file="$passfile" --execute=ALTER
    rm -f "$passfile"
    ops_succeed "$plan" '{"password_logged":false}' true
}

ops_db_grant() {
    local engine="$1"
    local name privilege
    name="$(ops_db_name "$engine")"
    privilege="$(ops_require_text privilege)"
    case "$privilege" in
        connect | read | read_write) ;;
        *) ops_fail INVALID_INPUT "Privilege is not in the allowlist." ;;
    esac
    local quoted
    quoted="$(ops_py_value quote-ident "$name")"
    ops_db_mutate "$engine" grant "$name" "GRANT CONNECT ON DATABASE ${quoted} TO CURRENT_USER;"
}

ops_db_revoke() {
    local engine="$1"
    local name
    name="$(ops_db_name "$engine")"
    local quoted
    quoted="$(ops_py_value quote-ident "$name")"
    ops_db_mutate "$engine" revoke "$name" "REVOKE ALL ON DATABASE ${quoted} FROM CURRENT_USER;"
}

ops_db_list() {
    local engine="$1"
    local action="$2"
    local plan
    plan="$(ops_db_plan "$engine" "$action")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    ops_exec "$(ops_db_client "$engine")" --list
    ops_succeed "$plan" '{"listed":true}' false
}

ops_db_drop_database() {
    local engine="$1"
    local name quoted
    name="$(ops_db_name "$engine")"
    quoted="$(ops_py_value quote-ident "$name")"
    ops_db_mutate "$engine" drop_database "$name" "DROP DATABASE ${quoted};"
}

ops_db_drop_role() {
    local engine="$1"
    local role quoted
    role="$(ops_require_ident role)"
    if [[ "$role" == "rahandaz" || "$role" == "postgres" || "$role" == "root" ]]; then
        ops_fail PROTECTED_DATABASE "That role name is reserved."
    fi
    quoted="$(ops_py_value quote-ident "$role")"
    ops_db_mutate "$engine" drop_role "$role" "DROP ROLE ${quoted};"
}

ops_db_backup() {
    local engine="$1"
    local name target plan
    name="$(ops_db_name "$engine")"
    target="$(ops_require_text output_path)"
    plan="$(ops_db_plan "$engine" backup "$name")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_require_sandbox_apply
    local dest
    dest="$(ops_py_value inside "$OPS_SANDBOX" "$target")"
    case "$engine" in
        postgresql) ops_exec pg_dump --format=custom --file="$dest" -- "$name" ;;
        mysql | mariadb) ops_exec "$([[ "$engine" == mysql ]] && printf mysqldump || printf mariadb-dump)" --result-file="$dest" -- "$name" ;;
        mongodb) ops_exec mongodump --archive="$dest" --db="$name" ;;
    esac
    if [[ ! -s "$dest" ]]; then
        ops_fail VERIFICATION_FAILED "Backup output was empty."
    fi
    ops_succeed "$plan" '{"backup_written":true}' true
}

ops_db_restore() {
    local engine="$1"
    local name
    name="$(ops_db_name "$engine")"
    ops_db_mutate "$engine" restore "$name" "SELECT 1;"
}

ops_db_install() {
    local engine="$1"
    local plan
    plan="$(ops_db_plan "$engine" install)"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_fail PACKAGE_UNTESTED "Database engine install has no passing VM test."
}

ops_db_verify() {
    ops_db_install "$1"
}
