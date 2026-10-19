#!/usr/bin/python3
"""Exercise each catalog item in an explicit disposable Ubuntu container only."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile
import traceback
import sys
import pwd

if not Path('/.dockerenv').exists() or not Path('/opt/rahandaz-lab/scripts').is_dir() or os.geteuid() != 0:
    raise SystemExit('A disposable Ubuntu lab container is required.')
source = Path('/opt/rahandaz-src/infrastructure/operations/software/helper.py')
spec = importlib.util.spec_from_file_location('software_helper', source)
helper = importlib.util.module_from_spec(spec); spec.loader.exec_module(helper)
subprocess.run(['/usr/bin/python3', str(source.with_name('test_helper.py'))], check=True)
version = helper.os_version()
if version == '26.04':
    try: helper.readiness()
    except helper.Refused as error: assert str(error) == 'OS_NOT_AVAILABLE'
    else: raise AssertionError('Experimental Ubuntu must remain closed.')
    print(json.dumps({'ubuntu': version, 'production_capability': 'refused', 'vm_certified': False, 'service_manager_verification': 'not_run'}), flush=True)
    raise SystemExit(0)
try: pwd.getpwnam('rahandaz-svc')
except KeyError: subprocess.run(['useradd', '--system', '--shell', '/usr/sbin/nologin', '--home-dir', '/var/lib/rahandaz', 'rahandaz-svc'], check=True)
Path('/var/lib/rahandaz').mkdir(exist_ok=True)
identity = pwd.getpwnam('rahandaz-svc')
os.chown('/var/lib/rahandaz', identity.pw_uid, identity.pw_gid)
subprocess.run(['/usr/bin/apt-get', 'update'], check=True, timeout=180)
with tempfile.TemporaryDirectory(prefix='software-lab-') as name:
    helper.ROOT = Path(name); helper.RELEASE = Path('/')
    # Only database authorization is substituted. Extraction, hashes, APT and binaries are real.
    # Service manager, AppArmor/userns, and kernel isolation require a VM.
    helper.authorize = lambda data: None
    helper.verify_optional_service = lambda unit: None
    request = {'schema_version': '1', 'action': 'start', 'operation': 'software.install',
               'execution_id': '01999694-d50a-7000-8000-000000000001', 'packages': sys.argv[1:] or list(helper.catalog())}
    assert all(key in helper.catalog() for key in request['packages'])
    (helper.ROOT/request['execution_id']).mkdir()
    results = []
    for package in request['packages']:
        try:
            first = helper.install(package, request, lambda phase: print(json.dumps({'package': package, 'phase': phase}), flush=True))
            second = helper.install(package, request)
            assert second['verified'] and not second['changed']
            result = {'package': package, 'status': 'verified', 'first': first, 'idempotent': True}
        except helper.Refused as error:
            result = {'package': package, 'status': 'refused', 'error_code': str(error)}
        except Exception as error:
            result = {'package': package, 'status': 'error', 'exception': type(error).__name__}
            traceback.print_exc()
        results.append(result)
        print(json.dumps(result), flush=True)
    print(json.dumps({'ubuntu': version, 'results': results, 'vm_certified': False, 'service_manager_verification': 'not_run'}), flush=True)
