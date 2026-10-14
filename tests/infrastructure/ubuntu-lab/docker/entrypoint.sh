#!/bin/sh
# Lab PID 1. Writes a root-only marker and then sleeps. It does not start systemd.
set -eu
install -d -m 0700 /run/rahandaz-lab /run/rahandaz-lab/tmp /run/rahandaz-lab/locks /run/rahandaz-lab/sql /run/rahandaz-lab/logs
# /run can be empty at container start. Recreate the temp dir before any exec uses TMPDIR.
if [ ! -d /run/rahandaz-lab/tmp ]; then
    install -d -m 0700 /run/rahandaz-lab/tmp
fi
python3 -c 'import os, secrets
from pathlib import Path
path = Path("/run/rahandaz-lab/marker")
if path.is_symlink() or (path.exists() and not path.is_file()):
    raise SystemExit(1)
if path.is_file():
    raise SystemExit(0)
fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
os.write(fd, ("rahandaz-lab-v1\n" + secrets.token_hex(32) + "\n").encode())
os.fchmod(fd, 0o600)
os.close(fd)'
exec sleep infinity
