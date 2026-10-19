#!/usr/bin/python3 -I
"""Unprivileged client for the fixed, systemd-activated panel helper socket."""
import json
import socket
import sys


def main():
    try:
        if len(sys.argv) != 1: raise ValueError()
        data = sys.stdin.buffer.read(4097)
        if len(data) > 4096: raise ValueError()
        with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as connection:
            connection.settimeout(45)
            connection.connect('/run/rahandaz-panel-helper.sock')
            connection.sendall(data)
            connection.shutdown(socket.SHUT_WR)
            result = bytearray()
            while True:
                chunk = connection.recv(4097 - len(result))
                if not chunk: break
                result.extend(chunk)
                if len(result) > 4096: raise ValueError()
        output = json.loads(result)
        print(json.dumps(output))
        return 0 if output.get('status') == 'succeeded' else 1
    except Exception:
        print(json.dumps({'schema_version': '1', 'status': 'failed', 'error_code': 'PANEL_HELPER_UNAVAILABLE'}))
        return 1


if __name__ == '__main__': sys.exit(main())
