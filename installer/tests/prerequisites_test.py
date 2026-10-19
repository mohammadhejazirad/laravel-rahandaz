#!/usr/bin/env python3
"""Prerequisite contract tests. Every mutating command is a shell fixture, never host APT."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class PrerequisitesTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='rahandaz-prerequisites-test.')
        self.addCleanup(self.temp.cleanup)
        self.work = Path(self.temp.name)
        self.header = '''set -Eeuo pipefail
export LC_ALL=C
INSTALLER_HOME="$PROJECT_ROOT/installer"
source "$INSTALLER_HOME/lib/common.sh"
source "$INSTALLER_HOME/lib/os.sh"
source "$INSTALLER_HOME/lib/gate.sh"
source "$INSTALLER_HOME/lib/php-runtime.sh"
source "$INSTALLER_HOME/lib/geo.sh"
source "$INSTALLER_HOME/lib/prerequisites.sh"
# Fixture prompts stay deterministic. Live geo runs only on a real sysroot outside this harness.
export INSTALLER_OFFLINE=1
PREREQ_STATE="$TEST_WORK/state"
PREREQ_APT_DIR="$TEST_WORK/apt"
PREREQ_POLICY="$TEST_WORK/policy"
mkdir -p "$PREREQ_STATE/owned" "$PREREQ_APT_DIR/parts"
PREREQ_YES=0 PREREQ_APPROVE_REPAIR=0 PREREQ_OWNS_POLICY=0
# Fail closed if a test accidentally reaches a real mutation command.
apt-get() { echo UNMOCKED_APT >&2; exit 99; }
systemctl() { echo UNMOCKED_SYSTEMCTL >&2; exit 99; }
dpkg() { echo UNMOCKED_DPKG >&2; exit 99; }
'''

    def run_bash(self, code, input=''):
        return subprocess.run(['bash', '-c', self.header + code], input=input, text=True,
                              stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                              env={**os.environ, 'PROJECT_ROOT': str(ROOT), 'TEST_WORK': str(self.work)}, timeout=20)

    def good(self, result):
        self.assertEqual(result.returncode, 0, result.stdout)

    def bad(self, result, message):
        self.assertNotEqual(result.returncode, 0, result.stdout)
        self.assertIn(message, result.stdout)

    def main_fixture(self):
        return '''installer_detect_os() { INSTALLER_OS_VERSION=24.04; INSTALLER_OS_CODENAME=noble; INSTALLER_ARCH=amd64; }
prereq_resources() { echo RESOURCE_REPORT; }
installer_mutation_allowed() { :; }
prereq_capacity() { :; }
prereq_php_default_guard() { :; }
prereq_init() { echo MUTATION_REACHED; exit 73; }
'''

    def test_plan_no_mutation(self):
        result = self.run_bash(self.main_fixture() + 'prereq_main --plan')
        self.good(result)
        self.assertNotIn('MUTATION_REACHED', result.stdout)
        self.assertNotIn('[y/n]', result.stdout)

    def test_resource_confirmation_before_mirror(self):
        result = self.run_bash(self.main_fixture() + 'prereq_main', 'y\ny\nn\n')
        self.bad(result, 'Proceed with prerequisite installation?')
        self.assertLess(result.stdout.index('RESOURCE_REPORT'), result.stdout.index('resource report correct'))
        self.assertLess(result.stdout.index('resource report correct'), result.stdout.index('located in Iran'))
        self.assertLess(result.stdout.index('located in Iran'), result.stdout.index('Proceed with'))
        self.assertIn('Iranian Ubuntu mirrors: yes', result.stdout)
        self.assertNotIn('MUTATION_REACHED', result.stdout)

    def test_no_selects_official(self):
        result = self.run_bash(self.main_fixture() + 'prereq_main', 'y\nn\nn\n')
        self.assertIn('Iranian Ubuntu mirrors: no', result.stdout)
        self.assertNotIn('MUTATION_REACHED', result.stdout)

    def test_decline_resources_aborts_before_mirror(self):
        result = self.run_bash(self.main_fixture() + 'prereq_main', 'n\n')
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('located in Iran', result.stdout)
        self.assertNotIn('MUTATION_REACHED', result.stdout)

    def test_eof_is_not_a_no_choice(self):
        result = self.run_bash(self.main_fixture() + 'prereq_main', 'y\n')
        self.bad(result, 'Mirror selection is required')
        self.assertNotIn('MUTATION_REACHED', result.stdout)

    def test_invalid_answers_reprompt(self):
        result = self.run_bash(self.main_fixture() + 'prereq_main', 'maybe\ny\nmaybe\nn\nn\n')
        self.assertEqual(result.stdout.count('Please enter y or n.'), 2)
        self.assertNotIn('MUTATION_REACHED', result.stdout)

    def test_automation_requires_mirror_choice(self):
        self.bad(self.run_bash(self.main_fixture() + 'prereq_main --yes'), 'explicit --iranian-mirrors')

    def test_invalid_options(self):
        for args in ['--iranian-mirrors', '--iranian-mirrors maybe', '--unknown']:
            self.bad(self.run_bash('prereq_main ' + args), 'INVALID_INPUT')

    def test_reject_other_distribution_and_unsupported_version(self):
        for os_id, version, codename in [('debian', '12', 'bookworm'), ('ubuntu', '20.04', 'focal')]:
            (self.work / 'os-release').write_text(f'ID={os_id}\nVERSION_ID="{version}"\nVERSION_CODENAME={codename}\n')
            self.bad(self.run_bash('INSTALLER_OS_RELEASE="$TEST_WORK/os-release"; installer_detect_os'), 'UNSUPPORTED_OS')

    def test_accept_three_target_releases(self):
        for version, codename in [('22.04', 'jammy'), ('24.04', 'noble'), ('26.04', 'resolute')]:
            (self.work / 'os-release').write_text(f'ID=ubuntu\nVERSION_ID="{version}"\nVERSION_CODENAME={codename}\n')
            self.good(self.run_bash('uname() { echo x86_64; }; INSTALLER_OS_RELEASE="$TEST_WORK/os-release"; installer_detect_os'))

    def test_reject_other_architecture(self):
        (self.work / 'os-release').write_text('ID=ubuntu\nVERSION_ID="24.04"\nVERSION_CODENAME=noble\n')
        self.bad(self.run_bash('uname() { echo aarch64; }; INSTALLER_OS_RELEASE="$TEST_WORK/os-release"; installer_detect_os'), 'UNSUPPORTED_ARCHITECTURE')

    def test_fully_installed_packages_skip_apt(self):
        self.good(self.run_bash('dpkg-query() { printf "install ok installed"; }; prereq_install_packages nginx redis-server'))

    def test_unpacked_is_not_installed_and_rerun_rechecks(self):
        self.good(self.run_bash('''dpkg-query() { if [[ -f "$TEST_WORK/installed" ]]; then printf 'install ok installed'; else printf 'install ok unpacked'; fi; }
prereq_apt() { echo "$*" >>"$TEST_WORK/apt-calls"; [[ "$1" == --simulate ]] || touch "$TEST_WORK/installed"; }
prereq_install_packages nginx
prereq_install_packages nginx
[[ $(wc -l <"$TEST_WORK/apt-calls") == 2 ]]
grep -q -- --no-remove "$TEST_WORK/apt-calls"
'''))

    def test_incomplete_package_after_apt_is_failure(self):
        self.bad(self.run_bash("dpkg-query() { echo 'install ok half-configured'; }; prereq_apt() { :; }; prereq_install_packages nginx"), 'PACKAGE_INCOMPLETE')

    def test_download_failure_rerun_keeps_actual_state(self):
        first = self.run_bash("dpkg-query() { return 1; }; prereq_apt() { echo NETWORK_DOWN; return 1; }; prereq_install_packages nginx")
        self.bad(first, 'NETWORK_DOWN')
        self.assertFalse((self.work / 'state' / 'owned' / 'nginx').exists())

    def test_repair_requires_explicit_approval(self):
        self.bad(self.run_bash('''PREREQ_YES=1
dpkg() { [[ "$1" == --audit ]] && { echo 'pending external package'; return 0; }; echo MUST_NOT_CONFIGURE; exit 99; }
prereq_repair_packages'''), 'REPAIR_APPROVAL_REQUIRED')

    def test_repair_never_allows_removals(self):
        self.good(self.run_bash('''PREREQ_APPROVE_REPAIR=1
dpkg() { [[ "$1" != --force-confold ]] || shift; if [[ "$1" == --audit && ! -f "$TEST_WORK/repaired" ]]; then echo pending; elif [[ "$1" == --configure ]]; then touch "$TEST_WORK/repaired"; fi; }
prereq_apt() { echo "$*" >>"$TEST_WORK/repair-calls"; }
prereq_repair_packages
grep -q -- '--simulate --no-remove --fix-broken' "$TEST_WORK/repair-calls"
grep -q -- '-y --no-remove --fix-broken' "$TEST_WORK/repair-calls"
'''))

    def test_external_policy_preserved(self):
        (self.work / 'policy').write_text('# external administrator policy\n')
        self.bad(self.run_bash('prereq_policy_acquire'), 'POLICY_CONFLICT')
        self.assertEqual((self.work / 'policy').read_text(), '# external administrator policy\n')

    def test_own_policy_cleanup_and_interrupted_recovery(self):
        self.good(self.run_bash('prereq_policy_acquire; PREREQ_OWNS_POLICY=0; chmod 0600 "$PREREQ_POLICY"; prereq_policy_acquire; [[ -x "$PREREQ_POLICY" ]]; prereq_cleanup; [[ ! -e "$PREREQ_POLICY" ]]'))

    def test_nonexecutable_external_deny_policy_is_not_accepted(self):
        (self.work / 'policy').write_text('#!/bin/sh\nexit 101\n')
        (self.work / 'policy').chmod(0o600)
        self.bad(self.run_bash('prereq_policy_acquire'), 'POLICY_CONFLICT')
        self.assertEqual((self.work / 'policy').stat().st_mode & 0o777, 0o600)

    def test_changed_policy_not_deleted(self):
        self.good(self.run_bash('prereq_policy_acquire; echo external >"$PREREQ_POLICY"; prereq_cleanup; [[ -f "$PREREQ_POLICY" ]]'))

    def test_official_sources_and_security_suite(self):
        self.good(self.run_bash('''INSTALLER_OS_CODENAME=noble PREREQ_IRAN=no
prereq_apt() { :; }
prereq_freshness() { :; }
prereq_select_sources
grep -q 'noble-security' "$PREREQ_APT_DIR/ubuntu.sources"
grep -q 'Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg' "$PREREQ_APT_DIR/ubuntu.sources"
grep -q 'archive.ubuntu.com' "$PREREQ_APT_DIR/ubuntu.sources"
'''))

    def test_iranian_primary_is_liara(self):
        self.good(self.run_bash('''INSTALLER_OS_CODENAME=noble PREREQ_IRAN=yes
prereq_freshness() { :; }
prereq_apt() { :; }
prereq_select_sources
grep -q mirror_selected=liara "$PREREQ_STATE/audit.log"
grep -q linux-mirror.liara.ir "$PREREQ_APT_DIR/ubuntu.sources"
'''))

    def test_disabled_stale_mirror_is_never_attempted(self):
        self.good(self.run_bash('''INSTALLER_OS_CODENAME=noble PREREQ_IRAN=yes
prereq_apt() { cat "$PREREQ_APT_DIR/ubuntu.sources" >>"$TEST_WORK/attempts"; return 1; }
if prereq_select_sources; then exit 99; fi
! grep -q chabokan "$TEST_WORK/attempts"
'''))

    def test_iranian_fallback_no_silent_upstream(self):
        self.good(self.run_bash('''INSTALLER_OS_CODENAME=noble PREREQ_IRAN=yes
prereq_freshness() { :; }
prereq_apt() { ! grep -q linux-mirror.liara "$PREREQ_APT_DIR/ubuntu.sources"; }
prereq_select_sources
grep -q mirror.arvancloud.ir "$PREREQ_APT_DIR/ubuntu.sources"
! grep -q ubuntu-archive "$PREREQ_STATE/audit.log"
'''))

    def test_all_mirrors_fail_closed(self):
        self.bad(self.run_bash('INSTALLER_OS_CODENAME=resolute PREREQ_IRAN=yes; prereq_apt() { return 1; }; prereq_select_sources'), 'MIRROR_UNAVAILABLE')

    def test_existing_inactive_service_not_started_unattended(self):
        self.bad(self.run_bash('''PREREQ_YES=1
systemctl() { case "$1" in is-enabled) echo disabled ;; is-active) return 3 ;; *) echo MUST_NOT_START; exit 99 ;; esac; }
prereq_service nginx nginx.service 80
'''), 'EXISTING_SERVICE')

    def test_masked_service_not_unmasked(self):
        self.bad(self.run_bash('systemctl() { echo masked; }; prereq_service nginx nginx.service 80'), 'SERVICE_MASKED')

    def test_active_service_not_restarted(self):
        self.good(self.run_bash('''systemctl() { case "$1" in is-enabled) echo enabled ;; is-active) return 0 ;; *) exit 99 ;; esac; }
prereq_service nginx nginx.service 80'''))

    def test_port_conflict_not_killed(self):
        self.bad(self.run_bash('''touch "$PREREQ_STATE/owned/nginx"
systemctl() { case "$1" in is-enabled) echo disabled ;; is-active) return 3 ;; *) exit 99 ;; esac; }
ss() { echo listener; }
prereq_service nginx nginx.service 80'''), 'PORT_CONFLICT')

    def test_new_service_started_and_verified(self):
        self.good(self.run_bash('''touch "$PREREQ_STATE/owned/nginx"
systemctl() { case "$1" in is-enabled) echo disabled ;; is-active) [[ -f "$TEST_WORK/active" ]] ;; start) touch "$TEST_WORK/active" ;; enable) : ;; *) exit 99 ;; esac; }
ss() { :; }
prereq_service nginx nginx.service 80
[[ -f "$TEST_WORK/active" ]]'''))

    def test_postgres_aggregate_active_is_not_health(self):
        self.bad(self.run_bash('''prereq_service() { :; }
pg_lsclusters() { echo '16 main 5432 down postgres /data /logs'; }
prereq_health'''), 'POSTGRES_UNHEALTHY')

    def test_no_postgres_cluster_is_not_success(self):
        self.bad(self.run_bash('prereq_service() { :; }; pg_lsclusters() { :; }; prereq_health'), 'POSTGRES_UNHEALTHY')

    def test_metadata_missing_is_not_healthy(self):
        self.bad(self.run_bash('INSTALLER_OS_CODENAME=noble; mkdir -p "$PREREQ_APT_DIR/lists"; prereq_freshness'), 'METADATA_MISSING')

    def test_metadata_freshness_checks_security_and_updates(self):
        self.good(self.run_bash('''INSTALLER_OS_CODENAME=noble
mkdir -p "$PREREQ_APT_DIR/lists"
for suite in security updates; do
    printf 'Date: %s\\n' "$(date -Ru)" >"$PREREQ_APT_DIR/lists/mirror_dists_noble-${suite}_InRelease"
done
prereq_freshness
'''))

    def test_stale_security_metadata_rejected(self):
        self.bad(self.run_bash('''INSTALLER_OS_CODENAME=noble
mkdir -p "$PREREQ_APT_DIR/lists"
printf 'Date: %s\\n' "$(date -Ru -d '8 days ago')" >"$PREREQ_APT_DIR/lists/mirror_dists_noble-security_InRelease"
prereq_freshness
'''), 'Rejected stale')

    def test_foreign_os_fixture_cannot_override_live_entry(self):
        result = subprocess.run(['bash', str(ROOT / 'installer/bin/rahandaz-prerequisites'), '--plan'],
                                text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                env={**os.environ, 'INSTALLER_OS_RELEASE': '/does-not-exist'}, timeout=10)
        self.assertNotIn('os-release is missing', result.stdout)

    def test_php_source_allows_only_named_dependency_exceptions(self):
        for codename in ['jammy', 'noble', 'resolute']:
            result = self.run_bash('''INSTALLER_OS_CODENAME=''' + codename + '''
installer_php_key_ok() { :; }
gpg() { touch "$PREREQ_APT_DIR/php.gpg"; }
prereq_apt() { :; }
prereq_php_source
cat "$PREREQ_APT_DIR/preferences"
''')
            self.good(result)
            self.assertIn('Package: php8.4-* php-common', result.stdout)
            self.assertIn('Pin-Priority: -1', result.stdout)
            self.assertEqual(' libgd3' in result.stdout, codename == 'jammy')

    def test_apt_failure_exit_is_normalized_and_audited(self):
        result = self.run_bash('''trap 'prereq_on_error "$?"' ERR
false
''')
        self.assertEqual(result.returncode, 1)
        self.assertIn('failed_exit=1', (self.work / 'state/audit.log').read_text())

    def test_all_bash_sources_are_ascii(self):
        # Scan project sources, including extensionless Bash entry points.
        # Web translations belong in templates, never in shell sources.
        excluded = {'.git', 'node_modules', 'vendor', 'storage', 'var',
                    '__pycache__', '.venv', 'reports'}
        for directory, subdirectories, files in os.walk(ROOT):
            subdirectories[:] = sorted(set(subdirectories) - excluded)
            for name in sorted(files):
                path = Path(directory) / name
                if path.is_symlink() or not path.is_file():
                    continue
                if path.suffix in ('.sh', '.bash') or (
                    not path.suffix and b'bash' in path.read_bytes().split(b'\n', 1)[0]
                ):
                    with self.subTest(path=str(path.relative_to(ROOT))):
                        path.read_text().encode('ascii')


if __name__ == '__main__':
    unittest.main(verbosity=2)
