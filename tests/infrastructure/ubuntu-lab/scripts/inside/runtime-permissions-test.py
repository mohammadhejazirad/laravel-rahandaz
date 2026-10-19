#!/usr/bin/env python3
"""Real ACL regression checks, exclusively in disposable Ubuntu lab containers."""
import os
from pathlib import Path
import shutil
import subprocess

if os.environ.get('RAHANDAZ_RUNTIME_PERMISSIONS_LAB') != '1' or not Path('/.dockerenv').exists():
    raise SystemExit('Disposable Ubuntu runtime-permissions lab required.')

root = Path('/var/lib/rahandaz')
if (root / 'current').exists():
    raise SystemExit('Refusing to replace an existing application.')
if subprocess.run(['id', 'rahandaz-svc'], capture_output=True).returncode:
    subprocess.run(['useradd', '--system', '--user-group', '--shell', '/usr/sbin/nologin', 'rahandaz-svc'], check=True)
release = root / 'releases/runtime-permissions-test'
release.mkdir(parents=True)
(release / 'artisan').write_text('fixture, not executable\n')
(release / 'public').mkdir()
(release / 'bootstrap/cache').mkdir(parents=True)
(release / 'storage/framework/views').mkdir(parents=True)
(release / 'storage/logs').mkdir()
(release / 'storage').chmod(0o750)
(release / '.env').write_text('FIXTURE_ONLY=true\n')
(root / 'current').symlink_to(release)
Path('/etc/rahandaz').mkdir(exist_ok=True)


def apply():
    return subprocess.run(['bash', '-euc', 'source /opt/rahandaz/installer/lib/security.sh; security_runtime_permissions'], capture_output=True, text=True)


def service(command):
    subprocess.run(['runuser', '-u', 'rahandaz-svc', '--', 'bash', '-euc', command], check=True)


def verify(storage):
    for path in [storage / 'logs', storage / 'framework/views', release / 'bootstrap/cache']:
        service(f'touch "{path}/service-created"')
        # Files created by root Artisan after the permissions stage must stay writable.
        target = path / 'root-created'
        target.write_text('preserve\n')
        service(f'printf append >> "{target}"')
    service(f'test ! -w "{release}/artisan"; test ! -w "{release}/.env"')
    assert 'append' in (storage / 'logs/root-created').read_text()


result = apply()
assert result.returncode == 0, result.stderr
verify(release / 'storage')
assert apply().returncode == 0
print('PASS real storage, service write, root-created files, immutable code and repeatability')

# Existing data is preserved when testing the supported shared-storage layout.
shutil.move(str(release / 'storage'), str(root / 'shared/storage-retained'))
shared = root / 'shared/storage'
shutil.rmtree(shared)
shutil.move(str(root / 'shared/storage-retained'), str(shared))
(release / 'storage').symlink_to(shared)
assert apply().returncode == 0
verify(shared)
print('PASS shared storage layout and retained runtime data')

result = subprocess.run(['bash', '-euc', 'source /opt/rahandaz/installer/lib/security.sh; security_welcome_permissions'], capture_output=True, text=True)
assert result.returncode == 0, result.stderr
welcome = root / 'welcome/index.html'
old_umask = os.umask(0o077)
try:
    welcome.write_text('public welcome fixture')
finally:
    os.umask(old_umask)
subprocess.run(['runuser', '-u', 'www-data', '--', 'cat', str(welcome)], check=True, stdout=subprocess.DEVNULL)
print('PASS Nginx can read welcome content even under private installer umask')

external = Path('/tmp/external-runtime-fixture')
external.mkdir()
(external / 'sentinel').write_text('unchanged')
(shared / 'escape').symlink_to(external, target_is_directory=True)
assert apply().returncode != 0
assert (external / 'sentinel').read_text() == 'unchanged'
assert (external / 'sentinel').stat().st_uid == 0
print('PASS nested symlink refused; external target unchanged')
