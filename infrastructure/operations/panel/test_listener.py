"""Contract and recovery tests. Fake services run only in an isolated temp tree."""
import importlib.util
import io
import json
import os
from pathlib import Path
import socket
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('listener', Path(__file__).with_name('listener.py'))
m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
EXEC = '019a0123-4567-789a-8abc-0123456789ab'

def payload(**changes):
    return dict({'schema_version': '1', 'operation': 'panel.port.update', 'execution_id': EXEC, 'expected_port': 8080, 'port': 8081}, **changes)

class ListenerTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='rahandaz-panel-port-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.calls = []
        self.fail = None
        def execute(args):
            self.calls.append(args)
            if self.fail and self.fail(args): raise m.Refused('INJECTED_FAILURE')
        self.listener = m.Listener(self.root, execute=execute, probe=lambda n: None, free=lambda n: None)
        x = self.listener
        target=self.root/'var/lib/rahandaz/releases/fixture'
        target.mkdir(parents=True,mode=0o700)
        target.parent.chmod(0o700); target.parent.parent.chmod(0o700)
        x.release.symlink_to(target)
        (target/'artisan').write_text('fixture')
        for p in (x.state.parent, x.site.parent, x.link.parent, x.journal): p.mkdir(parents=True, exist_ok=True, mode=0o700)
        (x.release/'.env').write_text('APP_KEY=fixture-keep\nAPP_URL=http://192.0.2.1:8080\n')
        x.state.write_text(json.dumps({'schema_version': '1', 'enabled': True, 'allow_public_http': True, 'host': '192.0.2.1', 'port': 8080}))
        x.site.write_text(m.render('192.0.2.1',8080)); x.link.symlink_to(x.site)
        for p in (x.state, x.site, x.release/'.env'): p.chmod(0o600)
        self.before = [p.read_bytes() for p in (x.state, x.site, x.release/'.env')]

    def unchanged(self):
        x = self.listener
        self.assertEqual(self.before, [p.read_bytes() for p in (x.state,x.site,x.release/'.env')])

    def test_success_then_idempotent_retry_reloads_once(self):
        x = self.listener
        self.assertTrue(x.apply(payload())['verified'])
        self.assertEqual(x.apply(payload())['port'],8081)
        self.assertEqual(sum(c==['/usr/bin/systemctl','reload','nginx'] for c in self.calls),1)
        self.assertIn('APP_KEY=fixture-keep', (x.release/'.env').read_text())
        self.assertIn('APP_URL=http://192.0.2.1:8081', (x.release/'.env').read_text())
        self.assertEqual((x.journal/EXEC/'env').stat().st_mode & 0o777,0o600)

    def test_invalid_ports_and_input_injection_never_mutate(self):
        for n in (-1,22,80,443,1719,1720,1723,6000,6667,10080,65536,True,'8081; reboot'):
            with self.assertRaises(m.Refused): self.listener.apply(payload(port=n))
        with self.assertRaises(m.Refused): self.listener.apply(payload(command='reboot'))
        self.unchanged(); self.assertEqual(self.calls,[])

    def test_stale_expected_port_does_not_overwrite_other_changes(self):
        with self.assertRaisesRegex(m.Refused,'STALE_PORT'): self.listener.apply(payload(expected_port=8090))
        self.unchanged()

    def test_occupied_port_is_rejected(self):
        with socket.socket() as sock:
            sock.bind(('0.0.0.0',0))
            self.listener.free = m.Listener().port_free
            with self.assertRaisesRegex(m.Refused,'PORT_IN_USE'): self.listener.apply(payload(port=sock.getsockname()[1]))
        self.unchanged()

    def test_nginx_validation_failure_does_not_reload(self):
        self.fail=lambda a:a==['/usr/sbin/nginx','-t']
        with self.assertRaises(m.Refused): self.listener.apply(payload())
        self.unchanged(); self.assertFalse(any('reload' in a for a in self.calls))

    def test_health_failure_restores_config_env_and_origin(self):
        def probe(n):
            if n==8081: raise m.Refused('HEALTHCHECK_FAILED')
        self.listener.probe=probe
        with self.assertRaisesRegex(m.Refused,'HEALTHCHECK_FAILED'): self.listener.apply(payload())
        self.unchanged(); self.assertFalse((self.listener.journal/'active.json').exists())

    def test_foreign_config_and_symlink_are_refused(self):
        self.listener.site.write_text('# customer configuration\n')
        with self.assertRaisesRegex(m.Refused,'FOREIGN_CONFIG'): self.listener.apply(payload())
        self.listener.site.unlink(); self.listener.site.symlink_to(self.listener.state)
        with self.assertRaisesRegex(m.Refused,'UNSAFE_OWNERSHIP'): self.listener.apply(payload())

    def test_disabled_enrollment_is_refused(self):
        state=json.loads(self.listener.state.read_text()); state['enabled']=False
        self.listener.state.write_text(json.dumps(state))
        with self.assertRaisesRegex(m.Refused,'PANEL_LISTENER_DISABLED'): self.listener.apply(payload())

    def test_interruption_is_recovered_before_retry(self):
        class PowerLoss(BaseException): pass
        def stop(args):
            if args==['/usr/bin/systemctl','reload','nginx']: raise PowerLoss()
        self.listener.execute=stop
        with self.assertRaises(PowerLoss): self.listener.apply(payload())
        self.assertTrue((self.listener.journal/'active.json').exists())
        self.listener.execute=lambda args:self.calls.append(args)
        self.assertTrue(self.listener.apply(payload())['verified'])
        self.assertFalse((self.listener.journal/'active.json').exists())

    def test_recovery_failure_is_explicit_and_retains_private_backup(self):
        def probe(n): raise m.Refused('HEALTHCHECK_FAILED')
        self.listener.probe=probe
        with self.assertRaisesRegex(m.Refused,'MANUAL_RECOVERY_REQUIRED'): self.listener.apply(payload())
        self.assertTrue((self.listener.journal/EXEC/'env').is_file())
        self.assertTrue((self.listener.journal/'active.json').exists())

class ClientTests(unittest.TestCase):
    def test_socket_transport_has_fixed_path_and_preserves_verification(self):
        spec = importlib.util.spec_from_file_location('client', Path(__file__).with_name('client.py'))
        client = importlib.util.module_from_spec(spec); spec.loader.exec_module(client)
        data = json.dumps(payload()).encode()
        evidence = json.dumps({'schema_version':'1','status':'succeeded','port':8081,'verified':True}).encode()
        calls = []
        class Connection:
            chunks = [evidence, b'']
            def __enter__(self): return self
            def __exit__(self, *args): pass
            def settimeout(self, value): calls.append(('timeout',value))
            def connect(self, value): calls.append(('connect',value))
            def sendall(self, value): calls.append(('send',value))
            def shutdown(self, value): calls.append(('shutdown',value))
            def recv(self, count): return self.chunks.pop(0)
        output = io.StringIO()
        with patch.object(client.sys, 'argv', ['client.py']), patch.object(client.sys, 'stdin', io.TextIOWrapper(io.BytesIO(data))), patch.object(client.sys, 'stdout', output), patch.object(client.socket, 'socket', return_value=Connection()):
            self.assertEqual(client.main(), 0)
        self.assertIn(('connect','/run/rahandaz-panel-helper.sock'), calls)
        self.assertIn(('send',data), calls)
        self.assertIn(('shutdown',socket.SHUT_WR), calls)
        self.assertTrue(json.loads(output.getvalue())['verified'])

    def test_socket_transport_fails_closed_when_helper_is_unavailable(self):
        spec = importlib.util.spec_from_file_location('client', Path(__file__).with_name('client.py'))
        client = importlib.util.module_from_spec(spec); spec.loader.exec_module(client)
        output = io.StringIO()
        with patch.object(client.sys, 'argv', ['client.py']), patch.object(client.sys, 'stdin', io.TextIOWrapper(io.BytesIO(b'{}'))), patch.object(client.sys, 'stdout', output), patch.object(client.socket, 'socket', side_effect=OSError('fixture')):
            self.assertEqual(client.main(), 1)
        self.assertEqual(json.loads(output.getvalue())['error_code'],'PANEL_HELPER_UNAVAILABLE')

if __name__=='__main__': unittest.main()
