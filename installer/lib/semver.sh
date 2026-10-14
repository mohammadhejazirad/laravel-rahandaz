#!/usr/bin/env bash
# Numeric SemVer compare. 1.10 is greater than 1.9. A prerelease is below its release.

if [[ -n "${INSTALLER_LIB_SEMVER:-}" ]]; then
    return 0
fi
INSTALLER_LIB_SEMVER=1

installer_semver_split() {
    local raw="${1#v}"
    local core pre major minor patch
    pre=""
    if [[ "$raw" == *+* ]]; then
        raw="${raw%%+*}"
    fi
    if [[ "$raw" == *-* ]]; then
        core="${raw%%-*}"
        pre="${raw#*-}"
    else
        core="$raw"
    fi
    IFS=. read -r major minor patch <<<"${core}"
    minor="${minor:-0}"
    patch="${patch:-0}"
    [[ "$major" =~ ^(0|[1-9][0-9]*)$ && "$minor" =~ ^(0|[1-9][0-9]*)$ && "$patch" =~ ^(0|[1-9][0-9]*)$ ]] || return 1
    [[ -z "$pre" || "$pre" =~ ^[0-9A-Za-z.]+$ ]] || return 1
    printf '%s %s %s %s\n' "$major" "$minor" "$patch" "$pre"
}

installer_semver_ident_cmp() {
    local left="$1"
    local right="$2"
    if [[ "$left" =~ ^[0-9]+$ && "$right" =~ ^[0-9]+$ ]]; then
        if ((10#$left > 10#$right)); then
            printf '1\n'
        elif ((10#$left < 10#$right)); then
            printf -- '-1\n'
        else
            printf '0\n'
        fi
        return 0
    fi
    # SemVer: a numeric identifier has lower precedence than a non-numeric one.
    if [[ "$left" =~ ^[0-9]+$ ]]; then
        printf -- '-1\n'
        return 0
    fi
    if [[ "$right" =~ ^[0-9]+$ ]]; then
        printf '1\n'
        return 0
    fi
    if [[ "$left" == "$right" ]]; then
        printf '0\n'
    elif [[ "$left" > "$right" ]]; then
        printf '1\n'
    else
        printf -- '-1\n'
    fi
}

installer_semver_compare() {
    local left right lmaj lmin lpat lpre rmaj rmin rpat rpre
    local lparts rparts index lpart rpart ident
    left="$(installer_semver_split "$1")" || return 1
    right="$(installer_semver_split "$2")" || return 1
    read -r lmaj lmin lpat lpre <<<"$left"
    read -r rmaj rmin rpat rpre <<<"$right"
    if ((10#$lmaj != 10#$rmaj)); then
        ((10#$lmaj > 10#$rmaj)) && printf '1\n' || printf -- '-1\n'
        return 0
    fi
    if ((10#$lmin != 10#$rmin)); then
        ((10#$lmin > 10#$rmin)) && printf '1\n' || printf -- '-1\n'
        return 0
    fi
    if ((10#$lpat != 10#$rpat)); then
        ((10#$lpat > 10#$rpat)) && printf '1\n' || printf -- '-1\n'
        return 0
    fi
    if [[ -z "$lpre" && -z "$rpre" ]]; then
        printf '0\n'
        return 0
    fi
    if [[ -z "$lpre" ]]; then
        printf '1\n'
        return 0
    fi
    if [[ -z "$rpre" ]]; then
        printf -- '-1\n'
        return 0
    fi
    IFS=. read -r -a lparts <<<"$lpre"
    IFS=. read -r -a rparts <<<"$rpre"
    index=0
    while ((index < ${#lparts[@]} || index < ${#rparts[@]})); do
        if ((index >= ${#lparts[@]})); then
            printf -- '-1\n'
            return 0
        fi
        if ((index >= ${#rparts[@]})); then
            printf '1\n'
            return 0
        fi
        lpart="${lparts[$index]}"
        rpart="${rparts[$index]}"
        ident="$(installer_semver_ident_cmp "$lpart" "$rpart")"
        if [[ "$ident" != "0" ]]; then
            printf '%s\n' "$ident"
            return 0
        fi
        index=$((index + 1))
    done
    printf '0\n'
}
