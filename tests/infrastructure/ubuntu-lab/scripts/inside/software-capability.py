#!/usr/bin/python3
"""Real APT verification inside the disposable lab only; not a systemd/VM certificate."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile

if not Path('/.dockerenv').exists() or not Path('/opt/rahandaz-lab/scripts').is_dir() or os.geteuid() != 0:
    raise SystemExit('A disposable Ubuntu lab container is required.')
source = Path('/opt/rahandaz-src/infrastructure/operations/software/helper.py')
spec = importlib.util.spec_from_file_location('software_helper', source)
helper = importlib.util.module_from_spec(spec); spec.loader.exec_module(helper)
version = next(line.split('=')[1].strip('"') for line in Path('/etc/os-release').read_text().splitlines() if line.startswith('VERSION_ID='))
subprocess.run(['/usr/bin/python3', str(source.with_name('test_helper.py'))], check=True)
if version == '26.04':
    try: helper.readiness()
    except helper.Refused as error:
        assert str(error) == 'OS_NOT_AVAILABLE'
    else: raise AssertionError('Experimental Ubuntu must remain closed.')
    print(json.dumps({'ubuntu': version, 'contract': 'passed', 'production_capability': 'refused', 'vm_certified': False}))
    raise SystemExit(0)
subprocess.run(['/usr/bin/apt-get', 'update'], check=True, timeout=180)
with tempfile.TemporaryDirectory(prefix='software-lab-') as name:
    helper.ROOT = Path(name); helper.RELEASE = Path('/')
    # This lab isolates APT verification; real peer/DB/systemd authorization is tested separately.
    helper.authorize = lambda data: None
    request = {'schema_version': '1', 'action': 'start', 'operation': 'software.install',
               'execution_id': '01999694-d50a-7000-8000-000000000001', 'packages': ['ffmpeg', 'htop', 'jq', 'unzip']}
    (helper.ROOT/request['execution_id']).mkdir()
    result = [helper.install(package, request) for package in request['packages']]
    second = [helper.install(package, request) for package in request['packages']]
    assert all(item['verified'] for item in result)
    assert all(item['verified'] and not item['changed'] for item in second)
    print(json.dumps({'ubuntu': version, 'packages': result, 'second_run': 'no_changes', 'vm_certified': False}))
