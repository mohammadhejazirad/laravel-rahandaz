#!/usr/bin/python3 -I
"""Bounded client for the independent software capability; JSON stdin only."""
import json
import socket
import sys


def main():
    try:
        data = sys.stdin.buffer.read(4097)
        if len(sys.argv) != 1 or len(data) > 4096:
            raise ValueError()
        with socket.socket(socket.AF_UNIX) as connection:
            connection.settimeout(8)
            connection.connect('/run/rahandaz-software-helper.sock')
            connection.sendall(data)
            connection.shutdown(socket.SHUT_WR)
            response = bytearray()
            while True:
                part = connection.recv(16385 - len(response))
                if not part:
                    break
                response.extend(part)
                if len(response) > 16384:
                    raise ValueError()
        output = json.loads(response)
        print(json.dumps(output))
        return 0
    except Exception:
        print(json.dumps({'schema_version': '1', 'status': 'unavailable', 'error_code': 'SOFTWARE_HELPER_UNAVAILABLE'}))
        return 1


if __name__ == '__main__':
    sys.exit(main())
