#!/usr/bin/env bash

ops_catalog_file() {
    if [[ -n "${RAHANDAZ_OPS_CATALOG:-}" ]]; then
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
    local catalog
    catalog="$(ops_catalog_file)"
    python3 - "$catalog" "$package" <<'PY'
import json, sys
catalog = json.loads(open(sys.argv[1], encoding="utf-8").read())
row = catalog.get("packages", {}).get(sys.argv[2])
if row is None:
    print("UNKNOWN_PACKAGE")
    raise SystemExit(3)
print(json.dumps({"verification": catalog.get("verification", "untested"), "package": row}, separators=(",", ":")))
PY
}

ops_package_action() {
    local action="$1"
    local package plan row verification apt
    package="$(ops_require_package)"
    if ops_wants_apply; then
        ops_require_sandbox_apply
    fi
    row="$(ops_package_lookup "$package" 2>/dev/null || true)"
    if [[ "$row" == "UNKNOWN_PACKAGE" || -z "$row" ]]; then
        ops_fail INVALID_INPUT "Package is not in the catalog."
    fi
    verification="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["verification"])' "$row")"
    apt="$(python3 -c 'import json,sys; print(json.loads(sys.argv[1])["package"].get("apt") or "")' "$row")"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":sys.argv[1],"package":sys.argv[2],"apt":sys.argv[3] or None,"verification":sys.argv[4]},separators=(",",":")))' "$action" "$package" "$apt" "$verification")"
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    if [[ "$verification" != "vm-passed" || -z "$apt" ]]; then
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
    if ! ops_exec dpkg-query -W -f '${Status}' -- "$apt" | grep -q "install ok installed"; then
        ops_fail VERIFICATION_FAILED "Package verification did not observe an installed package."
    fi
    ops_succeed "$plan" "{\"package\":\"${package}\",\"verified\":true}" true
}

ops_package_verify() {
    local package plan
    package="$(ops_require_package)"
    plan="$(python3 -c 'import json,sys; print(json.dumps({"action":"verify","package":sys.argv[1],"verification":"untested"},separators=(",",":")))' "$package")"
    ops_package_lookup "$package" >/dev/null || ops_fail INVALID_INPUT "Package is not in the catalog."
    if ! ops_wants_apply; then
        ops_preview "$plan"
    fi
    ops_fail PACKAGE_UNTESTED "Package verification cannot succeed before a VM test."
}
