#!/usr/bin/env bash
# Turn an already signed bundle into pinned GitHub assets and a copy/paste guide.
# This script neither creates project keys nor publishes anything.
set -euo pipefail

INSTALLER_HOME="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${INSTALLER_HOME}/lib/common.sh"
source "${INSTALLER_HOME}/lib/verify.sh"
source "${INSTALLER_HOME}/lib/semver.sh"
manifest='' signature='' public_key='' artifact='' repository='' output=''
while (( $# )); do
    (( $# >= 2 )) || { printf '%s\n' 'Missing option value.' >&2; exit 2; }
    case "$1" in
        --manifest) manifest="$2" ;;
        --signature) signature="$2" ;;
        --public-key) public_key="$2" ;;
        --artifact) artifact="$2" ;;
        --repository) repository="$2" ;;
        --output) output="$2" ;;
        *) printf '%s\n' 'Unknown option.' >&2; exit 2 ;;
    esac
    shift 2
done
[[ -n "$manifest" && -n "$signature" && -n "$artifact" && -n "$output" ]] || exit 2
[[ "$repository" =~ ^[A-Za-z0-9][A-Za-z0-9_-]*/[A-Za-z0-9][A-Za-z0-9_.-]*$ ]] || {
    printf '%s\n' 'Repository must be owner/name, without credentials or a URL.' >&2; exit 2;
}
public_key="$(installer_public_key "$public_key")"
installer_verify_manifest "$manifest" "$signature" "$public_key" "$artifact"
version="$INSTALLER_RELEASE_VERSION"
installer_semver_split "$version" >/dev/null && [[ "$version" != *+* ]] || {
    printf '%s\n' 'Release must use SemVer without build metadata.' >&2; exit 2;
}
[[ "$(basename "$artifact")" == "rahandaz-${version}.tar.gz" ]] || exit 2

python3 - "$INSTALLER_HOME/bootstrap/github-install.sh.in" "$public_key" "$version" "$repository" \
    "$manifest" "$signature" "$artifact" "$output" <<'PY'
from pathlib import Path
import hashlib, re, shlex, shutil, sys

template, public_key, version, repository, manifest, signature, artifact, output = sys.argv[1:]
if not re.fullmatch(r"(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?", version):
    raise ValueError("Invalid SemVer")
if "-" in version and any(p.isdigit() and len(p) > 1 and p.startswith("0") for p in version.split("-", 1)[1].split(".")):
    raise ValueError("Invalid numeric prerelease identifier")
key = Path(public_key).read_text().strip()
if (not key.startswith("-----BEGIN PUBLIC KEY-----") or not key.endswith("-----END PUBLIC KEY-----")
        or "PRIVATE" in key or "RAHANDAZ_PINNED_RELEASE_KEY" in key):
    raise ValueError("Use an SPKI PEM public key, never a private key")
destination = Path(output)
# A fresh directory prevents mixing repository assets into Release uploads.
destination.mkdir(parents=True, exist_ok=False)
names = ["install.sh", "release.manifest", "release.manifest.sig", Path(artifact).name,
         "rahandaz-release.pub", "SHA256SUMS", "INSTALL.md"]
if any((destination / name).exists() or (destination / name).is_symlink() for name in names):
    raise ValueError("Output must not contain existing release assets")
script = Path(template).read_text().replace("@@VERSION@@", version).replace("@@REPOSITORY@@", repository).replace("@@PUBLIC_KEY@@", key)
if "@@" in script:
    raise ValueError("Unexpanded bootstrap template")
(destination / "install.sh").write_text(script)
(destination / "install.sh").chmod(0o755)
for source, name in [(manifest, "release.manifest"), (signature, "release.manifest.sig"),
                     (artifact, Path(artifact).name), (public_key, "rahandaz-release.pub")]:
    shutil.copyfile(source, destination / name)
    (destination / name).chmod(0o644)
digest = hashlib.sha256(script.encode()).hexdigest()
fingerprint = hashlib.sha256(Path(public_key).read_bytes()).hexdigest()
url = f"https://github.com/{repository}/releases/download/v{version}/install.sh"
command = ("(set -eu; rahandaz_install_dir=$(mktemp -d); "
           + "trap 'rm -rf -- \"$rahandaz_install_dir\"' EXIT; curl --proto '=https' --proto-redir '=https' "
           "--tlsv1.2 -fsSL --connect-timeout 15 --max-time 120 --retry 3 " + shlex.quote(url)
           + ' -o "$rahandaz_install_dir/install.sh"; printf "' + digest
           + '  %s\\n" "$rahandaz_install_dir/install.sh" | sha256sum --check --status; '
           + 'if [ "$(id -u)" -eq 0 ]; then bash "$rahandaz_install_dir/install.sh"; '
           + 'else sudo bash "$rahandaz_install_dir/install.sh"; fi)')
guide = f"""# Rahandaz {version} - installation candidate

This file is generated from a verified signed artifact; it does not certify VM installation.
Draft releases cannot be downloaded anonymously. Prereleases are for disposable VMs only.
Do not promote this command into README until public assets and release gates are verified.

## One command after SSH login

Use Bash and a root or sudo-capable account. This downloads a pinned script, checks its
literal SHA-256 before execution, then verifies the manifest signature and artifact checksum.
You will be asked for the panel origin, then `Panel port [8080]: `. Press Enter for
8080 or enter a browser-safe port from 1024 to 65535. An HTTP origin without a port
is synchronized with that choice. With an IPv4 origin, the initial installer also enrolls
the restricted GUI panel-port capability and preserves a loopback recovery site on port 80.
HTTP does not encrypt credentials; TLS is not provisioned automatically. For trusted SSH
access use `http://127.0.0.1` with a tunnel to the selected port.

```bash
{command}
```

The command's initial trust is the reviewed documentation and GitHub HTTPS. Compare the
public key pin with the maintainer's independent trusted channel; a key downloaded beside
the artifact is not an independent trust root. No shell is executed from a curl pipe.

## Inspect and verify manually

Download `install.sh`, check SHA-256 `{digest}`, inspect it, then run
`bash install.sh --verify-only` (no host mutation). To inspect the installation plan use
`bash install.sh --plan --public-url http://127.0.0.1`.
After review, `sudo bash install.sh --public-url http://127.0.0.1` performs installation.
For noninteractive installation add `--http-port 8080`. If it fails, retry this exact
version with `--resume`; the saved origin and port are retained. Do not delete keys or
regenerate APP_KEY. A conflicting origin port is refused.
Updates use the separate signed updater, not a fresh install of a newer version.

Bootstrap prerequisites: Ubuntu x86_64, Bash, curl, CA certificates, OpenSSL, Python 3,
and coreutils. Missing bootstrap tools fail before any package installation.
Node.js, npm and Composer are not required on the server: vendor and built assets are bundled.
The currently missing host executor is NOT enabled by this installer.

Initial owner credentials are written only to `/root/rahandaz-initial-credentials.txt`;
the installer reports the path, never the password. Open `/rahandaz/login` through your
trusted connection and change the initial password. Preserve `/etc/rahandaz` secrets.

Pinned public-key file SHA-256: `{fingerprint}`. The key is embedded inside `install.sh`.
`SHA256SUMS` is also signed as `SHA256SUMS.sig`; verify with the separately trusted key.
"""
(destination / "INSTALL.md").write_text(guide)
with (destination / "SHA256SUMS").open("w") as sums:
    for name in names:
        if name != "SHA256SUMS":
            sums.write(hashlib.sha256((destination / name).read_bytes()).hexdigest() + "  " + name + "\n")
PY
bash -n "$output/install.sh"
printf '%s\n' "Prepared pinned assets for ${repository} v${version}. No release was published."
