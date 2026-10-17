#!/usr/bin/env python3
"""Local signed-release/bootstrap tests. No network, sudo, APT or host mutation."""

import hashlib
import io
import os
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]


class ReleaseTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.key_directory = tempfile.TemporaryDirectory(prefix="rahandaz-release-key-")
        cls.key = Path(cls.key_directory.name) / "signing.key"
        cls.public_key = cls.key.with_suffix(".pub")
        subprocess.run(["openssl", "genpkey", "-algorithm", "RSA", "-pkeyopt", "rsa_keygen_bits:2048",
                        "-out", str(cls.key)], check=True, capture_output=True)
        subprocess.run(["openssl", "pkey", "-in", str(cls.key), "-pubout", "-out", str(cls.public_key)],
                       check=True, capture_output=True)

    @classmethod
    def tearDownClass(cls):
        cls.key_directory.cleanup()

    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix="rahandaz-release-test-")
        self.addCleanup(self.directory.cleanup)
        self.work = Path(self.directory.name)
        self.application = self.work / "application"
        self.version = "1.0.0-rc.1"
        files = {
            "artisan": "<?php\n",
            "composer.json": "{}\n", "composer.lock": "{}\n", "vendor/autoload.php": "<?php\n",
            "public/build/manifest.json": "{}\n", ".env.example": "APP_KEY=\n",
            "installer/bin/rahandaz-install": '#!/usr/bin/env bash\nset -eu\nprintf "%s\\n" "$@" >"$RAHANDAZ_RELEASE_TEST_MARKER"\nexit "${RAHANDAZ_RELEASE_TEST_EXIT:-0}"\n',
        }
        for name, content in files.items():
            path = self.application / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(content)
        self.environment = os.environ.copy()
        self.marker = self.work / "executed"
        self.environment["RAHANDAZ_RELEASE_TEST_MARKER"] = str(self.marker)
        self.environment["PYTHONDONTWRITEBYTECODE"] = "1"

    def command(self, *arguments, **kwargs):
        return subprocess.run([str(x) for x in arguments], cwd=ROOT, env=self.environment,
                              text=True, capture_output=True, **kwargs)

    def assemble(self, output="packed", version=None):
        return self.command("bash", "installer/release/assemble.sh", "--from", self.application,
                            "--version", version or self.version, "--output", self.work / output)

    def prepare(self, artifact=None):
        if artifact is None:
            result = self.assemble()
            self.assertEqual(result.returncode, 0, result.stderr)
            artifact = Path(result.stdout.strip())
        signed = self.work / "signed"
        result = self.command("bash", "installer/release/sign-manifest.sh", "--key", self.key,
                              "--artifact", artifact, "--version", self.version, "--output", signed)
        self.assertEqual(result.returncode, 0, result.stderr)
        assets = self.work / "assets"
        result = self.command("bash", "installer/release/prepare-github.sh", "--manifest", signed / "release.manifest",
                              "--signature", signed / "release.manifest.sig", "--public-key", self.public_key,
                              "--artifact", signed / artifact.name, "--repository", "mohammadhejazirad/laravel-rahandaz",
                              "--output", assets)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assets = assets
        return assets

    def mock_downloads(self):
        binaries = self.work / "bin"
        binaries.mkdir()
        curl = binaries / "curl"
        curl.write_text('''#!/usr/bin/env python3
import os, pathlib, shutil, sys
arguments = sys.argv[1:]
for flag in ("--proto", "--proto-redir"):
    if arguments[arguments.index(flag) + 1] != "=https":
        raise SystemExit(99)
if os.environ.get("RELEASE_TEST_CURL_FAIL"):
    raise SystemExit(60)
url = next(value for value in arguments if value.startswith("https://"))
base = "https://github.com/mohammadhejazirad/laravel-rahandaz/releases/download/v1.0.0-rc.1/"
if not url.startswith(base):
    raise SystemExit(98)
name = url[len(base):]
if "/" in name or ".." in name:
    raise SystemExit(97)
output_flag = "--output" if "--output" in arguments else "-o"
shutil.copyfile(pathlib.Path(os.environ["RELEASE_TEST_ASSETS"]) / name, arguments[arguments.index(output_flag) + 1])
''')
        curl.chmod(0o755)
        self.environment["PATH"] = str(binaries) + os.pathsep + self.environment["PATH"]
        self.environment["RELEASE_TEST_ASSETS"] = str(self.assets)

    def bootstrap(self, *arguments):
        return self.command("bash", self.assets / "install.sh", *arguments)

    def copy_paste_command(self):
        # Test the exact generated command, replacing sudo only with an unprivileged plan adapter.
        binaries = self.work / "bin"
        (binaries / "id").write_text("#!/usr/bin/env bash\nprintf '1000\\n'\n")
        (binaries / "id").chmod(0o755)
        (binaries / "sudo").write_text('''#!/usr/bin/env python3
import subprocess, sys
raise SystemExit(subprocess.call(sys.argv[1:] + ["--plan", "--public-url", "http://127.0.0.1"]))
''')
        (binaries / "sudo").chmod(0o755)
        guide = (self.assets / "INSTALL.md").read_text()
        command = guide.split("```bash\n", 1)[1].split("\n```", 1)[0]
        return self.command("bash", "-c", command)

    def test_origin_routes_to_managed_nginx_host_unless_explicitly_overridden(self):
        self.prepare()
        self.mock_downloads()
        result = self.bootstrap("--plan", "--public-url", "http://127.0.0.1:8080")
        self.assertEqual(result.returncode, 0, result.stderr)
        arguments = self.marker.read_text().splitlines()
        self.assertEqual(arguments[arguments.index("--server-name") + 1], "127.0.0.1")
        result = self.bootstrap("--plan", "--public-url", "https://panel.example.org", "--server-name", "panel.internal")
        self.assertEqual(result.returncode, 0, result.stderr)
        arguments = self.marker.read_text().splitlines()
        self.assertEqual(arguments.count("--server-name"), 1)
        self.assertEqual(arguments[arguments.index("--server-name") + 1], "panel.internal")

    def test_initial_owner_receives_new_private_path_and_rerun_preserves_credentials(self):
        root = self.work / "root"
        (root / "var/lib/rahandaz/current").mkdir(parents=True)
        php = self.work / "php-fixture"
        php.write_text("""#!/usr/bin/env python3
import os, pathlib, sys
path = pathlib.Path(next(a.split('=', 1)[1] for a in sys.argv if a.startswith('--credentials-file=')))
if path.exists() or path.parent.stat().st_mode & 0o077:
    raise SystemExit(1)
path.write_text('fixture-owner-credential')
os.chmod(path, 0o600)
""")
        php.chmod(0o755)
        result = self.command("bash", "-c", """
set -euo pipefail
INSTALLER_HOME="$1/installer"
source "$INSTALLER_HOME/lib/common.sh"
source "$INSTALLER_HOME/lib/engine.sh"
TEST_ROOT="$3"
installer_root_path() { printf '%s%s' "$TEST_ROOT" "$1"; }
installer_state_get() { printf succeeded; }
MODE=apply
INSTALLER_PHP_BIN="$2"
stage_13_first_boot
stage_13_first_boot
""", "fixture", ROOT, php, root)
        self.assertEqual(result.returncode, 0, result.stderr)
        credentials = root / "root/rahandaz-initial-credentials.txt"
        self.assertEqual(credentials.read_text(), "fixture-owner-credential")
        self.assertEqual(credentials.stat().st_mode & 0o777, 0o600)
        self.assertNotIn("fixture-owner-credential", result.stdout + result.stderr)

    def test_nginx_is_loaded_after_services_and_reload_failure_is_not_success(self):
        script = """
set -euo pipefail
INSTALLER_HOME="$1/installer"
source "$INSTALLER_HOME/lib/common.sh"
source "$INSTALLER_HOME/lib/render.sh"
source "$INSTALLER_HOME/lib/engine.sh"
TEST_ROOT="$2"
installer_sysroot() { printf /; }
installer_root_path() { printf '%s%s' "$TEST_ROOT" "$1"; }
installer_pid1_name() { printf systemd; }
security_runtime_permissions() { :; }
security_redis_units() { :; }
systemctl() {
    printf '%s\n' "$*" >> "$TEST_ROOT/systemctl.log"
    if [[ "$*" == 'reload nginx' && "$FAIL_RELOAD" == 1 ]]; then return 1; fi
}
MODE=apply
INSTALLER_PHP_BIN=/usr/bin/php8.4
INSTALLER_PHP_FPM_BIN=/usr/sbin/php-fpm8.4
FAIL_RELOAD="$3"
stage_12_systemd_services
"""
        for fail in ("0", "1"):
            root = self.work / ("systemd-" + fail)
            root.mkdir()
            result = self.command("bash", "-c", script, "fixture", ROOT, root, fail)
            self.assertEqual(result.returncode, int(fail), result.stderr)
            calls = (root / "systemctl.log").read_text().splitlines()
            self.assertEqual(calls[-2:], ["enable --now nginx", "reload nginx"])

    def test_exact_generated_command_downloads_verifies_and_runs_plan(self):
        self.prepare()
        self.mock_downloads()
        result = self.copy_paste_command()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("--plan", self.marker.read_text())

    def test_exact_generated_command_rejects_tampered_script(self):
        self.prepare()
        self.mock_downloads()
        with (self.assets / "install.sh").open("a") as script:
            script.write("\nexit 0\n")
        self.assertNotEqual(self.copy_paste_command().returncode, 0)
        self.assertFalse(self.marker.exists())

    def test_ready_bundle_includes_vendor_and_empty_runtime_directories(self):
        result = self.assemble()
        self.assertEqual(result.returncode, 0, result.stderr)
        with tarfile.open(result.stdout.strip()) as archive:
            names = archive.getnames()
            self.assertIn("rahandaz/vendor/autoload.php", names)
            self.assertIn("rahandaz/storage/framework/views", names)
            self.assertTrue(all(m.isfile() or m.isdir() for m in archive.getmembers()))

    def test_bundle_is_reproducible(self):
        first, second = self.assemble("first"), self.assemble("second")
        self.assertEqual(first.returncode, 0, first.stderr)
        self.assertEqual(second.returncode, 0, second.stderr)
        self.assertEqual(Path(first.stdout.strip()).read_bytes(), Path(second.stdout.strip()).read_bytes())

    def test_runtime_data_and_dev_files_are_not_packaged(self):
        for name in ("storage/app/customer.sql", "var/credentials.json", ".git/config",
                     "node_modules/package/index.js", "public/hot", "bootstrap/cache/config.php"):
            path = self.application / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("private runtime data\n")
        result = self.assemble()
        self.assertEqual(result.returncode, 0, result.stderr)
        with tarfile.open(result.stdout.strip()) as archive:
            self.assertFalse(any(m.isfile() and b"private runtime data" in archive.extractfile(m).read()
                                 for m in archive.getmembers()))

    def test_environment_files_are_rejected(self):
        for name in (".env", ".env.production", "public/.env.local"):
            with self.subTest(name=name):
                path = self.application / name
                path.write_text("APP_KEY=not-a-real-key\n")
                self.assertNotEqual(self.assemble().returncode, 0)
                path.unlink()

    def test_private_keys_and_sensitive_files_are_rejected(self):
        for name, content in (("public/master.key", "private"), ("public/id_ed25519", "private"),
                              ("public/renamed.txt", "-----BEGIN RSA PRIVATE KEY-----"),
                              ("public/customer.sql", "dump")):
            with self.subTest(name=name):
                path = self.application / name
                path.write_text(content)
                self.assertNotEqual(self.assemble().returncode, 0)
                path.unlink()

    def test_symlinks_and_hardlinks_are_rejected(self):
        link = self.application / "public/linked"
        link.symlink_to(self.application / "artisan")
        self.assertNotEqual(self.assemble().returncode, 0)
        link.unlink()
        os.link(self.application / "artisan", link)
        self.assertNotEqual(self.assemble().returncode, 0)

    def test_incomplete_artifact_is_rejected(self):
        (self.application / "vendor/autoload.php").unlink()
        self.assertNotEqual(self.assemble().returncode, 0)

    def test_unsafe_version_and_output_are_rejected(self):
        self.assertNotEqual(self.assemble(version="../../escape").returncode, 0)
        self.assertNotEqual(self.command("bash", "installer/release/assemble.sh", "--from", self.application,
                                        "--version", self.version, "--output", self.application / "dist").returncode, 0)

    def test_existing_bundle_is_not_overwritten(self):
        self.assertEqual(self.assemble().returncode, 0)
        self.assertNotEqual(self.assemble().returncode, 0)

    def test_generated_guide_pins_script_and_version(self):
        assets = self.prepare()
        guide = (assets / "INSTALL.md").read_text()
        digest = hashlib.sha256((assets / "install.sh").read_bytes()).hexdigest()
        self.assertIn(digest, guide)
        self.assertIn("releases/download/v1.0.0-rc.1/install.sh", guide)
        self.assertNotIn("/latest/", guide)
        self.assertNotIn("| bash", guide)
        self.assertIn(self.public_key.read_text().strip(), (assets / "install.sh").read_text())
        self.assertEqual(self.command("bash", "-n", assets / "install.sh").returncode, 0)

    def test_verify_only_does_not_execute_installer(self):
        self.prepare()
        self.mock_downloads()
        result = self.bootstrap("--verify-only")
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("Verified Rahandaz", result.stdout)
        self.assertFalse(self.marker.exists())

    def test_plan_forwards_only_validated_arguments(self):
        self.prepare()
        self.mock_downloads()
        result = self.bootstrap("--plan", "--public-url", "http://127.0.0.1", "--http-port", "8080")
        self.assertEqual(result.returncode, 0, result.stderr)
        arguments = self.marker.read_text().splitlines()
        self.assertIn("--plan", arguments)
        self.assertIn("8080", arguments)
        self.assertNotIn("--apply", arguments)

    def test_installer_failure_is_not_success(self):
        self.prepare()
        self.mock_downloads()
        self.environment["RAHANDAZ_RELEASE_TEST_EXIT"] = "10"
        self.assertEqual(self.bootstrap("--plan").returncode, 10)

    def test_help_requires_neither_network_nor_root(self):
        self.prepare()
        self.assertEqual(self.bootstrap("--help").returncode, 0)
        self.assertFalse(self.marker.exists())

    def test_invalid_origin_or_option_cannot_execute(self):
        self.prepare()
        for arguments in (("--plan", "--public-url", "http://example.com\nAPP_KEY=bad"),
                          ("--plan", "--public-url", "http://user:password@example.com"),
                          ("--plan", "--public-url", "http://example.com/path"),
                          ("--plan", "--server-name", "example.com; return 200;"),
                          ("--plan", "--http-port", "08"),
                          ("--plan", "--acknowledge-existing", "anything"),
                          ("--public-key", str(self.public_key)), ("--public-url",)):
            with self.subTest(arguments=arguments):
                self.assertNotEqual(self.bootstrap(*arguments).returncode, 0)
                self.assertFalse(self.marker.exists())

    def test_tampered_manifest_and_signature_cannot_execute(self):
        self.prepare()
        self.mock_downloads()
        for name in ("release.manifest", "release.manifest.sig"):
            path = self.assets / name
            original = path.read_bytes()
            # OpenSSL may ignore trailing signature bytes; corrupt signed bytes instead.
            path.write_bytes(original + b"tamper" if name == "release.manifest" else bytes([original[0] ^ 1]) + original[1:])
            self.assertNotEqual(self.bootstrap("--plan").returncode, 0)
            self.assertFalse(self.marker.exists())
            path.write_bytes(original)

    def test_tampered_artifact_cannot_execute(self):
        self.prepare()
        self.mock_downloads()
        with (self.assets / f"rahandaz-{self.version}.tar.gz").open("ab") as artifact:
            artifact.write(b"tampered")
        result = self.bootstrap("--plan")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("CHECKSUM_MISMATCH", result.stderr)
        self.assertFalse(self.marker.exists())

    def test_tls_failure_cannot_execute(self):
        self.prepare()
        self.mock_downloads()
        self.environment["RELEASE_TEST_CURL_FAIL"] = "1"
        self.assertNotEqual(self.bootstrap("--plan").returncode, 0)
        self.assertFalse(self.marker.exists())

    def test_signed_unsafe_archive_cannot_execute(self):
        artifact = self.work / f"rahandaz-{self.version}.tar.gz"
        with tarfile.open(artifact, "w:gz") as archive:
            member = tarfile.TarInfo("../outside")
            member.size = 4
            archive.addfile(member, io.BytesIO(b"evil"))
        self.prepare(artifact)
        self.mock_downloads()
        self.assertNotEqual(self.bootstrap("--plan").returncode, 0)
        self.assertFalse((self.work / "outside").exists())
        self.assertFalse(self.marker.exists())

    def test_signed_symlink_archive_cannot_execute(self):
        artifact = self.work / f"rahandaz-{self.version}.tar.gz"
        with tarfile.open(artifact, "w:gz") as archive:
            member = tarfile.TarInfo("rahandaz/vendor/autoload.php")
            member.type = tarfile.SYMTYPE
            member.linkname = "/etc/passwd"
            archive.addfile(member)
        self.prepare(artifact)
        self.mock_downloads()
        self.assertNotEqual(self.bootstrap("--plan").returncode, 0)
        self.assertFalse(self.marker.exists())

    def test_signed_wrong_version_cannot_execute(self):
        self.prepare()
        self.mock_downloads()
        manifest = self.assets / "release.manifest"
        manifest.write_text(manifest.read_text().replace("version=1.0.0-rc.1", "version=1.0.0-rc.2"))
        self.command("openssl", "dgst", "-sha256", "-sign", self.key, "-out", self.assets / "release.manifest.sig", manifest, check=True)
        result = self.bootstrap("--plan")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("UNVERIFIED_ARTIFACT", result.stderr)
        self.assertFalse(self.marker.exists())

    def test_signed_duplicate_members_cannot_execute(self):
        artifact = self.work / f"rahandaz-{self.version}.tar.gz"
        with tarfile.open(artifact, "w:gz") as archive:
            for _ in range(2):
                archive.addfile(tarfile.TarInfo("rahandaz/artisan"), io.BytesIO(b""))
        self.prepare(artifact)
        self.mock_downloads()
        self.assertNotEqual(self.bootstrap("--plan").returncode, 0)
        self.assertFalse(self.marker.exists())

    def test_signed_alias_duplicate_cannot_execute(self):
        artifact = self.work / f"rahandaz-{self.version}.tar.gz"
        with tarfile.open(artifact, "w:gz") as archive:
            for name in ("rahandaz/artisan", "rahandaz/./artisan"):
                archive.addfile(tarfile.TarInfo(name), io.BytesIO(b""))
        self.prepare(artifact)
        self.mock_downloads()
        self.assertNotEqual(self.bootstrap("--plan").returncode, 0)
        self.assertFalse(self.marker.exists())

    def test_release_output_refuses_existing_repository_asset_directory(self):
        self.prepare()
        occupied = self.work / "repository-assets"
        (occupied / "branding").mkdir(parents=True)
        marker = occupied / "branding/logo.svg"
        marker.write_text("repository asset")
        signed = self.work / "signed"
        result = self.command("bash", "installer/release/prepare-github.sh",
                              "--repository", "mohammadhejazirad/laravel-rahandaz",
                              "--manifest", signed / "release.manifest",
                              "--signature", signed / "release.manifest.sig",
                              "--public-key", self.public_key,
                              "--artifact", signed / f"rahandaz-{self.version}.tar.gz",
                              "--output", occupied)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(marker.read_text(), "repository asset")
        self.assertFalse((occupied / "install.sh").exists())

    def test_generated_checksums_cover_all_release_files(self):
        assets = self.prepare()
        lines = (assets / "SHA256SUMS").read_text().splitlines()
        for line in lines:
            digest, name = line.split("  ", 1)
            self.assertEqual(digest, hashlib.sha256((assets / name).read_bytes()).hexdigest())
        self.assertEqual(len(lines), 6)


if __name__ == "__main__":
    unittest.main(verbosity=2)
