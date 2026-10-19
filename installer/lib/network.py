#!/usr/bin/env python3
"""Installer network contract v1; no host mutations from argument validation."""
import argparse
import ipaddress
import json
from pathlib import Path
import re
import socket
import sys
from urllib.parse import urlsplit

UNSAFE_PORTS = {1719, 1720, 1723, 2049, 3659, 4045, 5060, 5061, 6000, 6566, 6665, 6666, 6667, 6668, 6669, 6697, 10080}


def port(value):
    if not re.fullmatch(r'[1-9][0-9]{3,4}', value) or not 1024 <= int(value) <= 65535 or int(value) in UNSAFE_PORTS:
        raise ValueError('Panel port must be 1024..65535 and allowed by browsers.')
    return int(value)


def resolve(origin, number, name):
    if not re.fullmatch(r'https?://[A-Za-z0-9.-]+(?::[1-9][0-9]{0,4})?', origin):
        raise ValueError('Provide an HTTP(S) origin without credentials, path, query or fragment.')
    parsed = urlsplit(origin)
    if parsed.port is not None and not 1 <= parsed.port <= 65535:
        raise ValueError('Invalid origin port.')
    selected = port(number or (str(parsed.port) if parsed.scheme == 'http' and parsed.port else '8080'))
    if parsed.scheme == 'http':
        if parsed.port is not None and parsed.port != selected:
            raise ValueError('The origin port and --http-port must match.')
        origin = 'http://' + parsed.hostname + ':' + str(selected)
    if name == '_':
        name = parsed.hostname
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9.-]{0,252}', name):
        raise ValueError('Invalid server name.')
    public = False
    try:
        address = ipaddress.IPv4Address(parsed.hostname)
        public = parsed.scheme == 'http' and not address.is_loopback
        if public and (address.is_unspecified or address.is_multicast or address.is_link_local or name != str(address)):
            raise ValueError('Public HTTP requires a unicast IPv4 origin and matching server name.')
    except ipaddress.AddressValueError:
        pass
    return {'schema_version': '1', 'origin': origin, 'port': selected, 'server_name': name, 'public_ipv4': public}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['resolve', 'load', 'default', 'port', 'free', 'site'])
    parser.add_argument('values', nargs='*')
    args = parser.parse_args()
    if args.action == 'port':
        port(args.values[0])
    elif args.action == 'free':
        number = port(args.values[0])
        # IPv4 public listener and IPv6 loopback/bootstrap must not overlap another service.
        for family, host in ((socket.AF_INET, '0.0.0.0'), (socket.AF_INET6, '::')):
            if family == socket.AF_INET6 and not socket.has_ipv6:
                continue
            with socket.socket(family) as listener:
                if family == socket.AF_INET6:
                    listener.setsockopt(socket.IPPROTO_IPV6, socket.IPV6_V6ONLY, 1)
                listener.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
                listener.bind((host, number))
    elif args.action == 'default':
        value = urlsplit(args.values[0])
        print(value.port if value.scheme == 'http' and value.port else 8080)
    elif args.action == 'site':
        # Reuse the exact Nginx template managed by the post-install restricted helper.
        import runpy
        helper = Path(__file__).resolve().parents[2] / 'infrastructure/operations/panel/listener.py'
        if not helper.is_file():
            helper = Path('/var/lib/rahandaz/current/infrastructure/operations/panel/listener.py')
        api = runpy.run_path(str(helper))
        print(api['render'](args.values[0], port(args.values[1])), end='')
    else:
        if args.action == 'load':
            path = Path(args.values[0])
            if path.is_symlink() or not path.is_file() or path.stat().st_mode & 0o077:
                raise ValueError('Unsafe network journal.')
            data = json.loads(path.read_text())
            expected = resolve(data['origin'], str(data['port']), data['server_name'])
            if data != expected:
                raise ValueError('Invalid network journal.')
        else:
            data = resolve(*args.values)
        print(json.dumps(data, sort_keys=True))


if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, KeyError, TypeError) as error:
        print('INVALID_NETWORK: ' + str(error), file=sys.stderr)
        raise SystemExit(2)
