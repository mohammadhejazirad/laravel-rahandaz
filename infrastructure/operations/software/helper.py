#!/usr/bin/python3 -I
"""Software capability v1. Fixed catalog, authenticated peer, durable asynchronous result."""
import fcntl
import json
import os
from pathlib import Path
import pwd
import grp
import re
import signal
import shutil
import socket
import struct
import subprocess
import sys
import tempfile
import time
import urllib.request

ROOT = Path('/var/lib/rahandaz/software')
RELEASE = Path('/var/lib/rahandaz/current')
UUID = r'[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}'
ENV = {'PATH': '/usr/sbin:/usr/bin:/sbin:/bin', 'LANG': 'C', 'LC_ALL': 'C',
       'DEBIAN_FRONTEND': 'noninteractive', 'HOME': '/root'}


class Refused(Exception):
    pass


def atomic(path, value, mode=0o600):
    fd, name = tempfile.mkstemp(dir=path.parent)
    try:
        with os.fdopen(fd, 'w') as file:
            json.dump(value, file)
            file.flush()
            os.fsync(file.fileno())
        os.chmod(name, mode)
        os.replace(name, path)
    finally:
        if os.path.exists(name):
            os.unlink(name)


def catalog():
    return json.loads(Path(__file__).with_name('catalog.json').read_text())['packages']


def trusted(path):
    for item in (path, *path.parents):
        if item.is_symlink() or item.stat().st_uid != 0 or item.stat().st_mode & 0o022:
            raise Refused('UNSAFE_CODE')


def trusted_release():
    # Only this root-owned activation symlink is allowed; all resolved code is immutable.
    trusted(RELEASE.parent)
    if RELEASE.lstat().st_uid != 0:
        raise Refused('UNSAFE_CODE')
    resolved = RELEASE.resolve(strict=True)
    trusted(resolved)
    for name in ('artisan', 'infrastructure/operations/software/authorize.php', 'bootstrap/app.php', 'vendor/autoload.php'):
        trusted(resolved/name)


def request(data):
    if not isinstance(data, dict) or set(data) != {'schema_version', 'action', 'operation', 'execution_id', 'packages'}:
        raise Refused('INVALID_CONTRACT')
    if data['schema_version'] != '1' or data['action'] not in ('start', 'status') or data['operation'] not in ('setup.initialize', 'software.install'):
        raise Refused('INVALID_CONTRACT')
    if not isinstance(data['execution_id'], str) or not re.fullmatch(UUID, data['execution_id']):
        raise Refused('INVALID_EXECUTION_ID')
    packages = data['packages']
    if not isinstance(packages, list) or len(packages) > 4 or any(not isinstance(p, str) or p not in catalog() for p in packages) or len(set(packages)) != len(packages):
        raise Refused('PACKAGE_NOT_ALLOWED')
    if data['operation'] == 'software.install' and not packages:
        raise Refused('PACKAGE_NOT_ALLOWED')
    return data


def service_identity():
    service = pwd.getpwnam('rahandaz-svc')
    return {'user': service.pw_uid, 'group': service.pw_gid, 'extra_groups': []}


def run(args, timeout=30, service=False):
    try:
        result = subprocess.run(args, env=ENV, cwd=RELEASE, capture_output=True, timeout=timeout, **(service_identity() if service else {}))
        if result.returncode:
            raise Refused('COMMAND_FAILED')
        return result.stdout.decode(errors='replace')
    except subprocess.TimeoutExpired:
        raise Refused('COMMAND_TIMEOUT') from None


def authorize(data):
    trusted_release()
    result = subprocess.run(['/usr/bin/php8.4',
                             str(RELEASE/'infrastructure/operations/software/authorize.php')],
                            input=json.dumps(data).encode(), env=ENV, cwd=RELEASE,
                            capture_output=True, timeout=10, **service_identity())
    try:
        proof = json.loads(result.stdout)
    except (ValueError, TypeError):
        raise Refused('AUTHORIZATION_CHECK_FAILED') from None
    if result.returncode or proof != {'schema_version': '1', 'authorized': True}:
        raise Refused('AUTHORIZATION_REVOKED')


def verify_services():
    # is-active with multiple names succeeds when ANY unit is active, not all.
    for unit in ('nginx', 'rahandaz-php-fpm', 'rahandaz-queue', 'rahandaz-scheduler.timer',
                 'redis-rahandaz-queue', 'redis-rahandaz-cache'):
        run(['/usr/bin/systemctl', 'is-active', '--quiet', unit])


def readiness():
    info = {}
    for line in Path('/etc/os-release').read_text().splitlines():
        if '=' in line:
            key, value = line.split('=', 1)
            info[key] = value.strip('"')
    if info.get('ID') != 'ubuntu' or info.get('VERSION_ID') not in ('22.04', '24.04'):
        raise Refused('OS_NOT_AVAILABLE')
    service = pwd.getpwnam('rahandaz-svc')
    if service.pw_uid == 0 or service.pw_shell not in ('/usr/sbin/nologin', '/bin/false'):
        raise Refused('SERVICE_ACCOUNT_UNSAFE')
    shadow = next((line.split(':')[1] for line in Path('/etc/shadow').read_text().splitlines() if line.startswith('rahandaz-svc:')), '')
    if not shadow.startswith(('!', '*')):
        raise Refused('SERVICE_ACCOUNT_UNSAFE')
    forbidden = {'sudo', 'admin', 'docker', 'lxd', 'disk', 'shadow'}
    if any(group.gr_name in forbidden and (service.pw_name in group.gr_mem or group.gr_gid == service.pw_gid) for group in grp.getgrall()):
        raise Refused('SERVICE_ACCOUNT_UNSAFE')
    for unit in ('rahandaz-php-fpm', 'rahandaz-queue'):
        if run(['/usr/bin/systemctl', 'show', '--property=NoNewPrivileges', '--value', unit]).strip() != 'yes':
            raise Refused('SERVICE_ACCOUNT_UNSAFE')
    verify_services()
    run(['/usr/bin/php8.4', 'artisan', 'rahandaz:doctor'], service=True)
    state = Path('/etc/rahandaz/panel-listener.json')
    port = json.loads(state.read_text())['port'] if state.is_file() else 80
    for route in ('/up', '/rahandaz/login'):
        with urllib.request.urlopen('http://127.0.0.1:'+str(int(port))+route, timeout=5) as response:
            if response.status != 200:
                raise Refused('PANEL_HEALTH_FAILED')
    return {'os': info['VERSION_ID'], 'control_plane_verified': True}


def installed(package):
    result = subprocess.run(['/usr/bin/dpkg-query', '-W', '-f=${db:Status-Status}\t${Version}', '--', package],
                            capture_output=True, env=ENV, timeout=5)
    fields = result.stdout.decode().split('\t')
    return fields[1] if result.returncode == 0 and len(fields) == 2 and fields[0] == 'installed' else None


def verify_tool(package):
    binary, option = {'htop': ('/usr/bin/htop', '--version'), 'jq': ('/usr/bin/jq', '--version'),
                      'ffmpeg': ('/usr/bin/ffmpeg', '-version'), 'unzip': ('/usr/bin/unzip', '-v')}[package]
    trusted(Path(binary))
    if not os.access(binary, os.X_OK) or not run([binary, option], 5).strip():
        raise Refused('PACKAGE_VERIFICATION_FAILED')


def apt_plan(preview):
    planned = {}
    for line in preview.splitlines():
        if line.startswith('Remv ') or (line.startswith('Inst ') and re.match(r'Inst \S+ \[', line)):
            raise Refused('PACKAGE_CONFLICT')
        if line.startswith('Inst '):
            match = re.match(r'Inst ([a-z0-9][a-z0-9+.:_-]*) \(([A-Za-z0-9.+:~_-]{1,120}) ', line)
            if not match:
                raise Refused('PACKAGE_VERSION_UNAVAILABLE')
            planned[match[1]] = match[2]
    return planned


def install(package, data):
    apt = catalog()[package]['apt']
    version = installed(apt)
    if version:
        verify_tool(package)
        return {'package': package, 'version': version, 'verified': True, 'changed': False}
    if shutil.disk_usage('/').free < 1024**3:
        raise Refused('INSUFFICIENT_DISK')
    options = ['--no-install-recommends', '--no-remove', '--no-upgrade', '-o', 'DPkg::Lock::Timeout=30',
               '-o', 'Dpkg::Options::=--force-confold']
    preview = run(['/usr/bin/apt-get', '--simulate', *options, 'install', apt])
    # Never upgrade/remove an existing package as a side effect of an optional tool.
    dependencies = apt_plan(preview)
    exact = dependencies.get(apt)
    if exact is None:
        raise Refused('PACKAGE_VERSION_UNAVAILABLE')
    if not re.fullmatch(r'[A-Za-z0-9.+:~_-]{1,120}', exact):
        raise Refused('PACKAGE_VERSION_UNAVAILABLE')
    # Download first. A retry never resolves a different version within this execution.
    plan = ROOT/data['execution_id']/'plan.json'
    planned = json.loads(plan.read_text()) if plan.exists() else {}
    planned[package] = dependencies
    atomic(plan, planned)
    pins = [name+'='+version for name, version in dependencies.items()]
    run(['/usr/bin/apt-get', '-y', '--download-only', *options, 'install', *pins], 90)
    authorize(data)
    if apt_plan(run(['/usr/bin/apt-get', '--simulate', *options, 'install', *pins])) != dependencies:
        raise Refused('PACKAGE_PLAN_CHANGED')
    # Hold the real dpkg lock through APT; never remove lock files or repair foreign transactions.
    run(['/usr/bin/apt-get', '-y', *options, 'install', *pins], 90)
    version = installed(apt)
    if version != exact:
        raise Refused('PACKAGE_VERIFICATION_FAILED')
    verify_tool(package)
    return {'package': package, 'version': version, 'verified': True, 'changed': True}


def response(status, **values):
    return {'schema_version': '1', 'status': status, **values}


def same_request(left, right):
    return {k: v for k, v in left.items() if k != 'action'} == {k: v for k, v in right.items() if k != 'action'}


def alive(state):
    try:
        return (Path('/proc/sys/kernel/random/boot_id').read_text().strip() == state['boot_id']
                and Path('/proc/'+str(state['pid'])+'/stat').read_text().split()[21] == state['pid_start'])
    except (OSError, KeyError):
        return False


def main():
    connection = None
    lock = None
    try:
        if os.geteuid() != 0 or len(sys.argv) != 1:
            raise Refused('PRIVILEGE_REFUSED')
        connection = socket.socket(fileno=os.dup(0))
        connection.settimeout(3)
        _, uid, _ = struct.unpack('3i', connection.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, 12))
        if uid != pwd.getpwnam('rahandaz-svc').pw_uid:
            raise Refused('PEER_REFUSED')
        raw = bytearray()
        while len(raw) <= 4096:
            part = connection.recv(4097-len(raw))
            if not part:
                break
            raw.extend(part)
        data = request(json.loads(raw))
        trusted(Path(__file__))
        trusted(Path(__file__).with_name('catalog.json'))
        trusted_release()
        trusted(ROOT.parent)
        ROOT.mkdir(mode=0o700, exist_ok=True)
        if ROOT.is_symlink() or ROOT.stat().st_uid != 0 or ROOT.stat().st_mode & 0o077:
            raise Refused('UNSAFE_STATE')
        directory = ROOT/data['execution_id']
        if directory.is_symlink():
            raise Refused('UNSAFE_STATE')
        if directory.exists():
            trusted(directory)
            if not (directory/'request.json').is_file() or not (directory/'result.json').is_file():
                connection.sendall(json.dumps(response('running')).encode())
                return
            original = json.loads((directory/'request.json').read_text())
            if not same_request(original, data):
                raise Refused('REQUEST_CONFLICT')
            state = json.loads((directory/'result.json').read_text())
            if state['status'] == 'running' and not alive(state):
                state = response('failed', error_code='INSTALL_INTERRUPTED')
                atomic(directory/'result.json', state)
            state = {key: value for key, value in state.items() if key not in ('pid', 'pid_start', 'boot_id')}
            connection.sendall(json.dumps(state).encode())
            return
        if data['action'] == 'status':
            raise Refused('EXECUTION_NOT_FOUND')
        authorize(data)
        lock = (ROOT/'lock').open('a')
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise Refused('SOFTWARE_BUSY') from None
        directory.mkdir(mode=0o700)
        atomic(directory/'request.json', data)
        atomic(directory/'result.json', response('running', pid=os.getpid(),
               boot_id=Path('/proc/sys/kernel/random/boot_id').read_text().strip(),
               pid_start=Path('/proc/self/stat').read_text().split()[21]))
        # Acknowledge before lengthy work; the control worker and HTTP remain responsive.
        connection.sendall(json.dumps(response('running')).encode())
        connection.shutdown(socket.SHUT_WR)
        connection.close()
        connection = None
        def interrupted(signum, frame):
            raise Refused('INSTALL_INTERRUPTED')
        signal.signal(signal.SIGTERM, interrupted)
        evidence = []
        try:
            verified = readiness()
            if run(['/usr/bin/dpkg', '--audit']).strip():
                raise Refused('PACKAGE_STATE_UNSAFE')
            for package in data['packages']:
                authorize(data)
                evidence.append(install(package, data))
                atomic(directory/'packages.json', evidence)
            authorize(data)
            readiness()
            result = response('succeeded', execution_id=data['execution_id'], operation=data['operation'], verified_at=int(time.time()), verified=True, **verified, packages=evidence)
        except Exception as error:
            result = response('failed', error_code=str(error) if isinstance(error, Refused) else 'SOFTWARE_INSTALL_FAILED', packages=evidence)
        atomic(directory/'result.json', result)
    except Exception as error:
        if connection is not None:
            connection.sendall(json.dumps(response('failed', error_code=str(error) if isinstance(error, Refused) else 'INVALID_INPUT')).encode())
    finally:
        if connection is not None:
            connection.close()
        if lock is not None:
            lock.close()


if __name__ == '__main__':
    main()
