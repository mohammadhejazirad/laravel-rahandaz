#!/usr/bin/env bash
# Security tests mutate only disposable unprivileged Ubuntu containers, never the host.
set -Eeuo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"
version="$(lab_version "${1:-}")"
mode="${2:---fixtures}"
case "$mode" in --fixtures|--live|--ssh-config) ;; *) lab_die 'Use --fixtures, --live or --ssh-config.' ;; esac
repo="$(cd "$LAB_ROOT/../../.." && pwd)"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
report="$LAB_ROOT/reports/security-${version}-${stamp}"
mkdir -p "$report"
name="rahandaz-security-lab-${version//./-}-$$"
trap 'docker rm -f "$name" >/dev/null 2>&1 || true' EXIT
set +e
docker run --name "$name" --cpus=1 --memory=2g --pids-limit=512 \
    --volume "$repo:/opt/rahandaz:ro" --workdir /opt/rahandaz \
    --entrypoint /bin/bash "rahandaz-ubuntu-lab:$version" -c '
    set -Eeuo pipefail
    export TMPDIR=/tmp
    python3 installer/tests/security_test.py
    bash installer/bin/rahandaz-security --plan
    bash installer/bin/rahandaz-firewall --plan
    if [[ "$1" == --live || "$1" == --ssh-config ]]; then
        INSTALLER_HOME=/opt/rahandaz/installer
        for lib in common state os gate render runtime engine prerequisites; do source "$INSTALLER_HOME/lib/$lib.sh"; done
        installer_detect_os
        PREREQ_STATE=/var/lib/rahandaz-prerequisites-v1
        PREREQ_APT_DIR=/var/lib/rahandaz-prerequisites-apt-v1
        PREREQ_IRAN=yes
        prereq_init
        prereq_select_sources
        if [[ "$1" == --ssh-config ]]; then
            prereq_install_packages openssh-server
            python3 tests/infrastructure/ubuntu-lab/scripts/inside/ssh-policy-test.py
            echo "OPENSSH_EFFECTIVE_CONFIG_VERIFIED; LIVE_SSH_LOGIN_HARDENING_REQUIRE_VM"
            exit 0
        fi
        prereq_install_packages sudo postgresql postgresql-client redis-server iproute2 acl
        security_accounts
        [[ "$(getent passwd rahandaz | cut -d: -f7)" == /bin/bash ]]
        id -nG rahandaz | tr " " "\n" | grep -qx sudo
        [[ "$(passwd -S rahandaz-svc | awk "{print \$2}")" == L ]]
        ! id -nG rahandaz-svc | tr " " "\n" | grep -Eq "^(sudo|docker|adm)$"
        visudo -c >/dev/null
        grep -qx "rahandaz ALL=(ALL:ALL) PASSWD: ALL" /etc/sudoers.d/99-rahandaz-admin
        python3 tests/infrastructure/ubuntu-lab/scripts/inside/security-check-password.py
        sha256sum /var/lib/rahandaz-bootstrap-security-v1/ubuntu.password >/tmp/initial-password.sha256
        security_accounts
        sha256sum --check --status /tmp/initial-password.sha256
        # Test resume after account creation/password application, before checkpoint.
        rm /var/lib/rahandaz-bootstrap-security-v1/admin.password-applied
        security_accounts
        sha256sum --check --status /tmp/initial-password.sha256
        cluster=$(pg_lsclusters --no-header | awk "NR==1 {print \$1, \$2}")
        read -r major cluster_name <<<"$cluster"
        pg_ctlcluster "$major" "$cluster_name" start 7>&-
        INSTALLER_STATE_DIR=/var/lib/rahandaz-bootstrap-security-v1
        installer_state_init
        MODE=apply
        stage_07_postgresql_setup
        sha256sum /etc/rahandaz/database.password >>/tmp/initial-password.sha256
        stage_07_postgresql_setup
        # No systemd in containers: run dedicated Redis foreground processes under redis UID.
        security_redis_config queue 6381
        security_redis_config cache 6380
        runuser -u redis -- redis-server /etc/redis/redis-rahandaz-queue.conf --supervised no 7>&- >/tmp/queue.log 2>&1 &
        runuser -u redis -- redis-server /etc/redis/redis-rahandaz-cache.conf --supervised no 7>&- >/tmp/cache.log 2>&1 &
        for attempt in {1..30}; do
            if security_datastore_health >/tmp/redis-health.log 2>&1; then break; fi
            sleep 0.2
        done
        security_datastore_health
        sha256sum /etc/rahandaz/redis-*.password /etc/redis/redis-rahandaz-*.conf >>/tmp/initial-password.sha256
        security_redis_config queue 6381
        security_redis_config cache 6380
        sha256sum --check --status /tmp/initial-password.sha256
        security_credentials
        [[ "$(stat -c %a /root/rahandaz-system-credentials.txt)" == 600 ]]
        # A collision must fail without changing the foreign account.
        mv /var/lib/rahandaz-bootstrap-security-v1/admin.intent /tmp/admin.intent
        if security_accounts; then echo "FOREIGN_ACCOUNT_ADOPTED"; exit 1; fi
        mv /tmp/admin.intent /var/lib/rahandaz-bootstrap-security-v1/admin.intent
        security_postgres_health
        security_password_once /tmp/rotated-ubuntu.password
        { printf "rahandaz:"; cat /tmp/rotated-ubuntu.password; } | chpasswd
        sha256sum /etc/shadow >/tmp/rotated-shadow.sha256
        security_accounts
        sha256sum --check --status /tmp/rotated-shadow.sha256
        mkdir -p /var/lib/rahandaz/releases/lab/public /var/lib/rahandaz/releases/lab/bootstrap/cache
        touch /var/lib/rahandaz/releases/lab/artisan
        printf "fixture-only\n" >/var/lib/rahandaz/releases/lab/.env
        printf "public-fixture\n" >/var/lib/rahandaz/releases/lab/public/index.html
        chmod 600 /var/lib/rahandaz/releases/lab/.env
        ln -s /var/lib/rahandaz/releases/lab /var/lib/rahandaz/current
        security_runtime_permissions
        runuser -u rahandaz-svc -- cat /var/lib/rahandaz/current/.env >/dev/null
        if runuser -u rahandaz -- cat /var/lib/rahandaz/current/.env 2>/dev/null; then exit 1; fi
        if runuser -u www-data -- cat /var/lib/rahandaz/current/.env 2>/dev/null; then exit 1; fi
        runuser -u www-data -- cat /var/lib/rahandaz/current/public/index.html >/dev/null
        if runuser -u rahandaz-svc -- touch /var/lib/rahandaz/current/forbidden 2>/dev/null; then exit 1; fi
        runuser -u rahandaz-svc -- touch /var/lib/rahandaz/shared/storage/allowed
        echo "LIVE_ACCOUNTS_POSTGRES_REDIS_RERUN_VERIFIED; SYSTEMD_SSH_UFW_REQUIRE_VM"
    fi
    ' bash "$mode" >"$report/output.log" 2>&1
rc=$?
set -e
printf 'exit_code=%s\nmode=%s\nubuntu=%s\nenvironment=container-lab\nvm_certification=not_run\n' "$rc" "$mode" "$version" >"$report/result.txt"
cat "$report/result.txt"
printf 'Report: %s\n' "$report/output.log"
exit "$rc"
