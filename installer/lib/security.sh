#!/usr/bin/env bash
# Bootstrap security contract v1. Caller holds installer/prerequisite lock.
# Secrets never enter argv, audit, stdout or application responses.
[[ -z "${INSTALLER_LIB_SECURITY:-}" ]] || return 0
INSTALLER_LIB_SECURITY=1

security_password_once() {
    python3 - "$1" <<'PY'
import os, pathlib, secrets, stat, string, sys
p = pathlib.Path(sys.argv[1])
p.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
if p.is_symlink():
    raise SystemExit('SECRET_PATH_CONFLICT')
if p.exists():
    s = p.stat()
    value = p.read_text().removesuffix('\n')
    if not value or any(ord(c) < 33 or ord(c) > 126 for c in value):
        raise SystemExit('SECRET_FORMAT_CONFLICT')
    if not stat.S_ISREG(s.st_mode) or s.st_uid != os.geteuid() or s.st_mode & 0o077 or not p.read_text().strip():
        raise SystemExit('SECRET_PERMISSION_CONFLICT')
else:
    alphabet = string.ascii_letters + string.digits + '!@%_+-'
    while True:
        password = ''.join(secrets.choice(alphabet) for _ in range(12))
        if all(any(c in group for c in password) for group in (string.ascii_lowercase, string.ascii_uppercase, string.digits, '!@%_+-')):
            break
    # Publish only a fully written secret, atomically; interrupted temp files are never adopted.
    import tempfile
    fd, name = tempfile.mkstemp(dir=p.parent, prefix='.password-')
    try:
        with os.fdopen(fd, 'w') as f:
            f.write(password + '\n'); f.flush(); os.fsync(f.fileno())
        os.link(name, p)
    finally:
        os.unlink(name)
PY
}

security_lock() {
    if [[ "$(readlink "/proc/$$/fd/7" 2>/dev/null || true)" == /var/lib/rahandaz-bootstrap-security-v1/lock ]]; then
        flock -n 7 || return 1
        return 0
    fi
    install -d -m 0700 /var/lib/rahandaz-bootstrap-security-v1 || return 1
    exec 7>/var/lib/rahandaz-bootstrap-security-v1/lock
    flock -n 7 || { installer_error INSTALLER_BUSY 'Another security bootstrap is running.'; return 1; }
}

security_accounts() {
    local state=/var/lib/rahandaz-bootstrap-security-v1 shell groups
    security_lock || return 1
    mkdir -p "$state"
    chmod 0700 "$state"
    # Never promote an existing service account or adopt an unrelated login.
    if id rahandaz >/dev/null 2>&1 && [[ ! -f "$state/admin.intent" ]]; then
        installer_error ACCOUNT_CONFLICT 'Existing rahandaz account requires an explicit migration; it will not be promoted.'; return 1
    fi
    if id rahandaz-svc >/dev/null 2>&1; then
        [[ -f "$state/service.intent" && "$(id -u rahandaz-svc)" != 0 && "$(getent passwd rahandaz-svc | cut -d: -f6)" == /var/lib/rahandaz ]] || {
            installer_error ACCOUNT_CONFLICT 'Existing service identity requires explicit migration.'; return 1;
        }
        shell=$(getent passwd rahandaz-svc | cut -d: -f7)
        groups=" $(id -nG rahandaz-svc) "
        [[ "$shell" == /usr/sbin/nologin && "$groups" != *' sudo '* && "$groups" != *' adm '* && "$groups" != *' docker '* ]] || {
            installer_error ACCOUNT_CONFLICT 'Service identity is not restricted.'; return 1;
        }
    else
        : >"$state/service.intent"
        useradd --system --create-home --home-dir /var/lib/rahandaz --shell /usr/sbin/nologin --user-group rahandaz-svc || return 1
        passwd -l rahandaz-svc >/dev/null || return 1
    fi
    [[ "$(passwd -S rahandaz-svc | awk '{print $2}')" == L ]] || {
        installer_error ACCOUNT_CONFLICT 'Service password must remain locked.'; return 1;
    }
    chown root:root /var/lib/rahandaz || return 1
    chmod 0755 /var/lib/rahandaz || return 1
    command -v sudo >/dev/null || { installer_error MISSING_DEPENDENCY 'Install the sudo package before account setup.'; return 1; }
    security_password_once "$state/ubuntu.password" || return 1
    # Persist intent before useradd, so a disconnected run can resume without rotating a password.
    : >"$state/admin.intent"
    if ! id rahandaz >/dev/null 2>&1; then
        useradd --create-home --home-dir /home/rahandaz --shell /bin/bash --user-group rahandaz || return 1
    fi
    [[ "$(getent passwd rahandaz | cut -d: -f6-7)" == '/home/rahandaz:/bin/bash' ]] || {
        installer_error ACCOUNT_CONFLICT 'Administrator home or shell differs from the managed contract.'; return 1;
    }
    [[ "$(id -u rahandaz)" != 0 ]] || return 1
    if [[ ! -f "$state/admin.password-applied" ]]; then
        { printf 'rahandaz:'; cat "$state/ubuntu.password"; } | chpasswd || return 1
        : >"$state/admin.password-applied"
    fi
    local policy=/etc/sudoers.d/99-rahandaz-admin policy_tmp
    if [[ -e "$policy" ]] && ! grep -qx '# rahandaz-managed: administrator-v1' "$policy"; then
        installer_error SUDO_CONFLICT 'Existing administrator sudo policy requires review.'; return 1
    fi
    if [[ ! -e "$policy" ]]; then
        policy_tmp=$(mktemp /etc/sudoers.d/.rahandaz.XXXXXX) || return 1
        printf '%s\n' '# rahandaz-managed: administrator-v1' 'rahandaz ALL=(ALL:ALL) PASSWD: ALL' >"$policy_tmp"
        chmod 0440 "$policy_tmp"
        if ! visudo -cf "$policy_tmp" >/dev/null; then rm -f "$policy_tmp"; return 1; fi
        mv "$policy_tmp" "$policy" || return 1
    fi
    [[ ! -L "$policy" && "$(cat "$policy")" == $'# rahandaz-managed: administrator-v1\nrahandaz ALL=(ALL:ALL) PASSWD: ALL' ]] || {
        installer_error SUDO_CONFLICT 'Managed sudo policy drift requires review.'; return 1;
    }
    usermod -aG sudo rahandaz || return 1
    visudo -c >/dev/null || { installer_error SUDO_INVALID 'The sudo policy is invalid.'; return 1; }
    # SSH policy remains unchanged. Operator must verify a second session before closing root.
}

security_credentials() {
    local root="${1:-}" reveal="${2:-no}"
    python3 - "$root" "$reveal" <<'PY' || return 1
import os, pathlib, sys, tempfile
root = pathlib.Path(sys.argv[1] or '/')
items = [('Ubuntu SSH/sudo', 'var/lib/rahandaz-bootstrap-security-v1/ubuntu.password'),
         ('PostgreSQL', 'etc/rahandaz/database.password'),
         ('Redis queue', 'etc/rahandaz/redis-queue.password'),
         ('Redis cache', 'etc/rahandaz/redis-cache.password')]
lines = ['Rahandaz bootstrap credentials (private; store in a password manager).',
         'Username: rahandaz (fixed). Passwords are independent.',
         'SSH access depends on existing sshd policy; it has not been weakened.',
         'This file records initial passwords; subsequent rotations may make it stale.', '']
for label, relative in items:
    p = root / relative
    if p.is_file() and not p.is_symlink():
        lines.append(label + ' password: ' + p.read_text().strip())
out = root / 'root/rahandaz-system-credentials.txt'
out.parent.mkdir(parents=True, exist_ok=True)
if out.is_symlink():
    raise SystemExit('CREDENTIAL_PATH_CONFLICT')
fd, name = tempfile.mkstemp(dir=out.parent, prefix='.credentials-')
with os.fdopen(fd, 'w') as f:
    f.write('\n'.join(lines) + '\n'); f.flush(); os.fsync(f.fileno())
os.replace(name, out)
if sys.argv[2] == 'yes':
    # Deliberate local terminal disclosure only. No tee, log, stdout or web route.
    with open('/dev/tty', 'w') as tty:
        tty.write('\n' + '\n'.join(lines) + '\n\n')
PY
    installer_say 'Private credentials: /root/rahandaz-system-credentials.txt (root only, mode 0600).'
}

security_redis_config() {
    local kind="$1" port="$2" conf="/etc/redis/redis-rahandaz-$1.conf" data="/var/lib/redis-rahandaz-$1" secret="/etc/rahandaz/redis-$1.password"
    if [[ -e "$conf" ]] && ! grep -qx '# rahandaz-managed: redis-security-v1' "$conf"; then
        installer_error REDIS_CONFLICT 'Existing Redis configuration requires explicit migration.'; return 1
    fi
    if [[ ! -e "$conf" ]] && ss -H -ltn "sport = :$port" | grep -q .; then
        installer_error PORT_CONFLICT "Redis port $port is already occupied."; return 1
    fi
    security_password_once "$secret" || return 1
    install -d -o redis -g redis -m 0750 "$data" || return 1
    # Passwords are SHA256 ACL verifiers, never plaintext in Redis config.
    python3 - "$kind" "$port" "$secret" "$conf" "$data" <<'PY'
import hashlib, pathlib, sys, tempfile, os, grp
kind, port, secret, target, data = sys.argv[1:]
h = hashlib.sha256(pathlib.Path(secret).read_text().strip().encode()).hexdigest()
text = f'''# rahandaz-managed: redis-security-v1
bind 127.0.0.1
protected-mode yes
port {port}
supervised systemd
daemonize no
dir {data}
user default off
user rahandaz on #{h} ~* +@all
'''
text += 'appendonly yes\nappendfsync everysec\nmaxmemory-policy noeviction\n' if kind == 'queue' else 'appendonly no\nsave ""\nmaxmemory 128mb\nmaxmemory-policy allkeys-lru\n'
p = pathlib.Path(target)
# Do not rewrite a running instance's configuration on rerun or undo operator rotation.
if p.exists():
    if p.read_text() != text:
        raise SystemExit('REDIS_CONFIG_DRIFT: explicit reconciliation required')
else:
    fd, tmp = tempfile.mkstemp(dir=p.parent)
    with os.fdopen(fd, 'w') as f:
        f.write(text); f.flush(); os.fsync(f.fileno())
    os.chown(tmp, 0, grp.getgrnam('redis').gr_gid); os.chmod(tmp, 0o640)
    os.replace(tmp, p)
PY
}

security_datastore_health() {
    python3 - <<'PY'
import pathlib, socket
for kind, port in [('queue', 6381), ('cache', 6380)]:
    password = pathlib.Path('/etc/rahandaz/redis-' + kind + '.password').read_bytes().strip()
    def command(*args):
        return b'*%d\r\n' % len(args) + b''.join(b'$%d\r\n' % len(a) + a + b'\r\n' for a in args)
    with socket.create_connection(('127.0.0.1', port), timeout=5) as s:
        f = s.makefile('rb')
        s.sendall(command(b'PING'))
        if not f.readline().startswith(b'-NOAUTH'):
            raise SystemExit('REDIS_UNAUTHENTICATED_ACCESS')
        s.sendall(command(b'AUTH', b'rahandaz', password))
        if f.readline() != b'+OK\r\n':
            raise SystemExit('REDIS_AUTH_FAILED')
        s.sendall(command(b'PING'))
        if f.readline() != b'+PONG\r\n':
            raise SystemExit('REDIS_HEALTH_FAILED')
PY
}

security_postgres_health() {
    python3 - <<'PY'
import os, pathlib, subprocess, tempfile
password = pathlib.Path('/etc/rahandaz/database.password').read_text().strip()
fd, name = tempfile.mkstemp()
try:
    with os.fdopen(fd, 'w') as f:
        f.write('127.0.0.1:5432:rahandaz:rahandaz:' + password.replace('\\', '\\\\').replace(':', '\\:') + '\n')
    env = os.environ.copy()
    env.pop('PGPASSWORD', None)
    env['PGPASSFILE'] = name
    result = subprocess.run(['psql', '-X', '-w', '-h', '127.0.0.1', '-U', 'rahandaz', '-d', 'rahandaz', '-tAc',
        "SELECT current_user='rahandaz' AND NOT rolsuper AND NOT rolcreatedb AND NOT rolcreaterole AND NOT rolreplication AND EXISTS (SELECT 1 FROM pg_database WHERE datname=current_database() AND datdba=pg_roles.oid) FROM pg_roles WHERE rolname=current_user"],
        env=env, capture_output=True, text=True, timeout=15)
    if result.returncode or result.stdout.strip() != 't':
        raise SystemExit('POSTGRES_AUTH_OR_PRIVILEGE_FAILED')
finally:
    os.unlink(name)
PY
}

security_redis_units() {
    local kind target
    for kind in queue cache; do
        target="/etc/systemd/system/redis-rahandaz-$kind.service"
        if [[ -e "$target" ]] && ! grep -qx '# rahandaz-managed: redis-security-v1' "$target"; then
            installer_error REDIS_UNIT_CONFLICT 'Existing unit requires explicit migration.'; return 1
        fi
        if [[ ! -e "$target" ]]; then
            cat >"$target" <<UNIT
# rahandaz-managed: redis-security-v1
[Unit]
Description=Rahandaz Redis $kind
After=network.target
[Service]
Type=notify
User=redis
Group=redis
ExecStart=/usr/bin/redis-server /etc/redis/redis-rahandaz-$kind.conf
Restart=on-failure
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
PrivateTmp=true
ReadWritePaths=/var/lib/redis-rahandaz-$kind
[Install]
WantedBy=multi-user.target
UNIT
            chmod 0644 "$target"
        fi
    done
    systemctl daemon-reload || return 1
    systemctl enable --now redis-rahandaz-queue.service redis-rahandaz-cache.service || return 1
    security_datastore_health
}

security_runtime_permissions() {
    # Code/configuration stays root-owned. Service writes only shared runtime data/logs.
    local release
    release=$(readlink -f /var/lib/rahandaz/current) || return 1
    [[ "$release" == /var/lib/rahandaz/releases/* && -f "$release/artisan" ]] || return 1
    chown root:root /var/lib/rahandaz /var/lib/rahandaz/releases "$release" || return 1
    chmod 0755 /var/lib/rahandaz /var/lib/rahandaz/releases "$release" || return 1
    setfacl -R -m u:rahandaz-svc:rX "$release" || return 1
    # Nginx receives only traversal through the release and read access to public assets.
    setfacl -m u:www-data:x "$release" || return 1
    setfacl -R -m u:www-data:rX "$release/public" || return 1
    install -d -o rahandaz-svc -g rahandaz-svc -m 0750 /var/lib/rahandaz/shared/storage /var/log/rahandaz || return 1
    chown -R rahandaz-svc:rahandaz-svc /var/lib/rahandaz/shared/storage "$release/bootstrap/cache" || return 1
    chgrp rahandaz-svc /etc/rahandaz || return 1
    chmod 0750 /etc/rahandaz || return 1
    local file
    for file in /etc/rahandaz/master.key /etc/rahandaz/canary.json; do
        [[ ! -f "$file" ]] || { chown root:rahandaz-svc "$file" && chmod 0640 "$file"; } || return 1
    done
}
