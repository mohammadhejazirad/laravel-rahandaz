#!/usr/bin/python3 -I
"""Root-only enrollment of the panel listener capability, never run by HTTP."""
import argparse
import ipaddress
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--host', required=True)
    parser.add_argument('--current-port', required=True, type=int)
    parser.add_argument('--allow-public-http', required=True, action='store_true')
    args = parser.parse_args()
    if os.geteuid() != 0: parser.error('Root is required.')
    ipaddress.IPv4Address(args.host)
    if not 1 <= args.current_port <= 65535: parser.error('Invalid current port.')
    state = Path('/etc/rahandaz/panel-listener.json')
    if state.exists() or state.is_symlink(): parser.error('Already enrolled; refusing to replace listener state.')
    destination = Path('/usr/local/lib/rahandaz/operations/panel')
    destination.mkdir(parents=True, exist_ok=True, mode=0o755)
    for path in (destination, destination.parent, destination.parent.parent, state.parent):
        if path.is_symlink() or path.stat().st_uid != 0 or path.stat().st_mode & 0o022:
            parser.error('Unsafe installation directory.')
    target = destination/'listener.py'
    if target.is_symlink(): parser.error('Unsafe helper path.')
    shutil.copyfile(Path(__file__).with_name('listener.py'), target)
    target.chmod(0o755)
    policy = Path('/etc/sudoers.d/rahandaz-panel-listener')
    if policy.exists() or policy.is_symlink(): parser.error('Existing sudo policy requires operator review.')
    content = '# rahandaz-managed: panel-listener-v1\nrahandaz-svc ALL=(root) NOPASSWD: /usr/local/lib/rahandaz/operations/panel/listener.py ""\n'
    fd, name = tempfile.mkstemp(dir=policy.parent, prefix='.panel-policy-')
    try:
        with os.fdopen(fd, 'w') as out: out.write(content)
        os.chmod(name, 0o440)
        subprocess.run(['/usr/sbin/visudo', '-cf', name], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        os.replace(name, policy)
    finally:
        if os.path.exists(name): os.unlink(name)
    state.write_text(json.dumps({'schema_version': '1', 'enabled': True, 'host': args.host, 'port': args.current_port, 'previous_port': None, 'allow_public_http': True})+'\n')
    state.chmod(0o644)
    print('Panel listener enrolled. No listener or firewall was changed.')

if __name__ == '__main__': main()
