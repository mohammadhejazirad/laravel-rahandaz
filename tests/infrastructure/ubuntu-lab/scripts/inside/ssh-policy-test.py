#!/usr/bin/env python3
"""Real OpenSSH parser tests only: no daemon start/reload, network or host mutation."""
import pathlib
import subprocess
import tempfile
import unittest

TEMPLATE = pathlib.Path('/opt/rahandaz/installer/templates/ssh/rahandaz-admin-key-only.conf')

class SshPolicyTest(unittest.TestCase):
    def test_effective_account_policy_and_preserved_bootstrap_users(self):
        pathlib.Path('/run/sshd').mkdir(mode=0o755, exist_ok=True)
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            key = root / 'host-key'
            subprocess.run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-f', str(key)], check=True)
            baseline = root / 'sshd_config.before'
            baseline.write_text(f'HostKey {key}\nPort 22222\nUsePAM yes\nPubkeyAuthentication yes\nPasswordAuthentication yes\nKbdInteractiveAuthentication yes\nPermitRootLogin yes\n')
            candidate = root / 'sshd_config.after'
            candidate.write_text(baseline.read_text() + f'Include {TEMPLATE}\n')
            for config in [baseline, candidate]:
                subprocess.run(['/usr/sbin/sshd', '-t', '-f', str(config)], check=True, capture_output=True)
            def effective(config, user):
                result = subprocess.run(['/usr/sbin/sshd', '-T', '-f', str(config), '-C', f'user={user},host=localhost,addr=127.0.0.1'], check=True, capture_output=True, text=True)
                return dict(line.split(' ', 1) for line in result.stdout.splitlines())
            for user in ['root', 'other-admin', 'rahandaz-svc']:
                self.assertEqual(effective(baseline, user), effective(candidate, user), user)
            actual = effective(candidate, 'rahandaz')
            self.assertEqual(actual['authenticationmethods'], 'publickey')
            self.assertEqual(actual['passwordauthentication'], 'no')
            self.assertEqual(actual['kbdinteractiveauthentication'], 'no')
            self.assertEqual(actual['pubkeyauthentication'], 'yes')
            self.assertEqual(actual['usepam'], 'yes')
            self.assertEqual(actual['authorizedkeysfile'], '/etc/ssh/rahandaz/authorized_keys/%u')
            self.assertEqual(actual['authorizedkeyscommand'], 'none')
            self.assertEqual(actual['trustedusercakeys'], 'none')
            # Include context is scoped by OpenSSH. Verify both early and late placement,
            # rather than assuming that a fragment leaks Match state into its parent.
            early = root / 'sshd_config.early'
            early.write_text(f'Include {TEMPLATE}\n' + baseline.read_text())
            subprocess.run(['/usr/sbin/sshd', '-t', '-f', str(early)], check=True, capture_output=True)
            for user in ['root', 'other-admin', 'rahandaz-svc', 'rahandaz']:
                self.assertEqual(effective(early, user), effective(candidate, user), user)


    def test_sandbox_hardening_requires_publickey_and_denies_root(self):
        pathlib.Path('/run/sshd').mkdir(mode=0o755, exist_ok=True)
        with tempfile.TemporaryDirectory() as directory:
            script = r'''set -euo pipefail
source /opt/rahandaz/infrastructure/operations/lib/ssh.sh
OPS_SANDBOX="$1"
ops_wants_apply() { return 0; }
ops_require_sandbox_apply() { return 0; }
# Fixture only: these are deliberately NOT proof of an SSH connection.
ops_flag() { return 0; }
ops_snapshot_file() { printf fixture; }
ops_refuse_without_systemd() { return 0; }
ops_succeed() { return 0; }
ops_exec() {
    case "$1" in
        sshd) /usr/sbin/sshd "${@:2}" ;;
        systemctl) [[ "$*" == 'systemctl reload ssh' ]] ;;
        *) return 1 ;;
    esac
}
ops_ssh_harden
'''
            subprocess.run(['bash', '-c', script, 'fixture', directory], check=True, capture_output=True)
            config = pathlib.Path(directory) / 'etc/ssh/sshd_config.d/rahandaz.conf'
            for user in ['root', 'rahandaz']:
                result = subprocess.run(['/usr/sbin/sshd', '-T', '-f', str(config), '-C', f'user={user},host=localhost,addr=127.0.0.1'], check=True, capture_output=True, text=True)
                actual = dict(line.split(' ', 1) for line in result.stdout.splitlines())
                self.assertEqual(actual['authenticationmethods'], 'publickey')
                self.assertEqual(actual['passwordauthentication'], 'no')
                self.assertEqual(actual['kbdinteractiveauthentication'], 'no')
                self.assertEqual(actual['permitrootlogin'], 'no')

if __name__ == '__main__':
    unittest.main(verbosity=2)
