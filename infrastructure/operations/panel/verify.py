#!/usr/bin/python3 -I
"""Read-only initial-install verification of the restricted panel listener."""
import argparse
import grp
import json
import os
from pathlib import Path
import runpy
import stat
import subprocess


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--port', required=True, type=int)
    args = parser.parse_args()
    if os.geteuid() != 0:
        parser.error('Root is required.')
    api = runpy.run_path(str(Path(__file__).with_name('listener.py')))
    api['port'](args.port)
    listener = api['Listener']()
    state = json.loads(listener.state.read_text())
    if state.get('schema_version') != '1' or state.get('enabled') is not True or state.get('allow_public_http') is not True or state.get('port') != args.port:
        raise ValueError('Listener enrollment does not match the selected port.')
    if listener.site.read_text() != api['render'](state['host'], args.port) or listener.link.resolve() != listener.site:
        raise ValueError('The managed panel site does not match enrollment.')
    sock = Path('/run/rahandaz-panel-helper.sock').stat()
    if not stat.S_ISSOCK(sock.st_mode) or stat.S_IMODE(sock.st_mode) != 0o660 or sock.st_uid != 0 or sock.st_gid != grp.getgrnam('rahandaz-svc').gr_gid:
        raise ValueError('Unsafe panel helper socket.')
    helper = Path('/usr/local/lib/rahandaz/operations/panel/listener.py')
    if helper.is_symlink() or helper.stat().st_uid != 0 or helper.stat().st_mode & 0o022 or helper.read_bytes() != Path(__file__).with_name('listener.py').read_bytes():
        raise ValueError('The installed helper does not match the verified release.')
    subprocess.run(['/usr/bin/systemctl', 'is-active', '--quiet', 'rahandaz-panel-helper.socket'], check=True)
    listener.health(args.port)
    print('Panel listener and restricted helper verified.')


if __name__ == '__main__':
    main()
