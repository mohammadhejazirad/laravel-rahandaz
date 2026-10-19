#!/usr/bin/python3 -I
"""Software capability v1. Fixed catalog, authenticated peer, durable asynchronous result."""
import fcntl
import hashlib
import platform
import tarfile
import zipfile
import stat
import contextlib
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
import urllib.error

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
    if not isinstance(packages, list) or len(packages) > 32 or any(not isinstance(p, str) or p not in catalog() for p in packages) or len(set(packages)) != len(packages):
        raise Refused('PACKAGE_NOT_ALLOWED')
    if data['operation'] == 'setup.initialize' and any(not catalog()[p].get('setup', False) for p in packages):
        raise Refused('PACKAGE_NOT_ALLOWED')
    if data['operation'] == 'software.install' and not packages:
        raise Refused('PACKAGE_NOT_ALLOWED')
    return data


def service_identity():
    service = pwd.getpwnam('rahandaz-svc')
    return {'user': service.pw_uid, 'group': service.pw_gid, 'extra_groups': []}


def run(args, timeout=30, service=False):
    try:
        process = subprocess.Popen(args, env=ENV | ({'HOME': '/var/lib/rahandaz'} if service else {}), cwd=RELEASE,
                                   stdout=subprocess.PIPE, stderr=subprocess.PIPE, start_new_session=True,
                                   **(service_identity() if service else {}))
        try:
            stdout, stderr = process.communicate(timeout=timeout)
        except subprocess.TimeoutExpired:
            os.killpg(process.pid, signal.SIGKILL)
            process.communicate(timeout=5)
            raise Refused('COMMAND_TIMEOUT') from None
        result = subprocess.CompletedProcess(args, process.returncode, stdout, stderr)
        if result.returncode:
            detail = (result.stderr + result.stdout).decode(errors='replace').lower()
            if args[0] == '/usr/bin/apt-get':
                if any(word in detail for word in ('could not get lock', 'unable to acquire', 'is another process using')):
                    raise Refused('PACKAGE_MANAGER_BUSY')
                if any(word in detail for word in ('temporary failure resolving', 'could not resolve', 'connection timed out', 'failed to fetch', 'network is unreachable', 'connection failed')):
                    raise Refused('PACKAGE_DOWNLOAD_FAILED')
                if any(word in detail for word in ('not authenticated', 'unauthenticated', 'no_pubkey', 'hash sum mismatch', 'invalid signature')):
                    raise Refused('PACKAGE_SIGNATURE_INVALID')
                if any(word in detail for word in ('unable to locate package', 'has no installation candidate', 'version') ) and ('not found' in detail or 'no installation candidate' in detail or 'unable to locate' in detail):
                    raise Refused('PACKAGE_VERSION_UNAVAILABLE')
                if any(word in detail for word in ('unmet dependencies', 'held broken packages', 'conflicts:', 'but it is not installable')):
                    raise Refused('PACKAGE_CONFLICT')
                raise Refused('PACKAGE_COMMAND_FAILED')
            raise Refused('COMMAND_FAILED')
        return (result.stdout + (result.stderr if args[0] != '/usr/bin/apt-get' else b'')).decode(errors='replace')
    except subprocess.TimeoutExpired:
        raise Refused('COMMAND_TIMEOUT') from None


def authorize(data):
    trusted_release()
    try:
        result = subprocess.run(['/usr/bin/php8.4',
                             str(RELEASE/'infrastructure/operations/software/authorize.php')],
                            input=json.dumps(data).encode(), env=ENV, cwd=RELEASE,
                            capture_output=True, timeout=10, **service_identity())
    except (OSError, subprocess.SubprocessError):
        # Do not expose subprocess stderr or input, which may contain private data.
        raise Refused('AUTHORIZATION_CHECK_FAILED') from None
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


def trusted_binary(binary):
    path = Path(binary)
    # Ubuntu packages may provide root-owned alternative/wrapper symlinks.
    if path.is_symlink():
        trusted(path.parent)
        if path.lstat().st_uid != 0:
            raise Refused('UNSAFE_CODE')
        path = path.resolve(strict=True)
    trusted(path)
    if not os.access(path, os.X_OK):
        raise Refused('PACKAGE_VERIFICATION_FAILED')
    return str(path)


def verify_optional_service(unit):
    try:
        run(['/usr/bin/systemctl', 'is-active', '--quiet', unit])
    except Refused:
        raise Refused('PACKAGE_SERVICE_FAILED') from None


def verify_tool(package):
    entry = catalog()[package]
    binary = trusted_binary(entry['executable_path'])
    output = run([binary, *entry['version_args']], 15, service=True).strip()
    if not output:
        raise Refused('PACKAGE_VERIFICATION_FAILED')
    if entry.get('version') and entry['version'] not in output:
        raise Refused('PACKAGE_VERIFICATION_FAILED')
    if entry.get('service'):
        verify_optional_service(entry['service'])
    return output


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


def preserve_installed_versions(data):
    # APT otherwise prefers newer dependency candidates even with --no-upgrade.
    # Private preferences preserve installed versions without changing /etc/apt.
    directory = ROOT/data['execution_id']
    preference = directory/'installed-versions.pref'
    current = Path('/etc/apt/preferences')
    content = current.read_text()+'\n' if current.is_file() else ''
    result = subprocess.run(['/usr/bin/dpkg-query', '-W', '-f=${binary:Package}\t${Version}\t${db:Status-Status}\n'],
                            env=ENV, capture_output=True, timeout=10, check=True)
    for line in result.stdout.decode().splitlines():
        parts = line.split('\t')
        if len(parts) == 3 and parts[2] == 'installed':
            name, version = parts[:2]
            if not re.fullmatch(r'[a-z0-9][a-z0-9+.:_-]{0,160}', name) or not re.fullmatch(r'[A-Za-z0-9.+:~_-]{1,120}', version):
                raise Refused('PACKAGE_STATE_UNSAFE')
            content += 'Package: '+name+'\nPin: version '+version+'\nPin-Priority: 10001\n\n'
    fd, temporary = tempfile.mkstemp(dir=directory)
    try:
        with os.fdopen(fd, 'w') as output:
            output.write(content)
        os.replace(temporary, preference)
    finally:
        if os.path.exists(temporary): os.unlink(temporary)
    return preference


def install(package, data, progress=lambda phase: None, dependency=None):
    entry = catalog()[package] if dependency is None else {"kind": "apt", "apt": dependency}
    if entry['kind'] in ('archive', 'chrome'):
        return install_archive(package, data, progress)
    apt = entry['apt']
    version = installed(apt)
    if version:
        progress('verification')
        if dependency is not None:
            return
        verify_tool(package)
        return {'package': package, 'version': version, 'verified': True, 'changed': False, 'executable_path': entry['executable_path']}
    if entry.get('protected'):
        raise Refused('CORE_SERVICE_MISSING')
    for conflict in entry.get('conflicts', []):
        if installed(conflict):
            raise Refused('SERVICE_CONFLICT')
    if entry.get('port'):
        with socket.socket() as probe:
            try:
                probe.bind(('0.0.0.0', entry['port']))
            except OSError:
                raise Refused('PORT_IN_USE') from None
    if shutil.disk_usage('/').free < 1024**3:
        raise Refused('INSUFFICIENT_DISK')
    options = ['--no-install-recommends', '--no-remove', '--no-upgrade', '-o', 'DPkg::Lock::Timeout=30',
               '-o', 'Dpkg::Options::=--force-confold',
               '-o', 'Dir::Etc::preferences='+str(preserve_installed_versions(data))]
    progress('dependency_plan')
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
    planned[dependency or package] = dependencies
    atomic(plan, planned)
    pins = [name+'='+version for name, version in dependencies.items()]
    progress('download')
    run(['/usr/bin/apt-get', '-y', '--download-only', *options, 'install', *pins], 90)
    authorize(data)
    if apt_plan(run(['/usr/bin/apt-get', '--simulate', *options, 'install', *pins])) != dependencies:
        raise Refused('PACKAGE_PLAN_CHANGED')
    # Hold the real dpkg lock through APT; never remove lock files or repair foreign transactions.
    progress('installation')
    run(['/usr/bin/apt-get', '-y', *options, 'install', *pins], 180)
    progress('verification')
    version = installed(apt)
    if version != exact:
        raise Refused('PACKAGE_VERIFICATION_FAILED')
    if dependency is not None:
        return
    verify_tool(package)
    return {'package': package, 'version': version, 'verified': True, 'changed': True, 'executable_path': entry['executable_path']}


def os_version():
    values = dict(line.split('=', 1) for line in Path('/etc/os-release').read_text().splitlines() if '=' in line)
    return values.get('VERSION_ID', '').strip('"')


def download_archive_once(entry, output):
    # URLs and hashes are exclusively from the root-owned signed catalog.
    urls = ([entry['base_url']+'chrome.zip.part-'+str(i).zfill(3) for i in range(entry['parts'])]
            if 'parts' in entry else [entry['url']])
    digest = hashlib.sha256()
    size = 0
    deadline = time.monotonic()+300
    class HttpsRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, req, fp, code, msg, headers, newurl):
            if not newurl.startswith('https://'):
                raise Refused('DOWNLOAD_FAILED')
            return super().redirect_request(req, fp, code, msg, headers, newurl)
    opener = urllib.request.build_opener(HttpsRedirect())
    try:
        with output.open('xb') as target:
            for url in urls:
                if not url.startswith('https://'):
                    raise Refused('DOWNLOAD_FAILED')
                with opener.open(url, timeout=30) as source:
                    while True:
                        chunk = source.read(1024*1024)
                        if not chunk:
                            break
                        size += len(chunk)
                        if size > 600*1024**2 or time.monotonic() > deadline:
                            raise Refused('DOWNLOAD_FAILED')
                        digest.update(chunk)
                        target.write(chunk)
    except urllib.error.HTTPError as error:
        raise Refused('DOWNLOAD_ACCESS_DENIED' if error.code in (401, 403) else 'DOWNLOAD_RATE_LIMITED' if error.code == 429 else 'DOWNLOAD_FAILED') from None
    except (OSError, ValueError):
        raise Refused('DOWNLOAD_FAILED') from None
    if digest.hexdigest() != entry['sha256']:
        raise Refused('CHECKSUM_MISMATCH')


def download_archive(entry, output):
    for attempt in range(2):
        try:
            return download_archive_once(entry, output)
        except Refused as error:
            if str(error) not in ('DOWNLOAD_FAILED', 'DOWNLOAD_ACCESS_DENIED', 'DOWNLOAD_RATE_LIMITED') or attempt == 1 or any(not url.startswith('https://') for url in ([entry['url']] if 'url' in entry else [entry['base_url']])):
                raise
            output.unlink(missing_ok=True)
            time.sleep(2)


def safe_extract(archive, destination, entry):
    # Extract manually: never let a link redirect a later archive member.
    root = entry['archive_root']
    links = []
    size = 0
    seen = set()
    def path_for(name):
        parts = name.rstrip('/').split('/')
        if not parts or parts[0] != root or any(p in ('', '.', '..') for p in parts):
            raise Refused('ARCHIVE_UNSAFE')
        return destination.joinpath(*parts)
    def write(name, mode, length, source, directory=False, link=None):
        nonlocal size
        path = path_for(name)
        if path in seen:
            raise Refused('ARCHIVE_UNSAFE')
        seen.add(path)
        size += length
        if len(seen) > 50000 or size > 2*1024**3:
            raise Refused('ARCHIVE_UNSAFE')
        if link is not None:
            if link.startswith('/') or not (path.parent/link).resolve().is_relative_to(destination/root):
                raise Refused('ARCHIVE_UNSAFE')
            links.append((path, link))
            return
        path.parent.mkdir(parents=True, exist_ok=True, mode=0o755)
        if directory:
            path.mkdir(exist_ok=True, mode=0o755)
        else:
            with path.open('xb') as output:
                shutil.copyfileobj(source, output, 1024*1024)
            path.chmod(0o755 if mode & 0o111 else 0o644)
    if entry['format'] == 'zip':
        with zipfile.ZipFile(archive) as items:
            for item in items.infolist():
                mode = item.external_attr >> 16
                if stat.S_ISLNK(mode) or stat.S_IFMT(mode) not in (0, stat.S_IFREG, stat.S_IFDIR):
                    raise Refused('ARCHIVE_UNSAFE')
                with items.open(item) as source:
                    write(item.filename, mode, item.file_size, source, directory=item.is_dir())
    else:
        with tarfile.open(archive, 'r:*') as items:
            for item in items:
                if not (item.isfile() or item.isdir() or item.issym()):
                    raise Refused('ARCHIVE_UNSAFE')
                with contextlib.ExitStack() as stack:
                    source = stack.enter_context(items.extractfile(item)) if item.isfile() else None
                    write(item.name, item.mode, item.size, source, directory=item.isdir(), link=item.linkname if item.issym() else None)
    for path in destination.rglob('*'):
        if path.is_dir() and not path.is_symlink():
            path.chmod(0o755)
    for path, target in links:
        if any(parent.is_symlink() for parent in path.parents if parent.is_relative_to(destination)) or path.exists():
            raise Refused('ARCHIVE_UNSAFE')
        path.parent.mkdir(parents=True, exist_ok=True, mode=0o755)
        path.symlink_to(target)
    # Resolve again after every link exists, including indirect link chains.
    for path, _ in links:
        if not path.resolve().is_relative_to(destination/root):
            raise Refused('ARCHIVE_UNSAFE')


def chrome_sandbox_policy(binary, data):
    restriction = Path('/proc/sys/kernel/apparmor_restrict_unprivileged_userns')
    if not restriction.exists() or restriction.read_text().strip() != '1':
        return
    if os_version() != '24.04' or not Path('/usr/sbin/apparmor_parser').is_file():
        raise Refused('SANDBOX_UNAVAILABLE')
    # Ubuntu restricts user namespaces for unprofiled applications. Grant only this
    # immutable, pinned executable the same userns allowance as packaged browsers.
    profile = Path('/etc/apparmor.d/rahandaz-chrome')
    text = '# rahandaz-managed: chrome-sandbox-v1\nabi <abi/4.0>,\ninclude <tunables/global>\nprofile rahandaz-chrome '+binary+' flags=(unconfined) {\n  userns,\n}\n'
    trusted(profile.parent)
    if profile.exists() or profile.is_symlink():
        trusted(profile)
        if profile.read_text() != text:
            raise Refused('SANDBOX_POLICY_CONFLICT')
    else:
        authorize(data)
        fd = os.open(profile, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o644)
        with os.fdopen(fd, 'w') as output:
            output.write(text)
    run(['/usr/sbin/apparmor_parser', '--skip-kernel-load', profile.as_posix()], 15)
    authorize(data)
    run(['/usr/sbin/apparmor_parser', '--replace', profile.as_posix()], 15)


def chrome_render(binary):
    with tempfile.TemporaryDirectory(prefix='rahandaz-render-', dir='/var/tmp') as name:
        folder = Path(name)
        identity = service_identity()
        os.chown(folder, identity['user'], identity['group'])
        for extension, option, magic in [('png', '--screenshot=', b'\x89PNG\r\n\x1a\n'), ('pdf', '--print-to-pdf=', b'%PDF-')]:
            output = folder/('verified.'+extension)
            args = [binary, '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
                    '--disable-background-networking', '--user-data-dir='+str(folder/'profile'),
                    option+str(output), 'data:text/html,<html><body>Rahandaz verification</body></html>']
            try:
                run(args, 45, service=True)
            except Refused:
                raise Refused('RENDER_VERIFICATION_FAILED') from None
            if not output.is_file() or output.is_symlink() or output.stat().st_size < 100 or output.read_bytes()[:len(magic)] != magic:
                raise Refused('RENDER_VERIFICATION_FAILED')


def mongo_smoke(binary):
    if not re.search(r'\bavx\b', Path('/proc/cpuinfo').read_text()):
        raise Refused('CPU_FEATURE_UNAVAILABLE')
    with tempfile.TemporaryDirectory(prefix='rahandaz-mongo-', dir='/var/tmp') as name:
        folder = Path(name)
        identity = service_identity()
        os.chown(folder, identity['user'], identity['group'])
        with socket.socket() as reserve:
            reserve.bind(('127.0.0.1', 0))
            port = reserve.getsockname()[1]
        process = subprocess.Popen([binary, '--dbpath', name, '--bind_ip', '127.0.0.1', '--port', str(port),
                                    '--nounixsocket', '--wiredTigerCacheSizeGB', '0.25', '--logpath', str(folder/'engine.log')],
                                   env=ENV, cwd=RELEASE, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, **identity)
        try:
            deadline = time.monotonic()+30
            while time.monotonic() < deadline:
                if process.poll() is not None:
                    break
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=1) as connection:
                        connection.settimeout(2)
                        # OP_MSG {hello: 1, $db: "admin"}; match responseTo before accepting BSON ok=1.
                        body = b'\x10hello\0'+struct.pack('<i', 1)+b'\x02$db\0'+struct.pack('<i', 6)+b'admin\0'
                        doc = struct.pack('<i', len(body)+5)+body+b'\0'
                        message = struct.pack('<iiii', 21+len(doc), 1729, 0, 2013)+b'\0'*5+doc
                        connection.sendall(message)
                        raw = bytearray()
                        while len(raw) < 16:
                            chunk = connection.recv(16-len(raw))
                            if not chunk: break
                            raw.extend(chunk)
                        if len(raw) == 16:
                            length, _, response_to, opcode = struct.unpack('<iiii', raw)
                            if 21 < length <= 1024*1024 and response_to == 1729 and opcode == 2013:
                                while len(raw) < length:
                                    chunk = connection.recv(length-len(raw))
                                    if not chunk: break
                                    raw.extend(chunk)
                                if len(raw) == length and b'\x01ok\0'+struct.pack('<d', 1.0) in raw:
                                    return
                except OSError:
                    pass
                time.sleep(0.2)
            raise Refused('PACKAGE_VERIFICATION_FAILED')
        finally:
            if process.poll() is None:
                process.terminate()
                try: process.wait(timeout=15)
                except subprocess.TimeoutExpired:
                    process.kill(); process.wait(timeout=5)


def install_archive(package, data, progress):
    if platform.machine() != 'x86_64':
        raise Refused('ARCHITECTURE_UNAVAILABLE')
    entry = dict(catalog()[package])
    if 'builds' in entry:
        build = entry['builds'].get(os_version())
        if build is None:
            raise Refused('OS_NOT_AVAILABLE')
        entry.update(build)
    destination = Path(entry['destination'])
    if destination.is_symlink():
        raise Refused('UNSAFE_DESTINATION')
    # Create only fixed catalog ancestors; reject foreign/service-writable parents.
    for parent in reversed(destination.parents):
        if not parent.exists():
            parent.mkdir(mode=0o755)
            parent.chmod(0o755)
        trusted(parent)
    if package == 'google-chrome':
        progress('dependency_plan')
        libs = ['libnss3', 'libnspr4', 'libatk1.0-0' if os_version() == '22.04' else 'libatk1.0-0t64',
                'libatk-bridge2.0-0' if os_version() == '22.04' else 'libatk-bridge2.0-0t64',
                'libcups2' if os_version() == '22.04' else 'libcups2t64', 'libdrm2', 'libxkbcommon0',
                'libxcomposite1', 'libxdamage1', 'libxfixes3', 'libxrandr2', 'libgbm1',
                'libasound2' if os_version() == '22.04' else 'libasound2t64', 'fonts-liberation']
        for lib in libs:
            install(package, data, lambda phase: None, dependency=lib)
    marker = destination/'.rahandaz-install.json'
    expected = {'schema_version': '1', 'package': package, 'version': entry['version'], 'sha256': entry['sha256']}
    changed = False
    if destination.exists():
        trusted(destination)
        if not marker.is_file() or marker.is_symlink() or json.loads(marker.read_text()) != expected:
            raise Refused('UNSAFE_DESTINATION')
        trusted(marker)
    else:
        if shutil.disk_usage(destination.parent).free < 2*1024**3:
            raise Refused('INSUFFICIENT_DISK')
        progress('download')
        with tempfile.TemporaryDirectory(prefix='.rahandaz-', dir=destination.parent) as name:
            staging = Path(name)
            archive = staging/'download'
            download_archive(entry, archive)
            progress('installation')
            safe_extract(archive, staging, entry)
            extracted = staging/entry['archive_root']
            atomic(extracted/'.rahandaz-install.json', expected, 0o644)
            authorize(data)
            if destination.exists() or destination.is_symlink():
                raise Refused('UNSAFE_DESTINATION')
            os.rename(extracted, destination)
            changed = True
    progress('verification')
    verify_tool(package)
    if package == 'google-chrome':
        chrome_sandbox_policy(entry['executable_path'], data)
        chrome_render(entry['executable_path'])
    elif package == 'mongodb':
        mongo_smoke(entry['executable_path'])
    elif package == 'node':
        run([entry['executable_path'], str(destination/'lib/node_modules/npm/bin/npm-cli.js'), '--version'], 15, service=True)
    return {'package': package, 'version': entry['version'], 'verified': True, 'changed': changed, 'executable_path': entry['executable_path']}


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
                state.update(status='failed', error_code='INSTALL_INTERRUPTED', verified=False, verified_at=int(time.time()))
                for row in state.get('packages', []):
                    if row['status'] in ('queued', 'running'):
                        row.update(status='skipped' if row['status'] == 'queued' else 'failed', error_code='INSTALL_INTERRUPTED')
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
        evidence = [{'package': package, 'status': 'queued', 'phase': 'pending', 'verified': False,
                     'version': None, 'changed': False, 'error_code': None} for package in data['packages']]
        events = []
        verified = {}
        def publish(status='running', error_code=None):
            counts = {state: sum(item['status'] == state for item in evidence) for state in ('succeeded', 'failed', 'skipped')}
            result = response(status, execution_id=data['execution_id'], operation=data['operation'],
                              verified_at=int(time.time()), verified=status == 'succeeded',
                              control_plane_verified=verified.get('control_plane_verified', False),
                              os=verified.get('os'), packages=evidence, events=events[-200:],
                              summary={'total': len(evidence), **counts}, error_code=error_code)
            if status == 'running':
                result.update(pid=os.getpid(), boot_id=Path('/proc/sys/kernel/random/boot_id').read_text().strip(),
                              pid_start=Path('/proc/self/stat').read_text().split()[21])
            atomic(directory/'result.json', result)
        def event(package, phase, error_code=None):
            events.append({'sequence': len(events)+1, 'package': package, 'phase': phase,
                           'error_code': error_code, 'recorded_at': int(time.time())})
            publish()
        try:
            event(None, 'control_plane')
            verified = readiness()
            if run(['/usr/bin/dpkg', '--audit']).strip():
                raise Refused('PACKAGE_STATE_UNSAFE')
            for item in evidence:
                package = item['package']
                authorize(data)
                item.update(status='running', phase='preflight')
                event(package, 'preflight')
                try:
                    def progress(phase):
                        item['phase'] = phase
                        event(package, phase)
                    outcome = install(package, data, progress)
                    item.update(outcome, status='succeeded', phase='verified', error_code=None)
                    event(package, 'verified')
                except Refused as error:
                    item.update(status='failed', error_code=str(error))
                    event(package, 'failed', str(error))
                    if str(error) in ('AUTHORIZATION_REVOKED', 'AUTHORIZATION_CHECK_FAILED', 'INSTALL_INTERRUPTED', 'PACKAGE_STATE_UNSAFE', 'UNSAFE_CODE', 'COMMAND_TIMEOUT'):
                        raise
                    # Never repair interrupted dpkg or continue mutation on unsafe package state.
                    if run(['/usr/bin/dpkg', '--audit']).strip():
                        raise Refused('PACKAGE_STATE_UNSAFE')
            authorize(data)
            event(None, 'final_health')
            readiness()
            failures = [item for item in evidence if item['status'] == 'failed']
            publish('failed' if failures else 'succeeded', failures[0]['error_code'] if failures else None)
        except Exception as error:
            code = str(error) if isinstance(error, Refused) else 'SOFTWARE_INSTALL_FAILED'
            for item in evidence:
                if item['status'] in ('queued', 'running'):
                    item.update(status='skipped' if item['status'] == 'queued' else 'failed', error_code=code)
            events.append({'sequence': len(events)+1, 'package': None, 'phase': 'failed', 'error_code': code, 'recorded_at': int(time.time())})
            publish('failed', code)
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
