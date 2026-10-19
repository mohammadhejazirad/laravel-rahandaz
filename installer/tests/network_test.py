#!/usr/bin/env python3
"""Network/prompt contracts: pseudo-terminal, persistent resume and real bind conflicts."""
import importlib.util
import json
import os
from pathlib import Path
import pty
import select
import signal
import socket
import subprocess
import tempfile
import time
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location('network', ROOT/'installer/lib/network.py')
NETWORK = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(NETWORK)

SCRIPT = r'''
set -euo pipefail
INSTALLER_HOME="$1/installer"
INSTALLER_STATE_DIR="$2"
source "$INSTALLER_HOME/lib/common.sh"
source "$INSTALLER_HOME/lib/network.sh"
installer_sysroot() { printf '%s' "$INSTALLER_STATE_DIR/root"; }
MODE="$3"; PUBLIC_URL="$4"; HTTP_PORT="$5"; SERVER_NAME=_
installer_configure_network
installer_save_network
printf 'RESULT %s %s %s %s\n' "$HTTP_PORT" "$PUBLIC_URL" "$SERVER_NAME" "$PANEL_PUBLIC_IPV4"
'''


class NetworkTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='rahandaz-network-')
        self.addCleanup(self.temp.cleanup)
        self.work = Path(self.temp.name)

    def run_config(self, mode='apply', origin='http://194.110.24.123', port='', state=None):
        return subprocess.run(['bash', '-c', SCRIPT, 'fixture', str(ROOT), str(state or self.work), mode, origin, port], text=True, capture_output=True, stdin=subprocess.DEVNULL)

    def tty_config(self, replies, origin='http://194.110.24.123'):
        child, fd = pty.fork()
        if child == 0:
            os.execvp('bash', ['bash', '-c', SCRIPT, 'fixture', str(ROOT), str(self.work), 'apply', origin, ''])
        output = b''
        sent = 0
        status = None
        deadline = time.monotonic() + 15
        try:
            while time.monotonic() < deadline:
                ready, _, _ = select.select([fd], [], [], .1)
                if ready:
                    try:
                        data = os.read(fd, 65536)
                    except OSError:
                        data = b''
                    output += data
                prompts = output.count(b'Panel port [')
                if sent < prompts and sent < len(replies):
                    os.write(fd, replies[sent]); sent += 1
                done, value = os.waitpid(child, os.WNOHANG)
                if done:
                    status = os.waitstatus_to_exitcode(value)
                    break
            if status is None:
                os.kill(child, signal.SIGKILL); os.waitpid(child, 0)
                self.fail('Prompt test timed out: ' + output.decode(errors='replace'))
        finally:
            os.close(fd)
        return status, output.decode()

    def test_enter_accepts_8080_in_real_terminal_and_persists_private_journal(self):
        status, output = self.tty_config([b'\n'])
        self.assertEqual(status, 0, output)
        self.assertIn('Panel port [8080]: ', output)
        self.assertIn('RESULT 8080 http://194.110.24.123:8080', output)
        state = self.work/'network.json'
        self.assertEqual(state.stat().st_mode & 0o777, 0o600)
        self.assertEqual(json.loads(state.read_text())['port'], 8080)
        self.assertTrue(output.isascii())

    def test_custom_port_and_invalid_retry(self):
        status, output = self.tty_config([b'08\n', b'6000\n', b'9001\n'])
        self.assertEqual(status, 0, output)
        self.assertEqual(output.count('Invalid port.'), 2)
        self.assertIn('RESULT 9001 http://194.110.24.123:9001', output)

    def test_eof_stops_before_persisting_configuration(self):
        status, output = self.tty_config([b'\x04'])
        self.assertNotEqual(status, 0)
        self.assertIn('input was interrupted', output)
        self.assertFalse((self.work/'network.json').exists())

    def test_missing_origin_is_prompted_before_port(self):
        # Origin is covered through the actual signed bootstrap forwarding tests;
        # here noninteractive execution must never guess a public address.
        result = self.run_config(origin='')
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Provide --public-url', result.stderr)
        self.assertFalse((self.work/'network.json').exists())

    def test_noninteractive_requires_explicit_port(self):
        result = self.run_config()
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Provide --http-port', result.stderr)

    def test_plan_does_not_prompt_bind_or_persist(self):
        result = self.run_config(mode='plan', origin='')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('RESULT 8080 http://127.0.0.1:8080', result.stdout)
        self.assertFalse((self.work/'network.json').exists())

    def test_resume_keeps_original_port_and_refuses_changes(self):
        result = self.run_config(port='9001')
        self.assertEqual(result.returncode, 0, result.stderr)
        before = (self.work/'network.json').read_bytes()
        result = self.run_config(mode='resume', origin='')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('RESULT 9001 http://194.110.24.123:9001', result.stdout)
        result = self.run_config(mode='resume', port='8080')
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('NETWORK_CONFIG_CHANGED', result.stderr)
        self.assertEqual((self.work/'network.json').read_bytes(), before)

    def test_explicit_origin_port_is_the_prompt_default_and_conflicts_refused(self):
        status, output = self.tty_config([b'\n'], 'http://194.110.24.123:9001')
        self.assertEqual(status, 0, output)
        self.assertIn('Panel port [9001]: ', output)
        with self.assertRaises(ValueError):
            NETWORK.resolve('http://194.110.24.123:9001', '8080', '_')

    def test_invalid_inputs_and_unsafe_ports_refused(self):
        for value in ['0', '80', '-1', '08', '08080', '65536', '1e4', '8080\n', *map(str, NETWORK.UNSAFE_PORTS)]:
            with self.subTest(value=value), self.assertRaises(ValueError):
                NETWORK.port(value)
        for origin in ['http://user:pass@host', 'http://host/path', 'http://host?query', 'http://0.0.0.0', 'http://169.254.169.254', 'http://224.0.0.1', 'http://host:99999']:
            with self.subTest(origin=origin), self.assertRaises(ValueError):
                NETWORK.resolve(origin, '8080', '_')

    def test_browser_port_contract_matches_post_install_helper(self):
        import runpy
        helper = runpy.run_path(str(ROOT/'infrastructure/operations/panel/listener.py'))
        self.assertEqual(NETWORK.UNSAFE_PORTS, helper['UNSAFE_PORTS'])

    def test_real_occupied_port_refused_without_killing_existing_listener(self):
        with socket.socket() as occupied:
            occupied.bind(('127.0.0.1', 0)); occupied.listen()
            number = occupied.getsockname()[1]
            if number in NETWORK.UNSAFE_PORTS:
                self.skipTest('OS allocated a browser-restricted ephemeral port.')
            result = subprocess.run(['python3', ROOT/'installer/lib/network.py', 'free', str(number)], capture_output=True, text=True)
            self.assertNotEqual(result.returncode, 0)
            with socket.create_connection(('127.0.0.1', number)):
                pass

    def test_tampered_or_linked_journal_refused(self):
        state = self.work/'network.json'
        state.write_text('{}'); state.chmod(0o600)
        self.assertNotEqual(self.run_config(origin='', port='').returncode, 0)
        state.unlink(); target = self.work/'target'; target.write_text('{}'); state.symlink_to(target)
        self.assertNotEqual(self.run_config(origin='', port='').returncode, 0)

    def test_public_site_uses_helper_template_and_recovery_is_loopback(self):
        result = self.run_config(port='9001')
        self.assertEqual(result.returncode, 0, result.stderr)
        stage = r'''
source "$INSTALLER_HOME/lib/render.sh"
source "$INSTALLER_HOME/lib/engine.sh"
INSTALLER_OFFLINE=1
set +e
stage_11_nginx_php_fpm
status=$?
[[ "$status" == 2 ]]
'''
        result = subprocess.run(['bash', '-c', SCRIPT + stage, 'fixture', str(ROOT), str(self.work), 'apply', '', ''], text=True, capture_output=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        available = self.work/'root/etc/nginx/sites-available'
        recovery = (available/'rahandaz.conf').read_text()
        public = (available/'rahandaz-panel.conf').read_text()
        self.assertIn('listen 127.0.0.1:80;', recovery)
        self.assertIn('listen [::1]:80;', recovery)
        self.assertNotIn('listen 9001;', recovery)
        self.assertIn('listen 9001;', public)
        self.assertIn('location = / { return 302 /rahandaz/login; }', public)
        self.assertTrue((self.work/'root/etc/nginx/sites-enabled/rahandaz-panel.conf').is_symlink())
        # Foreign files must survive a retry unchanged.
        (available/'rahandaz-panel.conf').write_text('# operator config\n')
        result = subprocess.run(['bash', '-c', SCRIPT + stage, 'fixture', str(ROOT), str(self.work), 'apply', '', ''], text=True, capture_output=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual((available/'rahandaz-panel.conf').read_text(), '# operator config\n')


class EnrollmentTest(unittest.TestCase):
    def setUp(self):
        previous_umask = os.umask(0o077)
        self.addCleanup(os.umask, previous_umask)
        self.temp = tempfile.TemporaryDirectory(prefix='rahandaz-enrollment-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        for relative in ['etc/rahandaz', 'etc/sudoers.d', 'etc/systemd/system']:
            (self.root/relative).mkdir(parents=True)
        spec = importlib.util.spec_from_file_location('provision', ROOT/'infrastructure/operations/panel/provision.py')
        self.module = importlib.util.module_from_spec(spec); spec.loader.exec_module(self.module)
        fixture_root = self.root
        original_stat = Path.stat
        def fixture_path(*args):
            value = str(Path(*args))
            if value.startswith(('/etc/', '/usr/local/')):
                value = str(fixture_root/value.lstrip('/'))
            return Path(value)
        def fixture_stat(path, **kwargs):
            value = list(original_stat(path, **kwargs))
            if str(path).startswith(str(fixture_root)):
                value[4] = 0
            return os.stat_result(value)
        self.addCleanup(patch.stopall)
        patch.object(Path, 'stat', new=fixture_stat).start()
        patch.object(self.module, 'Path', fixture_path).start()
        patch.object(self.module.os, 'geteuid', return_value=0).start()
        self.command = patch.object(self.module.subprocess, 'run').start()

    def enroll(self, upgrade=False):
        args = ['provision', '--host', '192.0.2.123', '--current-port', '8080', '--allow-public-http']
        if upgrade: args.append('--upgrade')
        with patch('sys.argv', args):
            self.module.main()

    def test_fresh_enrollment_and_upgrade_preserve_state(self):
        self.enroll()
        state = self.root/'etc/rahandaz/panel-listener.json'
        before = state.read_bytes()
        self.enroll(upgrade=True)
        self.assertEqual(state.read_bytes(), before)
        self.assertEqual(json.loads(before)['port'], 8080)
        self.assertIn('NoNewPrivileges=true', (self.root/'etc/systemd/system/rahandaz-panel-helper@.service').read_text())
        self.assertFalse((self.root/'etc/sudoers.d/rahandaz-panel-listener').exists())

    def test_interrupted_enrollment_before_state_is_resumable(self):
        self.command.side_effect = subprocess.CalledProcessError(1, ['systemd-analyze'])
        with self.assertRaises(subprocess.CalledProcessError): self.enroll()
        self.assertFalse((self.root/'etc/rahandaz/panel-listener.json').exists())
        self.command.side_effect = None
        self.enroll(upgrade=True)
        self.assertTrue((self.root/'etc/rahandaz/panel-listener.json').is_file())

    def test_interrupted_socket_activation_preserves_state_on_retry(self):
        self.command.side_effect = [None, None, subprocess.CalledProcessError(1, ['systemctl'])]
        with self.assertRaises(subprocess.CalledProcessError): self.enroll()
        state = self.root/'etc/rahandaz/panel-listener.json'
        before = state.read_bytes()
        self.command.side_effect = None
        self.enroll(upgrade=True)
        self.assertEqual(state.read_bytes(), before)

    def test_foreign_unit_refused_without_overwrite(self):
        unit = self.root/'etc/systemd/system/rahandaz-panel-helper.socket'
        unit.write_text('# external socket unit\n')
        with self.assertRaises(SystemExit): self.enroll(upgrade=True)
        self.assertEqual(unit.read_text(), '# external socket unit\n')


if __name__ == '__main__':
    unittest.main(verbosity=2)
