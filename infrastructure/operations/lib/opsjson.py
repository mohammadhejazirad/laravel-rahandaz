#!/usr/bin/env python3
"""Stdlib JSON, path, and secret guard for the Rahandaz operations engine."""

from __future__ import annotations

import base64
import hashlib
import json
import os
import re
import secrets
import stat
import sys
from pathlib import Path

SECRET_KEY = re.compile(r"password|secret|token|private_key|authorization|credential", re.I)
UUID_V7 = re.compile(r"^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$")
IDENT = re.compile(r"^[A-Za-z_][A-Za-z0-9_]{0,62}$")
UNIT = re.compile(r"^[A-Za-z0-9][A-Za-z0-9:_.@-]{0,127}$")
DOMAIN = re.compile(r"^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$")
PUBLIC_KEY = re.compile(
    r"^(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp256|ecdsa-sha2-nistp384|ecdsa-sha2-nistp521|sk-ssh-ed25519@openssh.com) [A-Za-z0-9+/=]+(?: [\x21-\x7e]{0,100})?$"
)
PROJECT_USER = re.compile(r"^rhproj_[a-z0-9]{6,16}$")
SHA = re.compile(r"^[0-9a-f]{40}$")
PACKAGE = re.compile(r"^[a-z0-9][a-z0-9+.-]{0,62}$")
LAB_TOKEN = re.compile(r"^[0-9a-f]{32,}$")
LAB_PREFIXES = (
    Path("/etc/nginx"),
    Path("/etc/ssh/sshd_config.d"),
    Path("/var/lib/rahandaz"),
    Path("/srv/projects"),
    Path("/run/rahandaz-lab"),
    Path("/var/backups/rahandaz"),
)
LAB_EXACT_FILES = {
    Path("/root/rahandaz-account.secret"),
    Path("/root/rahandaz-account-rotated.secret"),
}
LAB_CATALOG_DIRS = {
    Path("/opt/rahandaz-lab/catalog"),
    Path("/opt/rahandaz-src/tests/infrastructure/ubuntu-lab/catalog"),
    Path("/run/rahandaz-lab/catalog"),
}

PROTECTED_DATABASES = {
    "postgresql": {"postgres", "template0", "template1", "rahandaz"},
    "mysql": {"mysql", "information_schema", "performance_schema", "sys", "rahandaz"},
    "mariadb": {"mysql", "information_schema", "performance_schema", "sys", "rahandaz"},
    "mongodb": {"admin", "local", "config", "rahandaz"},
}

PROTECTED_UNITS = {
    "nginx",
    "nginx.service",
    "postgresql",
    "postgresql.service",
    "redis",
    "redis.service",
    "redis-server",
    "redis-server.service",
    "ssh",
    "ssh.service",
    "sshd",
    "sshd.service",
}


def fail(code: str, message: str, status: int = 3) -> None:
    print(message, file=sys.stderr)
    print(code, file=sys.stderr)
    raise SystemExit(status)


def load_object(path: str) -> dict:
    file = Path(path)
    if file.is_symlink() or not file.is_file():
        fail("INVALID_INPUT", "Input must be a regular file.")
    if file.stat().st_size > 262144:
        fail("INVALID_INPUT", "Input is larger than 256KiB.")
    try:
        data = json.loads(file.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as error:
        fail("INVALID_INPUT", f"Input is not JSON: {error}")
    if not isinstance(data, dict):
        fail("INVALID_INPUT", "Input must be a JSON object.")
    scan(data)
    return data


def scan(value: object, key: str | None = None) -> None:
    if key is not None and SECRET_KEY.search(key):
        fail("SECRET_IN_INPUT", "Input contains a forbidden secret key.")
    if isinstance(value, dict):
        for child_key, child in value.items():
            scan(child, str(child_key))
    elif isinstance(value, list):
        for child in value:
            scan(child)
    elif isinstance(value, str) and "PRIVATE KEY" in value:
        fail("PRIVATE_KEY_REJECTED", "A private key was rejected.")


def dig(data: dict, key: str) -> object | None:
    current: object = data
    for part in key.split("."):
        if not isinstance(current, dict) or part not in current:
            return None
        current = current[part]
    return current


def emit(payload: dict) -> None:
    scan(payload)
    json.dump(payload, sys.stdout, ensure_ascii=False, separators=(",", ":"))
    sys.stdout.write("\n")


def sandbox_ok(path: str) -> Path:
    if path == "" or "\x00" in path or not path.startswith("/"):
        fail("PATH_ESCAPE", "Sandbox must be an absolute path.")
    candidate = Path(path)
    if candidate.is_symlink():
        fail("SYMLINK_REJECTED", "Sandbox must not be a symlink.")
    try:
        real = candidate.resolve(strict=True)
    except OSError:
        fail("PATH_ESCAPE", "Sandbox does not exist.")
    if not real.is_dir() or real.is_symlink():
        fail("PATH_ESCAPE", "Sandbox must be a real directory.")
    tmp_roots = {Path("/tmp").resolve()}
    tmpdir = os.environ.get("TMPDIR")
    if tmpdir:
        tmp_roots.add(Path(tmpdir).resolve())
    if not any(real == root or root in real.parents for root in tmp_roots):
        fail("LIVE_MUTATION_BLOCKED", "Sandbox must stay under the temporary directory.")
    if real == Path("/") or len(real.parts) < 3:
        fail("PATH_ESCAPE", "Sandbox is too broad.")
    return real


def contained(root: Path, candidate: str) -> Path:
    if candidate == "" or "\x00" in candidate:
        fail("PATH_ESCAPE", "Path is empty or traverses upward.")
    if ".." in Path(candidate).parts:
        fail("PATH_ESCAPE", "Path traversal was rejected.")
    target = Path(candidate)
    if not target.is_absolute():
        target = root / target
    if target.is_symlink():
        fail("SYMLINK_REJECTED", "The target is a symlink.")
    real_root = root.resolve()
    try:
        if target.exists():
            real = target.resolve(strict=True)
        else:
            real = target.parent.resolve(strict=True) / target.name
            if target.parent.is_symlink():
                fail("SYMLINK_REJECTED", "A parent directory is a symlink.")
    except OSError:
        fail("PATH_ESCAPE", "The path cannot be resolved.")
    if real != real_root and real_root not in real.parents:
        fail("PATH_ESCAPE", "The path escapes its root.")
    return real


def inside(root: Path, candidate: str) -> Path:
    return contained(root, candidate)


def lab_ok() -> None:
    token = os.environ.get("RAHANDAZ_OPS_LAB_TOKEN", "")
    if not LAB_TOKEN.fullmatch(token):
        fail("LIVE_MUTATION_BLOCKED", "Container lab token was rejected.")
    marker = Path("/run/rahandaz-lab/marker")
    if marker.is_symlink() or not marker.is_file():
        fail("LIVE_MUTATION_BLOCKED", "Container lab marker is not a regular file.")
    try:
        info = marker.stat()
    except OSError:
        fail("LIVE_MUTATION_BLOCKED", "Container lab marker could not be read.")
    if stat.S_IMODE(info.st_mode) != 0o600 or info.st_uid != 0:
        fail("LIVE_MUTATION_BLOCKED", "Container lab marker mode is not root-owned 0600.")
    try:
        lines = marker.read_text(encoding="utf-8").splitlines()
    except (OSError, UnicodeError):
        fail("LIVE_MUTATION_BLOCKED", "Container lab marker could not be read.")
    if len(lines) < 2 or lines[0] != "rahandaz-lab-v1":
        fail("LIVE_MUTATION_BLOCKED", "Container lab marker version was rejected.")
    try:
        matched = secrets.compare_digest(lines[1], token)
    except (TypeError, ValueError):
        matched = False
    if not matched:
        fail("LIVE_MUTATION_BLOCKED", "Container lab marker was rejected.")
    dockerenv = Path("/.dockerenv")
    if dockerenv.is_symlink() or not dockerenv.is_file():
        fail("LIVE_MUTATION_BLOCKED", "Container lab requires a regular /.dockerenv.")
    for sock in (Path("/var/run/docker.sock"), Path("/run/docker.sock")):
        if sock.exists() or sock.is_symlink():
            fail("LIVE_MUTATION_BLOCKED", "Container lab refuses a Docker socket.")
    try:
        sched = Path("/proc/1/sched").read_text(encoding="utf-8", errors="replace")
    except OSError:
        fail("LIVE_MUTATION_BLOCKED", "Container lab could not read pid 1.")
    name = sched.split(None, 1)[0].split("(", 1)[0] if sched.strip() else ""
    if name in {"systemd", "init"}:
        fail("LIVE_MUTATION_BLOCKED", "Container lab refuses pid 1 systemd or init.")


def accept_lab_root(path: str) -> Path:
    if path == "" or "\x00" in path or not path.startswith("/"):
        fail("PATH_ESCAPE", "Lab root must be absolute.")
    candidate = Path(path)
    if candidate.is_symlink():
        fail("SYMLINK_REJECTED", "Lab root must not be a symlink.")
    try:
        real = candidate.resolve(strict=True)
    except OSError:
        fail("PATH_ESCAPE", "Lab root does not exist.")
    if not real.is_dir() or real.is_symlink() or real == Path("/"):
        fail("PATH_ESCAPE", "Lab root is too broad.")
    if not any(real == prefix or prefix in real.parents for prefix in LAB_PREFIXES):
        fail("PATH_ESCAPE", "Lab root is outside the allowlist.")
    return real


def lab_path(candidate: str) -> Path:
    lab_ok()
    if candidate == "" or "\x00" in candidate or not candidate.startswith("/") or ".." in Path(candidate).parts:
        fail("PATH_ESCAPE", "Lab path was rejected.")
    target = Path(candidate)
    if target.is_symlink() or target.parent.is_symlink():
        fail("SYMLINK_REJECTED", "Lab path must not use a symlink.")
    try:
        if target.exists():
            real = target.resolve(strict=True)
        else:
            real = target.parent.resolve(strict=True) / target.name
    except OSError:
        fail("PATH_ESCAPE", "Lab path cannot be resolved.")
    if real in LAB_EXACT_FILES:
        return real
    if any(real == prefix or prefix in real.parents for prefix in LAB_PREFIXES):
        return real
    fail("PATH_ESCAPE", "Lab path is outside the allowlist.")
    raise AssertionError("unreachable")


def lab_catalog(path: str) -> Path:
    lab_ok()
    file = Path(path)
    if file.name != "container-lab.json" or file.is_symlink() or not file.is_file():
        fail("INVALID_INPUT", "Lab catalog must be a regular container-lab.json file.")
    if stat.S_IMODE(file.stat().st_mode) & 0o002:
        fail("INVALID_INPUT", "Lab catalog must not be world-writable.")
    try:
        real = file.resolve(strict=True)
    except OSError:
        fail("PATH_ESCAPE", "Lab catalog cannot be resolved.")
    if real.parent not in LAB_CATALOG_DIRS:
        fail("PATH_ESCAPE", "Lab catalog directory was rejected.")
    return real


def write_role_password(role: str, material: str, dest: str) -> None:
    if not IDENT.fullmatch(role):
        fail("INVALID_INPUT", "Identifier is not allowed.")
    source = Path(material)
    if source.is_symlink() or not source.is_file():
        fail("INVALID_INPUT", "Credential file must be a regular file.")
    if stat.S_IMODE(source.stat().st_mode) != 0o600:
        fail("INVALID_INPUT", "Credential file mode must be 0600.")
    raw = source.read_bytes()
    if raw.endswith(b"\n"):
        raw = raw[:-1]
    if raw == b"" or b"\n" in raw or b"\x00" in raw or b"PRIVATE KEY" in raw:
        fail("INVALID_INPUT", "Credential material was rejected.")
    password = raw.decode("utf-8")
    delimiter = "rahandazpwd"
    while delimiter in password:
        delimiter += "x"
    sql = f'ALTER ROLE "{role}" PASSWORD ${delimiter}${password}${delimiter}$;\n'
    destination = Path(dest)
    if destination.is_symlink() or ".." in destination.parts:
        fail("SYMLINK_REJECTED", "SQL file must not be a symlink.")
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW
    fd = os.open(destination, flags, 0o600)
    try:
        os.write(fd, sql.encode())
        os.fchmod(fd, 0o600)
    finally:
        os.close(fd)


def quote_ident(value: str) -> str:
    if not IDENT.fullmatch(value):
        fail("INVALID_INPUT", "Identifier is not allowed.")
    return '"' + value.replace('"', '""') + '"'


def fingerprint(line: str) -> str:
    line = line.strip("\n")
    if "\n" in line or not PUBLIC_KEY.fullmatch(line):
        fail("INVALID_INPUT", "Public key format was rejected.")
    blob = base64.b64decode(line.split(" ", 2)[1], validate=True)
    digest = hashlib.sha256(blob).digest()
    return "SHA256:" + base64.b64encode(digest).decode().rstrip("=")


def write_password(path: str) -> None:
    destination = Path(path)
    if destination.is_symlink():
        fail("SYMLINK_REJECTED", "Password file must not be a symlink.")
    password = secrets.token_urlsafe(24)
    if len(password) < 24:
        fail("INVALID_INPUT", "Generated password is too short.")
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW
    fd = os.open(destination, flags, 0o600)
    try:
        os.write(fd, (password + "\n").encode())
        os.fchmod(fd, 0o600)
    finally:
        os.close(fd)
    mode = stat.S_IMODE(destination.stat().st_mode)
    if mode != 0o600:
        fail("INVALID_INPUT", "Password file mode is not 0600.")


def os_release(path: str) -> dict:
    values: dict[str, str] = {}
    for line in Path(path).read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key] = value.strip().strip("\"'")
    version = values.get("VERSION_ID", "")
    codename = values.get("VERSION_CODENAME", "")
    recognized = values.get("ID") == "ubuntu" and version in {"22.04", "24.04", "26.04"}
    expected = {"22.04": "jammy", "24.04": "noble", "26.04": "resolute"}
    if recognized and codename != expected[version]:
        recognized = False
    return {
        "id": values.get("ID"),
        "version": version or None,
        "codename": codename or None,
        "recognized": recognized,
        "compatibility": "untested" if recognized else "unsupported",
    }


def main() -> None:
    command = sys.argv[1] if len(sys.argv) > 1 else ""
    if command == "get":
        value = dig(load_object(sys.argv[2]), sys.argv[3])
        if value is None:
            raise SystemExit(2)
        if isinstance(value, bool):
            sys.stdout.write("true" if value else "false")
        elif isinstance(value, (dict, list)):
            sys.stdout.write(json.dumps(value, ensure_ascii=False, separators=(",", ":")))
        else:
            sys.stdout.write(str(value))
        return
    if command == "emit-result":
        payload = {
            "schema_version": "1.0",
            "operation": os.environ["OPS_OPERATION"],
            "operation_version": os.environ["OPS_OPERATION_VERSION"],
            "execution_id": os.environ["OPS_EXECUTION_ID"],
            "status": os.environ["OPS_STATUS"],
            "error_code": os.environ.get("OPS_ERROR_CODE") or None,
            "observed_state": json.loads(os.environ.get("OPS_OBSERVED") or "{}"),
            "plan": json.loads(os.environ.get("OPS_PLAN") or "{}"),
            "mutated": os.environ.get("OPS_MUTATED") == "true",
        }
        message = os.environ.get("OPS_MESSAGE")
        if message:
            payload["observed_state"]["message"] = message
        emit(payload)
        return
    if command == "sandbox-ok":
        print(sandbox_ok(sys.argv[2]))
        return
    if command == "inside":
        if os.environ.get("RAHANDAZ_OPS_LAB") == "1":
            lab_ok()
            print(contained(accept_lab_root(sys.argv[2]), sys.argv[3]))
            return
        print(inside(sandbox_ok(sys.argv[2]), sys.argv[3]))
        return
    if command == "lab-ok":
        lab_ok()
        sys.stdout.write("ok\n")
        return
    if command == "lab-path":
        print(lab_path(sys.argv[2]))
        return
    if command == "lab-catalog":
        print(lab_catalog(sys.argv[2]))
        return
    if command == "write-role-password":
        write_role_password(sys.argv[2], sys.argv[3], sys.argv[4])
        return
    if command == "quote-ident":
        sys.stdout.write(quote_ident(sys.argv[2]))
        return
    if command == "uuid":
        if not UUID_V7.fullmatch(sys.argv[2]):
            fail("MISSING_EXECUTION_ID", "execution_id must be a UUID v7.")
        return
    if command == "ident":
        if not IDENT.fullmatch(sys.argv[2]):
            fail("INVALID_INPUT", "Identifier is not allowed.")
        return
    if command == "unit":
        if not UNIT.fullmatch(sys.argv[2]):
            fail("INVALID_INPUT", "Unit name is not allowed.")
        return
    if command == "domain":
        if not DOMAIN.fullmatch(sys.argv[2]):
            fail("INVALID_INPUT", "Domain is not allowed.")
        return
    if command == "package":
        if not PACKAGE.fullmatch(sys.argv[2]):
            fail("INVALID_INPUT", "Package name is not allowed.")
        return
    if command == "sha":
        if not SHA.fullmatch(sys.argv[2]):
            fail("INVALID_INPUT", "Commit SHA is not allowed.")
        return
    if command == "project-user":
        if not PROJECT_USER.fullmatch(sys.argv[2]):
            fail("INVALID_INPUT", "Project user must look like rhproj_<id>.")
        return
    if command == "fingerprint":
        print(fingerprint(Path(sys.argv[2]).read_text(encoding="utf-8")))
        return
    if command == "password-file":
        write_password(sys.argv[2])
        return
    if command == "os-release":
        emit_bare = os_release(sys.argv[2])
        json.dump(emit_bare, sys.stdout, ensure_ascii=False, separators=(",", ":"))
        sys.stdout.write("\n")
        return
    if command == "protected-database":
        engine, name = sys.argv[2], sys.argv[3]
        if name in PROTECTED_DATABASES.get(engine, set()):
            fail("PROTECTED_DATABASE", "The control-plane database is protected.")
        return
    if command == "docker-slug":
        slug = sys.argv[2]
        if not re.fullmatch(r"[a-z0-9][a-z0-9-]{1,48}", slug) or slug.startswith("rahandaz"):
            fail("CONTROL_PLANE_PROJECT", "This project name is reserved or not a project slug.")
        return
    if command == "docker-file":
        kind, name = sys.argv[2], sys.argv[3]
        compose = re.fullmatch(r"(docker-compose|compose)\.ya?ml", name)
        dockerfile = re.fullmatch(r"Dockerfile(\.[A-Za-z0-9._-]{1,32})?", name)
        if kind == "compose" and compose is None:
            fail("INVALID_INPUT", "Compose file name is not allowed.")
        if kind == "dockerfile" and dockerfile is None:
            fail("INVALID_INPUT", "Dockerfile name is not allowed.")
        if "/" in name or name.startswith("."):
            fail("INVALID_INPUT", "File name must be a basename.")
        return
    if command == "docker-layout":
        compose = False
        dockerfile = False
        for name in sys.argv[2:]:
            base = Path(name).name
            if base != name or "/" in name or "\\" in name:
                fail("INVALID_INPUT", "Layout names must be basenames.")
            if re.fullmatch(r"(docker-compose|compose)\.ya?ml", base):
                compose = True
            if re.fullmatch(r"Dockerfile(\.[A-Za-z0-9._-]{1,32})?", base):
                dockerfile = True
        if compose and dockerfile:
            print("compose_and_dockerfile")
        elif compose:
            print("compose_only")
        elif dockerfile:
            print("dockerfile_only")
        else:
            print("absent")
        return
    if command == "protected-unit":
        unit = sys.argv[2]
        bare = unit.removesuffix(".service")
        if (
            unit in PROTECTED_UNITS
            or bare in PROTECTED_UNITS
            or (bare.startswith("php") and bare.endswith("-fpm"))
            or bare.startswith("rahandaz")
        ):
            print("protected")
        else:
            print("ordinary")
        return
    if command == "registry":
        registry = json.loads(Path(sys.argv[2]).read_text(encoding="utf-8"))
        for row in registry["operations"]:
            if row["name"] == sys.argv[3]:
                json.dump(row, sys.stdout, ensure_ascii=False, separators=(",", ":"))
                return
        fail("UNKNOWN_OPERATION", "Operation is not in the registry.")
    fail("INVALID_INPUT", "Unknown opsjson command.")


if __name__ == "__main__":
    try:
        main()
    except BrokenPipeError:
        raise SystemExit(0)
