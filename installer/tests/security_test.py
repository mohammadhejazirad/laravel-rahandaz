#!/usr/bin/env python3
"""Bootstrap security contracts. No host account, firewall or datastore mutations."""
import pathlib
import re
import subprocess
import tempfile
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
LIB = ROOT / 'installer/lib/security.sh'

class SecurityTests(unittest.TestCase):
    def run_shell(self, script):
        return subprocess.run(['bash', '-euo', 'pipefail', '-c', f'source "{LIB}"; ' + script], capture_output=True, text=True)

    def test_password_entropy_format_permissions_and_resume(self):
        with tempfile.TemporaryDirectory() as directory:
            paths = [pathlib.Path(directory) / str(i) for i in range(64)]
            result = self.run_shell('; '.join(f'security_password_once "{p}"' for p in paths))
            self.assertEqual(result.returncode, 0, result.stderr)
            values = [p.read_text().strip() for p in paths]
            self.assertEqual(len(set(values)), len(values))
            for p, value in zip(paths, values):
                self.assertEqual(len(value), 12)
                for pattern in [r'[a-z]', r'[A-Z]', r'[0-9]', r'[!@%_+\-]']:
                    self.assertRegex(value, pattern)
                self.assertEqual(p.stat().st_mode & 0o777, 0o600)
            before = paths[0].read_bytes()
            self.assertEqual(self.run_shell(f'security_password_once "{paths[0]}"').returncode, 0)
            self.assertEqual(paths[0].read_bytes(), before)
            self.assertEqual(result.stdout, '')

    def test_reject_symlink_empty_and_public_secret(self):
        with tempfile.TemporaryDirectory() as directory:
            base = pathlib.Path(directory)
            target = base / 'target'; target.write_text('external')
            link = base / 'link'; link.symlink_to(target)
            empty = base / 'empty'; empty.touch(mode=0o600)
            public = base / 'public'; public.write_text('do-not-adopt'); public.chmod(0o644)
            multiline = base / 'multiline'; multiline.write_text('first\nsecond\n'); multiline.chmod(0o600)
            for p in [link, empty, public, multiline]:
                self.assertNotEqual(self.run_shell(f'security_password_once "{p}"').returncode, 0)
            self.assertEqual(target.read_text(), 'external')

    def test_preserve_existing_long_secret(self):
        with tempfile.TemporaryDirectory() as directory:
            p = pathlib.Path(directory) / 'password'
            p.write_text('ExistingLegacyPasswordWhichMustNotRotate\n'); p.chmod(0o600)
            before = p.read_bytes()
            self.assertEqual(self.run_shell(f'security_password_once "{p}"').returncode, 0)
            self.assertEqual(p.read_bytes(), before)

    def test_private_credential_bundle_and_no_stdout_secret(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            p = root / 'etc/rahandaz/database.password'; p.parent.mkdir(parents=True)
            p.write_text('ExampleFixtureOnly123'); p.chmod(0o600)
            result = self.run_shell(f'installer_say() {{ :; }}; security_credentials "{root}" no')
            self.assertEqual(result.returncode, 0, result.stderr)
            bundle = root / 'root/rahandaz-system-credentials.txt'
            self.assertIn('ExampleFixtureOnly123', bundle.read_text())
            self.assertEqual(bundle.stat().st_mode & 0o777, 0o600)
            self.assertNotIn('ExampleFixtureOnly123', result.stdout + result.stderr)

    def test_service_identity_and_firewall_contract(self):
        for relative in ['php-fpm/rahandaz.conf.tpl', 'systemd/rahandaz-queue.service.tpl', 'systemd/rahandaz-scheduler.service.tpl']:
            text = (ROOT / 'installer/templates' / relative).read_text()
            self.assertIn('rahandaz-svc', text)
            self.assertNotRegex(text, r'(?im)^user\s*=\s*rahandaz$')
        firewall = (ROOT / 'installer/bin/rahandaz-firewall').read_text()
        self.assertLess(firewall.index('systemd-run '), firewall.index('ufw --force enable'))
        self.assertIn('SECOND_CONNECTION_REQUIRED', firewall)
        self.assertIn('EXTERNAL_FIREWALL', firewall)
        self.assertNotIn('ufw reset', firewall)
        self.assertNotRegex(firewall, r'ufw allow (5432|6379|6380|6381)')

if __name__ == '__main__':
    unittest.main(verbosity=2)
