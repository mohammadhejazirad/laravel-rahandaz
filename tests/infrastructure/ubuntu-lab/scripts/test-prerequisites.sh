#!/usr/bin/env bash
# Prerequisite tests run only inside disposable Ubuntu lab containers.
# --live permits actual package installation INSIDE those containers, never on the Docker host.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
mode="${2:---fixtures}"
case "$mode" in --fixtures|--live|--mirrors) ;; *) lab_die 'Use --fixtures, --live or --mirrors.' ;; esac
repo="$(cd "$LAB_ROOT/../../.." && pwd)"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
report="$LAB_ROOT/reports/prerequisites-${version}-${stamp}"
mkdir -p "$report"
name="rahandaz-prerequisites-lab-${version//./-}-$$"
trap 'docker rm -f "$name" >/dev/null 2>&1 || true' EXIT
set +e
docker run --name "$name" --cpus=1 --memory=2g --pids-limit=512 \
    --volume "$repo:/opt/rahandaz:ro" --workdir /opt/rahandaz \
    --entrypoint /bin/bash "rahandaz-ubuntu-lab:$version" -c '
        set -Eeuo pipefail
        export TMPDIR=/tmp
        mkdir -p /run/rahandaz-lab
        token=$(python3 -c "import secrets; print(secrets.token_hex(32))")
        printf "rahandaz-lab-v1\n%s\n" "$token" >/run/rahandaz-lab/marker
        chmod 0600 /run/rahandaz-lab/marker
        export RAHANDAZ_OPS_LAB_TOKEN="$token"
        python3 installer/tests/prerequisites_test.py
        RAHANDAZ_INSTALLER_REFUSE_LIVE=1 bash installer/tests/run.sh
        bash installer/bin/rahandaz-prerequisites --plan
        if [[ "$1" == --mirrors ]]; then
            INSTALLER_HOME=/opt/rahandaz/installer
            source "$INSTALLER_HOME/lib/common.sh"
            source "$INSTALLER_HOME/lib/os.sh"
            source "$INSTALLER_HOME/lib/php-runtime.sh"
            source "$INSTALLER_HOME/lib/prerequisites.sh"
            installer_detect_os
            PREREQ_STATE=/var/lib/rahandaz-prerequisites-v1
            PREREQ_APT_DIR=/var/lib/rahandaz-prerequisites-apt-v1
            PREREQ_IRAN=yes
            prereq_init
            prereq_select_sources
            echo "SIGNED_IRANIAN_APT_INDEXES_VERIFIED_FROM_LAB_NETWORK; RESTRICTED_IRAN_NETWORK_NOT_CERTIFIED"
        fi
        if [[ "$1" == --live ]]; then
            . /etc/os-release
            if [[ "${VERSION_ID}" == "26.04" ]]; then
                set +e
                bash installer/bin/rahandaz-prerequisites --apply --yes --iranian-mirrors no >/tmp/ubuntu26.log 2>&1
                refused=$?
                set -e
                [[ "$refused" != 0 ]]
                grep -q UBUNTU_NOT_RECOMMENDED /tmp/ubuntu26.log
                echo "UBUNTU_26_APPLY_REFUSED_BEFORE_MUTATION"
                exit 0
            fi
            find /etc/apt/sources.list.d -type f -exec sha256sum {} + >/tmp/original-sources.sha256
            if [[ -f /etc/apt/sources.list ]]; then
                sha256sum /etc/apt/sources.list >>/tmp/original-sources.sha256
            fi
            set +e
            bash installer/bin/rahandaz-prerequisites --apply --yes --iranian-mirrors no
            first=$?
            set -e
            [[ "$first" == 10 ]] || { echo "LIVE_INSTALL_FAILED exit=$first"; exit 1; }
            printf "\n# lab preservation probe\n" >>/etc/nginx/nginx.conf
            printf "\n# lab preservation probe\n" >>/etc/redis/redis.conf
            sha256sum /etc/nginx/nginx.conf /etc/redis/redis.conf >/tmp/existing-config.sha256
            set +e
            bash installer/bin/rahandaz-prerequisites --apply --yes --iranian-mirrors no
            second=$?
            set -e
            [[ "$second" == 10 ]] || { echo "RERUN_FAILED exit=$second"; exit 1; }
            sha256sum --check --status /tmp/existing-config.sha256
            # Simulate a safely interrupted package installation, exclusively inside this container.
            package=$(find /var/lib/rahandaz-prerequisites-apt-v1/archives -maxdepth 1 -name "acl_*_amd64.deb" -print -quit)
            [[ -n "$package" ]]
            dpkg --unpack "$package"
            set +e
            bash installer/bin/rahandaz-prerequisites --apply --yes --iranian-mirrors no >/tmp/unapproved-repair.log 2>&1
            denied=$?
            set -e
            [[ "$denied" == 1 ]]
            grep -q REPAIR_APPROVAL_REQUIRED /tmp/unapproved-repair.log
            echo "UNAPPROVED_PACKAGE_REPAIR_REFUSED"
            set +e
            bash installer/bin/rahandaz-prerequisites --apply --yes --iranian-mirrors no --approve-package-repair
            repaired=$?
            set -e
            [[ "$repaired" == 10 ]] || { echo "REPAIR_FAILED exit=$repaired"; exit 1; }
            [[ "$(dpkg-query -W -f '\''${Status}'\'' acl)" == "install ok installed" ]]
            sha256sum --check --status /tmp/existing-config.sha256
            if [[ -s /tmp/original-sources.sha256 ]]; then
                sha256sum --check --status /tmp/original-sources.sha256
            fi
            echo "INTERRUPTED_PACKAGE_RECOVERED_AND_EXTERNAL_CONFIG_PRESERVED"
            echo "PACKAGES_AND_RERUN_VERIFIED; SYSTEMD_HEALTH_REBOOT_REQUIRE_VM"
        fi
    ' bash "$mode" >"$report/output.log" 2>&1
rc=$?
set -e
printf 'exit_code=%s\nmode=%s\nubuntu=%s\nenvironment=container-lab\nvm_certification=not_run\n' "$rc" "$mode" "$version" >"$report/result.txt"
cat "$report/result.txt"
printf 'Report: %s\n' "$report/output.log"
exit "$rc"
