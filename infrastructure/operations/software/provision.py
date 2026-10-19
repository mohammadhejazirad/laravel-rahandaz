#!/usr/bin/python3 -I
"""Enroll the signed, root-owned software capability; never install optional packages here."""
import argparse
import json
import os
from pathlib import Path
import shutil
import subprocess

MARKER = '# rahandaz-managed: software-v1\n'
SOCKET = MARKER + '''[Unit]
Description=Rahandaz restricted software socket
[Socket]
ListenStream=/run/rahandaz-software-helper.sock
SocketUser=root
SocketGroup=rahandaz-svc
SocketMode=0660
Accept=yes
MaxConnections=8
RemoveOnStop=yes
[Install]
WantedBy=sockets.target
'''
SERVICE = MARKER + '''[Unit]
Description=Rahandaz restricted software installation
[Service]
Type=exec
User=root
ExecStart=/usr/bin/python3 -I /usr/local/lib/rahandaz/operations/software/helper.py
StandardInput=socket
StandardOutput=null
StandardError=journal
RuntimeMaxSec=900
TimeoutStopSec=30
KillMode=control-group
UMask=0077
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictSUIDSGID=true
'''


def trusted(path):
    """All ancestors and the file itself must be immutable to the service account."""
    for item in (path, *path.parents):
        if item.is_symlink() or item.stat().st_uid != 0 or item.stat().st_mode & 0o022:
            raise ValueError('Unsafe software capability path: '+str(item))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--upgrade', action='store_true')
    args = parser.parse_args()
    if os.geteuid() != 0:
        parser.error('Root is required.')
    source = Path(__file__).resolve(strict=True).parent
    for name in ('helper.py', 'catalog.json'):
        trusted(source/name)
    destination = Path('/usr/local/lib/rahandaz/operations/software')
    destination.mkdir(parents=True, exist_ok=True, mode=0o755)
    trusted(destination)
    state = Path('/etc/rahandaz/software-capability.json')
    trusted(state.parent)
    expected = {'schema_version': '1', 'enabled': True}
    if state.exists() or state.is_symlink():
        trusted(state)
        if not args.upgrade or json.loads(state.read_text()) != expected:
            parser.error('Existing enrollment requires operator review.')
    units = Path('/etc/systemd/system')
    targets = [('rahandaz-software-helper.socket', SOCKET), ('rahandaz-software-helper@.service', SERVICE)]
    for name, _ in targets:
        path = units/name
        if path.exists() or path.is_symlink():
            trusted(path)
            if not args.upgrade or not path.read_text().startswith(MARKER):
                parser.error('Existing systemd unit requires operator review.')
    for name in ('helper.py', 'catalog.json'):
        target = destination/name
        if target.exists() or target.is_symlink():
            trusted(target)
        temporary = destination/(name+'.new')
        if temporary.exists() or temporary.is_symlink():
            parser.error('Stale enrollment file requires operator review.')
        shutil.copyfile(source/name, temporary)
        temporary.chmod(0o644)
        os.replace(temporary, target)
    for name, value in targets:
        (units/name).write_text(value)
        (units/name).chmod(0o644)
    subprocess.run(['/usr/bin/systemd-analyze', 'verify', *[str(units/name) for name, _ in targets]], check=True)
    subprocess.run(['/usr/bin/systemctl', 'daemon-reload'], check=True)
    subprocess.run(['/usr/bin/systemctl', 'enable', '--now', 'rahandaz-software-helper.socket'], check=True)
    state.write_text(json.dumps(expected)+'\n')
    state.chmod(0o644)
    print('Software capability enrolled. No optional software was installed.')


if __name__ == '__main__':
    main()
