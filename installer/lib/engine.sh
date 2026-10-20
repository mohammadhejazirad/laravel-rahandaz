#!/usr/bin/env bash
# Stage implementations. Each function is one stable stage.

if [[ -n "${INSTALLER_LIB_ENGINE:-}" ]]; then
    return 0
fi
INSTALLER_LIB_ENGINE=1
source "${INSTALLER_HOME}/lib/security.sh"
source "${INSTALLER_HOME}/lib/network.sh"

installer_root_path() {
    local root
    root="$(installer_sysroot)"
    printf '%s%s\n' "$root" "$1"
}

stage_01_preflight() {
    installer_detect_os || return 1
    installer_check_resources || return 1
    installer_check_conflicts || return 1
    if [[ "$MODE" != plan ]]; then
        installer_network_preflight || return 1
    fi
    if ! installer_php_baseline_available; then
        installer_error PHP_BASELINE_UNAVAILABLE "Rahandaz runs on PHP 8.4. This Ubuntu release has no verified PHP 8.4 suite."
        return 1
    fi
    installer_php_key_ok || return 1
    installer_say "Ubuntu ${INSTALLER_OS_VERSION} ${INSTALLER_OS_CODENAME} ${INSTALLER_ARCH}"
    return 0
}

stage_02_repository_setup() {
    local id dest root backup
    root="$(installer_sysroot)"
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN apt sources stay on the signed Ubuntu archive unless it is unreachable or an Iranian mirror was requested. PHP 8.4 packages are pinned to packages.sury.org and no other package from that origin is installed."
        return 0
    fi
    id="$(installer_select_mirror "$INSTALLER_OS_CODENAME" "${INSTALLER_MIRROR_PIN:-}" "${PREFER_IRANIAN:-0}")" || return 1
    INSTALLER_ERROR_CODE=""
    installer_state_set selected_mirror "$id"
    installer_say "MIRROR ${id} $(installer_mirror_field "$id" 4) $(installer_mirror_field "$id" 5)"
    if [[ "$id" == "ubuntu-archive" && "${PREFER_IRANIAN:-0}" != "1" ]]; then
        installer_say "Keeping the existing APT sources because the official archive verified."
    else
        dest="$(installer_root_path /etc/apt/sources.list.d/rahandaz-ubuntu.sources)"
        backup="$(installer_root_path /var/lib/rahandaz/installer/apt-backup)"
        mkdir -p "$backup" "$(dirname "$dest")"
        if [[ -f "$(installer_root_path /etc/apt/sources.list.d/ubuntu.sources)" && ! -f "$backup/ubuntu.sources" ]]; then
            cp -a "$(installer_root_path /etc/apt/sources.list.d/ubuntu.sources)" "$backup/ubuntu.sources"
        fi
        if [[ -f "$(installer_root_path /etc/apt/sources.list)" && ! -f "$backup/sources.list" ]]; then
            cp -a "$(installer_root_path /etc/apt/sources.list)" "$backup/sources.list"
        fi
        installer_write_apt_sources "$id" "$INSTALLER_OS_CODENAME" "$dest"
        if grep -E -q 'trusted=yes|allow-unauthenticated|Acquire::https::Verify-Peer false' "$dest"; then
            rm -f "$dest"
            installer_error MIRROR_SIGNATURE_INVALID "Refusing an APT source that disables authentication."
            return 1
        fi
    fi
    installer_write_php_repository || return 1
    installer_say "PHP repository pinned for php8.4-* only."
    return 0
}

installer_apt_install() {
    local package
    for package in "$@"; do
        installer_package_allowed "$package" || {
            installer_error APT_FAILED "Package ${package} is outside the control-plane allowlist."
            return 1
        }
        if installer_pkg_installed "$package"; then
            continue
        fi
        if [[ "$(installer_sysroot)" != "/" || "${INSTALLER_OFFLINE:-}" == "1" ]]; then
            installer_error PACKAGES_NOT_INSTALLED "Package ${package} is not installed and this run cannot call apt."
            return 1
        fi
        if ! installer_require_cmd apt-get; then
            installer_error APT_FAILED "apt-get is not available."
            return 1
        fi
        DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$package"
    done
}

stage_03_system_packages() {
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN base packages: sudo ufw ca-certificates curl gnupg ubuntu-keyring git unzip tar openssl python3 acl"
        return 0
    fi
    if [[ "$(installer_sysroot)" == "/" && "${INSTALLER_OFFLINE:-}" != "1" ]]; then
        DEBIAN_FRONTEND=noninteractive apt-get update -o Acquire::Retries=3
    fi
    installer_apt_install sudo ufw ca-certificates curl gnupg ubuntu-keyring git unzip tar openssl python3 acl
}

stage_04_runtime_installation() {
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN runtime: postgresql redis nginx and PHP 8.4 from /usr/bin/php8.4. Other PHP versions stay installed. Node.js is not installed."
        return 0
    fi
    installer_apt_install postgresql postgresql-client redis-server nginx "${INSTALLER_PHP_PACKAGES[@]}" || return 1
    if [[ "$(installer_sysroot)" != "/" || "${INSTALLER_OFFLINE:-}" == "1" ]]; then
        return 0
    fi
    if ! installer_php84_binary_ok; then
        installer_error PHP_BASELINE_UNAVAILABLE "Rahandaz requires the regular file /usr/bin/php8.4 at exactly PHP 8.4."
        return 1
    fi
    "$INSTALLER_PHP_BIN" -m | awk 'BEGIN { need["pdo_pgsql"]=1; need["redis"]=1; need["sodium"]=1; need["zip"]=1; need["fileinfo"]=1; need["mbstring"]=1; need["curl"]=1; need["xml"]=1; need["bcmath"]=1; need["intl"]=1 } { delete need[$1] } END { for (name in need) { print name; missing=1 } exit missing }' && return 0
    installer_error PHP_BASELINE_UNAVAILABLE "A required PHP 8.4 extension is missing."
    return 1
}

stage_05_operational_account() {
    if [[ "$MODE" == plan ]]; then
        installer_say 'PLAN administrator rahandaz (password sudo; SSH key onboarding pending); separate locked nologin service rahandaz-svc.'
        return 0
    fi
    if [[ "$(installer_sysroot)" != / || "${INSTALLER_OFFLINE:-}" == 1 ]]; then
        mkdir -p "$(installer_root_path /var/lib/rahandaz)"
        printf '%s\n' 'admin=rahandaz service=rahandaz-svc shell=nologin locked=yes sudo=no' >"$(installer_root_path /var/lib/rahandaz/account.contract)"
        return 2
    fi
    security_accounts || return 1
    security_credentials

}

installer_tar_members_safe() {
    local archive="$1"
    local member kind
    while IFS= read -r member; do
        [[ -n "$member" ]] || continue
        [[ "$member" != /* ]] || return 1
        [[ "$member" != *".."* ]] || return 1
    done < <(tar -tzf "$archive")
    while IFS= read -r kind; do
        case "$kind" in
            l* | h*) return 1 ;;
        esac
    done < <(tar -tvzf "$archive" | awk '{ print substr($0, 1, 1) }')
}

installer_prune_releases() {
    local root current extra pruned
    root="$(installer_root_path /var/lib/rahandaz/releases)"
    [[ -d "$root" && ! -L "$root" ]] || return 0
    current="$(readlink -f "$(installer_root_path /var/lib/rahandaz/current)" 2>/dev/null || true)"
    extra="${1:-}"
    pruned="$(python3 - "$root" "$current" "$extra" <<'PY'
import pathlib, shutil, sys
root_text, current_text, extra_text = sys.argv[1:]
root = pathlib.Path(root_text).resolve()
keep = set()

def remember(value):
    if not value:
        return
    path = pathlib.Path(value)
    if not path.is_absolute():
        path = root / value
    try:
        resolved = path.resolve()
    except OSError:
        return
    if resolved == root or root in resolved.parents:
        keep.add(resolved)

remember(current_text)
remember(extra_text)
dirs = [path.resolve() for path in root.iterdir() if path.is_dir() and not path.is_symlink()]
dirs.sort(key=lambda path: path.stat().st_mtime, reverse=True)
keep.update(dirs[:2])
removed = 0
for path in dirs:
    if path in keep or path == root or root not in path.parents:
        continue
    shutil.rmtree(path)
    removed += 1
if removed:
    print(removed)
PY
)" || return 1
    if [[ -n "$pruned" ]]; then
        installer_say "PRUNE ${pruned} old release directories. The current release and the previous one stay."
    fi
}

stage_06_application_release() {
    local root dest version archive
    root="$(installer_sysroot)"
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN extract a signed release under /var/lib/rahandaz/releases and point current at it."
        return 0
    fi
    if [[ -z "${INSTALLER_RELEASE_VERSION:-}" || -z "${INSTALLER_ARTIFACT:-}" ]]; then
        installer_error UNVERIFIED_ARTIFACT "Apply requires a verified release artifact."
        return 1
    fi
    version="$INSTALLER_RELEASE_VERSION"
    archive="$INSTALLER_ARTIFACT"
    installer_tar_members_safe "$archive" || {
        installer_error UNVERIFIED_ARTIFACT "Artifact contains an unsafe path."
        return 1
    }
    dest="$(installer_root_path "/var/lib/rahandaz/releases/${version}")"
    if [[ -d "$dest" && -f "$(installer_root_path /var/lib/rahandaz/current/artisan)" ]]; then
        installer_prune_releases || return 1
        return 0
    fi
    mkdir -p "$dest"
    tar -xzf "$archive" -C "$dest" --strip-components=1
    [[ -f "$dest/artisan" && -f "$dest/public/build/manifest.json" && -f "$dest/composer.lock" ]] || {
        installer_error ARTIFACT_INCOMPLETE "The release is missing artisan, composer.lock, or built assets."
        return 1
    }
    ln -sfn "$dest" "$(installer_root_path /var/lib/rahandaz/current)"
    case "$version" in
        *-alpha.* | *-beta.* | *-rc.*) installer_write_release_record "$version" beta ;;
        *-*) installer_write_release_record "$version" development ;;
        *) installer_write_release_record "$version" stable ;;
    esac
    mkdir -p "$(installer_root_path /var/lib/rahandaz/shared/storage)"
    if [[ ! -e "$dest/storage" ]]; then
        ln -s "$(installer_root_path /var/lib/rahandaz/shared/storage)" "$dest/storage"
    fi
    installer_prune_releases || return 1
    return 0
}

installer_postgres_small_profile() {
    local count version cluster dropin tmp
    if installer_acknowledged postgresql; then
        installer_say "KEEP PostgreSQL memory settings. An existing installation was acknowledged."
        return 0
    fi
    count="$(pg_lsclusters --no-header | awk 'NF { c++ } END { print c + 0 }')"
    if [[ "$count" != 1 ]]; then
        installer_say "KEEP PostgreSQL memory settings. Expected one cluster."
        return 0
    fi
    version="$(pg_lsclusters --no-header | awk 'NF { print $1; exit }')"
    cluster="$(pg_lsclusters --no-header | awk 'NF { print $2; exit }')"
    [[ "$version" =~ ^[0-9]+$ && "$cluster" =~ ^[A-Za-z0-9._-]+$ ]] || {
        installer_error POSTGRES_START_FAILED "PostgreSQL cluster identity could not be read."
        return 1
    }
    dropin="/etc/postgresql/${version}/${cluster}/conf.d/rahandaz-small.conf"
    if [[ ! -d "$(dirname "$dropin")" ]]; then
        installer_say "KEEP PostgreSQL memory settings. conf.d is absent."
        return 0
    fi
    if [[ -f "$dropin" && ! -L "$dropin" ]]; then
        installer_say "KEEP PostgreSQL small profile."
        return 0
    fi
    if [[ -e "$dropin" ]]; then
        installer_error POSTGRES_START_FAILED "PostgreSQL profile path is a symlink."
        return 1
    fi
    tmp="$(mktemp)"
    cat >"$tmp" <<'EOF'
# rahandaz-managed: postgres-small-v1
shared_buffers = 64MB
effective_cache_size = 256MB
work_mem = 2MB
maintenance_work_mem = 32MB
max_connections = 40
huge_pages = off
jit = off
EOF
    install -m 0644 "$tmp" "$dropin"
    rm -f "$tmp"
    chgrp postgres "$dropin" 2>/dev/null || true
    if [[ -d /run/systemd/system ]]; then
        systemctl restart postgresql || {
            installer_error POSTGRES_START_FAILED "PostgreSQL did not restart with the small profile."
            return 1
        }
    else
        pg_ctlcluster "$version" "$cluster" restart || {
            installer_error POSTGRES_START_FAILED "PostgreSQL did not restart with the small profile."
            return 1
        }
    fi
    security_postgres_health || return 1
    installer_say "APPLIED PostgreSQL small profile and restarted the cluster."
}

stage_07_postgresql_setup() {
    local root password_file role_exists database_exists
    root="$(installer_sysroot)"
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN create database rahandaz and role rahandaz if they are absent. An existing password is not rotated."
        return 0
    fi
    password_file="$(installer_root_path /etc/rahandaz/database.password)"
    if [[ "$root" != "/" || "${INSTALLER_OFFLINE:-}" == "1" ]]; then
        local plan
        plan="$(installer_root_path /var/lib/rahandaz/installer/postgres.plan)"
        mkdir -p "$(dirname "$password_file")" "$(dirname "$plan")"
        printf '%s\n' 'create_if_absent role=rahandaz database=rahandaz rotate_password=no' >"$plan"
        return 2
    fi
    security_lock || return 1
    role_exists=$(runuser -u postgres -- psql -X -v ON_ERROR_STOP=1 -tAc "SELECT 1 FROM pg_roles WHERE rolname='rahandaz'") || return 1
    if [[ "$role_exists" != 1 ]]; then
        security_password_once "$password_file" || return 1
        local sql password
        sql="$(mktemp)"
        chmod 0600 "$sql"
        password="$(python3 - "$password_file" <<'PYHASH'
import base64, hashlib, hmac, pathlib, secrets, sys
password = pathlib.Path(sys.argv[1]).read_text().strip().encode()
salt = secrets.token_bytes(16)
salted = hashlib.pbkdf2_hmac('sha256', password, salt, 4096)
client = hmac.digest(salted, b'Client Key', 'sha256')
server = hmac.digest(salted, b'Server Key', 'sha256')
b64 = lambda value: base64.b64encode(value).decode()
print('SCRAM-SHA-256$4096:' + b64(salt) + '$' + b64(hashlib.sha256(client).digest()) + ':' + b64(server))
PYHASH
)"
        printf '%s\n' "CREATE ROLE rahandaz LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD '${password}';" >"$sql"
        unset password
        chgrp postgres "$sql" 2>/dev/null || true
        chmod 0640 "$sql"
        if ! runuser -u postgres -- psql -v ON_ERROR_STOP=1 -f "$sql"; then
            rm -f "$sql"
            installer_error POSTGRES_START_FAILED "Creating the database role failed."
            return 1
        fi
        rm -f "$sql"
        installer_state_set database_created yes
    elif [[ ! -s "$password_file" && ! -f "$(installer_root_path /var/lib/rahandaz/current/.env)" ]]; then
        installer_error DATABASE_PASSWORD_UNKNOWN "The role exists and the saved password is missing. The installer will not rotate it."
        return 1
    fi
    database_exists=$(runuser -u postgres -- psql -X -v ON_ERROR_STOP=1 -tAc "SELECT 1 FROM pg_database WHERE datname='rahandaz'") || return 1
    if [[ "$database_exists" != 1 ]]; then
        runuser -u postgres -- psql -v ON_ERROR_STOP=1 -c 'CREATE DATABASE rahandaz OWNER rahandaz;' || {
            installer_error POSTGRES_START_FAILED "Creating the database failed."
            return 1
        }
        installer_state_set database_created yes
    fi
    security_postgres_health || return 1
    installer_postgres_small_profile || return 1
    security_credentials
    return 0
}

stage_08_redis_setup() {
    if [[ "$MODE" == plan ]]; then
        installer_say 'PLAN dedicated authenticated Redis queue 127.0.0.1:6381 and cache 127.0.0.1:6380; existing Redis unchanged.'
        return 0
    fi
    if [[ "$(installer_sysroot)" != / || "${INSTALLER_OFFLINE:-}" == 1 ]]; then
        return 2
    fi
    security_lock || return 1
    security_redis_config queue 6381 || return 1
    security_redis_config cache 6380 || return 1
    security_redis_units || return 1
    security_credentials

}

stage_09_laravel_configuration() {
    local root envfile keyfile current
    root="$(installer_sysroot)"
    current="$(installer_root_path /var/lib/rahandaz/current)"
    envfile="$current/.env"
    keyfile="$(installer_root_path /etc/rahandaz/master.key)"
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN write production .env once. APP_KEY and the master key are not regenerated."
        return 0
    fi
    if [[ ! -d "$current" ]]; then
        installer_error ARTIFACT_INCOMPLETE "The current release is not in place."
        return 1
    fi
    if [[ ! -f "$envfile" ]]; then
        cat >"$envfile" <<EOF
APP_NAME=Rahandaz
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${PUBLIC_URL}
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=7
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rahandaz
DB_USERNAME=rahandaz
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_PATH=/rahandaz
SESSION_SECURE_COOKIE=false
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_QUEUE_HOST=127.0.0.1
REDIS_QUEUE_PORT=6381
REDIS_QUEUE_USERNAME=rahandaz
REDIS_QUEUE_PASSWORD=
REDIS_QUEUE_DB=0
REDIS_CACHE_HOST=127.0.0.1
REDIS_CACHE_PORT=6380
REDIS_CACHE_USERNAME=rahandaz
REDIS_CACHE_PASSWORD=
REDIS_CACHE_DB=0
RAHANDAZ_EXECUTION_MODE=disabled
RAHANDAZ_BASE_PATH=/rahandaz
RAHANDAZ_MASTER_KEY_FILE=/etc/rahandaz/master.key
RAHANDAZ_CANARY_FILE=/etc/rahandaz/canary.json
RAHANDAZ_WORKER_TIMEOUT=60
REDIS_QUEUE_RETRY_AFTER=90
EOF
        chmod 0600 "$envfile"
    fi
    python3 - "$envfile" <<'PY' || return 1
import pathlib, sys
path = pathlib.Path(sys.argv[1])
lines = path.read_text().splitlines()
if any(line.startswith("LOG_DAILY_DAYS=") for line in lines):
    raise SystemExit(0)
out = []
changed = False
for line in lines:
    if line == "LOG_CHANNEL=stack":
        out.append("LOG_CHANNEL=daily")
        changed = True
    else:
        out.append(line)
if not changed:
    raise SystemExit(0)
out.append("LOG_DAILY_DAYS=7")
path.write_text("\n".join(out) + "\n")
path.chmod(0o600)
PY
    if [[ -z "$(installer_env_get "$envfile" DB_PASSWORD)" && -s "$(installer_root_path /etc/rahandaz/database.password)" ]]; then
        python3 - "$envfile" "$(installer_root_path /etc/rahandaz/database.password)" <<'PY'
import pathlib, sys
env = pathlib.Path(sys.argv[1])
password = pathlib.Path(sys.argv[2]).read_text().strip()
lines = []
for line in env.read_text().splitlines():
    if line.startswith("DB_PASSWORD="):
        lines.append("DB_PASSWORD=" + password)
    else:
        lines.append(line)
env.write_text("\n".join(lines) + "\n")
PY
        chmod 0600 "$envfile"
    fi
    if [[ "$root" == / && "${INSTALLER_OFFLINE:-}" != 1 ]]; then
        python3 - "$envfile" <<'PYREDIS' || return 1
import pathlib, sys
p = pathlib.Path(sys.argv[1])
text = p.read_text()
for kind in ('QUEUE', 'CACHE'):
    key = 'REDIS_' + kind + '_PASSWORD='
    value = pathlib.Path('/etc/rahandaz/redis-' + kind.lower() + '.password').read_text().strip()
    lines = text.splitlines()
    if key + value in lines:
        continue
    if key not in lines:
        raise SystemExit('REDIS_ENV_CONFLICT: explicit migration required')
    text = text.replace(key + '\n', key + value + '\n')
p.write_text(text)
PYREDIS
    fi
    installer_create_secret_once "$keyfile" || return 1
    if [[ "$root" != "/" || "${INSTALLER_OFFLINE:-}" == "1" ]]; then
        return 2
    fi
    if [[ -z "$(installer_env_get "$envfile" APP_KEY)" ]]; then
        (cd "$current" && "${INSTALLER_PHP_BIN}" artisan key:generate --no-interaction) || {
            installer_error KEY_EXISTS "APP_KEY could not be created."
            return 1
        }
    fi
    (cd "$current" && "${INSTALLER_PHP_BIN}" artisan rahandaz:keys:init --installer --no-interaction) || {
        installer_error KEY_EXISTS "The master key canary could not be created."
        return 1
    }
    return 0
}

stage_10_database_migration() {
    local current
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN migrate forward on an empty installer-created database. migrate:fresh is not used."
        return 0
    fi
    if [[ "${MODE}" == "upgrade" && "${APPROVE_MIGRATIONS:-0}" != "1" ]]; then
        installer_error MIGRATION_REFUSED "Upgrade migrations need --approve-migrations."
        return 1
    fi
    if [[ "$(installer_sysroot)" != "/" || "${INSTALLER_OFFLINE:-}" == "1" ]]; then
        return 2
    fi
    if [[ "$(installer_state_get database_created)" != "yes" && "${APPROVE_MIGRATIONS:-0}" != "1" ]]; then
        installer_error MIGRATION_REFUSED "Refusing to migrate a database this installer did not create."
        return 1
    fi
    current="$(installer_root_path /var/lib/rahandaz/current)"
    (cd "$current" && "${INSTALLER_PHP_BIN}" artisan migrate --force --no-interaction) || {
        installer_error MIGRATION_FAILED "Migrations failed. The database was not dropped."
        return 1
    }
    (cd "$current" && "${INSTALLER_PHP_BIN}" artisan db:seed --force --no-interaction) || {
        installer_error MIGRATION_FAILED "Catalog seed failed."
        return 1
    }
    return 0
}

stage_11_nginx_php_fpm() {
    local root site pool pool_dir server_names listen4 listen6 panel link
    local bootstrap_link
    bootstrap_link="$(installer_root_path /etc/nginx/sites-enabled/rahandaz.conf)"
    root="$(installer_sysroot)"
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN a dedicated Nginx site and a private PHP 8.4 FPM pool. Distro PHP-FPM pools are not changed."
        return 0
    fi
    site="$(installer_root_path /etc/nginx/sites-available/rahandaz.conf)"
    listen4="$HTTP_PORT"
    listen6="[::]:$HTTP_PORT"
    if [[ "${PANEL_PUBLIC_IPV4:-0}" == 1 ]]; then
        listen4=127.0.0.1:80
        listen6='[::1]:80'
    fi
    if [[ -L "$site" || ( -e "$site" && "$(head -n 1 "$site")" != '# rahandaz-managed: control-plane' ) ]]; then
        installer_error FOREIGN_CONFIG 'The bootstrap site is not managed by Rahandaz.'
        return 1
    fi
    if [[ ( -e "$bootstrap_link" && ! -L "$bootstrap_link" ) || ( -L "$bootstrap_link" && "$(readlink "$bootstrap_link")" != "$site" ) ]]; then
        installer_error FOREIGN_CONFIG 'The bootstrap site link requires operator review.'
        return 1
    fi
    server_names="localhost 127.0.0.1"
    case "$SERVER_NAME" in
        _ | localhost | 127.0.0.1) ;;
        *) server_names="$SERVER_NAME $server_names" ;;
    esac
    installer_render_template "${INSTALLER_HOME}/templates/nginx/rahandaz.conf.tpl" "$site" \
        HTTP_LISTEN4 "$listen4" HTTP_LISTEN6 "$listen6" \
        SERVER_NAME "$server_names" \
        PUBLIC_ROOT /var/lib/rahandaz/current/public \
        WELCOME_ROOT /var/lib/rahandaz/welcome \
        FPM_SOCK /run/rahandaz/php-fpm.sock \
        CLIENT_MAX_BODY 100m || return 1
    if [[ "${PANEL_PUBLIC_IPV4:-0}" == 1 ]]; then
        panel="$(installer_root_path /etc/nginx/sites-available/rahandaz-panel.conf)"
        link="$(installer_root_path /etc/nginx/sites-enabled/rahandaz-panel.conf)"
        if [[ -L "$panel" || ( -e "$panel" && "$(head -n 1 "$panel")" != '# rahandaz-managed: panel-listener-v1' ) \
            || ( -e "$link" && ! -L "$link" ) \
            || ( -L "$link" && "$(readlink "$link")" != "$panel" ) ]]; then
            installer_error FOREIGN_CONFIG 'The public panel site is not managed by Rahandaz.'
            return 1
        fi
        python3 "${INSTALLER_HOME}/lib/network.py" site "$SERVER_NAME" "$HTTP_PORT" >"${panel}.pending" || return 1
        chmod 0644 "${panel}.pending"
        mv "${panel}.pending" "$panel"
        mkdir -p "$(dirname "$link")"
        ln -sfn "$panel" "$link"
    fi
    pool_dir="$(installer_root_path /etc/rahandaz/php/pool.d)"
    pool="${pool_dir}/rahandaz.conf"
    mkdir -p "$pool_dir" "$(installer_root_path /etc/nginx/sites-enabled)"
    cp "${INSTALLER_HOME}/templates/php-fpm/rahandaz-fpm.conf" "$(installer_root_path /etc/rahandaz/php/php-fpm.conf)"
    chmod 0644 "$(installer_root_path /etc/rahandaz/php/php-fpm.conf)"
    mkdir -p "$(installer_root_path /etc/rahandaz/php/ini)"
    cp "${INSTALLER_HOME}/templates/php/opcache.ini" "$(installer_root_path /etc/rahandaz/php/ini/opcache.ini)"
    chmod 0644 "$(installer_root_path /etc/rahandaz/php/ini/opcache.ini)"
    installer_render_template "${INSTALLER_HOME}/templates/php-fpm/rahandaz.conf.tpl" "$pool" \
        FPM_SOCK /run/rahandaz/php-fpm.sock \
        UPLOAD_MAX 100M || return 1
    ln -sfn "$site" "$(installer_root_path /etc/nginx/sites-enabled/rahandaz.conf)"
    if [[ "$root" != "/" || "${INSTALLER_OFFLINE:-}" == "1" ]]; then
        return 2
    fi
    nginx -t || {
        rm -f "$(installer_root_path /etc/nginx/sites-enabled/rahandaz.conf)"
        if [[ "${PANEL_PUBLIC_IPV4:-0}" == 1 ]]; then rm -f "$link"; fi
        installer_error NGINX_TEST_FAILED "nginx -t failed. The site was not enabled."
        return 1
    }
    return 0
}

installer_enroll_software() {
    /usr/bin/python3 -I /var/lib/rahandaz/current/infrastructure/operations/software/provision.py --upgrade
}

installer_quiesce_unused_defaults() {
    # Config files of distribution Redis and PHP-FPM are not rewritten.
    # An installation the operator acknowledged is left running.
    if ! installer_acknowledged redis-server \
        && systemctl is-active --quiet redis-rahandaz-queue.service \
        && systemctl is-active --quiet redis-rahandaz-cache.service \
        && systemctl list-unit-files --no-legend redis-server.service 2>/dev/null | grep -q '^redis-server.service'; then
        systemctl disable --now redis-server.service || return 1
        installer_say "STOPPED unused redis-server. Its configuration file was not modified."
    fi
    if ! installer_acknowledged php8.4-fpm \
        && systemctl is-active --quiet rahandaz-php-fpm.service \
        && systemctl list-unit-files --no-legend php8.4-fpm.service 2>/dev/null | grep -q '^php8.4-fpm.service'; then
        systemctl disable --now php8.4-fpm.service || return 1
        installer_say "STOPPED distro php8.4-fpm. The panel pool is rahandaz-php-fpm."
    fi
}

stage_12_systemd_services() {
    local root release php_bin fpm_bin cli_dest lib_dest item logs mode
    root="$(installer_sysroot)"
    release="/var/lib/rahandaz/current"
    php_bin="${INSTALLER_PHP_BIN}"
    fpm_bin="${INSTALLER_PHP_FPM_BIN}"
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN systemd units for rahandaz-php-fpm, the scheduler timer, and the Redis cache. The queue worker stays stopped while execution mode is disabled. ExecStart uses /usr/bin/php8.4. Cron is not installed."
        return 0
    fi
    mkdir -p "$(installer_root_path /etc/systemd/system)"
    installer_render_template "${INSTALLER_HOME}/templates/systemd/rahandaz-php-fpm.service.tpl" \
        "$(installer_root_path /etc/systemd/system/rahandaz-php-fpm.service)" \
        PHP_FPM_BIN "$fpm_bin" || return 1
    installer_render_template "${INSTALLER_HOME}/templates/systemd/rahandaz-queue.service.tpl" \
        "$(installer_root_path /etc/systemd/system/rahandaz-queue.service)" \
        RELEASE "$release" PHP_BIN "$php_bin" DATA_ROOT /var/lib/rahandaz || return 1
    installer_render_template "${INSTALLER_HOME}/templates/systemd/rahandaz-scheduler.service.tpl" \
        "$(installer_root_path /etc/systemd/system/rahandaz-scheduler.service)" \
        RELEASE "$release" PHP_BIN "$php_bin" DATA_ROOT /var/lib/rahandaz || return 1
    installer_render_template "${INSTALLER_HOME}/templates/systemd/rahandaz-scheduler.timer.tpl" \
        "$(installer_root_path /etc/systemd/system/rahandaz-scheduler.timer)" || return 1
    cp "${INSTALLER_HOME}/templates/systemd/rahandaz.slice" \
        "$(installer_root_path /etc/systemd/system/rahandaz.slice)"
    chmod 0644 "$(installer_root_path /etc/systemd/system/rahandaz.slice)"
    logs="$(installer_root_path /etc/logrotate.d/rahandaz)"
    mkdir -p "$(dirname "$logs")"
    if [[ -e "$logs" && ! -L "$logs" ]] && [[ "$(head -n 1 "$logs")" != '# rahandaz-managed: panel-logs-v1' ]]; then
        installer_error FOREIGN_CONFIG "The logrotate snippet is not managed by Rahandaz."
        return 1
    fi
    if [[ -L "$logs" ]]; then
        installer_error FOREIGN_CONFIG "The logrotate snippet path is a symlink."
        return 1
    fi
    cp "${INSTALLER_HOME}/templates/logrotate/rahandaz" "$logs"
    chmod 0644 "$logs"
    if [[ "$(installer_sysroot)" == / && "${INSTALLER_OFFLINE:-}" != 1 ]]; then
        security_redis_units || return 1
    fi
    lib_dest="$(installer_root_path /usr/local/lib/rahandaz)"
    mkdir -p "$lib_dest"
    for item in bin lib templates trust release VERSION; do
        rm -rf "${lib_dest}/${item}"
        cp -a "${INSTALLER_HOME}/${item}" "${lib_dest}/${item}"
    done
    mkdir -p "$(installer_root_path /usr/local/sbin)"
    cli_dest="$(installer_root_path /usr/local/sbin/rahandaz)"
    cat >"$cli_dest" <<'EOF'
#!/usr/bin/env bash
exec /usr/local/lib/rahandaz/bin/rahandaz "$@"
EOF
    chmod 0755 "$cli_dest" "${lib_dest}/bin/rahandaz"
    if [[ -e "$(installer_root_path /etc/cron.d/rahandaz)" ]]; then
        installer_error STAGE_FAILED "Refusing to keep a Rahandaz cron file beside the systemd timer."
        return 1
    fi
    if [[ "$root" != "/" || "$(installer_pid1_name)" != "systemd" ]]; then
        installer_state_set systemd_activation requires_vm
        return 2
    fi
    installer_enroll_panel_listener || return 1
    installer_enroll_software || {
        installer_error STAGE_FAILED "The restricted software helper could not be enrolled."
        return 1
    }
    systemctl daemon-reload
    security_runtime_permissions || return 1
    systemctl enable --now redis-rahandaz-queue.service redis-rahandaz-cache.service rahandaz-php-fpm.service rahandaz-scheduler.timer || {
        installer_error STAGE_FAILED "A Rahandaz unit did not become active."
        return 1
    }
    # Restart so the slice, scan dir, and ondemand pool apply to an already running master.
    systemctl restart rahandaz-php-fpm.service || {
        installer_error STAGE_FAILED "The panel PHP-FPM pool did not restart."
        return 1
    }
    mode="$(installer_env_get "$(installer_root_path /var/lib/rahandaz/current/.env)" RAHANDAZ_EXECUTION_MODE)"
    if [[ "$mode" == local ]]; then
        systemctl enable --now rahandaz-queue.service || {
            installer_error STAGE_FAILED "The queue worker did not become active."
            return 1
        }
    else
        systemctl disable --now rahandaz-queue.service || {
            installer_error STAGE_FAILED "The idle queue worker could not be stopped."
            return 1
        }
        installer_say "QUEUE worker is installed and stopped while execution mode is disabled."
    fi
    installer_quiesce_unused_defaults || {
        installer_error STAGE_FAILED "An unused distribution service could not be stopped."
        return 1
    }
    # APT may have started Nginx before the Rahandaz site existed.
    systemctl enable --now nginx || return 1
    systemctl reload nginx || {
        installer_error STAGE_FAILED "Nginx did not load the verified Rahandaz site."
        return 1
    }
    return 0
}

stage_13_first_boot() {
    local current creds
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN create the panel owner once and store the password only in /root/rahandaz-initial-credentials.txt."
        return 0
    fi
    if [[ "$(installer_state_get stage.10-database-migration)" != "succeeded" ]]; then
        return 2
    fi
    current="$(installer_root_path /var/lib/rahandaz/current)"
    creds="$(installer_root_path /root/rahandaz-initial-credentials.txt)"
    if [[ -s "$creds" ]]; then
        installer_say "Owner credential file already exists. It was not rewritten."
        return 0
    fi
    local tmp private_dir
    private_dir="$(mktemp -d)"
    chmod 0700 "$private_dir"
    tmp="$private_dir/owner.txt"
    # The CLI refuses an existing file; reserve a private directory, not the file.
    if ! (cd "$current" && "${INSTALLER_PHP_BIN}" artisan rahandaz:bootstrap-owner --credentials-file="$tmp" --no-interaction); then
        rm -rf -- "$private_dir"
        installer_error OWNER_FAILED "The initial owner was not created."
        return 1
    fi
    if [[ ! -s "$tmp" ]]; then
        rm -rf -- "$private_dir"
        installer_say "Owner already exists. No credential file was written."
        return 0
    fi
    mkdir -p "$(dirname "$creds")"
    mv "$tmp" "$creds"
    chmod 0600 "$creds"
    rmdir "$private_dir"
    installer_say "Owner credentials written to ${creds}. The password was not printed."
    return 0
}

stage_14_health_verification() {
    local base welcome recovery
    base="http://127.0.0.1:${HTTP_PORT}"
    recovery="$base"
    if [[ "${PANEL_PUBLIC_IPV4:-0}" == 1 ]]; then recovery=http://127.0.0.1:80; fi
    if [[ "$MODE" == "plan" ]]; then
        installer_say "PLAN healthcheck ${base}/up , ${base}/rahandaz/login , and the static welcome page."
        return 0
    fi
    if [[ "$(installer_sysroot)" != "/" || "${INSTALLER_OFFLINE:-}" == "1" ]]; then
        return 2
    fi
    # Resume must repair runtime access even when the systemd stage was already completed.
    security_runtime_permissions || {
        installer_error RUNTIME_PERMISSION_FAILED "Runtime storage or cache permissions could not be verified."
        return 1
    }
    installer_curl --fail --silent --show-error --max-time 15 --header "Host: localhost" "${base}/up" >/dev/null || {
        installer_error HEALTHCHECK_FAILED "Laravel /up did not succeed."
        return 1
    }
    installer_curl --fail --silent --show-error --max-time 15 --header "Host: localhost" "${base}/rahandaz/login" >/dev/null || {
        installer_error HEALTHCHECK_FAILED "The panel login URL did not succeed."
        return 1
    }
    if [[ "${PANEL_PUBLIC_IPV4:-0}" == 1 ]]; then
        /usr/bin/python3 -I /var/lib/rahandaz/current/infrastructure/operations/panel/verify.py --port "$HTTP_PORT" || {
            installer_error HEALTHCHECK_FAILED 'The public panel listener or restricted helper did not verify.'
            return 1
        }
        installer_curl --fail --silent --show-error --max-time 15 --header "Host: localhost" "${recovery}/up" >/dev/null || return 1
        installer_curl --fail --silent --show-error --max-time 15 --header "Host: localhost" "${recovery}/rahandaz/login" >/dev/null || return 1
    fi
    welcome="$(installer_root_path /var/lib/rahandaz/welcome/index.html)"
    security_welcome_permissions || return 1
    if [[ ! -f "${INSTALLER_HOME}/templates/welcome/index.html" ]]; then
        installer_error HEALTHCHECK_FAILED "The welcome template is not in this installer bundle."
        return 1
    fi
    local content
    content="$(cat "${INSTALLER_HOME}/templates/welcome/index.html")"
    content="${content//\{\{PANEL_URL\}\}/${PUBLIC_URL}/rahandaz/login}"
    content="${content//\{\{HEALTH_MARKER\}\}/verified}"
    printf '%s\n' "$content" >"$welcome"
    chown root:www-data "$welcome" || return 1
    chmod 0640 "$welcome" || return 1
    installer_curl --fail --silent --show-error --max-time 15 --header "Host: localhost" "${recovery}/" | grep -q 'data-health-marker="verified"' || {
        rm -f "$welcome"
        installer_error HEALTHCHECK_FAILED "The welcome page did not serve the verified marker."
        return 1
    }
    return 0
}

stage_15_installation_summary() {
    local id state failed=0 incomplete=0
    installer_say "Rahandaz Installer $(cat "${INSTALLER_HOME}/VERSION")"
    for id in \
        01-preflight 02-repository-setup 03-system-packages 04-runtime-installation \
        05-operational-account 06-application-release 07-postgresql-setup 08-redis-setup \
        09-laravel-configuration 10-database-migration 11-nginx-php-fpm 12-systemd-services \
        13-first-boot 14-health-verification; do
        state="$(installer_state_get "stage.${id}")"
        installer_say "STAGE ${id} ${state:-not_run}"
        [[ "$state" == "succeeded" || "$state" == "planned" ]] || incomplete=1
        [[ "$state" == "failed" ]] && failed=1
    done
    if [[ -n "$(installer_state_get selected_mirror)" ]]; then
        installer_say "MIRROR $(installer_state_get selected_mirror)"
    fi
    installer_say "LOG ${INSTALLER_LOG:-stderr}"
    installer_say "PANEL ${PUBLIC_URL}/rahandaz/login"
    installer_say "NEXT create or use the owner, then open the panel. Doctor: rahandaz doctor"
    installer_say "CREDENTIALS /root/rahandaz-initial-credentials.txt is mode 0600 and is not printed again."
    installer_say 'SYSTEM_CREDENTIALS /root/rahandaz-system-credentials.txt (root only). Reveal locally: sudo bash /usr/local/lib/rahandaz/bin/rahandaz-security --show-credentials'
    installer_say 'FIREWALL review separately with /usr/local/lib/rahandaz/bin/rahandaz-firewall --plan; activation requires a second SSH session.'
    if [[ "$failed" -eq 0 && "$incomplete" -eq 0 && "$MODE" != "plan" ]]; then
        installer_say "RESULT success"
        INSTALLER_RESULT="success"
        installer_state_remove error_code || return 1
    elif [[ "$MODE" == "plan" && "$failed" -eq 0 ]]; then
        installer_say "RESULT plan"
        INSTALLER_RESULT="plan"
    else
        installer_say "RESULT incomplete"
        INSTALLER_RESULT="incomplete"
    fi
    installer_state_set result "$INSTALLER_RESULT"
    if [[ "$INSTALLER_RESULT" == "incomplete" ]]; then
        return 2
    fi
    return 0
}
