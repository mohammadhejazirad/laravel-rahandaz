#!/usr/bin/env python3
"""Build a ready-to-run archive without following links or packaging runtime data."""

import argparse
import gzip
import os
from pathlib import Path
import re
import stat
import tarfile
import tempfile


def assemble(source: Path, version: str, output: Path) -> Path:
    if not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?", version):
        raise ValueError("Version must be a release version without paths or build metadata.")
    if source.is_symlink() or not source.is_dir():
        raise ValueError("Source must be a real application directory.")
    source = source.resolve()
    output = output.resolve()
    if output == source or source in output.parents:
        raise ValueError("Output must be outside the application tree.")
    if any(p.name != ".env.example" for p in source.glob(".env*")):
        raise ValueError("Refusing to pack a tree that contains an environment file.")
    required = (
        "artisan", "composer.json", "composer.lock", "vendor/autoload.php",
        "public/build/manifest.json", "installer/bin/rahandaz-install",
    )
    for name in required:
        path = source / name
        if not path.is_file() or path.is_symlink():
            raise ValueError(f"The tree is missing a regular {name}.")

    roots = (
        "app", "bootstrap", "config", "database", "installer", "public", "resources",
        "routes", "vendor", "artisan", "composer.json", "composer.lock", ".env.example",
        "README.md", "LICENSE", "SECURITY.md", "CHANGELOG.md", "infrastructure/operations/panel",
    )
    paths = []
    for name in roots:
        root = source / name
        if not root.exists() and not root.is_symlink():
            continue
        candidates = [root, *sorted(root.rglob("*"))] if root.is_dir() else [root]
        for path in candidates:
            relative = path.relative_to(source)
            # Environment-dependent Laravel caches and installer tests are not runtime code.
            if (relative.parts[:2] in (("bootstrap", "cache"), ("installer", "tests"))
                    or relative.as_posix() == "public/hot"):
                continue
            metadata = path.lstat()
            if not stat.S_ISREG(metadata.st_mode) and not stat.S_ISDIR(metadata.st_mode):
                raise ValueError(f"Refusing a link or special file: {relative}")
            if path.is_file():
                if metadata.st_nlink > 1:
                    raise ValueError(f"Refusing a hard-linked file: {relative}")
                if ((path.name.startswith(".env") and relative.as_posix() != ".env.example")
                        or path.suffix.lower() in (".pem", ".key", ".p12", ".pfx", ".log", ".sql", ".dump", ".sqlite", ".backup")
                        or path.name in ("id_rsa", "id_ed25519", "id_ecdsa", "id_dsa", "credentials.json")):
                    raise ValueError(f"Refusing a sensitive file: {relative}")
                if re.search(rb"-----BEGIN [A-Z ]*PRIVATE KEY-----", path.read_bytes()):
                    raise ValueError(f"Refusing private key material: {relative}")
            paths.append(path)

    output.mkdir(parents=True, exist_ok=True)
    artifact = output / f"rahandaz-{version}.tar.gz"
    if artifact.exists() or artifact.is_symlink():
        raise ValueError("Refusing to replace an existing release artifact.")
    epoch = int(os.environ.get("SOURCE_DATE_EPOCH", "0"))
    with tempfile.NamedTemporaryFile(dir=output, prefix=".assemble-", delete=False) as temporary:
        temporary_path = Path(temporary.name)
        try:
            with gzip.GzipFile(fileobj=temporary, mode="wb", filename="", mtime=epoch) as compressed:
                with tarfile.open(fileobj=compressed, mode="w", format=tarfile.PAX_FORMAT) as archive:
                    for directory in ("rahandaz", "rahandaz/bootstrap/cache", "rahandaz/storage/app/private",
                                      "rahandaz/storage/framework/cache/data", "rahandaz/storage/framework/sessions",
                                      "rahandaz/storage/framework/views", "rahandaz/storage/logs"):
                        member = tarfile.TarInfo(directory)
                        member.type = tarfile.DIRTYPE
                        member.mode, member.mtime = 0o755, epoch
                        archive.addfile(member)
                    for path in paths:
                        member = archive.gettarinfo(str(path), "rahandaz/" + path.relative_to(source).as_posix())
                        member.uid = member.gid = 0
                        member.uname = member.gname = "root"
                        member.mtime = epoch
                        member.mode = 0o755 if path.is_dir() or path.stat().st_mode & 0o111 else 0o644
                        if path.is_file():
                            with path.open("rb") as content:
                                archive.addfile(member, content)
                        else:
                            archive.addfile(member)
            temporary.close()
            temporary_path.replace(artifact)
        except BaseException:
            temporary_path.unlink(missing_ok=True)
            raise
    return artifact


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--from", dest="source", type=Path, required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--output", type=Path, required=True)
    arguments = parser.parse_args()
    try:
        print(assemble(arguments.source, arguments.version, arguments.output))
    except (ValueError, OSError, tarfile.TarError) as error:
        parser.exit(1, f"ARTIFACT_REJECTED: {error}\n")
