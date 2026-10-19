#!/usr/bin/python3 -I
"""Root-only enrollment of the panel listener capability, never run by HTTP."""
import argparse
import ipaddress
import json
import os
from pathlib import Path
import shutil
import subprocess

SOCKET = '''# rahandaz-managed: panel-listener-v1
[Unit]
Description=Rahandaz restricted panel listener socket
[Socket]
ListenStream=/run/rahandaz-panel-helper.sock
SocketUser=root
SocketGroup=rahandaz-svc
SocketMode=0660
Accept=yes
RemoveOnStop=yes
[Install]
WantedBy=sockets.target
'''

SERVICE = '''# rahandaz-managed: panel-listener-v1
[Unit]
Description=Rahandaz restricted panel listener helper
[Service]
Type=exec
User=root
ExecStart=/usr/bin/python3 -I /usr/local/lib/rahandaz/operations/panel/listener.py
StandardInput=socket
StandardOutput=inherit
StandardError=journal
TimeoutStartSec=50
RuntimeMaxSec=50
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ProtectSystem=strict
ReadWritePaths=/etc/nginx/sites-available /etc/nginx/sites-enabled /etc/rahandaz /var/lib/rahandaz /var/log/nginx /run/nginx.pid
'''


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--host', required=True)
    parser.add_argument('--current-port', required=True, type=int)
    parser.add_argument('--allow-public-http', required=True, action='store_true')
    parser.add_argument('--upgrade', action='store_true', help='Refresh a managed enrollment without changing listener state.')
    args = parser.parse_args()
    if os.geteuid() != 0: parser.error('Root is required.')
    ipaddress.IPv4Address(args.host)
    if not 1 <= args.current_port <= 65535: parser.error('Invalid current port.')
    state = Path('/etc/rahandaz/panel-listener.json')
    if state.is_symlink(): parser.error('Unsafe listener state.')
    if state.exists():
        if not args.upgrade: parser.error('Already enrolled; use --upgrade to refresh the managed helper.')
        existing = json.loads(state.read_text())
        if existing.get('schema_version') != '1' or existing.get('host') != args.host or existing.get('port') != args.current_port:
            parser.error('Enrollment does not match expected host and port.')
        if state.stat().st_uid != 0 or state.stat().st_mode & 0o022: parser.error('Unsafe listener state.')
    elif args.upgrade: parser.error('No existing enrollment to upgrade.')
    destination = Path('/usr/local/lib/rahandaz/operations/panel')
    destination.mkdir(parents=True, exist_ok=True, mode=0o755)
    for path in (destination, destination.parent, destination.parent.parent, state.parent):
        if path.is_symlink() or path.stat().st_uid != 0 or path.stat().st_mode & 0o022:
            parser.error('Unsafe installation directory.')
    target = destination/'listener.py'
    if target.is_symlink(): parser.error('Unsafe helper path.')
    policy = Path('/etc/sudoers.d/rahandaz-panel-listener')
    content = '# rahandaz-managed: panel-listener-v1\nrahandaz-svc ALL=(root) NOPASSWD: /usr/local/lib/rahandaz/operations/panel/listener.py ""\n'
    if policy.is_symlink() or (policy.exists() and (not args.upgrade or policy.read_text() != content)):
        parser.error('Existing sudo policy requires operator review.')
    units = Path('/etc/systemd/system')
    for name in ('rahandaz-panel-helper.socket', 'rahandaz-panel-helper@.service'):
        path = units/name
        if path.is_symlink() or (path.exists() and (not args.upgrade or not path.read_text().startswith('# rahandaz-managed: panel-listener-v1\n'))):
            parser.error('Existing systemd unit requires operator review.')
    shutil.copyfile(Path(__file__).with_name('listener.py'), target)
    target.chmod(0o755)
    for name, text in (('rahandaz-panel-helper.socket', SOCKET), ('rahandaz-panel-helper@.service', SERVICE)):
        path = units/name
        path.write_text(text); path.chmod(0o644)
    subprocess.run(['/usr/bin/systemd-analyze', 'verify', str(units/'rahandaz-panel-helper.socket'), str(units/'rahandaz-panel-helper@.service')], check=True)
    if not state.exists():
        state.write_text(json.dumps({'schema_version': '1', 'enabled': True, 'host': args.host, 'port': args.current_port, 'previous_port': None, 'allow_public_http': True})+'\n')
        state.chmod(0o644)
    subprocess.run(['/usr/bin/systemctl', 'daemon-reload'], check=True)
    subprocess.run(['/usr/bin/systemctl', 'enable', '--now', 'rahandaz-panel-helper.socket'], check=True)
    # beta19 enrollment used sudo, which cannot work under the worker's NoNewPrivileges.
    # Remove only that exact managed policy after the restricted socket is available.
    if policy.exists(): policy.unlink()
    print('Panel listener helper enrolled. No HTTP listener or firewall was changed.')

if __name__ == '__main__': main()
