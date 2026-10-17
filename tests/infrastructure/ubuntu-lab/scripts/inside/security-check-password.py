#!/usr/bin/env python3
"""Verify the installed shadow hash inside the disposable security lab only."""
import ctypes
import pathlib

lib = ctypes.CDLL('libcrypt.so.1')
lib.crypt.argtypes = [ctypes.c_char_p, ctypes.c_char_p]
lib.crypt.restype = ctypes.c_char_p
password = pathlib.Path('/var/lib/rahandaz-bootstrap-security-v1/ubuntu.password').read_bytes().strip()
saved = next(line.split(':')[1].encode() for line in pathlib.Path('/etc/shadow').read_text().splitlines() if line.startswith('rahandaz:'))
assert lib.crypt(password, saved) == saved, 'Ubuntu password verification failed'
