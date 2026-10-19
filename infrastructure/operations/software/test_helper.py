#!/usr/bin/python3
"""Contract tests: never run APT or systemctl outside the explicit Ubuntu lab test."""
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import io
import tarfile
import zipfile
import hashlib
import socket
import signal
import time
from types import SimpleNamespace
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('software_helper', Path(__file__).with_name('helper.py'))
helper = importlib.util.module_from_spec(spec)
spec.loader.exec_module(helper)


class ContractTests(unittest.TestCase):
    def data(self):
        return {'schema_version': '1', 'action': 'start', 'operation': 'software.install',
                'execution_id': '01999694-d50a-7000-8000-000000000001', 'packages': ['jq']}

    def test_valid_minimal_setup_and_install(self):
        self.assertEqual(helper.request(self.data())['packages'], ['jq'])
        data = self.data() | {'operation': 'setup.initialize', 'packages': []}
        self.assertEqual(helper.request(data), data)

    def test_rejects_extra_fields_commands_urls_versions_and_duplicate_packages(self):
        mutations = [{'command': 'id'}, {'schema_version': 1}, {'execution_id': '../etc'},
                     {'packages': ['jq;id']}, {'packages': ['https://example.com']}, {'packages': ['jq', 'jq']},
                     {'packages': ['bash']}, {'packages': []}, {'operation': 'package.install'}, {'action': 'cancel'}]
        for mutation in mutations:
            with self.subTest(mutation=mutation), self.assertRaises(helper.Refused):
                helper.request(self.data() | mutation)

    def test_rejects_apt_upgrades_removals_and_unparseable_versions(self):
        for text in ['Remv nginx [1.0]', 'Inst libc6 [1.0] (2.0 Ubuntu [amd64])', 'Inst jq (bad;id repo)']:
            with self.subTest(text=text), self.assertRaises(helper.Refused):
                helper.apt_plan(text)
        self.assertEqual(helper.apt_plan('Inst jq (1.7.1-3ubuntu0.24.04.1 Ubuntu:24.04 [amd64])'), {'jq': '1.7.1-3ubuntu0.24.04.1'})

    def test_peer_policy_is_exact_uid_not_socket_group(self):
        source = Path(helper.__file__).read_text()
        self.assertIn('socket.SO_PEERCRED', source)
        self.assertIn("uid != pwd.getpwnam('rahandaz-svc').pw_uid", source)
        self.assertNotIn('shell=True', source)

    def test_trusted_paths_refuse_symlinks_and_service_writable_files(self):
        with tempfile.TemporaryDirectory() as name:
            target = Path(name)/'file'; target.write_text('x')
            link = Path(name)/'link'; link.symlink_to(target)
            with self.assertRaises(helper.Refused): helper.trusted(link)
            target.chmod(0o666)
            with self.assertRaises(helper.Refused): helper.trusted(target)

    def test_apt_plan_pins_all_new_dependencies_and_reauthorizes_before_apply(self):
        with tempfile.TemporaryDirectory() as name:
            data = self.data()
            directory = Path(name)/data['execution_id']; directory.mkdir()
            commands = []
            events = []
            def command(args, timeout=30):
                commands.append(args); events.append('run')
                return 'Inst libjq1 (1.7 Ubuntu [amd64])\nInst jq (1.7 Ubuntu [amd64])' if '--simulate' in args else ''
            def auth(_): events.append('authorize')
            with patch.object(helper, 'ROOT', Path(name)), patch.object(helper, 'installed', side_effect=[None, '1.7']), patch.object(helper, 'run', side_effect=command), patch.object(helper, 'authorize', side_effect=auth), patch.object(helper, 'verify_tool'):
                result = helper.install('jq', data)
            self.assertTrue(result['verified'])
            apply = commands[-1]
            self.assertIn('libjq1=1.7', apply)
            self.assertIn('jq=1.7', apply)
            self.assertIn('--no-upgrade', apply)
            self.assertIn('--no-remove', apply)
            self.assertLess(events.index('authorize'), len(events)-1)
            self.assertEqual(json.loads((directory/'plan.json').read_text()), {'jq': {'libjq1': '1.7', 'jq': '1.7'}})

    def test_changed_plan_or_revoked_authorization_never_applies(self):
        for revoked in (True, False):
            with tempfile.TemporaryDirectory() as name:
                data = self.data(); (Path(name)/data['execution_id']).mkdir()
                commands = []
                def run(args, timeout=30):
                    commands.append(args)
                    if '--simulate' in args: return 'Inst jq (1.7 Ubuntu [amd64])' if len(commands) == 1 else 'Inst jq (1.8 Ubuntu [amd64])'
                    return ''
                with patch.object(helper, 'ROOT', Path(name)), patch.object(helper, 'installed', return_value=None), patch.object(helper, 'run', side_effect=run), patch.object(helper, 'authorize', side_effect=helper.Refused('AUTHORIZATION_REVOKED') if revoked else None):
                    with self.assertRaises(helper.Refused): helper.install('jq', data)
                self.assertFalse(any('install' in cmd and '--simulate' not in cmd and '--download-only' not in cmd for cmd in commands))

    def test_installed_package_is_verified_without_apt(self):
        with patch.object(helper, 'installed', return_value='1.7'), patch.object(helper, 'run') as command, patch.object(helper, 'verify_tool') as verify:
            self.assertFalse(helper.install('jq', self.data())['changed'])
            command.assert_not_called(); verify.assert_called_once_with('jq')

    def test_authorization_drops_all_supplementary_groups_and_requires_json_proof(self):
        with patch.object(helper, 'trusted_release'), patch.object(helper.pwd, 'getpwnam', return_value=SimpleNamespace(pw_uid=999, pw_gid=999)):
            for proof, code in [(b'{"schema_version":"1","authorized":true}', 0), (b'', 0), (b'{"schema_version":"1","authorized":false}', 1)]:
                with patch.object(helper.subprocess, 'run', return_value=SimpleNamespace(returncode=code, stdout=proof)) as process:
                    if not code and proof:
                        helper.authorize(self.data())
                    else:
                        with self.assertRaises(helper.Refused): helper.authorize(self.data())
                    self.assertEqual(process.call_args.kwargs['user'], 999)
                    self.assertEqual(process.call_args.kwargs['group'], 999)
                    self.assertEqual(process.call_args.kwargs['extra_groups'], [])
                    self.assertEqual(process.call_args.args[0][0], '/usr/bin/php8.4')

    def test_journal_request_identity_and_orphan_recovery(self):
        self.assertTrue(helper.same_request(self.data(), self.data() | {'action': 'status'}))
        self.assertFalse(helper.same_request(self.data(), self.data() | {'packages': ['htop']}))
        self.assertFalse(helper.alive({'pid': 0, 'boot_id': 'old-boot', 'pid_start': '1'}))

    def test_authorization_process_failure_is_closed_and_distinct_from_invalid_input(self):
        for error in (PermissionError('identity denied'), helper.subprocess.TimeoutExpired('php', 10)):
            with self.subTest(error=type(error).__name__), patch.object(helper, 'trusted_release'), patch.object(helper, 'service_identity', return_value={}), patch.object(helper.subprocess, 'run', side_effect=error):
                with self.assertRaisesRegex(helper.Refused, '^AUTHORIZATION_CHECK_FAILED$'):
                    helper.authorize(self.data())

    def test_each_required_unit_is_checked_individually(self):
        for missing in ('nginx', 'rahandaz-php-fpm', 'rahandaz-queue', 'rahandaz-scheduler.timer', 'redis-rahandaz-queue', 'redis-rahandaz-cache'):
            calls = []
            def run(args, timeout=30):
                calls.append(args)
                self.assertEqual(len(args), 4)
                if args[-1] == missing: raise helper.Refused('COMMAND_FAILED')
                return ''
            with self.subTest(missing=missing), patch.object(helper, 'run', side_effect=run), self.assertRaises(helper.Refused):
                helper.verify_services()
            self.assertEqual(calls[-1][-1], missing)

    def test_setup_excludes_every_non_setup_package_and_catalog_has_categories(self):
        for key, entry in helper.catalog().items():
            self.assertIn('category', entry)
            self.assertTrue(entry['executable_path'].startswith('/'))
            if not entry['setup']:
                with self.assertRaises(helper.Refused):
                    helper.request(self.data() | {'operation': 'setup.initialize', 'packages': [key]})

    def test_archive_traversal_links_devices_and_duplicate_members_are_rejected(self):
        for unsafe in ('../escape', 'root/../../escape', '/root/escape', 'root//escape'):
            with tempfile.TemporaryDirectory() as name:
                archive = Path(name)/'archive.zip'
                with zipfile.ZipFile(archive, 'w') as output:
                    output.writestr(unsafe, b'x')
                with self.assertRaises(helper.Refused):
                    helper.safe_extract(archive, Path(name)/'out', {'archive_root': 'root', 'format': 'zip'})
        for kind, target in ((tarfile.SYMTYPE, '../../escape'), (tarfile.LNKTYPE, 'root/bin'), (tarfile.CHRTYPE, '')):
            with tempfile.TemporaryDirectory() as name:
                archive = Path(name)/'archive.tar'
                with tarfile.open(archive, 'w') as output:
                    item = tarfile.TarInfo('root/link'); item.type = kind; item.linkname = target
                    output.addfile(item)
                with self.assertRaises(helper.Refused):
                    helper.safe_extract(archive, Path(name)/'out', {'archive_root': 'root', 'format': 'tar.gz'})

    def test_extraction_strips_setid_and_retains_only_internal_relative_symlinks(self):
        with tempfile.TemporaryDirectory() as name:
            archive = Path(name)/'archive.tar'
            with tarfile.open(archive, 'w') as output:
                item = tarfile.TarInfo('root/bin/node'); item.mode = 0o6777; item.size = 3
                output.addfile(item, io.BytesIO(b'bin'))
                item = tarfile.TarInfo('root/bin/node-link'); item.type = tarfile.SYMTYPE; item.linkname = 'node'
                output.addfile(item)
            destination = Path(name)/'out'
            old_umask = os.umask(0o077)
            try:
                helper.safe_extract(archive, destination, {'archive_root': 'root', 'format': 'tar.gz'})
            finally:
                os.umask(old_umask)
            self.assertEqual((destination/'root/bin').stat().st_mode & 0o7777, 0o755)
            self.assertEqual((destination/'root/bin/node').stat().st_mode & 0o7777, 0o755)
            self.assertEqual((destination/'root/bin/node-link').read_bytes(), b'bin')

    def test_download_checks_combined_hash_and_refuses_non_https_without_network(self):
        with tempfile.TemporaryDirectory() as name:
            entry = {'url': 'http://example.invalid/installer', 'sha256': '0'*64}
            with patch.object(helper.urllib.request, 'build_opener') as opener, self.assertRaises(helper.Refused):
                helper.download_archive(entry, Path(name)/'download')
            opener.return_value.open.assert_not_called()
        with tempfile.TemporaryDirectory() as name:
            entry = {'url': 'https://example.invalid/archive', 'sha256': '0'*64}
            with patch.object(helper.urllib.request, 'build_opener') as opener:
                opener.return_value.open.return_value = io.BytesIO(b'wrong bytes')
                with self.assertRaisesRegex(helper.Refused, 'CHECKSUM_MISMATCH'):
                    helper.download_archive(entry, Path(name)/'download')

    def test_timeout_kills_descendants_holding_output_pipes(self):
        started = time.monotonic()
        with tempfile.TemporaryDirectory() as name, patch.object(helper, 'RELEASE', Path(name)):
            with self.assertRaisesRegex(helper.Refused, '^COMMAND_TIMEOUT$'):
                helper.run(['/usr/bin/python3', '-c', 'import subprocess; subprocess.Popen(["/usr/bin/python3", "-c", "import time; time.sleep(60)"])'], timeout=0.2)
        self.assertLess(time.monotonic()-started, 3)

    def test_private_apt_preferences_preserve_installed_versions_without_global_writes(self):
        with tempfile.TemporaryDirectory() as name:
            data = self.data(); (Path(name)/data['execution_id']).mkdir()
            with patch.object(helper, 'ROOT', Path(name)), patch.object(helper.subprocess, 'run', return_value=SimpleNamespace(stdout=b'libc6:amd64\t2.39-1\tinstalled\nremoved\t1\tconfig-files\n')):
                preference = helper.preserve_installed_versions(data)
            self.assertTrue(preference.is_relative_to(Path(name)))
            self.assertIn('Package: libc6:amd64\nPin: version 2.39-1\nPin-Priority: 10001', preference.read_text())
            self.assertNotIn('Package: removed', preference.read_text())

    def test_optional_service_failure_cannot_report_installation_success(self):
        with patch.object(helper, 'run', side_effect=helper.Refused('COMMAND_FAILED')):
            with self.assertRaisesRegex(helper.Refused, '^PACKAGE_SERVICE_FAILED$'):
                helper.verify_optional_service('mysql')

    def test_pinned_mirror_fallback_never_accepts_a_different_checksum(self):
        with tempfile.TemporaryDirectory() as name:
            entry = {'url': 'https://official.invalid/archive', 'fallback_urls': ['https://mirror.invalid/archive'], 'sha256': hashlib.sha256(b'official bytes').hexdigest()}
            denied = helper.urllib.error.HTTPError(entry['url'], 403, 'Forbidden', {}, None)
            with patch.object(helper.time, 'sleep'), patch.object(helper.urllib.request, 'build_opener') as opener:
                opener.return_value.open.side_effect = [denied, denied, io.BytesIO(b'official bytes')]
                target = Path(name)/'download'
                helper.download_archive(entry, target)
                self.assertEqual(target.read_bytes(), b'official bytes')
                self.assertEqual([call.args[0] for call in opener.return_value.open.call_args_list], [entry['url'], entry['url'], entry['fallback_urls'][0]])
        with tempfile.TemporaryDirectory() as name, patch.object(helper.urllib.request, 'build_opener') as opener:
            opener.return_value.open.return_value = io.BytesIO(b'changed mirror bytes')
            with self.assertRaisesRegex(helper.Refused, '^CHECKSUM_MISMATCH$'):
                helper.download_archive(entry, Path(name)/'download')
            self.assertEqual(opener.return_value.open.call_count, 1)

    @unittest.skipUnless(os.geteuid() == 0, 'Real root state ownership is tested in the Ubuntu lab.')
    def test_real_socket_peer_rejection_async_ack_idempotency_and_crash_reconcile(self):
        with tempfile.TemporaryDirectory() as name:
            state = Path(name)/'state'
            def exchange(data, deny=False, slow=False, fail=None):
                client, server = socket.socketpair()
                pid = os.fork()
                if pid == 0:
                    client.close(); os.dup2(server.fileno(), 0); server.close()
                    helper.ROOT = state
                    helper.trusted = lambda path: None
                    helper.trusted_release = lambda: None
                    helper.authorize = lambda data: None
                    helper.run = lambda args, timeout=30: ''
                    def readiness():
                        if slow: time.sleep(10)
                        return {'os': '24.04', 'control_plane_verified': True}
                    helper.readiness = readiness
                    def install(package, data, progress):
                        progress('download')
                        if package == 'ffmpeg' and fail: raise helper.Refused(fail)
                        return {'package': package, 'version': '1.7', 'verified': True, 'changed': True}
                    helper.install = install
                    helper.pwd.getpwnam = lambda _: SimpleNamespace(pw_uid=os.getuid() + (1 if deny else 0))
                    helper.main(); os._exit(0)
                server.close(); client.settimeout(2)
                client.sendall(json.dumps(data).encode()); client.shutdown(socket.SHUT_WR)
                raw = bytearray()
                while True:
                    try:
                        chunk = client.recv(16384)
                    except ConnectionResetError:
                        if raw: break
                        raise
                    if not chunk: break
                    raw.extend(chunk)
                client.close()
                return json.loads(raw), pid
            result, pid = exchange(self.data(), deny=True)
            os.waitpid(pid, 0)
            self.assertEqual(result['error_code'], 'PEER_REFUSED')
            self.assertFalse(state.exists())
            result, pid = exchange(self.data(), slow=True)
            self.assertEqual(result['status'], 'running')  # Ack arrives before the slow work.
            os.kill(pid, signal.SIGKILL); os.waitpid(pid, 0)
            result, pid = exchange(self.data() | {'action': 'status'})
            os.waitpid(pid, 0)
            self.assertEqual(result['error_code'], 'INSTALL_INTERRUPTED')
            self.assertNotIn('pid', result)
            data = self.data() | {'execution_id': '01999694-d50a-7000-8000-000000000002'}
            result, pid = exchange(data); os.waitpid(pid, 0)
            self.assertEqual(result['status'], 'running')
            result, pid = exchange(data); os.waitpid(pid, 0)
            self.assertEqual(result['status'], 'succeeded')
            self.assertTrue(result['verified'])
            result, pid = exchange(data | {'packages': ['htop']}); os.waitpid(pid, 0)
            self.assertEqual(result['error_code'], 'REQUEST_CONFLICT')
            for index, failure in enumerate(('PACKAGE_DOWNLOAD_FAILED', 'AUTHORIZATION_REVOKED'), start=3):
                batch = self.data() | {'execution_id': '01999694-d50a-7000-8000-'+str(index).zfill(12), 'packages': ['ffmpeg', 'jq']}
                _, pid = exchange(batch, fail=failure); os.waitpid(pid, 0)
                result, pid = exchange(batch); os.waitpid(pid, 0)
                self.assertEqual(result['status'], 'failed')
                self.assertEqual(result['packages'][0]['status'], 'failed')
                self.assertEqual(result['packages'][1]['status'], 'skipped' if failure == 'AUTHORIZATION_REVOKED' else 'succeeded')
                self.assertEqual(result['summary']['failed'], 1)
                self.assertEqual(result['summary']['total'], 2)
                self.assertGreater(len(result['events']), 1)


if __name__ == '__main__': unittest.main()
