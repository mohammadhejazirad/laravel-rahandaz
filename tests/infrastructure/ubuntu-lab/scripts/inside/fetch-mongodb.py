#!/usr/bin/python3
"""Fetch unchanged official runtime archives for a hash-pinned optional release mirror."""
import importlib.util
import json
import os
from pathlib import Path

if not Path('/.dockerenv').is_file() or not Path('/opt/rahandaz-lab/scripts').is_dir():
    raise SystemExit('A disposable Ubuntu lab container is required.')
source = Path('/opt/rahandaz-src/infrastructure/operations/software/helper.py')
spec = importlib.util.spec_from_file_location('software_helper', source)
helper = importlib.util.module_from_spec(spec); spec.loader.exec_module(helper)
output = Path('/opt/rahandaz-artifacts')
if not output.is_dir():
    raise SystemExit('An explicit artifact output mount is required.')
for version, build in helper.catalog()['mongodb']['builds'].items():
    target = output/build['url'].rsplit('/', 1)[1]
    if target.exists():
        if helper.hashlib.sha256(target.read_bytes()).hexdigest() != build['sha256']:
            raise SystemExit('Existing artifact checksum mismatch.')
    else:
        helper.download_archive(build, target)
        target.chmod(0o644)
    print(json.dumps({'ubuntu': version, 'artifact': target.name, 'sha256': build['sha256'], 'bytes': target.stat().st_size}), flush=True)
