#!/usr/bin/env python3
"""Real Nginx binding/routing smoke; disposable lab container only, no PHP/systemd certification."""
import importlib.util
import os
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

if not Path('/.dockerenv').is_file() or not Path('/run/rahandaz-lab/marker').is_file():
    raise SystemExit('Disposable Ubuntu lab container is required.')
root = Path('/opt/rahandaz')
spec = importlib.util.spec_from_file_location('network_tests', root/'installer/tests/network_test.py')
tests = importlib.util.module_from_spec(spec); spec.loader.exec_module(tests)
api = __import__('runpy').run_path(str(root/'infrastructure/operations/panel/listener.py'))
with tempfile.TemporaryDirectory(prefix='rahandaz-nginx-network-') as directory:
    work = Path(directory); work.chmod(0o755)
    stage = '''
source "$INSTALLER_HOME/lib/render.sh"
source "$INSTALLER_HOME/lib/engine.sh"
INSTALLER_OFFLINE=1
set +e
stage_11_nginx_php_fpm
[[ "$?" == 2 ]]
'''
    subprocess.run(['bash', '-c', tests.SCRIPT+stage, 'lab', str(root), str(work), 'apply', 'http://192.0.2.123', '8080'], check=True)
    public = work/'public'; public.mkdir(mode=0o755)
    (public/'build').mkdir(); (public/'build/probe.js').write_text('verified-build')
    welcome = work/'welcome'; welcome.mkdir(); (welcome/'index.html').write_text('verified-recovery')
    available = work/'root/etc/nginx/sites-available'
    recovery = (available/'rahandaz.conf').read_text().replace('/var/lib/rahandaz/current/public', str(public)).replace('/var/lib/rahandaz/welcome', str(welcome)).replace('include fastcgi_params;', 'include /etc/nginx/fastcgi_params;')
    (work/'recovery.conf').write_text(recovery)
    def write_panel(number):
        text = api['render']('192.0.2.123', number).replace('/var/lib/rahandaz/current/public', str(public)).replace('include fastcgi_params;', 'include /etc/nginx/fastcgi_params;')
        (work/'panel.conf').write_text(text)
    write_panel(8080)
    config = work/'nginx.conf'
    config.write_text(f'''pid {work}/nginx.pid;
error_log {work}/error.log;
events {{}}
http {{ access_log off; server {{ listen 80 default_server; listen [::]:80 default_server; server_name _; return 418; }}
include {work}/recovery.conf;
include {work}/panel.conf;
}}
''')
    command = ['/usr/sbin/nginx', '-p', str(work)+'/', '-c', str(config)]
    subprocess.run(command+['-t'], check=True)
    subprocess.run(command, check=True)
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, *args): return None
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
    def response(host, number, route, host_header='localhost'):
        req = urllib.request.Request(f'http://{host}:{number}{route}', headers={'Host': host_header})
        try: result = opener.open(req, timeout=2)
        except urllib.error.HTTPError as error: result = error
        with result: return result.status, result.read()
    try:
        address = socket.gethostbyname(socket.gethostname())
        for number in (8080, 8081, 8080):
            write_panel(number)
            subprocess.run(command+['-t'], check=True)
            subprocess.run(command+['-s', 'reload'], check=True)
            for attempt in range(30):
                try:
                    if response(address, number, '/')[0] == 302: break
                except OSError: pass
                time.sleep(.1)
            assert response(address, number, '/')[0] == 302
            assert response(address, number, '/build/probe.js') == (200, b'verified-build')
            assert response(address, number, '/.env')[0] in (403,404)
            assert response('127.0.0.1', 80, '/') == (200, b'verified-recovery')
            assert response(address, 80, '/')[0] == 418, 'Recovery site leaked onto the public port-80 address.'
        print('REAL_NGINX_DEFAULT_CUSTOM_RETURN_PORTS_STATIC_ASSET_PRIVATE_PATH_LOOPBACK_RECOVERY_VERIFIED')
        print('PHP_FPM_SYSTEMD_HELPER_SOCKET_FRESH_VM_HEALTH_NOT_RUN')
    finally:
        subprocess.run(command+['-s', 'quit'], check=True)
