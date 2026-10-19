#!/usr/bin/python3 -I
"""panel.port.update v1: narrowly privileged, fixed paths, JSON stdin only."""
import fcntl
import ipaddress
import json
import os
from pathlib import Path
import re
import shutil
import signal
import socket
import subprocess
import sys
import tempfile
import time
import urllib.request

MARKER = '# rahandaz-managed: panel-listener-v1\n'
UNSAFE_PORTS = {2049, 3659, 4045, 5060, 5061, 6000, 6566, 6665, 6666, 6667, 6668, 6669, 6697, 10080}

class Refused(Exception):
    pass

def port(value):
    if type(value) is not int or not 1024 <= value <= 65535 or value in UNSAFE_PORTS:
        raise Refused('INVALID_PORT')
    return value

def request(data):
    if not isinstance(data, dict) or set(data) != {'schema_version', 'operation', 'execution_id', 'expected_port', 'port'}:
        raise Refused('INVALID_INPUT')
    if data['schema_version'] != '1' or data['operation'] != 'panel.port.update':
        raise Refused('INVALID_CONTRACT')
    if not isinstance(data['execution_id'], str) or not re.fullmatch(r'[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}', data['execution_id']):
        raise Refused('INVALID_EXECUTION_ID')
    port(data['port'])
    if type(data['expected_port']) is not int or not 1 <= data['expected_port'] <= 65535:
        raise Refused('INVALID_PORT')
    return data

def render(host, number):
    # Literal IPv4 only in v1. No domain, path, directive or TLS input from Laravel.
    ipaddress.IPv4Address(host)
    return MARKER + '''server {
    listen PORT;
    server_name HOST localhost 127.0.0.1;
    root /var/lib/rahandaz/current/public;
    index index.php;
    server_tokens off;
    client_max_body_size 100m;
    location = / { return 302 /rahandaz/login; }
    location = /rahandaz { return 302 /rahandaz/login; }
    location = /up {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/lib/rahandaz/current/public/index.php;
        fastcgi_pass unix:/run/rahandaz/php-fpm.sock;
    }
    location ^~ /rahandaz/ { try_files $uri $uri/ /index.php?$query_string; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/lib/rahandaz/current/public/index.php;
        fastcgi_pass unix:/run/rahandaz/php-fpm.sock;
    }
    location ~ /\\.(?!well-known/) { deny all; }
    location ~* \\.(env|ini|log|sql|sqlite|key)$ { deny all; }
    location / { try_files $uri $uri/ =404; }
}
'''.replace('PORT', str(number)).replace('HOST', host)

def atomic(path, data, mode=0o644):
    fd, name = tempfile.mkstemp(dir=path.parent, prefix='.'+path.name+'.')
    try:
        with os.fdopen(fd, 'wb') as out:
            out.write(data); out.flush(); os.fsync(out.fileno())
        if path.exists():
            shutil.copystat(path, name)
            os.chown(name, path.stat().st_uid, path.stat().st_gid)
        else:
            os.chmod(name, mode)
        os.replace(name, path)
    finally:
        if os.path.exists(name): os.unlink(name)

class Listener:
    def __init__(self, root=Path('/'), execute=None, probe=None, free=None):
        self.root = root
        self.execute = execute or self.command
        self.probe = probe or self.health
        self.free = free or self.port_free
        self.state = root/'etc/rahandaz/panel-listener.json'
        self.site = root/'etc/nginx/sites-available/rahandaz-panel.conf'
        self.link = root/'etc/nginx/sites-enabled/rahandaz-panel.conf'
        self.release = root/'var/lib/rahandaz/current'
        self.journal = root/'var/lib/rahandaz/panel-listener'

    def command(self, args):
        try:
            subprocess.run(args, cwd=self.release, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=5)
        except (subprocess.SubprocessError, OSError):
            raise Refused('SERVICE_CHECK_FAILED') from None

    def port_free(self, number):
        try:
            with socket.socket() as s: s.bind(('0.0.0.0', number))
        except OSError:
            raise Refused('PORT_IN_USE') from None

    def health(self, number):
        for attempt in range(4):
            try:
                listeners = subprocess.check_output(['/usr/bin/ss', '-H', '-ltnp', 'sport = :'+str(number)], stderr=subprocess.DEVNULL, timeout=1).decode()
                owners = re.findall(r'pid=([0-9]+)', listeners)
                if not owners or not all(Path('/proc/'+pid+'/exe').resolve() == Path('/usr/sbin/nginx') for pid in owners):
                    raise Refused('LISTENER_NOT_OWNED')
                for route in ('/up', '/rahandaz/login'):
                    with urllib.request.urlopen('http://127.0.0.1:'+str(number)+route, timeout=1) as r:
                        if r.status != 200: raise Refused('HEALTHCHECK_FAILED')
                return
            except Exception:
                if attempt == 3: raise Refused('HEALTHCHECK_FAILED') from None
                time.sleep(.25)

    def restore(self, attempt):
        meta = json.loads((attempt/'recovery.json').read_text())
        atomic(self.state, (attempt/'state').read_bytes())
        atomic(self.release/'.env', (attempt/'env').read_bytes())
        if meta['site_exists']:
            atomic(self.site, (attempt/'site').read_bytes())
        else:
            self.site.unlink(missing_ok=True)
        if not meta['linked']: self.link.unlink(missing_ok=True)
        self.execute(['/usr/sbin/runuser', '-u', 'rahandaz-svc', '--', '/usr/bin/php8.4', 'artisan', 'config:cache'])
        self.execute(['/usr/sbin/nginx', '-t'])
        self.execute(['/usr/bin/systemctl', 'reload', 'nginx'])
        self.execute(['/usr/bin/systemctl', 'reload', 'rahandaz-php-fpm.service'])
        # The old listener or independent bootstrap recovery route must work again.
        self.probe(json.loads((attempt/'state').read_text())['port'])

    def recover(self):
        active = self.journal/'active.json'
        if not active.exists(): return
        pending = request(json.loads(active.read_text()))
        attempt = self.journal/pending['execution_id']
        if (attempt/'result.json').exists():
            try:
                self.probe(pending['port'])
                active.unlink()
                return
            except Exception: pass
        try:
            self.restore(attempt)
            active.unlink()
        except Exception:
            raise Refused('MANUAL_RECOVERY_REQUIRED') from None

    def apply(self, data):
        data = request(data)
        if not self.release.resolve().is_relative_to(self.root/'var/lib/rahandaz/releases') or not (self.release/'artisan').is_file():
            raise Refused('UNSAFE_RELEASE')
        # All ancestors and privileged files must remain outside the service user's control.
        for path in (self.state.parent, self.site.parent, self.link.parent, self.release.parent, self.root/'var/lib/rahandaz/releases', self.state, self.site, self.release.resolve(), self.release/'.env', self.journal):
            if path.exists() and (path.is_symlink() or path.stat().st_uid != os.geteuid() or path.stat().st_mode & 0o022):
                raise Refused('UNSAFE_OWNERSHIP')
        self.recover()
        state = json.loads(self.state.read_text())
        if state.get('schema_version') != '1' or state.get('enabled') is not True or state.get('allow_public_http') is not True:
            raise Refused('PANEL_LISTENER_DISABLED')
        ipaddress.IPv4Address(state['host'])
        if self.site.exists() and not self.site.read_text().startswith(MARKER): raise Refused('FOREIGN_CONFIG')
        if self.link.is_symlink() and self.link.resolve() != self.site: raise Refused('FOREIGN_CONFIG')
        if self.link.exists() and not self.link.is_symlink(): raise Refused('FOREIGN_CONFIG')
        number = data['port']
        if state['port'] == number and state.get('execution_id') == data['execution_id']:
            self.probe(number)
            return {'schema_version': '1', 'status': 'succeeded', 'port': number, 'verified': True}
        if state['port'] != data['expected_port']: raise Refused('STALE_PORT')
        if state['port'] == number: raise Refused('ALREADY_CURRENT')
        self.free(number)
        self.journal.mkdir(mode=0o700, exist_ok=True)
        attempt = self.journal/data['execution_id']
        attempt.mkdir(mode=0o700, exist_ok=True)
        env = self.release/'.env'
        original = {'state': self.state.read_bytes(), 'env': env.read_bytes(), 'site': self.site.read_bytes() if self.site.exists() else None, 'linked': self.link.is_symlink()}
        # Root-only recovery material never enters argv, stdout, or audit messages.
        for key in ('state', 'env', 'site'):
            if original[key] is not None: atomic(attempt/key, original[key], 0o600)
        atomic(attempt/'recovery.json', json.dumps({'site_exists': original['site'] is not None, 'linked': original['linked']}).encode(), 0o600)
        atomic(self.journal/'active.json', json.dumps(data).encode(), 0o600)
        changed = False
        try:
            atomic(self.site, render(state['host'], number).encode())
            if not self.link.is_symlink(): self.link.symlink_to(self.site)
            self.execute(['/usr/sbin/nginx', '-t'])
            changed = True
            lines = original['env'].decode().splitlines()
            origin = 'http://'+state['host']+':'+str(number)
            lines = [line for line in lines if not line.startswith('APP_URL=')]
            atomic(env, ('\n'.join(lines)+'\nAPP_URL='+origin+'\n').encode())
            self.execute(['/usr/sbin/runuser', '-u', 'rahandaz-svc', '--', '/usr/bin/php8.4', 'artisan', 'config:cache'])
            # Persist transitional origin for the authenticated cross-port status check.
            state.update(port=number, previous_port=data['expected_port'], execution_id=data['execution_id'])
            atomic(self.state, (json.dumps(state)+'\n').encode())
            self.execute(['/usr/bin/systemctl', 'reload', 'nginx'])
            self.execute(['/usr/bin/systemctl', 'reload', 'rahandaz-php-fpm.service'])
            self.probe(number)
            result = {'schema_version': '1', 'status': 'succeeded', 'port': number, 'verified': True}
            atomic(attempt/'result.json', json.dumps(result).encode(), 0o600)
            (self.journal/'active.json').unlink()
            return result
        except Exception:
            try:
                if changed:
                    self.restore(attempt)
                else:
                    if original['site'] is None: self.site.unlink(missing_ok=True)
                    else: atomic(self.site, original['site'])
                    if not original['linked']: self.link.unlink(missing_ok=True)
                (self.journal/'active.json').unlink(missing_ok=True)
            except Exception:
                raise Refused('MANUAL_RECOVERY_REQUIRED') from None
            raise

def main():
    try:
        if os.geteuid() != 0 or len(sys.argv) != 1: raise Refused('PRIVILEGE_REFUSED')
        def interrupted(signum, frame):
            raise Refused('INTERRUPTED')
        signal.signal(signal.SIGTERM, interrupted)
        signal.signal(signal.SIGINT, interrupted)
        data = sys.stdin.buffer.read(4097)
        if len(data) > 4096: raise Refused('INVALID_INPUT')
        data = request(json.loads(data))
        directory = Path('/var/lib/rahandaz/panel-listener')
        directory.mkdir(mode=0o700, exist_ok=True)
        if directory.is_symlink() or directory.stat().st_uid != 0 or directory.stat().st_mode & 0o077: raise Refused('UNSAFE_OWNERSHIP')
        with (directory/'lock').open('a') as lock:
            try: fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError: raise Refused('PANEL_PORT_BUSY') from None
            result = Listener().apply(data)
        print(json.dumps(result))
    except Exception as error:
        code = str(error) if isinstance(error, Refused) else 'PANEL_PORT_FAILED'
        print(json.dumps({'schema_version': '1', 'status': 'failed', 'error_code': code}))
        return 1
    return 0

if __name__ == '__main__':
    sys.exit(main())
