#!/usr/bin/env python3
"""Run the real Rahandaz operations binary inside one Ubuntu lab container.

Stdout is one JSON report. Diagnostics go to stderr and to log files.
The lab token and the rahandaz password are never written to the report.
"""

from __future__ import annotations

import json
import os
import pwd
import re
import shutil
import stat
import subprocess
import time
import uuid
from datetime import datetime, timezone
from pathlib import Path

OPS = "/opt/rahandaz-src/infrastructure/operations/bin/rahandaz-ops"
CATALOG = "/opt/rahandaz-lab/catalog/container-lab.json"
LOG_DIR = Path("/run/rahandaz-lab/logs")
PHASE = os.environ.get("RAHANDAZ_LAB_PHASE", "all")
UUID_V7 = re.compile(r"^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$")
SECRETS: set[str] = set()
CASES: list[dict] = []
STARTED = datetime.now(timezone.utc)


def utc_now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def uuid7() -> str:
    millisecond = int(time.time() * 1000) & ((1 << 48) - 1)
    rand_a = int.from_bytes(os.urandom(2), "big") & 0x0FFF
    rand_b = int.from_bytes(os.urandom(8), "big") & ((1 << 62) - 1)
    value = (millisecond << 80) | (0x7 << 76) | (rand_a << 64) | (0b10 << 62) | rand_b
    return str(uuid.UUID(int=value))


def read_token() -> str:
    marker = Path("/run/rahandaz-lab/marker")
    lines = marker.read_text(encoding="utf-8").splitlines()
    if len(lines) < 2 or lines[0] != "rahandaz-lab-v1":
        raise SystemExit(1)
    SECRETS.add(lines[1])
    return lines[1]


TOKEN = read_token()


def ubuntu_version() -> str:
    values: dict[str, str] = {}
    for line in Path("/etc/os-release").read_text(encoding="utf-8").splitlines():
        if "=" in line:
            key, value = line.split("=", 1)
            values[key] = value.strip().strip("\"'")
    return values.get("VERSION_ID", "")


VERSION = ubuntu_version()


def redact(text: str) -> str:
    cleaned = text
    for secret in list(SECRETS):
        if secret:
            cleaned = cleaned.replace(secret, "[redacted]")
    cleaned = re.sub(
        r"-----BEGIN [A-Z ]*PRIVATE KEY-----.*?-----END [A-Z ]*PRIVATE KEY-----",
        "[redacted]",
        cleaned,
        flags=re.S,
    )
    return cleaned


def remember_secret_file(path: Path) -> None:
    if path.is_file() and not path.is_symlink():
        body = path.read_text(encoding="utf-8", errors="replace").strip()
        if body:
            SECRETS.add(body)


def add_case(suite: str, name: str, status: str, started: str, duration: float, error_code: str | None, detail: str) -> None:
    safe_name = re.sub(r"[^a-z0-9._-]+", "-", f"{suite}-{name}".lower())
    log_path = LOG_DIR / f"{safe_name}.log"
    log_path.write_text(redact(detail), encoding="utf-8")
    os.chmod(log_path, 0o600)
    CASES.append(
        {
            "suite": suite,
            "name": name,
            "started_at": started,
            "duration_seconds": round(duration, 3),
            "status": status,
            "error_code": error_code,
            "log_ref": f"logs/{log_path.name}",
            "environment_type": "container-lab",
            "detail": redact(detail)[-500:],
        }
    )


def note(suite: str, name: str, status: str, error_code: str | None = None, detail: str = "") -> None:
    add_case(suite, name, status, utc_now(), 0.0, error_code, detail)


def run_cmd(
    argv: list[str],
    timeout: int = 120,
    input_text: str | None = None,
    env: dict[str, str] | None = None,
) -> subprocess.CompletedProcess[str]:
    try:
        return subprocess.run(
            argv,
            input=input_text,
            text=True,
            capture_output=True,
            timeout=timeout,
            check=False,
            env=env,
        )
    except FileNotFoundError as error:
        return subprocess.CompletedProcess(argv, 127, "", str(error))


def install_catalog() -> None:
    global CATALOG
    source = Path(CATALOG)
    destination_dir = Path("/run/rahandaz-lab/catalog")
    destination_dir.mkdir(parents=True, exist_ok=True)
    os.chmod(destination_dir, 0o755)
    destination = destination_dir / "container-lab.json"
    shutil.copyfile(source, destination)
    os.chmod(destination, 0o644)
    if stat.S_IMODE(destination.stat().st_mode) & 0o002:
        note("bootstrap", "catalog_copy", "failed", "CATALOG_MODE", "the container-local catalog stayed world-writable")
        return
    CATALOG = str(destination)
    note("bootstrap", "catalog_copy", "passed", None, "catalog was copied to a container-local mode 0644 file")


def run_phase(name: str, function) -> None:
    try:
        function()
    except Exception as error:
        note(name, "phase_exception", "failed", "PHASE_EXCEPTION", redact(f"{type(error).__name__}: {error}"))


def parse_result(raw: bytes) -> tuple[dict | None, str]:
    text = raw.decode("utf-8", "replace")
    for line in reversed(text.splitlines()):
        stripped = line.strip()
        if stripped.startswith("{") and stripped.endswith("}"):
            try:
                value = json.loads(stripped)
            except json.JSONDecodeError:
                continue
            if isinstance(value, dict):
                return value, text
    return None, text


def invoke(operation: str, payload: dict, privilege: str = "helper", extra: dict | None = None, timeout: int = 1900) -> tuple[dict | None, str, int]:
    env = os.environ.copy()
    env["RAHANDAZ_OPS_APPLY"] = "1"
    env["RAHANDAZ_OPS_LAB"] = "1"
    env["RAHANDAZ_OPS_LAB_TOKEN"] = TOKEN
    env["RAHANDAZ_OPS_CATALOG"] = CATALOG
    env.pop("RAHANDAZ_OPS_HELPER_CONFIRMED", None)
    env.pop("RAHANDAZ_OPS_HELPER_SOCKET", None)
    env.pop("RAHANDAZ_OPS_SANDBOX", None)
    env.pop("RAHANDAZ_OPS_BIN_DIR", None)
    if privilege == "project":
        env["RAHANDAZ_OPS_PROJECT_CONFIRMED"] = "1"
    else:
        env.pop("RAHANDAZ_OPS_PROJECT_CONFIRMED", None)
    if extra:
        env.update(extra)
    proc = subprocess.run(
        [OPS, operation],
        input=json.dumps(payload).encode(),
        capture_output=True,
        env=env,
        timeout=timeout,
        check=False,
    )
    result, text = parse_result(proc.stdout)
    detail = redact(text + "\n" + proc.stderr.decode("utf-8", "replace"))
    return result, detail, proc.returncode


def engine_case(
    suite: str,
    name: str,
    operation: str,
    payload: dict,
    *,
    expect: str = "succeeded",
    code: str | None = None,
    report: str | None = None,
    privilege: str = "helper",
    extra: dict | None = None,
    timeout: int = 1900,
    check=None,
) -> dict | None:
    started = utc_now()
    clock = time.monotonic()
    try:
        result, detail, _rc = invoke(operation, payload, privilege, extra, timeout)
    except subprocess.TimeoutExpired as error:
        add_case(suite, name, "failed", started, time.monotonic() - clock, "TIMEOUT", redact(str(error)))
        return None
    duration = time.monotonic() - clock
    if result is None:
        add_case(suite, name, "failed", started, duration, "NO_JSON", detail)
        return None
    actual = result.get("status")
    actual_code = result.get("error_code")
    if report is not None:
        ok = actual == "failed" and actual_code == code
        status = report if ok else "failed"
    else:
        ok = actual == expect and (code is None or actual_code == code)
        status = "passed" if ok else "failed"
    if ok and check is not None and not check(result):
        status = "failed"
        actual_code = actual_code or "OBSERVATION_MISMATCH"
    observed = result.get("observed_state")
    summary = detail
    if isinstance(observed, dict):
        safe = {key: value for key, value in observed.items() if not re.search(r"password|secret|token|private", str(key), re.I)}
        summary = json.dumps(safe, ensure_ascii=True) + "\n" + detail
    add_case(suite, name, status, started, duration, None if status == "passed" else actual_code, summary)
    return result


def payload(**extra: object) -> dict:
    body = {"execution_id": uuid7()}
    body.update(extra)
    return body


def check_facts(result: dict) -> bool:
    observed = result.get("observed_state") or {}
    return (
        observed.get("recognized") is True
        and observed.get("compatibility") == "untested"
        and observed.get("version") == VERSION
        and observed.get("id") == "ubuntu"
    )


def bootstrap() -> None:
    started = utc_now()
    clock = time.monotonic()
    proc = run_cmd(["apt-get", "update"], timeout=600)
    status = "passed" if proc.returncode == 0 else "failed"
    add_case("bootstrap", "apt_update", status, started, time.monotonic() - clock, None if status == "passed" else "APT_UPDATE_FAILED", proc.stderr[-500:])


def account_checks() -> None:
    created = engine_case("accounts", "create", "account.create_rahandaz_user", payload())
    secret = Path("/root/rahandaz-account.secret")
    remember_secret_file(secret)
    leaked = False
    if created is not None and secret.is_file():
        leaked = secret.read_text(encoding="utf-8", errors="replace").strip() in json.dumps(created)
    engine_case("accounts", "create_again", "account.create_rahandaz_user", payload(), check=lambda result: result.get("mutated") is False)
    try:
        user = pwd.getpwnam("rahandaz-svc")
    except KeyError:
        note("accounts", "linux_account", "failed", "ACCOUNT_MISSING", "user rahandaz-svc was not created")
        return
    groups = run_cmd(["id", "-nG", "rahandaz-svc"]).stdout.split()
    status_line = run_cmd(["passwd", "-S", "rahandaz-svc"]).stdout.split()
    locked = len(status_line) > 1 and status_line[1] == "L"
    mode = oct(secret.stat().st_mode & 0o777) if secret.is_file() else "missing"
    home_ok = user.pw_dir == "/var/lib/rahandaz" and user.pw_shell.endswith("nologin")
    sudo_free = "sudo" not in groups and "docker" not in groups
    ok = home_ok and sudo_free and locked and mode == "0o600" and not leaked and created is not None
    note(
        "accounts",
        "permissions",
        "passed" if ok else "failed",
        None if ok else "ACCOUNT_CHECK_FAILED",
        f"uid={user.pw_uid} gid={user.pw_gid} home={user.pw_dir} shell={user.pw_shell} groups={','.join(groups)} locked={locked} mode={mode} leaked={leaked}",
    )


def install_packages() -> None:
    named = [
        ("nginx", "nginx.install", None),
        ("postgresql", "db.postgresql.install", None),
        ("redis", "package.install", "redis"),
        ("php-cli", "package.install", "php-cli"),
        ("php-fpm", "package.install", "php-fpm"),
        ("php-pgsql", "package.install", "php-pgsql"),
        ("php-redis", "package.install", "php-redis"),
        ("composer", "package.install", "composer"),
        ("git", "package.install", "git"),
        ("openssl", "package.install", "openssl"),
        ("ffmpeg", "package.install", "ffmpeg"),
        ("supervisor", "package.install", "supervisor"),
        ("ufw", "firewall.install", None),
        ("openssh", "ssh.install_server", None),
        ("clamav", "package.install", "clamav"),
        ("memcached", "package.install", "memcached"),
    ]
    for label, operation, package in named:
        body = payload()
        if package is not None:
            body["package"] = package
        engine_case("packages", label, operation, body, timeout=1900)
    engine_case("packages", "nginx_again", "nginx.install", payload(), timeout=1900)
    engine_case(
        "packages",
        "memcached_remove",
        "package.remove",
        payload(package="memcached", approval=True),
        check=lambda result: (result.get("observed_state") or {}).get("absent") is True,
    )
    for package in ("mongodb", "nodejs", "python"):
        engine_case(
            "packages",
            package,
            "package.install",
            payload(package=package),
            report="unsupported",
            code="PACKAGE_UNTESTED",
        )
    note("packages", "mysql", "skipped", "CONFLICT_PROFILE", "MySQL is not installed in the default profile.")
    note("packages", "mariadb", "skipped", "CONFLICT_PROFILE", "MariaDB is not installed beside MySQL. The default profile installs neither.")
    note("packages", "docker", "skipped", "DOCKER_IN_DOCKER_OFF", "Docker Engine is not a panel dependency and is not installed in the lab.")
    engine_case("packages", "nginx_verify", "package.verify", payload(package="nginx"), check=lambda result: (result.get("observed_state") or {}).get("compatibility") == "container-lab")


def php_and_laravel() -> None:
    proc = run_cmd(["php", "-r", "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"])
    if proc.returncode != 0:
        note("queues", "laravel_panel", "blocked", "PHP_MISSING", "php was not installed, so artisan was not executed")
        note("queues", "job_reconciliation", "blocked", "ARTISAN_NOT_RUN", "OperationJob reconciliation was not executed")
        return
    version = proc.stdout.strip()
    parts = version.split(".")
    major, minor = int(parts[0]), int(parts[1])
    if (major, minor) < (8, 3):
        note("queues", "php_version", "unsupported", "PHP_TOO_OLD", f"distro PHP {version} is below the panel requirement 8.3")
    else:
        note("queues", "php_version", "passed", None, f"distro PHP {version} meets 8.3")
    note("queues", "laravel_panel", "blocked", "SOURCE_NOT_MOUNTED", "The panel source and .env are not mounted. artisan was not executed.")
    note("queues", "job_reconciliation", "blocked", "ARTISAN_NOT_RUN", "No worker crash reconciliation was claimed.")
    webhook = Path("/opt/rahandaz-lab/webhook/WebhookSignature.php")
    if not webhook.is_file():
        note("deployments", "webhook_signature", "blocked", "FIXTURE_MISSING", "WebhookSignature.php is not mounted")
        return
    script = r"""<?php
require '/opt/rahandaz-lab/webhook/WebhookSignature.php';
$signer = new App\Domain\Git\WebhookSignature();
$payload = 'lab-payload';
$secret = 'lab-webhook-secret';
$good = 'sha256=' . hash_hmac('sha256', $payload, $secret);
$ok = $signer->github($payload, $secret, $good) && !$signer->github($payload, $secret, 'sha256=0000');
$ok = $ok && $signer->gitlab($secret, $secret) && !$signer->gitlab($secret, 'other-token');
echo $ok ? "ok\n" : "bad\n";
"""
    proc = run_cmd(["php"], timeout=30, input_text=script)
    note(
        "deployments",
        "webhook_signature",
        "passed" if proc.stdout.strip() == "ok" else "failed",
        None if proc.stdout.strip() == "ok" else "WEBHOOK_CONTRACT",
        (proc.stderr or proc.stdout)[-200:],
    )


def start_services() -> None:
    engine_case("services", "nginx_start_systemd", "service.start", payload(unit="nginx"), report="unsupported", code="UNSUPPORTED_IN_CONTAINER")
    engine_case("services", "nginx_stop_protected", "service.stop", payload(unit="nginx"), expect="failed", code="PROTECTED_SERVICE")
    proc = run_cmd(["pg_lsclusters", "--no-header"])
    if proc.returncode != 0 or not proc.stdout.strip():
        note("services", "postgresql_adapter", "failed", "PG_CLUSTERS", proc.stderr[-300:] or proc.stdout[-300:])
    else:
        parts = proc.stdout.split()
        started = run_cmd(["pg_ctlcluster", parts[0], parts[1], "start"], timeout=120)
        note(
            "services",
            "postgresql_adapter",
            "passed" if started.returncode == 0 else "failed",
            None if started.returncode == 0 else "PG_START",
            (started.stderr or started.stdout)[-300:],
        )
    Path("/var/log/redis").mkdir(parents=True, exist_ok=True)
    Path("/var/lib/redis").mkdir(parents=True, exist_ok=True)
    redis = run_cmd(
        [
            "redis-server",
            "--daemonize",
            "yes",
            "--bind",
            "127.0.0.1",
            "--port",
            "6379",
            "--protected-mode",
            "yes",
            "--dir",
            "/var/lib/redis",
            "--dbfilename",
            "lab.rdb",
            "--logfile",
            "/var/log/redis/lab.log",
        ]
    )
    ping = run_cmd(["redis-cli", "-h", "127.0.0.1", "ping"])
    note(
        "services",
        "redis_adapter",
        "passed" if ping.stdout.strip() == "PONG" else "failed",
        None if ping.stdout.strip() == "PONG" else "REDIS_PING",
        redact((redis.stderr or "") + ping.stdout + ping.stderr)[-300:],
    )


NGINX_GOOD = """# rahandaz-managed: example
worker_processes 1;
user www-data;
error_log /var/log/nginx/error.log;
pid /run/nginx.pid;
events { worker_connections 64; }
http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    access_log /var/log/nginx/access.log;
    server {
        listen 127.0.0.1:8080 default_server;
        server_name example.test app.example.test;
        root /srv/projects/welcome/current;
        index index.html;
        location / { try_files $uri $uri/ =404; }
    }
}
"""

NGINX_BAD = """# rahandaz-managed: example
worker_processes ;
"""


def nginx_and_http() -> None:
    engine_case(
        "deployments",
        "activate_rel1",
        "deploy.activate",
        payload(project="welcome", release="rel1"),
        privilege="project",
    )
    release = Path("/srv/projects/welcome/releases/rel1")
    release.mkdir(parents=True, exist_ok=True)
    page = release / "index.html"
    shutil.copy("/opt/rahandaz-lab/fixtures/static/index.html", page)
    os.chmod(page, 0o644)
    for directory in (
        Path("/srv/projects"),
        Path("/srv/projects/welcome"),
        Path("/srv/projects/welcome/releases"),
        release,
    ):
        if directory.is_dir() and not directory.is_symlink():
            os.chmod(directory, 0o755)
    engine_case("nginx", "apply_good", "nginx.apply_site", payload(site="example", config=NGINX_GOOD))
    bad = engine_case("nginx", "apply_bad_rolls_back", "nginx.apply_site", payload(site="example", config=NGINX_BAD), expect="failed", code="NGINX_TEST_FAILED")
    config_path = Path("/etc/nginx/sites-available/example.conf")
    restored = config_path.is_file() and "try_files" in config_path.read_text(encoding="utf-8")
    if bad is None or not restored:
        note("nginx", "rollback_observed", "failed", "ROLLBACK_MISSING", "the previous nginx file was not kept")
    else:
        note("nginx", "rollback_observed", "passed", None, "invalid config was refused and the previous file remained")
    engine_case("nginx", "enable", "nginx.enable_site", payload(site="example"))
    enabled = Path("/etc/nginx/sites-enabled/example.conf")
    note("nginx", "enable_observed", "passed" if enabled.is_symlink() else "failed", None if enabled.is_symlink() else "LINK_MISSING", str(enabled))
    engine_case("nginx", "disable", "nginx.disable_site", payload(site="example"))
    note("nginx", "disable_observed", "passed" if not enabled.exists() else "failed", None if not enabled.exists() else "LINK_REMAINED", str(enabled))
    engine_case("nginx", "validate", "nginx.validate", payload(site="example"))
    started = run_cmd(["nginx", "-c", "/etc/nginx/sites-available/example.conf"])
    note(
        "nginx",
        "runtime_adapter",
        "passed" if started.returncode == 0 else "failed",
        None if started.returncode == 0 else "NGINX_START",
        (started.stderr or started.stdout)[-300:],
    )
    for host in ("example.test", "app.example.test"):
        page = run_cmd(["curl", "-fsS", "-H", f"Host: {host}", "http://127.0.0.1:8080/"])
        ok = page.returncode == 0 and "rahandaz-lab-welcome" in page.stdout
        note("nginx", f"http_{host}", "passed" if ok else "failed", None if ok else "HTTP", page.stderr[-200:])
    engine_case(
        "deployments",
        "healthcheck",
        "deploy.verify",
        payload(health_url="http://127.0.0.1:8080/"),
        privilege="project",
    )


def databases() -> None:
    engine_case("databases", "create", "db.postgresql.create_database", payload(database="labapp"))
    engine_case("databases", "role", "db.postgresql.create_role", payload(role="labrole"))
    engine_case(
        "databases",
        "reserved_role",
        "db.postgresql.create_role",
        payload(role="rahandaz"),
        expect="failed",
        code="PROTECTED_DATABASE",
    )
    secret = Path("/run/rahandaz-lab/role.secret")
    password = "lab-" + uuid7()
    secret.write_text(password + "\n", encoding="utf-8")
    os.chmod(secret, 0o600)
    SECRETS.add(password)
    engine_case(
        "databases",
        "password",
        "db.postgresql.change_password",
        payload(role="labrole", material_path=str(secret)),
        check=lambda result: (result.get("observed_state") or {}).get("material_logged") is False,
    )
    run_cmd(["runuser", "-u", "postgres", "--", "psql", "-d", "labapp", "-v", "ON_ERROR_STOP=1", "-c", "GRANT CONNECT ON DATABASE labapp TO labrole;"])
    login = run_cmd(
        ["psql", "-h", "127.0.0.1", "-U", "labrole", "-d", "labapp", "-tAc", "SELECT 1"],
        timeout=30,
        env={**os.environ, "PGPASSWORD": password},
    )
    SECRETS.add(password)
    note(
        "databases",
        "password_login",
        "passed" if login.stdout.strip() == "1" else "failed",
        None if login.stdout.strip() == "1" else "PASSWORD_LOGIN",
        "loopback login was checked without printing the password",
    )
    engine_case("databases", "grant", "db.postgresql.grant", payload(database="labapp", privilege="connect"))
    engine_case("databases", "revoke", "db.postgresql.revoke", payload(database="labapp"))
    seed = run_cmd(
        ["runuser", "-u", "postgres", "--", "psql", "-d", "labapp", "-v", "ON_ERROR_STOP=1", "-c", "CREATE TABLE lab_marker (id int primary key, note text); INSERT INTO lab_marker VALUES (1, 'rahandaz-lab');"]
    )
    note("databases", "seed", "passed" if seed.returncode == 0 else "failed", None if seed.returncode == 0 else "SEED", seed.stderr[-200:])
    backup_dir = Path("/var/backups/rahandaz")
    backup_dir.mkdir(parents=True, exist_ok=True)
    shutil.chown(backup_dir, "postgres", "postgres")
    os.chmod(backup_dir, 0o700)
    archive = "/var/backups/rahandaz/labapp.dump"
    engine_case("databases", "backup", "db.postgresql.backup", payload(database="labapp", output_path=archive))
    run_cmd(["runuser", "-u", "postgres", "--", "psql", "-d", "labapp", "-c", "DROP TABLE lab_marker;"])
    if Path(archive).is_file():
        shutil.chown(archive, "postgres", "postgres")
    engine_case(
        "databases",
        "restore",
        "db.postgresql.restore",
        payload(database="labapp", archive_path=archive, approval=True, backup_verified=True),
    )
    check = run_cmd(["runuser", "-u", "postgres", "--", "psql", "-d", "labapp", "-tAc", "SELECT note FROM lab_marker;"])
    note(
        "databases",
        "restore_observed",
        "passed" if check.stdout.strip() == "rahandaz-lab" else "failed",
        None if check.stdout.strip() == "rahandaz-lab" else "RESTORE_DATA",
        check.stderr[-200:],
    )
    engine_case("databases", "protected", "db.postgresql.drop_database", payload(database="rahandaz"), expect="failed", code="PROTECTED_DATABASE")
    push = run_cmd(["redis-cli", "-h", "127.0.0.1", "LPUSH", "rahandaz-lab", "one"])
    pop = run_cmd(["redis-cli", "-h", "127.0.0.1", "RPOP", "rahandaz-lab"])
    cache = run_cmd(["redis-cli", "-h", "127.0.0.1", "SET", "rahandaz-lab-cache", "value", "EX", "30"])
    got = run_cmd(["redis-cli", "-h", "127.0.0.1", "GET", "rahandaz-lab-cache"])
    ttl = run_cmd(["redis-cli", "-h", "127.0.0.1", "TTL", "rahandaz-lab-cache"])
    redis_ok = push.returncode == 0 and pop.stdout.strip() == "one" and got.stdout.strip() == "value" and ttl.stdout.strip().isdigit() and int(ttl.stdout.strip()) > 0
    note("databases", "redis_queue_and_cache", "passed" if redis_ok else "failed", None if redis_ok else "REDIS_DATA", "push, pop, set, get, and ttl were checked on 127.0.0.1")
    run_cmd(["redis-cli", "-h", "127.0.0.1", "SHUTDOWN", "NOSAVE"])
    time.sleep(1)
    again = run_cmd(
        ["redis-server", "--daemonize", "yes", "--bind", "127.0.0.1", "--port", "6379", "--protected-mode", "yes", "--dir", "/var/lib/redis", "--dbfilename", "lab.rdb", "--logfile", "/var/log/redis/lab.log"]
    )
    ping = run_cmd(["redis-cli", "-h", "127.0.0.1", "ping"])
    note("databases", "redis_restart", "passed" if ping.stdout.strip() == "PONG" else "failed", None if ping.stdout.strip() == "PONG" else "REDIS_RESTART", (again.stderr + ping.stdout)[-200:])


def ssh_cases() -> None:
    key_dir = Path("/run/rahandaz-lab/tmp")
    private = key_dir / "lab_ed25519"
    run_cmd(["ssh-keygen", "-q", "-t", "ed25519", "-N", "", "-f", str(private)])
    public = (private.with_suffix(private.suffix + ".pub")).read_text(encoding="utf-8").strip() if private.with_suffix(".pub").exists() else ""
    if not public and Path(str(private) + ".pub").is_file():
        public = Path(str(private) + ".pub").read_text(encoding="utf-8").strip()
    private.unlink(missing_ok=True)
    if not public.startswith("ssh-ed25519 "):
        note("ssh", "key_validation", "failed", "KEYGEN", "an ed25519 public key was not produced")
    else:
        engine_case("ssh", "install_key", "ssh.install_public_key", payload(username="rhproj_lab0001", public_key=public))
        engine_case(
            "ssh",
            "duplicate_key",
            "ssh.install_public_key",
            payload(username="rhproj_lab0001", public_key=public),
            check=lambda result: (result.get("observed_state") or {}).get("duplicate") is True and result.get("mutated") is False,
        )
        auth = Path("/home/rhproj_lab0001/.ssh/authorized_keys")
        mode = oct(auth.stat().st_mode & 0o777) if auth.is_file() else "missing"
        dir_mode = oct(auth.parent.stat().st_mode & 0o777) if auth.parent.is_dir() else "missing"
        note("ssh", "authorized_keys_mode", "passed" if mode == "0o600" and dir_mode == "0o700" else "failed", None if mode == "0o600" else "KEY_MODE", f"file={mode} dir={dir_mode}")
    engine_case("ssh", "invalid_key", "ssh.verify_public_key", payload(public_key="not-a-key"), expect="failed", code="INVALID_INPUT")
    engine_case(
        "ssh",
        "private_key_rejected",
        "ssh.verify_public_key",
        payload(public_key="-----BEGIN OPENSSH PRIVATE KEY-----\nAAAA\n-----END OPENSSH PRIVATE KEY-----"),
        expect="failed",
        code="PRIVATE_KEY_REJECTED",
    )
    engine_case("ssh", "harden_without_recovery", "ssh.harden", payload(), expect="failed", code="SSH_RECOVERY_REQUIRED")
    host_key = key_dir / "ssh_host_ed25519"
    run_cmd(["ssh-keygen", "-q", "-t", "ed25519", "-N", "", "-f", str(host_key)])
    config = key_dir / "sshd-test.conf"
    config.write_text(
        "\n".join(
            [
                "Port 2222",
                "ListenAddress 127.0.0.1",
                f"HostKey {host_key}",
                "PasswordAuthentication no",
                "PermitRootLogin no",
                "PidFile /run/rahandaz-lab/tmp/sshd-test.pid",
                "UsePAM no",
            ]
        )
        + "\n",
        encoding="utf-8",
    )
    privsep = Path("/run/sshd")
    privsep.mkdir(parents=True, exist_ok=True)
    os.chmod(privsep, 0o755)
    checked = run_cmd(["sshd", "-t", "-f", str(config)])
    note("ssh", "sshd_test", "passed" if checked.returncode == 0 else "failed", None if checked.returncode == 0 else "SSHD_TEST", checked.stderr[-300:])
    host_key.unlink(missing_ok=True)
    Path(str(host_key) + ".pub").unlink(missing_ok=True)
    note("ssh", "lockout_recovery", "requires_vm", "REQUIRES_VM", "SSH lockout recovery is not executed in Docker.")


def failure_cases() -> None:
    foreign = Path("/run/rahandaz-lab/os-release")
    foreign.write_text('ID=debian\nVERSION_ID="12"\nVERSION_CODENAME=bookworm\n', encoding="utf-8")
    engine_case(
        "security",
        "invalid_os",
        "host.facts",
        payload(),
        extra={"RAHANDAZ_OPS_OS_RELEASE": str(foreign)},
        check=lambda result: (result.get("observed_state") or {}).get("compatibility") == "unsupported",
    )
    engine_case("security", "root_script", "automation.custom_script", payload(uid="0", script_path="/var/lib/rahandaz/custom.sh", timeout_seconds="5"), privilege="project", expect="failed", code="ROOT_SCRIPT_FORBIDDEN")
    engine_case(
        "security",
        "helper_on_project_script",
        "automation.custom_script",
        payload(uid="1000", script_path="/var/lib/rahandaz/custom.sh", timeout_seconds="5"),
        privilege="project",
        extra={"RAHANDAZ_OPS_HELPER_CONFIRMED": "1"},
        expect="failed",
        code="ROOT_SCRIPT_FORBIDDEN",
    )
    engine_case("security", "cron", "automation.refuse_cron", payload(), expect="failed", code="CRON_WRITE_FORBIDDEN")
    engine_case(
        "security",
        "reboot_not_executed",
        "host.reboot_request",
        payload(
            owner_approval=True,
            reauthentication=True,
            active_jobs_idle=True,
            deployment_idle=True,
            backup_checked=True,
            recovery_plan=True,
        ),
        expect="failed",
        code="REBOOT_NOT_EXECUTED",
    )
    engine_case(
        "security",
        "firewall_enable",
        "firewall.enable",
        payload(approval=True, ssh_rule_present=True, panel_rule_present=True, recovery_verified=True),
        report="requires_vm",
        code="REQUIRES_VM",
    )
    ufw = run_cmd(["ufw", "status"])
    active = "Status: active" in (ufw.stdout + ufw.stderr)
    if active:
        note("security", "firewall_still_inactive", "failed", "UFW_ACTIVE", "UFW became active inside the container")
    else:
        note("security", "firewall_still_inactive", "passed", None, "UFW was not activated")
    engine_case(
        "security",
        "certificate",
        "ssl.issue",
        payload(domain="example.test", dns_checked=True, challenge="http-01"),
        report="unsupported",
        code="CERTIFICATE_UNTESTED",
    )
    if shutil.which("openssl"):
        cert = run_cmd(["openssl", "req", "-x509", "-newkey", "rsa:2048", "-keyout", "/run/rahandaz-lab/tmp/lab.key", "-out", "/run/rahandaz-lab/tmp/lab.crt", "-days", "1", "-nodes", "-subj", "/CN=example.test"], timeout=60)
        Path("/run/rahandaz-lab/tmp/lab.key").unlink(missing_ok=True)
        note("ssl", "local_test_certificate", "passed" if cert.returncode == 0 else "failed", None if cert.returncode == 0 else "OPENSSL", "local test certificate only; this is not public ACME")
    else:
        note("ssl", "local_test_certificate", "skipped", "OPENSSL_MISSING", "openssl was not available")
    for name, detail in (
        ("apt_lock", "An apt lock is not injected. The engine has no operation that deletes lock files."),
        ("disk_full", "The disk is not filled. Host quotas are not configured on this Docker filesystem."),
        ("bad_repository_key", "A broken repository key is not injected into the container apt sources."),
        ("interrupted_install", "An install is not killed mid-apt in this run."),
        ("package_download_failure", "The lab catalog is not replaced with a failing mirror in this run."),
    ):
        note("security", name, "skipped", "NOT_INJECTED", detail)
    note("recovery", "reboot", "requires_vm", "REQUIRES_VM", "A real Ubuntu reboot is not executed in Docker.")
    note("recovery", "kernel_update", "requires_vm", "REQUIRES_VM", "Kernel updates are not executed in Docker.")
    note("recovery", "boot_recovery", "requires_vm", "REQUIRES_VM", "Boot-time service recovery needs a VM.")
    note("recovery", "network_stack", "requires_vm", "REQUIRES_VM", "Host network-stack changes are not executed.")
    note("recovery", "production_certification", "requires_vm", "REQUIRES_VM", "Full installer production certification needs a VM.")
    engine_case(
        "deployments",
        "checkout",
        "deploy.checkout",
        payload(commit_sha="0123456789abcdef0123456789abcdef01234567"),
        privilege="project",
        expect="failed",
        code="PRECONDITION_FAILED",
    )
    note("deployments", "network_fetch", "blocked", "NETWORK_NOT_OPENED", "The engine does not fetch Git remotes. No provider token is used.")
    for framework in ("nodejs", "nextjs", "nuxtjs"):
        note("deployments", framework, "unsupported", "NODE_RUNTIME_UNAPPROVED", "The lab catalog keeps Node.js apt null.")
    hello = run_cmd(["python3", "/opt/rahandaz-lab/fixtures/python/hello.py"])
    note("deployments", "python_fixture", "passed" if hello.stdout.strip() == "rahandaz-lab-python" else "failed", None if hello.returncode == 0 else "PYTHON", hello.stdout.strip())
    engine_case(
        "deployments",
        "static_adapter",
        "deploy.adapter.static",
        payload(framework="static"),
        privilege="project",
        extra={"RAHANDAZ_OPS_PROJECT_UID": "65534"},
        report="unsupported",
        code="UNSUPPORTED_IN_CONTAINER",
    )
    engine_case("deployments", "activate_rel2", "deploy.activate", payload(project="welcome", release="rel2"), privilege="project")
    Path("/srv/projects/welcome/releases/rel2").mkdir(parents=True, exist_ok=True)
    engine_case(
        "deployments",
        "rollback",
        "deploy.rollback",
        payload(project="welcome", previous_release="rel1", approval=True),
        privilege="project",
    )
    current = Path("/srv/projects/welcome/current")
    target = os.path.realpath(current) if current.exists() else ""
    note("deployments", "rollback_observed", "passed" if target.endswith("/rel1") else "failed", None if target.endswith("/rel1") else "ROLLBACK", target)


def emit() -> int:
    failed = [case for case in CASES if case["status"] == "failed"]
    report = {
        "schema_version": "1.0",
        "ubuntu_version": VERSION,
        "image": f"ubuntu:{VERSION}",
        "image_digest": None,
        "architecture": os.uname().machine,
        "environment_type": "container-lab",
        "phase": PHASE,
        "started_at": STARTED.strftime("%Y-%m-%dT%H:%M:%SZ"),
        "finished_at": utc_now(),
        "duration_seconds": round((datetime.now(timezone.utc) - STARTED).total_seconds(), 3),
        "counts": {
            "passed": sum(case["status"] == "passed" for case in CASES),
            "failed": len(failed),
            "skipped": sum(case["status"] == "skipped" for case in CASES),
            "unsupported": sum(case["status"] == "unsupported" for case in CASES),
            "requires_vm": sum(case["status"] == "requires_vm" for case in CASES),
            "blocked": sum(case["status"] == "blocked" for case in CASES),
        },
        "cases": CASES,
    }
    text = redact(json.dumps(report, ensure_ascii=True, indent=2))
    sys_stdout(text + "\n")
    return 1 if failed else 0


def sys_stdout(text: str) -> None:
    import sys

    sys.stdout.write(text)


def main() -> int:
    LOG_DIR.mkdir(parents=True, exist_ok=True)
    os.chmod(LOG_DIR, 0o700)
    if not UUID_V7.fullmatch(uuid7()):
        note("bootstrap", "uuid", "failed", "UUID", "uuid7 helper failed its own pattern")
        return emit()
    install_catalog()
    engine_case("bootstrap", "facts", "host.facts", payload(), check=check_facts)
    arch = run_cmd(["uname", "-m"])
    note("bootstrap", "architecture", "passed" if arch.stdout.strip() else "failed", None, arch.stdout.strip())
    phases = [bootstrap, account_checks, install_packages]
    if PHASE != "install":
        phases.extend([php_and_laravel, start_services, databases, nginx_and_http, ssh_cases, failure_cases])
    for function in phases:
        run_phase(function.__name__, function)
    return emit()


if __name__ == "__main__":
    raise SystemExit(main())
