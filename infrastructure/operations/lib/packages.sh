#!/usr/bin/env bash

ops_catalog_file() {
    if [[ -n "${RAHANDAZ_OPS_CATALOG:-}" ]]; then
        if [[ "${RAHANDAZ_OPS_LAB:-}" == "1" || "${OPS_LAB:-}" == "1" ]]; then
            local err rc value code message
            err="$(mktemp)"
            set +e
            value="$(ops_py lab-catalog "$RAHANDAZ_OPS_CATALOG" 2>"$err")"
            rc=$?
            set -e
            if [[ "$rc" -ne 0 ]]; then
                message="$(head -n 1 "$err" 2>/dev/null || true)"
                code="$(tail -n 1 "$err" 2>/dev/null || true)"
                rm -f "$err"
                if [[ -n "${OPS_CATALOG_ERROR_FILE:-}" ]]; then
                    printf '%s\n%s\n' "${code:-INVALID_INPUT}" "${message:-Catalog was rejected.}" >"$OPS_CATALOG_ERROR_FILE"
                fi
                return 1
            fi
            rm -f "$err"
            printf '%s' "$value"
            return 0
        fi
        if [[ -z "${OPS_SANDBOX:-}" ]]; then
            ops_fail INVALID_INPUT "A catalog override requires a sandbox."
        fi
        ops_check inside "$OPS_SANDBOX" "$RAHANDAZ_OPS_CATALOG"
        printf '%s' "$RAHANDAZ_OPS_CATALOG"
        return 0
    fi
    printf '%s' "$OPS_ROOT/packages/catalog/ubuntu.json"
}

ops_package_lookup() {
    local package="$1"
    local catalog rc
    if ! catalog="$(ops_catalog_file)"; then
        return 1
    fi
    set +e
    python3 - "$catalog" "$package" <<'PY'
import json, sys
catalog = json.loads(open(sys.argv[1], encoding="utf-8").read())
row = catalog.get("packages", {}).get(sys.argv[2])
if row is None:
    print("UNKNOWN_PACKAGE")
    raise SystemExit(3)
print(json.dumps({"verification": catalog.get("verification", "untested"), "package": row}, separators=(",", ":")))
PY
    rc=$?
    set -e
    return "$rc"
}

ops_read_package_row() {
    local package="$1"
    local row_file lookup_rc code message
    OPS_CATALOG_ERROR_FILE="$(mktemp)"
    : >"$OPS_CATALOG_ERROR_FILE"
    row_file="$(mktemp)"
    set +e
    ops_package_lookup "$package" >"$row_file"
    lookup_rc=$?
    set -e
    OPS_PACKAGE_ROW="$(cat "$row_file")"
    rm -f "$row_file"
    if [[ "$lookup_rc" -ne 0 && -s "$OPS_CATALOG_ERROR_FILE" ]]; then
        code="$(head -n 1 "$OPS_CATALOG_ERROR_FILE")"
        message="$(sed -n '2p' "$OPS_CATALOG_ERROR_FILE")"
        rm -f "$OPS_CATALOG_ERROR_FILE"
        ops_fail "${code:-INVALID_INPUT}" "${message:-Catalog was rejected.}"
    fi
    rm -f "$OPS_CATALOG_ERROR_FILE"
    if [[ "$OPS_PACKAGE_ROW" == "UNKNOWN_PACKAGE" ]]; then
        ops_fail INVALID_INPUT "Package is not in the catalog."
    fi
    if [[ "$lookup_rc" -ne 0 || -z "$OPS_PACKAGE_ROW" ]]; then
        ops_fail INVALID_INPUT "Package catalog could not be read."
    fi
}

ops_package_action() {
    ops_package_apply_named "$1" "$(ops_require_package)"
}

ops_package_apply_named() {
    local action="$1"
    local package="$2"
    local plan row verification apt status observed
    ops_check package "$package"
    if ops_wants_apply; then
        ops_require_sandbox_apply
    fi
    ops_read_package_row "$package"
    row="$OPS_PACKAGE_ROW"
    verification="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["verification"])' "$row")"
    apt="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["package"].get("apt") or "")' "$row")"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":sys.argv[1],"package":sys.argv[2],"apt":sys.argv[3] or None,"verification":sys.argv[4]},separators=(",",":")))' "$action" "$package" "$apt" "$verification")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    if [[ -z "$apt" ]]; then
        ops_fail PACKAGE_UNTESTED "This package has no apt name, so it was not installed or removed."
    fi
    if [[ "$verification" == "vm-passed" ]]; then
        :
    elif [[ "$verification" == "container-lab" && "${OPS_LAB:-}" == "1" ]]; then
        :
    else
        ops_fail PACKAGE_UNTESTED "This package has no passing VM test, so it was not installed or removed."
    fi
    case "$action" in
        install)
            ops_exec apt-get -o APT::Get::Assume-Yes=true install -- "$apt"
            ;;
        remove)
            ops_require_approval
            ops_exec apt-get -o APT::Get::Assume-Yes=true remove -- "$apt"
            ;;
        update)
            ops_exec apt-get -o APT::Get::Assume-Yes=true install --only-upgrade -- "$apt"
            ;;
        *)
            ops_fail INVALID_INPUT "Unknown package action."
            ;;
    esac
    status="$(ops_exec dpkg-query -W -f '${Status}' -- "$apt" 2>/dev/null || true)"
    if [[ "$action" == "remove" ]]; then
        if [[ "$status" == *"install ok installed"* ]]; then
            ops_fail VERIFICATION_FAILED "Package removal did not observe an absent package."
        fi
        observed="{\"package\":\"${package}\",\"absent\":true"
    elif [[ "$status" != *"install ok installed"* ]]; then
        ops_fail VERIFICATION_FAILED "Package verification did not observe an installed package."
    else
        observed="{\"package\":\"${package}\",\"verified\":true"
    fi
    if [[ "$verification" == "container-lab" ]]; then
        observed="${observed},\"compatibility\":\"container-lab\"}"
    else
        observed="${observed}}"
    fi
    ops_succeed "$plan" "$observed" true
}

ops_package_verify() {
    local package plan row verification apt status
    package="$(ops_require_package)"
    if ops_wants_apply; then
        ops_require_sandbox_apply
    fi
    ops_read_package_row "$package"
    row="$OPS_PACKAGE_ROW"
    verification="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["verification"])' "$row")"
    apt="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["package"].get("apt") or "")' "$row")"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"verify","package":sys.argv[1],"verification":sys.argv[2]},separators=(",",":")))' "$package" "$verification")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    if [[ "${OPS_LAB:-}" == "1" && "$verification" == "container-lab" && -n "$apt" ]]; then
        status="$(ops_exec dpkg-query -W -f '${Status}' -- "$apt" 2>/dev/null || true)"
        if [[ "$status" != *"install ok installed"* ]]; then
            ops_fail VERIFICATION_FAILED "Package verification did not observe an installed package."
        fi
        ops_succeed "$plan" "{\"package\":\"${package}\",\"verified\":true,\"compatibility\":\"container-lab\"}" false
    fi
    ops_fail PACKAGE_UNTESTED "Package verification cannot succeed before a VM test."
}
